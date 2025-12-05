<?php

namespace App\Http\Controllers\Mobile\ApprovalApp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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
}

