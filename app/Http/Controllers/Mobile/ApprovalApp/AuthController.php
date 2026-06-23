<?php

namespace App\Http\Controllers\Mobile\ApprovalApp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Support\HrdApprovalAccess;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Login for Approval App
     * Different from web and member app - specifically for approval management
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'device_id' => 'nullable|string',
            'device_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        if ($user->status !== 'A') {
            return response()->json([
                'success' => false,
                'message' => 'User tidak aktif',
            ], 401);
        }

        // Optional: Check device ID if provided (for security)
        if ($request->filled('device_id')) {
            // You can add device validation logic here if needed
            // For now, we'll just store it
        }

        // Update last seen and device info
        $user->last_seen = now();
        if ($request->has('device_id')) {
            // Store device ID in imei field if needed (optional)
            // $user->imei = $request->device_id;
        }
        if ($request->has('device_info')) {
            // Only save device_info if the column exists
            try {
                $user->device_info = json_encode($request->device_info);
            } catch (\Exception $e) {
                // Column might not exist, skip saving device_info
                \Log::warning('device_info column not found in users table', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        $user->save();

        // Generate token and save to remember_token
        $token = bin2hex(random_bytes(32));
        $user->remember_token = $token;
        $user->save();

        // Append additional user info
        $this->appendUserInfo($user);

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $user
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan',
            ], 401);
        }

        $user = User::where('remember_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        }

        if ($user->status !== 'A') {
            return response()->json([
                'success' => false,
                'message' => 'User tidak aktif',
            ], 401);
        }

        $this->appendUserInfo($user);

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        
        if ($token) {
            $user = User::where('remember_token', $token)->first();
            if ($user) {
                $user->remember_token = null;
                $user->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout'
        ]);
    }

    /**
     * Append additional user information
     */
    protected function appendUserInfo($user)
    {
        // Load relationships
        $user->load(['divisi', 'jabatan', 'outlet']);
        
        // Add division information using relationship
        if ($user->divisi) {
            $user->division_name = $user->divisi->nama_divisi;
        } elseif ($user->division_id) {
            // Fallback to direct query if relationship not loaded
            $division = DB::table('tbl_data_divisi')
                ->where('id', $user->division_id)
                ->first();
            if ($division) {
                $user->division_name = $division->nama_divisi;
            }
        }

        // Add jabatan information using relationship
        if ($user->jabatan) {
            $user->jabatan_name = $user->jabatan->nama_jabatan;
            
            // Load level relationship
            $user->jabatan->load('level');
            if ($user->jabatan->level) {
                $user->jabatan->level_name = $user->jabatan->level->nama_level;
                // Also set at user level for easier access
                $user->level_name = $user->jabatan->level->nama_level;
            } elseif ($user->jabatan->id_level) {
                // Fallback: query level directly if relationship not available
                $level = DB::table('tbl_data_level')
                    ->where('id', $user->jabatan->id_level)
                    ->first();
                if ($level) {
                    $user->jabatan->level_name = $level->nama_level;
                    $user->level_name = $level->nama_level;
                }
            }
        } elseif ($user->id_jabatan) {
            // Fallback to direct query if relationship not loaded
            $jabatan = DB::table('tbl_data_jabatan')
                ->where('id_jabatan', $user->id_jabatan)
                ->first();
            if ($jabatan) {
                $user->jabatan_name = $jabatan->nama_jabatan;
                
                // Try to get level
                if (isset($jabatan->id_level) && $jabatan->id_level) {
                    $level = DB::table('tbl_data_level')
                        ->where('id', $jabatan->id_level)
                        ->first();
                    if ($level) {
                        $user->level_name = $level->nama_level;
                    }
                }
            }
        }

        // Add outlet information using relationship
        if ($user->outlet) {
            $user->outlet_name = $user->outlet->nama_outlet;
        } elseif ($user->id_outlet) {
            // Fallback to direct query if relationship not loaded
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $user->id_outlet)
                ->first();
            if ($outlet) {
                $user->outlet_name = $outlet->nama_outlet;
            }
        }

        // HR approval access flag for mobile app
        $user->can_access_hrd_approvals = HrdApprovalAccess::canAccessHrdApprovals($user);

        // Remove sensitive information
        unset($user->password);
        unset($user->imei);
    }
    
    /**
     * Upload banner
     */
    public function uploadBanner(Request $request)
    {
        $request->validate([
            'banner' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120' // 5MB max
        ]);

        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                ], 401);
            }
            
            // Delete old banner if exists
            if ($user->banner && \Storage::disk('public')->exists($user->banner)) {
                \Storage::disk('public')->delete($user->banner);
            }
            
            // Store new banner
            $path = $request->file('banner')->store('banners', 'public');
            $user->banner = $path;
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Banner berhasil diupload',
                'banner_path' => $path,
                'banner_url' => \Storage::disk('public')->url($path),
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
     * Upload avatar
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048' // 2MB max
        ]);

        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                ], 401);
            }
            
            // Delete old avatar if exists
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }
            
            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Avatar berhasil diupload',
                'avatar_path' => $path,
                'avatar_url' => \Storage::disk('public')->url($path),
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
     * Get allowed menus for current user
     */
    public function getAllowedMenus(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan',
            ], 401);
        }

        $user = User::where('remember_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        }

        if ($user->status !== 'A') {
            return response()->json([
                'success' => false,
                'message' => 'User tidak aktif',
            ], 401);
        }

        // Get allowed menus (same logic as HandleInertiaRequests)
        $allowedMenus = DB::table('users as u')
            ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
            ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
            ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
            ->where('u.id', $user->id)
            ->where('p.action', 'view')
            ->distinct()
            ->pluck('m.code')
            ->toArray();

        return response()->json([
            'success' => true,
            'allowedMenus' => $allowedMenus
        ]);
    }

    /**
     * Update profile (ymsoftapp self-service)
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        // PATCH + multipart sering tidak terbaca di PHP; fallback ke data user yang ada
        if (!$request->filled('nama_lengkap') && !empty($user->nama_lengkap)) {
            $request->merge(['nama_lengkap' => $user->nama_lengkap]);
        }

        $validator = Validator::make($request->all(), [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nama_panggilan' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'no_hp' => ['nullable', 'string', 'max:15'],
            'jenis_kelamin' => ['nullable', 'string', 'max:1'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'suku' => ['nullable', 'string', 'max:50'],
            'agama' => ['nullable', 'string', 'max:50'],
            'status_pernikahan' => ['nullable', 'string', 'max:50'],
            'golongan_darah' => ['nullable', 'string', 'max:5'],
            'alamat' => ['nullable', 'string'],
            'alamat_ktp' => ['nullable', 'string'],
            'pin_pos' => ['nullable', 'string', 'max:10'],
            'pin_payroll' => ['nullable', 'string', 'max:10'],
            'nama_rekening' => ['nullable', 'string', 'max:255'],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'npwp_number' => ['nullable', 'string', 'max:100'],
            'bpjs_health_number' => ['nullable', 'string', 'max:100'],
            'bpjs_employment_number' => ['nullable', 'string', 'max:100'],
            'last_education' => ['nullable', 'string', 'max:100'],
            'name_school_college' => ['nullable', 'string', 'max:255'],
            'school_college_major' => ['nullable', 'string', 'max:255'],
            'nama_kontak_darurat' => ['nullable', 'string', 'max:255'],
            'no_hp_kontak_darurat' => ['nullable', 'string', 'max:15'],
            'hubungan_kontak_darurat' => ['nullable', 'string', 'max:50'],
            'no_ktp' => ['nullable', 'string', 'max:50'],
            'nomor_kk' => ['nullable', 'string', 'max:50'],
            'imei' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'foto_ktp' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'foto_kk' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'upload_latest_color_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'tanggal_lahir.date' => 'Format tanggal lahir tidak valid.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->filled('pin_pos')) {
            $exists = User::where('pin_pos', $request->pin_pos)
                ->where('status', 'A')
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN POS sudah digunakan oleh karyawan aktif lain.',
                ], 422);
            }
        }

        $allowedFields = [
            'nama_lengkap', 'nama_panggilan', 'email', 'no_hp', 'jenis_kelamin',
            'tempat_lahir', 'tanggal_lahir', 'suku', 'agama', 'status_pernikahan',
            'golongan_darah', 'alamat', 'alamat_ktp', 'pin_pos', 'pin_payroll',
            'nama_rekening', 'no_rekening', 'npwp_number', 'bpjs_health_number',
            'bpjs_employment_number', 'last_education', 'name_school_college',
            'school_college_major', 'nama_kontak_darurat', 'no_hp_kontak_darurat',
            'hubungan_kontak_darurat', 'no_ktp', 'nomor_kk', 'imei',
        ];

        foreach ($allowedFields as $field) {
            if ($request->has($field) && $request->input($field) !== null && $request->input($field) !== '') {
                $user->$field = $request->input($field);
            }
        }

        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('users/avatars', 'public');
        }

        if ($request->hasFile('foto_ktp') && $request->file('foto_ktp')->isValid()) {
            if ($user->foto_ktp && Storage::disk('public')->exists($user->foto_ktp)) {
                Storage::disk('public')->delete($user->foto_ktp);
            }
            $user->foto_ktp = $request->file('foto_ktp')->store('users/foto_ktp', 'public');
        }

        if ($request->hasFile('foto_kk') && $request->file('foto_kk')->isValid()) {
            if ($user->foto_kk && Storage::disk('public')->exists($user->foto_kk)) {
                Storage::disk('public')->delete($user->foto_kk);
            }
            $user->foto_kk = $request->file('foto_kk')->store('users/foto_kk', 'public');
        }

        if ($request->hasFile('upload_latest_color_photo') && $request->file('upload_latest_color_photo')->isValid()) {
            if ($user->upload_latest_color_photo && Storage::disk('public')->exists($user->upload_latest_color_photo)) {
                Storage::disk('public')->delete($user->upload_latest_color_photo);
            }
            $user->upload_latest_color_photo = $request->file('upload_latest_color_photo')->store('users/photos', 'public');
        }

        $user->save();
        $this->appendUserInfo($user);

        return response()->json([
            'success' => true,
            'message' => 'Profile berhasil diperbarui',
            'user' => $user,
        ]);
    }

    /**
     * Update password (ymsoftapp)
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini salah',
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui',
        ]);
    }

    /**
     * Update e-signature (ymsoftapp)
     */
    public function updateSignature(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'signature' => ['required', 'file', 'image', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
                Storage::disk('public')->delete($user->signature_path);
            }

            $file = $request->file('signature');
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'signatures/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($filename, file_get_contents($file));

            $user->signature_path = $filename;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil disimpan',
                'signature_path' => $filename,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error uploading signature: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan tanda tangan: ' . $e->getMessage(),
            ], 500);
        }
    }
}

