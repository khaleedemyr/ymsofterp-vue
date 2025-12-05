<?php

namespace App\Http\Controllers;

use App\Models\Coaching;
use App\Models\CoachingApprover;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CoachingController extends Controller
{
    public function index(Request $request)
    {
        $query = Coaching::with([
            'employee.jabatan', 
            'employee.divisi', 
            'employee.outlet',
            'supervisor.jabatan', 
            'supervisor.divisi',
            'approvers.approver.jabatan'
        ])->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('nama_lengkap', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('coaching_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('coaching_date', '<=', $request->date_to);
        }

        $coachings = $query->paginate(15);

        return Inertia::render('Coaching/Index', [
            'coachings' => $coachings->items(),
            'pagination' => [
                'current_page' => $coachings->currentPage(),
                'last_page' => $coachings->lastPage(),
                'per_page' => $coachings->perPage(),
                'total' => $coachings->total(),
                'from' => $coachings->firstItem(),
                'to' => $coachings->lastItem(),
                'prev_page_url' => $coachings->previousPageUrl(),
                'next_page_url' => $coachings->nextPageUrl(),
            ]
        ]);
    }

    public function create()
    {
        // Load all active employees for the multiselect
        $employees = User::where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select([
                'users.id',
                'users.nama_lengkap',
                'users.tanggal_masuk',
                'users.signature_path',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_divisi.nama_divisi',
                'tbl_data_outlet.nama_outlet'
            ])
            ->orderBy('users.nama_lengkap')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'tanggal_masuk' => $user->tanggal_masuk,
                    'signature_path' => $user->signature_path,
                    'jabatan' => $user->nama_jabatan ? ['nama_jabatan' => $user->nama_jabatan] : null,
                    'divisi' => $user->nama_divisi ? ['nama_divisi' => $user->nama_divisi] : null,
                    'outlet' => $user->nama_outlet ? ['nama_outlet' => $user->nama_outlet] : null,
                ];
            });

        // Load all active outlets for location selection
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        // Load all active users for tiered signature
        $allUsers = User::where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select([
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'users.tanggal_masuk',
                'users.signature_path',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_divisi.nama_divisi',
                'tbl_data_outlet.nama_outlet'
            ])
            ->orderBy('users.nama_lengkap')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email,
                    'tanggal_masuk' => $user->tanggal_masuk,
                    'signature_path' => $user->signature_path,
                    'jabatan' => $user->nama_jabatan ? ['nama_jabatan' => $user->nama_jabatan] : null,
                    'divisi' => $user->nama_divisi ? ['nama_divisi' => $user->nama_divisi] : null,
                    'outlet' => $user->nama_outlet ? ['nama_outlet' => $user->nama_outlet] : null,
                ];
            });

        return Inertia::render('Coaching/Create', [
            'user' => auth()->user(),
            'employees' => $employees,
            'outlets' => $outlets,
            'allUsers' => $allUsers
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'violation_date' => 'required|date',
            'violation_details' => 'required|string',
            'location' => 'nullable|string|max:255',
            'disciplinary_actions' => 'nullable|array',
            'supervisor_comments' => 'nullable|string',
            'employee_response' => 'nullable|string',
            'approvers' => 'nullable|array',
            'approvers.*.id' => 'required|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            $coaching = Coaching::create([
                'employee_id' => $request->employee_id,
                'supervisor_id' => auth()->id(),
                'coaching_date' => now()->toDateString(),
                'violation_date' => $request->violation_date,
                'location' => $request->location,
                'violation_details' => $request->violation_details,
                'disciplinary_actions' => $request->disciplinary_actions ? json_encode($request->disciplinary_actions) : null,
                'supervisor_comments' => $request->supervisor_comments,
                'employee_response' => $request->employee_response,
                'supervisor_signature' => auth()->user()->signature_path,
                'employee_signature' => User::find($request->employee_id)->signature_path,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            // Save approvers if provided
            if ($request->approvers && is_array($request->approvers)) {
                foreach ($request->approvers as $index => $approver) {
                    CoachingApprover::create([
                        'coaching_id' => $coaching->id,
                        'approver_id' => $approver['id'],
                        'approval_level' => $index + 1,
                        'status' => 'pending'
                    ]);
                }
            }

            // Send notifications to approvers
            if ($request->approvers && is_array($request->approvers)) {
                $employee = User::find($request->employee_id);
                $creator = auth()->user();
                
                foreach ($request->approvers as $index => $approver) {
                    DB::table('notifications')->insert([
                        'user_id' => $approver['id'],
                        'task_id' => $coaching->id,
                        'type' => 'coaching_approval_required',
                        'message' => "Coaching baru memerlukan persetujuan Anda:\n\nKaryawan: {$employee->nama_lengkap}\nTanggal Pelanggaran: " . Carbon::parse($request->violation_date)->format('d/m/Y') . "\nDetail: {$request->violation_details}\nLevel Approval: " . ($index + 1) . "\nDibuat oleh: {$creator->nama_lengkap}",
                        'url' => config('app.url') . '/coaching/' . $coaching->id,
                        'is_read' => 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Send notification to employee being coached
            DB::table('notifications')->insert([
                'user_id' => $request->employee_id,
                'task_id' => $coaching->id,
                'type' => 'coaching_created',
                'message' => "Anda telah menerima coaching:\n\nTanggal Pelanggaran: " . Carbon::parse($request->violation_date)->format('d/m/Y') . "\nDetail: {$request->violation_details}\nDibuat oleh: {$creator->nama_lengkap}",
                'url' => config('app.url') . '/coaching/' . $coaching->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Coaching berhasil disimpan',
                'coaching' => $coaching
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan coaching: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Coaching $coaching)
    {
        $coaching->load([
            'employee.jabatan', 
            'employee.divisi', 
            'employee.outlet',
            'supervisor.jabatan', 
            'supervisor.divisi',
            'approvers.approver.jabatan',
            'creator',
            'updater'
        ]);

        return Inertia::render('Coaching/Show', [
            'coaching' => $coaching
        ]);
    }

    public function edit(Coaching $coaching)
    {
        $coaching->load([
            'employee.jabatan', 
            'employee.divisi', 
            'employee.outlet',
            'supervisor.jabatan', 
            'supervisor.divisi',
            'approvers.approver.jabatan'
        ]);

        // Load all active employees for the multiselect
        $employees = User::where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select([
                'users.id',
                'users.nama_lengkap',
                'users.tanggal_masuk',
                'users.signature_path',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_divisi.nama_divisi',
                'tbl_data_outlet.nama_outlet'
            ])
            ->orderBy('users.nama_lengkap')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'tanggal_masuk' => $user->tanggal_masuk,
                    'signature_path' => $user->signature_path,
                    'jabatan' => $user->nama_jabatan ? ['nama_jabatan' => $user->nama_jabatan] : null,
                    'divisi' => $user->nama_divisi ? ['nama_divisi' => $user->nama_divisi] : null,
                    'outlet' => $user->nama_outlet ? ['nama_outlet' => $user->nama_outlet] : null,
                ];
            });

        // Load all active outlets for location selection
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('Coaching/Edit', [
            'coaching' => $coaching,
            'user' => auth()->user(),
            'employees' => $employees,
            'outlets' => $outlets
        ]);
    }

    public function update(Request $request, Coaching $coaching)
    {
        $request->validate([
            'violation_date' => 'required|date',
            'violation_details' => 'required|string',
            'location' => 'nullable|string|max:255',
            'disciplinary_actions' => 'nullable|array',
            'supervisor_comments' => 'nullable|string',
            'employee_response' => 'nullable|string',
            'approvers' => 'nullable|array',
            'approvers.*.id' => 'required|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            $coaching->update([
                'violation_date' => $request->violation_date,
                'location' => $request->location,
                'violation_details' => $request->violation_details,
                'disciplinary_actions' => $request->disciplinary_actions ? json_encode($request->disciplinary_actions) : null,
                'supervisor_comments' => $request->supervisor_comments,
                'employee_response' => $request->employee_response,
                'updated_by' => auth()->id(),
            ]);

            // Update approvers if provided
            if ($request->approvers && is_array($request->approvers)) {
                // Delete existing approvers
                $coaching->approvers()->delete();
                
                // Create new approvers
                foreach ($request->approvers as $index => $approver) {
                    CoachingApprover::create([
                        'coaching_id' => $coaching->id,
                        'approver_id' => $approver['id'],
                        'approval_level' => $index + 1,
                        'status' => 'pending'
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Coaching berhasil diperbarui',
                'coaching' => $coaching
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui coaching: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Coaching $coaching)
    {
        try {
            DB::beginTransaction();

            // Delete related approvers first
            $coaching->approvers()->delete();
            
            // Delete the coaching
            $coaching->delete();

            DB::commit();

            return redirect()->route('coaching.index')->with('success', 'Coaching berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('coaching.index')->with('error', 'Gagal menghapus coaching: ' . $e->getMessage());
        }
    }

    public function searchUsers(Request $request)
    {
        $search = $request->get('q', '');

        if (strlen($search) < 2) {
            return response()->json(['success' => true, 'users' => []]);
        }

        $users = User::where('users.status', 'A')
            ->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', '%' . $search . '%')
                      ->orWhere('users.email', 'like', '%' . $search . '%');
            })
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select([
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'users.tanggal_masuk',
                'users.signature_path',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_divisi.nama_divisi',
                'tbl_data_outlet.nama_outlet'
            ])
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email,
                    'tanggal_masuk' => $user->tanggal_masuk,
                    'signature_path' => $user->signature_path,
                    'jabatan' => $user->nama_jabatan ? ['nama_jabatan' => $user->nama_jabatan] : null,
                    'divisi' => $user->nama_divisi ? ['nama_divisi' => $user->nama_divisi] : null,
                    'outlet' => $user->nama_outlet ? ['nama_outlet' => $user->nama_outlet] : null,
                ];
            });

        return response()->json(['success' => true, 'users' => $users]);
    }

    public function getUserActiveSanctions(Request $request)
    {
        try {
            $userId = $request->get('user_id', auth()->id());
            
            // If no user_id provided and not authenticated, return empty
            if (!$userId) {
                \Log::warning('No user ID provided for active sanctions');
                return response()->json([
                    'success' => true,
                    'active_sanctions' => []
                ]);
            }
            
            $currentDate = now()->format('Y-m-d');
            
            \Log::info('Getting active sanctions for user: ' . $userId . ' on date: ' . $currentDate);

            $coachings = Coaching::where('employee_id', $userId)
                ->where('status', 'completed')
                ->whereNotNull('disciplinary_actions')
                ->get();
                
            \Log::info('Found ' . $coachings->count() . ' completed coachings with disciplinary actions');
            
            $coachings = $coachings->map(function($coaching) {
                    $sanctions = json_decode($coaching->disciplinary_actions, true) ?? [];
                    \Log::info('Coaching ' . $coaching->id . ' sanctions: ' . json_encode($sanctions));
                    return [
                        'id' => $coaching->id,
                        'violation_date' => $coaching->violation_date,
                        'violation_details' => $coaching->violation_details,
                        'location' => $coaching->location,
                        'sanctions' => $sanctions
                    ];
                })
                ->filter(function($coaching) use ($currentDate) {
                    if (empty($coaching['sanctions'])) {
                        \Log::info('Coaching ' . $coaching['id'] . ' has no sanctions');
                        return false;
                    }
                    
                    // Check if any sanction is currently active
                    foreach ($coaching['sanctions'] as $sanction) {
                        if (isset($sanction['effective_date']) && isset($sanction['end_date'])) {
                            \Log::info('Checking sanction: ' . $sanction['name'] . ' from ' . $sanction['effective_date'] . ' to ' . $sanction['end_date']);
                            if ($sanction['effective_date'] <= $currentDate && $sanction['end_date'] >= $currentDate) {
                                \Log::info('Sanction is active!');
                                return true;
                            }
                        }
                    }
                    return false;
                });

            \Log::info('Returning ' . $coachings->count() . ' active sanctions');

            return response()->json([
                'success' => true,
                'active_sanctions' => $coachings->values()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getUserActiveSanctions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading active sanctions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'approver_id' => 'required|integer',
            'comments' => 'nullable|string'
        ]);

        try {
            // Support both route model binding (Coaching model) and $id (integer/string)
            $coaching = $id instanceof Coaching ? $id : Coaching::findOrFail($id);
            
            // Find the approver record by ID (not by approver_id)
            $approver = $coaching->approvers()
                ->where('id', $request->approver_id)
                ->where('status', 'pending')
                ->first();

            if (!$approver) {
                return response()->json([
                    'message' => 'Approver tidak ditemukan atau sudah diproses'
                ], 404);
            }

            $approver->update([
                'status' => 'approved',
                'approved_at' => now(),
                'comments' => $request->comments
            ]);

            // Send notification to employee
            $employee = $coaching->employee;
            $approverUser = User::find($approver->approver_id);
            
            DB::table('notifications')->insert([
                'user_id' => $coaching->employee_id,
                'task_id' => $coaching->id,
                'type' => 'coaching_approved',
                'message' => "Coaching Anda telah disetujui:\n\nLevel Approval: {$approver->approval_level}\nDisetujui oleh: {$approverUser->nama_lengkap}\nKomentar: " . ($request->comments ?: 'Tidak ada komentar'),
                'url' => config('app.url') . '/coaching/' . $coaching->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Check if all approvers have approved
            $totalApprovers = $coaching->approvers()->count();
            $approvedCount = $coaching->approvers()->where('status', 'approved')->count();
            
            if ($approvedCount >= $totalApprovers) {
                // All approvers have approved, update coaching status to completed
                $coaching->update(['status' => 'completed']);
                
                // Send notification to employee that coaching is completed
                DB::table('notifications')->insert([
                    'user_id' => $coaching->employee_id,
                    'task_id' => $coaching->id,
                    'type' => 'coaching_completed',
                    'message' => "Coaching Anda telah selesai dan disetujui oleh semua pihak:\n\nKaryawan: {$employee->nama_lengkap}\nTanggal Pelanggaran: " . Carbon::parse($coaching->violation_date)->format('d/m/Y') . "\nDetail: {$coaching->violation_details}",
                    'url' => config('app.url') . '/coaching/' . $coaching->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                // Send notification to next approver if exists
                $nextApprover = $coaching->approvers()
                    ->where('approval_level', $approver->approval_level + 1)
                    ->where('status', 'pending')
                    ->first();

                if ($nextApprover) {
                    DB::table('notifications')->insert([
                        'user_id' => $nextApprover->approver_id,
                        'task_id' => $coaching->id,
                        'type' => 'coaching_approval_required',
                        'message' => "Coaching memerlukan persetujuan Anda (Level {$nextApprover->approval_level}):\n\nKaryawan: {$employee->nama_lengkap}\nTanggal Pelanggaran: " . Carbon::parse($coaching->violation_date)->format('d/m/Y') . "\nDetail: {$coaching->violation_details}\nDisetujui sebelumnya oleh: {$approverUser->nama_lengkap}",
                        'url' => config('app.url') . '/coaching/' . $coaching->id,
                        'is_read' => 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            return response()->json([
                'message' => 'Coaching berhasil disetujui',
                'approver' => $approver
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyetujui coaching: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'approver_id' => 'required|integer',
            'comments' => 'required|string'
        ]);

        try {
            // Support both route model binding (Coaching model) and $id (integer/string)
            $coaching = $id instanceof Coaching ? $id : Coaching::findOrFail($id);
            
            // Find the approver record by ID (not by approver_id)
            $approver = $coaching->approvers()
                ->where('id', $request->approver_id)
                ->where('status', 'pending')
                ->first();

            if (!$approver) {
                return response()->json([
                    'message' => 'Approver tidak ditemukan atau sudah diproses'
                ], 404);
            }

            $approver->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'comments' => $request->comments
            ]);

            // Update coaching status to rejected when any approver rejects
            $coaching->update(['status' => 'rejected']);

            // Send notification to employee
            $employee = $coaching->employee;
            $approverUser = User::find($approver->approver_id);
            
            DB::table('notifications')->insert([
                'user_id' => $coaching->employee_id,
                'task_id' => $coaching->id,
                'type' => 'coaching_rejected',
                'message' => "Coaching Anda telah ditolak:\n\nLevel Approval: {$approver->approval_level}\nDitolak oleh: {$approverUser->nama_lengkap}\nAlasan: {$request->comments}",
                'url' => config('app.url') . '/coaching/' . $coaching->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Send notification to creator
            DB::table('notifications')->insert([
                'user_id' => $coaching->created_by,
                'task_id' => $coaching->id,
                'type' => 'coaching_rejected',
                'message' => "Coaching yang Anda buat telah ditolak:\n\nKaryawan: {$employee->nama_lengkap}\nLevel Approval: {$approver->approval_level}\nDitolak oleh: {$approverUser->nama_lengkap}\nAlasan: {$request->comments}",
                'url' => config('app.url') . '/coaching/' . $coaching->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'message' => 'Coaching berhasil ditolak',
                'approver' => $approver
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menolak coaching: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDetail($id)
    {
        try {
            $coaching = Coaching::with([
                'employee.jabatan',
                'employee.divisi',
                'employee.outlet',
                'supervisor.jabatan',
                'supervisor.divisi',
                'creator',
                'approvers.approver.jabatan',
                'approvers.approver.divisi',
            ])->find($id);
            
            if (!$coaching) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coaching tidak ditemukan'
                ], 404);
            }
            
            // Get approval flows
            $approvalFlows = $coaching->approvers()
                ->orderBy('approval_level')
                ->get()
                ->map(function($approver) {
                    return [
                        'id' => $approver->id,
                        'sequence' => $approver->approval_level,
                        'status' => $approver->status,
                        'approved_at' => $approver->approved_at,
                        'rejected_at' => $approver->rejected_at,
                        'comments' => $approver->comments,
                        'approver' => [
                            'id' => $approver->approver_id,
                            'nama_lengkap' => $approver->approver ? $approver->approver->nama_lengkap : null,
                        ],
                    ];
                });
            
            // Get current approver (pending approver for current user)
            $currentApprover = null;
            $currentUserId = auth()->id();
            if ($currentUserId) {
                $currentApprover = $coaching->approvers()
                    ->where('approver_id', $currentUserId)
                    ->where('status', 'pending')
                    ->first();
            }
            
            // Transform coaching data
            $coachingData = [
                'id' => $coaching->id,
                'type' => $coaching->type,
                'status' => $coaching->status,
                'violation_date' => $coaching->violation_date,
                'violation_details' => $coaching->violation_details,
                'coaching_date' => $coaching->coaching_date,
                'location' => $coaching->location,
                'created_at' => $coaching->created_at,
                'updated_at' => $coaching->updated_at,
                'employee' => [
                    'id' => $coaching->employee_id,
                    'nama_lengkap' => $coaching->employee ? $coaching->employee->nama_lengkap : null,
                ],
                'supervisor' => [
                    'id' => $coaching->supervisor_id,
                    'nama_lengkap' => $coaching->supervisor ? $coaching->supervisor->nama_lengkap : null,
                ],
                'creator' => [
                    'id' => $coaching->created_by,
                    'nama_lengkap' => $coaching->creator ? $coaching->creator->nama_lengkap : null,
                ],
                'approval_flows' => $approvalFlows->toArray(),
                'current_approver_id' => $currentApprover ? $currentApprover->id : null,
            ];
            
            return response()->json([
                'success' => true,
                'coaching' => $coachingData
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting Coaching detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Coaching detail'
            ], 500);
        }
    }

    public function getPendingApprovals(Request $request)
    {
        try {
            $userId = $request->get('user_id', auth()->id());
            
            // Debug logging
            \Log::info('Getting pending approvals for user: ' . $userId);
            
            $approvers = CoachingApprover::where('approver_id', $userId)
                ->where('status', 'pending')
                ->with(['coaching.employee', 'coaching.supervisor'])
                ->orderBy('created_at', 'desc')
                ->get();
                
            \Log::info('Found ' . $approvers->count() . ' pending approvers');
            
            $pendingApprovals = $approvers->filter(function($approver) {
                // Debug each approver
                if (!$approver->coaching) {
                    \Log::warning('Approver ' . $approver->id . ' has no coaching');
                    return false;
                }
                if (!$approver->coaching->employee) {
                    \Log::warning('Coaching ' . $approver->coaching->id . ' has no employee');
                    return false;
                }
                if (!$approver->coaching->supervisor) {
                    \Log::warning('Coaching ' . $approver->coaching->id . ' has no supervisor');
                    return false;
                }
                return true;
            })->map(function($approver) {
                return [
                    'id' => $approver->id,
                    'coaching_id' => $approver->coaching_id,
                    'approval_level' => $approver->approval_level,
                    'employee_name' => $approver->coaching->employee->nama_lengkap ?? 'N/A',
                    'violation_date' => $approver->coaching->violation_date,
                    'violation_details' => $approver->coaching->violation_details,
                    'supervisor_name' => $approver->coaching->supervisor->nama_lengkap ?? 'N/A',
                    'created_at' => $approver->created_at,
                    'coaching' => $approver->coaching
                ];
            });

            \Log::info('Returning ' . $pendingApprovals->count() . ' valid pending approvals');

            return response()->json([
                'success' => true,
                'pending_approvals' => $pendingApprovals
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getPendingApprovals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading pending approvals: ' . $e->getMessage()
            ], 500);
        }
    }

}
