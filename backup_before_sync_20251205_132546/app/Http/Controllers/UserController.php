<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use App\Exports\UsersExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $status = $request->input('status', 'A'); // Default to active users
        $perPage = $request->input('per_page', 15); // Default to 15 per page

        $query = User::query()
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select(
                'users.*',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_outlet.nama_outlet'
            );
        
        // Filter by status
        if ($status === 'A') {
            $query->where('users.status', 'A'); // Active users only
        } elseif ($status === 'N') {
            $query->where('users.status', 'N'); // Non-active users only
        } elseif ($status === 'B') {
            $query->where('users.status', 'B'); // New users only
        }
        // If status is 'all', don't filter by status (show all)
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%$search%")
                  ->orWhere('users.nik', 'like', "%$search%")
                  ->orWhere('users.email', 'like', "%$search%")
                  ->orWhere('users.no_hp', 'like', "%$search%")
                  ->orWhere('users.no_ktp', 'like', "%$search%")
                  ->orWhere('users.nama_panggilan', 'like', "%$search%")
                  ;
            });
        }
        if ($outletId) {
            $query->where('users.id_outlet', $outletId);
        }
        if ($divisionId) {
            $query->where('users.division_id', $divisionId);
        }
        $users = $query->orderBy('users.id', 'desc')->paginate($perPage)->withQueryString();

        $outlets = DB::table('tbl_data_outlet')->where('status', 'A')->select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $divisions = DB::table('tbl_data_divisi')->where('status', 'A')->select('id', 'nama_divisi')->orderBy('nama_divisi')->get();
        $jabatans = DB::table('tbl_data_jabatan')->where('status', 'A')->select('id_jabatan', 'nama_jabatan')->orderBy('nama_jabatan')->get();

        // Get statistics
        $total = User::count();
        $active = User::where('status', 'A')->count();
        $inactive = User::where('status', 'N')->count();
        $new = User::where('status', 'B')->count();
        
        // Log statistics for debugging
        \Log::info('User Statistics', [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'new' => $new,
            'sum' => $active + $inactive + $new
        ]);
        
        // Check for any invalid statuses
        $invalidStatuses = User::whereNotIn('status', ['A', 'N', 'B'])->orWhereNull('status')->get(['id', 'nik', 'nama_lengkap', 'status']);
        if ($invalidStatuses->count() > 0) {
            \Log::warning('Users with invalid status', $invalidStatuses->toArray());
        }
        
        $statistics = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'new' => $new,
        ];

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'outlets' => $outlets,
            'divisions' => $divisions,
            'jabatans' => $jabatans,
            'statistics' => $statistics,
        ]);
    }

    private function generateNIK()
    {
        $year = date('y');
        $month = date('m');
        
        // Get the last NIK for current year-month
        $lastNIK = User::where('nik', 'like', $year . $month . '%')
            ->orderBy('nik', 'desc')
            ->first();
        
        if ($lastNIK) {
            $lastSequence = intval(substr($lastNIK->nik, 4));
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }
        
        return $year . $month . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        \Log::info('UserController@store request', $request->all());
        $validated = $request->validate([
            'no_ktp' => 'nullable|string|max:50',
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:6',
            'hint_password' => 'nullable|string|max:255',
            'nama_panggilan' => 'nullable|string|max:255',
            'jenis_kelamin' => 'nullable|string|max:1',
            'tempat_lahir' => 'nullable|string|max:255',
            'tanggal_lahir' => 'nullable|date',
            'suku' => 'nullable|string|max:50',
            'agama' => 'nullable|string|max:50',
            'status_pernikahan' => 'nullable|string|max:50',
            'alamat' => 'nullable|string',
            'alamat_ktp' => 'nullable|string',
            'nomor_kk' => 'nullable|string|max:50',
            'no_hp' => 'nullable|string|max:15',
            'id_jabatan' => 'nullable|integer|exists:tbl_data_jabatan,id_jabatan',
            'id_outlet' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'division_id' => 'nullable|integer',
            'imei' => 'nullable|string|max:50',
            'golongan_darah' => 'nullable|string|max:5',
            'nama_rekening' => 'nullable|string|max:255',
            'no_rekening' => 'nullable|string|max:50',
            'nama_kontak_darurat' => 'nullable|string|max:255',
            'no_hp_kontak_darurat' => 'nullable|string|max:15',
            'hubungan_kontak_darurat' => 'nullable|string|max:50',
            'pin_pos' => 'nullable|string|max:10',
            'npwp_number' => 'nullable|string|max:100',
            'bpjs_health_number' => 'nullable|string|max:100',
            'bpjs_employment_number' => 'nullable|string|max:100',
            'last_education' => 'nullable|string|max:100',
            'name_school_college' => 'nullable|string|max:255',
            'school_college_major' => 'nullable|string|max:255',
            'tanggal_masuk' => 'nullable|date',
            'foto_ktp' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            'foto_kk' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            'upload_latest_color_photo' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Validasi unik pin_pos untuk karyawan aktif
        if ($request->filled('pin_pos')) {
            $exists = \App\Models\User::where('pin_pos', $request->pin_pos)
                ->where('status', 'A')
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'pin_pos' => 'PIN POS sudah digunakan oleh karyawan aktif lain.',
                ]);
            }
        }

        // Generate NIK
        $validated['nik'] = $this->generateNIK();
        $validated['status'] = 'A';

        // Handle file uploads
        if ($request->hasFile('foto_ktp')) {
            $validated['foto_ktp'] = $request->file('foto_ktp')->store('users/foto_ktp', 'public');
        }
        if ($request->hasFile('foto_kk')) {
            $validated['foto_kk'] = $request->file('foto_kk')->store('users/foto_kk', 'public');
        }
        if ($request->hasFile('upload_latest_color_photo')) {
            $validated['upload_latest_color_photo'] = $request->file('upload_latest_color_photo')->store('users/photos', 'public');
        }

        // Hash password if provided
        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        try {
            User::create($validated);
            \Log::info('UserController@store success', $validated);
            return redirect()->route('users.index')->with('success', 'Karyawan berhasil ditambahkan dengan NIK: ' . $validated['nik']);
        } catch (\Exception $e) {
            \Log::error('UserController@store error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withErrors(['error' => 'Gagal menyimpan data: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, User $user)
    {
        \Log::info('UserController@update request', $request->all());
        \Log::info('UserController@update user before update', $user->toArray());
        \Log::info('UserController@update specific request fields', [
            'id_jabatan' => $request->input('id_jabatan'),
            'id_outlet' => $request->input('id_outlet'),
            'division_id' => $request->input('division_id'),
            'has_id_jabatan' => $request->has('id_jabatan'),
            'has_id_outlet' => $request->has('id_outlet'),
            'has_division_id' => $request->has('division_id'),
        ]);
        \Log::info('UserController@update file uploads', [
            'has_foto_ktp' => $request->hasFile('foto_ktp'),
            'has_foto_kk' => $request->hasFile('foto_kk'),
            'has_upload_latest_color_photo' => $request->hasFile('upload_latest_color_photo'),
            'has_avatar' => $request->hasFile('avatar'),
        ]);
        
        try {
            $validated = $request->validate([
                'nik' => 'nullable|string|max:50',
                'no_ktp' => 'nullable|string|max:50',
                'nama_lengkap' => 'required|string|max:255',
                'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:6',
                'hint_password' => 'nullable|string|max:255',
                'nama_panggilan' => 'nullable|string|max:255',
                'jenis_kelamin' => 'nullable|string|max:1',
                'tempat_lahir' => 'nullable|string|max:255',
                'tanggal_lahir' => 'nullable|date',
                'suku' => 'nullable|string|max:50',
                'agama' => 'nullable|string|max:50',
                'status_pernikahan' => 'nullable|string|max:50',
                'alamat' => 'nullable|string',
                'alamat_ktp' => 'nullable|string',
                'nomor_kk' => 'nullable|string|max:50',
                'no_hp' => 'nullable|string|max:15',
                'id_jabatan' => 'nullable|integer|exists:tbl_data_jabatan,id_jabatan',
                'id_outlet' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
                'division_id' => 'nullable|integer|exists:tbl_data_divisi,id',
                'imei' => 'nullable|string|max:50',
                'golongan_darah' => 'nullable|string|max:5',
                'nama_rekening' => 'nullable|string|max:255',
                'no_rekening' => 'nullable|string|max:50',
                'nama_kontak_darurat' => 'nullable|string|max:255',
                'no_hp_kontak_darurat' => 'nullable|string|max:15',
                'hubungan_kontak_darurat' => 'nullable|string|max:50',
                'pin_pos' => 'nullable|string|max:10',
                'npwp_number' => 'nullable|string|max:100',
                'bpjs_health_number' => 'nullable|string|max:100',
                'bpjs_employment_number' => 'nullable|string|max:100',
                'last_education' => 'nullable|string|max:100',
                'name_school_college' => 'nullable|string|max:255',
                'school_college_major' => 'nullable|string|max:255',
                'tanggal_masuk' => 'nullable|date',
                'foto_ktp' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
                'foto_kk' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
                'upload_latest_color_photo' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
                'avatar' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            ]);
            
            \Log::info('UserController@update validated data', $validated);
            \Log::info('UserController@update validation specific fields', [
                'id_jabatan' => $validated['id_jabatan'] ?? 'not in validated',
                'id_outlet' => $validated['id_outlet'] ?? 'not in validated',
                'division_id' => $validated['division_id'] ?? 'not in validated',
            ]);
            
            // Validasi unik pin_pos untuk karyawan aktif (kecuali diri sendiri)
            if ($request->filled('pin_pos')) {
                $exists = \App\Models\User::where('pin_pos', $request->pin_pos)
                    ->where('status', 'A')
                    ->where('id', '!=', $user->id)
                    ->exists();
                if ($exists) {
                    throw ValidationException::withMessages([
                        'pin_pos' => 'PIN POS sudah digunakan oleh karyawan aktif lain.',
                    ]);
                }
            }
            
            // Handle file uploads
            if ($request->hasFile('foto_ktp')) {
                \Log::info('UserController@update processing foto_ktp upload');
                if ($user->foto_ktp && Storage::disk('public')->exists($user->foto_ktp)) {
                    Storage::disk('public')->delete($user->foto_ktp);
                }
                $validated['foto_ktp'] = $request->file('foto_ktp')->store('users/foto_ktp', 'public');
                \Log::info('UserController@update foto_ktp stored at: ' . $validated['foto_ktp']);
            }
            if ($request->hasFile('foto_kk')) {
                \Log::info('UserController@update processing foto_kk upload');
                if ($user->foto_kk && Storage::disk('public')->exists($user->foto_kk)) {
                    Storage::disk('public')->delete($user->foto_kk);
                }
                $validated['foto_kk'] = $request->file('foto_kk')->store('users/foto_kk', 'public');
                \Log::info('UserController@update foto_kk stored at: ' . $validated['foto_kk']);
            }
            if ($request->hasFile('upload_latest_color_photo')) {
                \Log::info('UserController@update processing upload_latest_color_photo upload');
                if ($user->upload_latest_color_photo && Storage::disk('public')->exists($user->upload_latest_color_photo)) {
                    Storage::disk('public')->delete($user->upload_latest_color_photo);
                }
                $validated['upload_latest_color_photo'] = $request->file('upload_latest_color_photo')->store('users/photos', 'public');
                \Log::info('UserController@update upload_latest_color_photo stored at: ' . $validated['upload_latest_color_photo']);
            }
            if ($request->hasFile('avatar')) {
                \Log::info('UserController@update processing avatar upload');
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $validated['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
                \Log::info('UserController@update avatar stored at: ' . $validated['avatar']);
            }
            if (!empty($validated['password'])) {
                $validated['password'] = bcrypt($validated['password']);
            } else {
                unset($validated['password']);
            }
            
            \Log::info('UserController@update final data to save', $validated);
            \Log::info('UserController@update specific fields', [
                'id_jabatan' => $validated['id_jabatan'] ?? 'not set',
                'id_outlet' => $validated['id_outlet'] ?? 'not set', 
                'division_id' => $validated['division_id'] ?? 'not set'
            ]);
            
            $user->update($validated);
            
            // Refresh user data to get updated values
            $user->refresh();
            
            \Log::info('UserController@update user after update', $user->toArray());
            \Log::info('UserController@update specific fields after update', [
                'id_jabatan' => $user->id_jabatan,
                'id_outlet' => $user->id_outlet,
                'division_id' => $user->division_id
            ]);
            \Log::info('UserController@update success', $validated);
            
            return redirect()->route('users.show', $user->id)->with('success', 'Data karyawan berhasil diupdate');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            \Log::error('UserController@update validation error', ['errors' => $ve->errors()]);
            return back()->withErrors($ve->errors());
        } catch (\Exception $e) {
            \Log::error('UserController@update error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withErrors(['error' => 'Gagal update data: ' . $e->getMessage()]);
        }
    }

    public function destroy(User $user)
    {
        // Set status to 'N' (Non-aktif) instead of deleting
        $oldData = $user->toArray();
        $user->update(['status' => 'N']);
        
        // Log activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'status_change',
            'module' => 'users',
            'description' => 'Menonaktifkan karyawan: ' . $user->nama_lengkap,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $user->fresh()->toArray()
        ]);

        return redirect()->back()->with('success', 'Karyawan berhasil dinonaktifkan!');
    }

    public function toggleStatus(User $user)
    {
        $oldData = $user->toArray();
        $newStatus = $user->status === 'A' ? 'N' : 'A';
        $user->update(['status' => $newStatus]);
        
        // Log activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'status_change',
            'module' => 'users',
            'description' => ($newStatus === 'A' ? 'Mengaktifkan' : 'Menonaktifkan') . ' karyawan: ' . $user->nama_lengkap,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $user->fresh()->toArray()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status karyawan berhasil diubah!',
            'new_status' => $newStatus
        ]);
    }

    public function show(User $user)
    {
        // Load relationships
        $user->load(['jabatan', 'outlet', 'divisi']);
        
        return Inertia::render('Users/Show', [
            'user' => $user
        ]);
    }

    public function getDropdownData()
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();
        $jabatans = DB::table('tbl_data_jabatan')
            ->where('status', 'A')
            ->select('id_jabatan', 'nama_jabatan')
            ->orderBy('nama_jabatan')
            ->get();
        
        // Check if division table exists
        $divisions = collect();
        try {
            if (DB::getSchemaBuilder()->hasTable('tbl_data_divisi')) {
                $divisions = DB::table('tbl_data_divisi')
                    ->where('status', 'A')
                    ->select('id as id_division', 'nama_divisi as nama_division')
                    ->orderBy('nama_divisi')
                    ->get();
            }
        } catch (\Exception $e) {
            // Table doesn't exist, use empty collection
            $divisions = collect();
        }
        
        return response()->json([
            'success' => true,
            'outlets' => $outlets,
            'jabatans' => $jabatans,
            'divisions' => $divisions,
        ]);
    }

    public function updateSaldo(Request $request, $userId)
    {
        $request->validate([
            'cuti' => 'nullable|numeric|min:0',
            'public_holiday' => 'nullable|numeric|min:0',
            'extra_off' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255'
        ]);

        $user = User::findOrFail($userId);
        
        try {
            DB::beginTransaction();
            
            $updated = [];
            
            // Update saldo cuti
            if ($request->has('cuti') && $request->cuti !== '') {
                $user->update(['cuti' => $request->cuti]);
                $updated[] = "Saldo cuti: {$request->cuti} hari";
            }
            
            // Update saldo public holiday
            if ($request->has('public_holiday') && $request->public_holiday !== '') {
                $this->updatePublicHolidayBalance($userId, $request->public_holiday, $request->notes);
                $updated[] = "Saldo public holiday: {$request->public_holiday} hari";
            }
            
            // Update saldo extra off
            if ($request->has('extra_off') && $request->extra_off !== '') {
                $this->updateExtraOffBalance($userId, $request->extra_off, $request->notes);
                $updated[] = "Saldo extra off: {$request->extra_off} hari";
            }
            
            if (empty($updated)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data yang diupdate'
                ], 400);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Saldo berhasil diupdate: ' . implode(', ', $updated)
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error updating saldo: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate saldo: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function updatePublicHolidayBalance($userId, $amount, $notes = null)
    {
        try {
            // Check if user has existing public holiday balance record
            $existingBalance = DB::table('holiday_attendance_compensations')
                ->where('user_id', $userId)
                ->where('compensation_type', 'bonus')
                ->where('status', 'approved')
                ->sum('compensation_amount');
                
            $currentBalance = $existingBalance ?: 0;
            $difference = $amount - $currentBalance;
            
            \Log::info('Public Holiday Balance Update', [
                'user_id' => $userId,
                'amount' => $amount,
                'current_balance' => $currentBalance,
                'difference' => $difference
            ]);
            
            if ($difference != 0) {
                // Create adjustment transaction
                DB::table('holiday_attendance_compensations')->insert([
                    'user_id' => $userId,
                    'holiday_date' => now()->toDateString(),
                    'compensation_type' => 'bonus',
                    'compensation_amount' => $difference,
                    'compensation_description' => $notes ?: 'Manual adjustment - Public Holiday Balance',
                    'status' => 'approved',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                \Log::info('Public Holiday Balance transaction created', [
                    'user_id' => $userId,
                    'difference' => $difference
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating public holiday balance: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function updateExtraOffBalance($userId, $amount, $notes = null)
    {
        try {
            // Get or create extra off balance record
            $balance = DB::table('extra_off_balance')
                ->where('user_id', $userId)
                ->first();
                
            \Log::info('Extra Off Balance Update', [
                'user_id' => $userId,
                'amount' => $amount,
                'existing_balance' => $balance ? $balance->balance : 0
            ]);
                
            if (!$balance) {
                // Create new balance record
                DB::table('extra_off_balance')->insert([
                    'user_id' => $userId,
                    'balance' => $amount,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                \Log::info('Extra Off Balance created', [
                    'user_id' => $userId,
                    'balance' => $amount
                ]);
            } else {
                // Update existing balance
                $currentBalance = $balance->balance;
                $difference = $amount - $currentBalance;
                
                DB::table('extra_off_balance')
                    ->where('user_id', $userId)
                    ->update([
                        'balance' => $amount,
                        'updated_at' => now()
                    ]);
                    
                \Log::info('Extra Off Balance updated', [
                    'user_id' => $userId,
                    'old_balance' => $currentBalance,
                    'new_balance' => $amount,
                    'difference' => $difference
                ]);
                    
                // Create transaction record for the adjustment
                if ($difference != 0) {
                    DB::table('extra_off_transactions')->insert([
                        'user_id' => $userId,
                        'transaction_type' => $difference > 0 ? 'earned' : 'used',
                        'amount' => abs($difference),
                        'source_type' => 'manual_adjustment',
                        'description' => $notes ?: 'Manual adjustment - Extra Off Balance',
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    \Log::info('Extra Off transaction created', [
                        'user_id' => $userId,
                        'difference' => $difference
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error updating extra off balance: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function getExtraOffBalance($userId)
    {
        $balance = DB::table('extra_off_balance')
            ->where('user_id', $userId)
            ->first();
            
        return response()->json([
            'success' => true,
            'balance' => $balance ? $balance->balance : 0
        ]);
    }
    
    public function getPublicHolidayBalance($userId)
    {
        $balance = DB::table('holiday_attendance_compensations')
            ->where('user_id', $userId)
            ->where('compensation_type', 'bonus')
            ->where('status', 'approved')
            ->sum('compensation_amount');
            
        return response()->json([
            'success' => true,
            'balance' => $balance ?: 0
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Create', [
            'user' => [
                'nik' => '',
                'no_ktp' => '',
                'nama_lengkap' => '',
                'email' => '',
                'password' => '',
                'hint_password' => '',
                'nama_panggilan' => '',
                'jenis_kelamin' => '',
                'tempat_lahir' => '',
                'tanggal_lahir' => '',
                'suku' => '',
                'agama' => '',
                'status_pernikahan' => '',
                'alamat' => '',
                'alamat_ktp' => '',
                'foto_ktp' => '',
                'nomor_kk' => '',
                'foto_kk' => '',
                'no_hp' => '',
                'status' => 'A',
                'id_jabatan' => '',
                'id_outlet' => '',
                'division_id' => '',
                'imei' => '',
                'golongan_darah' => '',
                'nama_rekening' => '',
                'no_rekening' => '',
                'nama_kontak_darurat' => '',
                'no_hp_kontak_darurat' => '',
                'hubungan_kontak_darurat' => '',
                'pin_pos' => '',
                'npwp_number' => '',
                'bpjs_health_number' => '',
                'bpjs_employment_number' => '',
                'last_education' => '',
                'name_school_college' => '',
                'school_college_major' => '',
                'upload_latest_color_photo' => '',
                'tanggal_masuk' => '',
            ]
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', [
            'user' => $user
        ]);
    }

    public function activate(Request $request, User $user)
    {
        $request->validate([
            'jabatan_id' => 'required|integer|exists:tbl_data_jabatan,id_jabatan',
            'division_id' => 'required|integer|exists:tbl_data_divisi,id',
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'tanggal_masuk' => 'required|date|before_or_equal:today',
        ]);

        try {
            $updateData = [
                'id_jabatan' => $request->jabatan_id,
                'division_id' => $request->division_id,
                'id_outlet' => $request->outlet_id,
                'tanggal_masuk' => $request->tanggal_masuk,
            ];

            // Jika status 'B' (Baru), aktifkan karyawan
            if ($user->status === 'B') {
                $updateData['status'] = 'A';
                $message = 'Karyawan berhasil diaktifkan!';
            } else {
                // Jika status lain, hanya update data tanpa mengubah status
                $message = 'Data karyawan berhasil diperbarui!';
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengaktifkan karyawan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload avatar for authenticated user
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            $user = auth()->user();
            
            // Delete old avatar if exists
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            
            // Update user record
            $user->update(['avatar' => $avatarPath]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar berhasil diupload',
                'avatar_path' => $avatarPath
            ]);

        } catch (\Exception $e) {
            \Log::error('Error uploading avatar: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload avatar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload banner for authenticated user
     */
    public function uploadBanner(Request $request)
    {
        $request->validate([
            'banner' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120' // 5MB max
        ]);

        try {
            $user = auth()->user();
            
            // Delete old banner if exists
            if ($user->banner && \Storage::disk('public')->exists($user->banner)) {
                \Storage::disk('public')->delete($user->banner);
            }

            // Store new banner
            $bannerPath = $request->file('banner')->store('banners', 'public');
            
            // Update user record
            $user->update(['banner' => $bannerPath]);

            return response()->json([
                'success' => true,
                'message' => 'Banner berhasil diupload',
                'banner_path' => $bannerPath
            ]);

        } catch (\Exception $e) {
            \Log::error('Error uploading banner: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload banner: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export users to Excel
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->only(['search', 'outlet_id', 'division_id', 'status']);
            
            return new UsersExport($filters);
            
        } catch (\Exception $e) {
            \Log::error('Error exporting users: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengexport data karyawan: ' . $e->getMessage()
            ], 500);
        }
    }
} 