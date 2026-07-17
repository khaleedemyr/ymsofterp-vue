<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\OvertimeSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeSubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));

        $records = OvertimeSubmission::query()
            ->with(['creator:id,nama_lengkap'])
            ->withCount('items')
            ->withCount(['items as employee_count' => fn ($q) => $q->select(DB::raw('COUNT(DISTINCT user_id)'))])
            ->when($search !== '', function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('creator', fn ($u) => $u->where('nama_lengkap', 'like', "%{$search}%"));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Attendance/OvertimeSubmissionIndex', [
            'records' => $records,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Attendance/OvertimeSubmissionForm', [
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'submission_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.user_id' => 'required|integer|exists:users,id',
            'items.*.overtime_date' => 'required|date',
            'items.*.requested_hours' => 'required|numeric|min:0.01|max:24',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $submission = OvertimeSubmission::create([
                'number' => $this->generateNumber(),
                'submission_date' => $validated['submission_date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $submission->items()->create([
                    'user_id' => (int) $item['user_id'],
                    'overtime_date' => $item['overtime_date'],
                    'requested_hours' => (float) $item['requested_hours'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('overtime-submissions.index')->with('success', 'Pengajuan lembur berhasil disimpan.');
    }

    public function destroy(OvertimeSubmission $overtimeSubmission)
    {
        if ((string) (auth()->user()?->id_role ?? '') !== '5af56935b011a') {
            abort(403, 'Hanya superadmin yang dapat menghapus data pengajuan lembur.');
        }

        $overtimeSubmission->delete();

        return redirect()->route('overtime-submissions.index')->with('success', 'Pengajuan lembur berhasil dihapus.');
    }

    public function searchUsers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $outletId = $request->get('outlet_id');

        $users = User::query()
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->where('users.status', 'A')
            ->when($outletId, fn ($q) => $q->where('users.id_outlet', $outletId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('users.nik', 'like', "%{$search}%")
                        ->orWhere('j.nama_jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nama_lengkap')
            ->limit(30)
            ->get([
                'users.id',
                'users.nik',
                'users.nama_lengkap as name',
                'users.email',
                DB::raw('j.nama_jabatan as jabatan'),
            ]);

        return response()->json(['success' => true, 'users' => $users]);
    }

    private function generateNumber(): string
    {
        $prefix = 'OTR'.now()->format('Ymd');
        $last = OvertimeSubmission::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
