<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

/**
 * Report Helper Controller
 * 
 * Handles helper/utility functions for reports:
 * - API endpoints for filters (outlets, regions)
 * - User outlet information
 * - Activity logs
 * 
 * Split from ReportController for better organization
 * 
 * Functions (4):
 * - apiOutlets: Get outlets list for filters (with user permission check)
 * - apiRegions: Get regions list for filters
 * - myOutletQr: Get current user's outlet QR code and name
 * - reportActivityLog: Activity log report with filtering
 */
class ReportHelperController extends Controller
{
    /**
     * API: Get all outlets (filtered by user permission)
     * 
     * Returns outlets list for filter dropdowns
     * Respects user permissions:
     * - Superuser (id_outlet = 1): All outlets
     * - Regular user: Only their outlet
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiOutlets()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json(['error' => 'User tidak terautentikasi'], 401);
            }
            
            // Cek apakah tabel exists
            if (!Schema::hasTable('tbl_data_outlet')) {
                Log::error('Table tbl_data_outlet does not exist');
                return response()->json(['error' => 'Tabel outlet tidak ditemukan'], 500);
            }
            
            $query = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->whereNotNull('nama_outlet')
                ->where('nama_outlet', '!=', '');
            
            // Jika user bukan superuser (id_outlet != 1), hanya tampilkan outlet mereka sendiri
            if ($user->id_outlet != 1) {
                $query->where('id_outlet', $user->id_outlet);
            }
            
            $outlets = $query->get(['id_outlet as id', 'nama_outlet as name', 'qr_code', 'region_id']);
            
            Log::info('apiOutlets called', [
                'user_id' => $user->id,
                'user_outlet_id' => $user->id_outlet,
                'outlets_count' => $outlets->count(),
                'outlets' => $outlets->toArray()
            ]);
            
            return response()->json(['outlets' => $outlets]);
        } catch (\Exception $e) {
            Log::error('Error in apiOutlets', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Get all regions
     * 
     * Returns regions list for filter dropdowns
     * No permission checks - all users can see all regions
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiRegions()
    {
        try {
            $regions = DB::table('regions')
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get();
            
            return response()->json(['regions' => $regions]);
        } catch (\Exception $e) {
            Log::error('Error in apiRegions', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Get qr_code and outlet name for current user's outlet
     * 
     * Used for auto-filling outlet filter for non-superusers
     * Returns null for superusers (id_outlet = 1)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function myOutletQr()
    {
        $user = auth()->user();
        $qr_code = null;
        $outlet_name = null;
        
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $user->id_outlet)->first();
            if ($outlet) {
                $qr_code = $outlet->qr_code;
                $outlet_name = $outlet->nama_outlet;
            }
        }
        
        return response()->json([
            'qr_code' => $qr_code,
            'outlet_name' => $outlet_name
        ]);
    }

    /**
     * Report Activity Log
     * 
     * Shows system activity logs with filtering capabilities
     * 
     * Filters:
     * - user_id: Filter by specific user
     * - activity_type: Filter by activity type (create, update, delete, etc.)
     * - module: Filter by module name
     * - date_from / date_to: Date range filter
     * - search: Search in description, module, user name, IP address
     * 
     * Supports both Inertia and JSON responses
     * 
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function reportActivityLog(Request $request)
    {
        $query = DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.id')
            ->select(
                'al.id',
                'al.user_id',
                'u.nama_lengkap as user_name',
                'al.activity_type',
                'al.module',
                'al.description',
                'al.ip_address',
                'al.user_agent',
                'al.old_data',
                'al.new_data',
                'al.created_at'
            );

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('al.user_id', $request->user_id);
        }

        if ($request->filled('outlet_id')) {
            $this->applyActivityLogOutletFilter($query, (int) $request->outlet_id);
        }

        // Filter by activity type
        if ($request->filled('activity_type')) {
            $query->where('al.activity_type', $request->activity_type);
        }

        // Filter by module
        if ($request->filled('module')) {
            $query->where('al.module', $request->module);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('al.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('al.created_at', '<=', $request->date_to);
        }

        // Filter by search (description, module, user name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('al.description', 'like', "%{$search}%")
                  ->orWhere('al.module', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('al.ip_address', 'like', "%{$search}%");
            });
        }

        // Get unique values for filters
        $users = DB::table('users')
            ->whereIn('id', function($q) {
                $q->select('user_id')->from('activity_logs')->distinct();
            })
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap')
            ->get();

        $activityTypes = DB::table('activity_logs')
            ->select('activity_type')
            ->distinct()
            ->orderBy('activity_type')
            ->pluck('activity_type');

        $modules = DB::table('activity_logs')
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $authUser = auth()->user();
        $outletsQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '');

        if ($authUser && $authUser->id_outlet != 1) {
            $outletsQuery->where('id_outlet', $authUser->id_outlet);
        }

        $outlets = $outletsQuery
            ->orderBy('nama_outlet')
            ->get(['id_outlet as id', 'nama_outlet as name']);

        // Pagination
        $perPage = $request->get('per_page', 25);
        $logs = $query->orderByDesc('al.created_at')->paginate($perPage)->withQueryString();
        $logs->setCollection(
            $logs->getCollection()->map(function ($log) {
                $log->outlet_name = $this->resolveActivityLogOutletName($log);
                $log->outlet_id_resolved = $this->resolveActivityLogOutletId($log);

                return $log;
            })
        );

        // For API requests, return JSON
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'logs' => $logs,
                'users' => $users,
                'outlets' => $outlets,
                'activityTypes' => $activityTypes,
                'modules' => $modules,
                'filters' => [
                    'user_id' => $request->user_id,
                    'outlet_id' => $request->outlet_id,
                    'activity_type' => $request->activity_type,
                    'module' => $request->module,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'search' => $request->search,
                    'per_page' => $perPage,
                ]
            ]);
        }

        return Inertia::render('Report/ActivityLog', [
            'logs' => $logs,
            'users' => $users,
            'outlets' => $outlets,
            'activityTypes' => $activityTypes,
            'modules' => $modules,
            'filters' => [
                'user_id' => $request->user_id,
                'outlet_id' => $request->outlet_id,
                'activity_type' => $request->activity_type,
                'module' => $request->module,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
                'per_page' => $perPage,
            ]
        ]);
    }

    private function applyActivityLogOutletFilter($query, int $outletId): void
    {
        $outletName = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->value('nama_outlet');

        $jsonRegex = '(\"outlet_id\"|\"id_outlet\")[[:space:]]*:[[:space:]]*\"?' . $outletId . '\"?([,}[:space:]])';
        $jsonPaths = [
            '$.outlet_id',
            '$.id_outlet',
            '$.header.outlet_id',
            '$.header.id_outlet',
        ];

        $query->where(function ($q) use ($outletId, $outletName, $jsonRegex, $jsonPaths) {
            $q->whereRaw('al.old_data REGEXP ?', [$jsonRegex])
                ->orWhereRaw('al.new_data REGEXP ?', [$jsonRegex]);

            foreach (['old_data', 'new_data'] as $column) {
                foreach ($jsonPaths as $path) {
                    $q->orWhereRaw(
                        "CAST(JSON_UNQUOTE(JSON_EXTRACT(al.{$column}, '{$path}')) AS UNSIGNED) = ?",
                        [$outletId]
                    );
                }
            }

            if ($outletName) {
                $q->orWhere('al.description', 'like', '%' . $outletName . '%');
            }
        });
    }

    private function decodeActivityLogJson($json): ?array
    {
        if (!$json) {
            return null;
        }

        $data = is_string($json) ? json_decode($json, true) : $json;

        return is_array($data) ? $data : null;
    }

    private function extractOutletIdFromActivityPayload(?array $data): ?int
    {
        if (!$data) {
            return null;
        }

        foreach (['outlet_id', 'id_outlet'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                return (int) $data[$key];
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $outletId = $this->extractOutletIdFromActivityPayload($value);
            if ($outletId) {
                return $outletId;
            }
        }

        return null;
    }

    private function extractOutletNameFromActivityPayload(?array $data): ?string
    {
        if (!$data) {
            return null;
        }

        foreach (['nama_outlet', 'outlet_name'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $name = $this->extractOutletNameFromActivityPayload($value);
            if ($name) {
                return $name;
            }
        }

        return null;
    }

    private function resolveActivityLogOutletId($log): ?int
    {
        foreach ([$log->new_data, $log->old_data] as $json) {
            $data = $this->decodeActivityLogJson($json);
            $outletId = $this->extractOutletIdFromActivityPayload($data);
            if ($outletId) {
                return $outletId;
            }
        }

        return null;
    }

    private function resolveActivityLogOutletName($log): ?string
    {
        foreach ([$log->new_data, $log->old_data] as $json) {
            $data = $this->decodeActivityLogJson($json);
            $outletName = $this->extractOutletNameFromActivityPayload($data);
            if ($outletName) {
                return $outletName;
            }
        }

        $outletId = $this->resolveActivityLogOutletId($log);
        if (!$outletId) {
            return null;
        }

        static $outletNames = [];
        if (!isset($outletNames[$outletId])) {
            $outletNames[$outletId] = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->value('nama_outlet');
        }

        return $outletNames[$outletId] ?: null;
    }
}
