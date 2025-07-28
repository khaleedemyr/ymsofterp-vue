<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');

        $query = User::query()
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select(
                'users.*',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_outlet.nama_outlet'
            );
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
        $users = $query->orderBy('users.id', 'desc')->paginate(10)->withQueryString();

        $outlets = DB::table('tbl_data_outlet')->where('status', 'A')->select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $divisions = DB::table('tbl_data_divisi')->where('status', 'A')->select('id', 'nama_divisi')->orderBy('nama_divisi')->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
            ],
            'outlets' => $outlets,
            'divisions' => $divisions,
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
                'avatar' => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            ]);
            
            \Log::info('UserController@update validated data', $validated);
            
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
                if ($user->foto_ktp && Storage::disk('public')->exists($user->foto_ktp)) {
                    Storage::disk('public')->delete($user->foto_ktp);
                }
                $validated['foto_ktp'] = $request->file('foto_ktp')->store('users/foto_ktp', 'public');
            }
            if ($request->hasFile('foto_kk')) {
                if ($user->foto_kk && Storage::disk('public')->exists($user->foto_kk)) {
                    Storage::disk('public')->delete($user->foto_kk);
                }
                $validated['foto_kk'] = $request->file('foto_kk')->store('users/foto_kk', 'public');
            }
            if ($request->hasFile('upload_latest_color_photo')) {
                if ($user->upload_latest_color_photo && Storage::disk('public')->exists($user->upload_latest_color_photo)) {
                    Storage::disk('public')->delete($user->upload_latest_color_photo);
                }
                $validated['upload_latest_color_photo'] = $request->file('upload_latest_color_photo')->store('users/photos', 'public');
            }
            if ($request->hasFile('avatar')) {
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $validated['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
            }
            if (!empty($validated['password'])) {
                $validated['password'] = bcrypt($validated['password']);
            } else {
                unset($validated['password']);
            }
            
            \Log::info('UserController@update final data to save', $validated);
            
            $user->update($validated);
            
            \Log::info('UserController@update user after update', $user->fresh()->toArray());
            \Log::info('UserController@update success', $validated);
            
            return redirect()->route('users.index')->with('success', 'Data karyawan berhasil diupdate');
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
        // Delete associated files
        if ($user->foto_ktp && Storage::disk('public')->exists($user->foto_ktp)) {
            Storage::disk('public')->delete($user->foto_ktp);
        }
        if ($user->foto_kk && Storage::disk('public')->exists($user->foto_kk)) {
            Storage::disk('public')->delete($user->foto_kk);
        }
        if ($user->upload_latest_color_photo && Storage::disk('public')->exists($user->upload_latest_color_photo)) {
            Storage::disk('public')->delete($user->upload_latest_color_photo);
        }

        $user->delete();
        return redirect()->back();
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
} 