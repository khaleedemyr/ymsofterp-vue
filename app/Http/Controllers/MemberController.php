<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        // Search functionality
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filter by status aktif
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        // Filter by status block
        if ($request->filled('block_status')) {
            $query->byBlockStatus($request->block_status);
        }

        // Filter by exclusive member
        if ($request->filled('exclusive')) {
            if ($request->exclusive === 'yes') {
                $query->exclusive();
            }
        }

        // Sort
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        $query->orderBy($sort, $direction);

        // Pagination
        $members = $query->paginate(15)->withQueryString();

        // Get statistics
        $stats = [
            'total_members' => Customer::count(),
            'active_members' => Customer::where('status_aktif', '1')->count(),
            'inactive_members' => Customer::where('status_aktif', '0')->count(),
            'exclusive_members' => Customer::where('exclusive_member', 'Y')->count(),
        ];

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'status', 'block_status', 'exclusive', 'sort', 'direction']),
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Members/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'costumers_id' => 'required|string|max:50|unique:mysql_second.costumers,costumers_id',
            'nik' => 'nullable|string|max:20',
            'name' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:20',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pekerjaan' => 'nullable|string|max:100',
            'valid_until' => 'required|date',
            'password2' => 'nullable|string|max:255',
            'android_password' => 'nullable|string|max:255',
            'pin' => 'nullable|string|max:10',
            'exclusive_member' => 'required|in:Y,N',
        ]);

        try {
            DB::connection('mysql_second')->beginTransaction();

            // Set nilai default untuk field yang dihilangkan
            $memberData = $request->all();
            $memberData['status_aktif'] = '1'; // Otomatis aktif
            $memberData['status_block'] = 'N'; // Otomatis tidak diblokir
            $memberData['tanggal_aktif'] = now()->toDateString(); // Tanggal hari ini
            $memberData['tanggal_register'] = now()->toDateString(); // Tanggal hari ini
            $memberData['hint'] = $request->password2; // Hint = password (tidak di hash)
            $memberData['barcode'] = null; // Kosong
            $memberData['device'] = null; // Kosong

            $member = Customer::create($memberData);

            DB::connection('mysql_second')->commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil ditambahkan!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal menambahkan member: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $member)
    {
        return Inertia::render('Members/Show', [
            'member' => $member
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $member)
    {
        return Inertia::render('Members/Edit', [
            'member' => $member
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $member)
    {
        $request->validate([
            'costumers_id' => 'required|string|max:50|unique:mysql_second.costumers,costumers_id,' . $member->id,
            'nik' => 'nullable|string|max:20',
            'name' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:20',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pekerjaan' => 'nullable|string|max:100',
            'valid_until' => 'required|date',
            'password2' => 'nullable|string|max:255',
            'android_password' => 'nullable|string|max:255',
            'pin' => 'nullable|string|max:10',
            'exclusive_member' => 'required|in:Y,N',
        ]);

        try {
            DB::connection('mysql_second')->beginTransaction();

            // Update data member
            $memberData = $request->all();
            $memberData['hint'] = $request->password2; // Hint = password (tidak di hash)

            $member->update($memberData);

            DB::connection('mysql_second')->commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal memperbarui member: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $member)
    {
        try {
            DB::connection('mysql_second')->beginTransaction();

            $member->delete();

            DB::connection('mysql_second')->commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil dihapus!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal menghapus member: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle status aktif member
     */
    public function toggleStatus(Customer $member)
    {
        try {
            DB::connection('mysql_second')->beginTransaction();

            $member->update([
                'status_aktif' => $member->status_aktif === '1' ? '0' : '1'
            ]);

            DB::connection('mysql_second')->commit();

            return back()->with('success', 'Status member berhasil diubah!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal mengubah status member: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle status block member
     */
    public function toggleBlock(Customer $member)
    {
        try {
            DB::connection('mysql_second')->beginTransaction();

            $member->update([
                'status_block' => $member->status_block === 'Y' ? 'N' : 'Y'
            ]);

            DB::connection('mysql_second')->commit();

            return back()->with('success', 'Status block member berhasil diubah!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal mengubah status block member: ' . $e->getMessage()]);
        }
    }

    /**
     * Export members to Excel
     */
    public function export(Request $request)
    {
        $query = Customer::query();

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('block_status')) {
            $query->byBlockStatus($request->block_status);
        }

        if ($request->filled('exclusive')) {
            if ($request->exclusive === 'yes') {
                $query->exclusive();
            }
        }

        $members = $query->get();

        // Generate Excel file
        $filename = 'members_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // You can implement Excel export here using Laravel Excel or similar package
        // For now, we'll return a simple response
        
        return response()->json([
            'success' => true,
            'message' => 'Export berhasil dibuat',
            'filename' => $filename,
            'count' => $members->count()
        ]);
    }
} 