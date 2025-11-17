<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsOccupation;
use App\Services\MemberTierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Register new member
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:member_apps_members,email',
            'nama_lengkap' => 'required|string|max:255',
            'mobile_phone' => 'required|string|max:20|unique:member_apps_members,mobile_phone',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'pekerjaan_id' => 'nullable|exists:member_apps_occupations,id',
            'password' => 'required|string|min:6',
            'pin' => 'nullable|string|min:4|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate member ID
            $memberId = $this->generateMemberId();

            $member = MemberAppsMember::create([
                'member_id' => $memberId,
                'email' => $request->email,
                'nama_lengkap' => $request->nama_lengkap,
                'mobile_phone' => $request->mobile_phone,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'pekerjaan_id' => $request->pekerjaan_id,
                'password' => Hash::make($request->password),
                'pin' => $request->pin ? Hash::make($request->pin) : null,
                'member_level' => 'Silver', // Default level untuk member baru (Silver, Loyal, Elite, Prestige)
                'is_active' => true,
                'just_points' => 0, // Start dengan 0 points
                'total_spending' => 0, // Start dengan 0 spending
            ]);

            // Create token
            $token = $member->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'member' => [
                        'id' => $member->id,
                        'member_id' => $member->member_id,
                        'email' => $member->email,
                        'nama_lengkap' => $member->nama_lengkap,
                        'mobile_phone' => $member->mobile_phone,
                        'member_level' => $member->member_level,
                        'just_points' => $member->just_points,
                    ],
                    'token' => $token,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Register Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login member
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find member by email or mobile_phone
            $member = MemberAppsMember::where('email', $request->email)
                ->orWhere('mobile_phone', $request->email)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if member is active
            if (!$member->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is inactive. Please contact support.'
                ], 403);
            }

            // Verify password
            if (!Hash::check($request->password, $member->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Update last login
            $member->update([
                'last_login_at' => now()
            ]);

            // Create token
            $token = $member->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'member' => [
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'email' => $member->email,
                    'nama_lengkap' => $member->nama_lengkap,
                    'mobile_phone' => $member->mobile_phone,
                    'member_level' => $member->member_level,
                    'just_points' => $member->just_points,
                    'total_spending' => $member->total_spending,
                    ],
                    'token' => $token,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout member
     */
    public function logout(Request $request)
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);
        } catch (\Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current authenticated member
     */
    public function me(Request $request)
    {
        try {
            $member = $request->user();
            
            // Get tier progress with rolling 12-month spending
            $tierProgress = MemberTierService::getTierProgress($member->id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'email' => $member->email,
                    'nama_lengkap' => $member->nama_lengkap,
                    'mobile_phone' => $member->mobile_phone,
                    'tanggal_lahir' => $member->tanggal_lahir,
                    'jenis_kelamin' => $member->jenis_kelamin,
                    'member_level' => $member->member_level,
                    'just_points' => $member->just_points,
                    'total_spending' => $member->total_spending, // Lifetime spending (for backward compatibility)
                    'rolling_12_month_spending' => $tierProgress['rolling_12_month_spending'] ?? 0, // Rolling 12-month spending
                    'is_exclusive_member' => $member->is_exclusive_member,
                    'occupation' => $member->occupation ? [
                        'id' => $member->occupation->id,
                        'name' => $member->occupation->name,
                    ] : null,
                    'photo' => $member->photo ? 'https://ymsofterp.com/storage/' . $member->photo : null,
                    'tier_progress' => $tierProgress,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get Profile Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload profile photo
     */
    public function uploadPhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $member = $request->user();

            // Delete old photo if exists
            if ($member->photo) {
                $oldPhotoPath = storage_path('app/public/' . $member->photo);
                if (file_exists($oldPhotoPath)) {
                    unlink($oldPhotoPath);
                }
            }

            // Upload new photo
            $file = $request->file('photo');
            $fileName = 'member-apps/members/' . time() . '_' . $member->id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $fileName);

            // Update member photo
            $member->photo = $fileName;
            $member->save();

            return response()->json([
                'success' => true,
                'message' => 'Photo uploaded successfully',
                'data' => [
                    'photo' => 'https://ymsofterp.com/storage/' . $fileName,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Upload Photo Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get occupations list for registration form
     */
    public function getOccupations()
    {
        try {
            $occupations = MemberAppsOccupation::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => $occupations
            ]);
        } catch (\Exception $e) {
            Log::error('Get Occupations Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get occupations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique member ID
     * Format: JTS-YYMM-XXXXX
     * - JTS = prefix
     * - YY = 2 digit tahun (24 untuk 2024)
     * - MM = 2 digit bulan (01-12)
     * - XXXXX = 5 digit sequence per bulan (reset setiap bulan)
     * 
     * Example: 
     * - November 2024: JTS-2411-00001, JTS-2411-00002, ...
     * - December 2024: JTS-2412-00001, JTS-2412-00002, ... (reset sequence)
     */
    private function generateMemberId()
    {
        $prefix = 'JTS'; // Justus prefix
        $year = date('y'); // 2 digit tahun (24 untuk 2024)
        $month = date('m'); // 2 digit bulan (01-12)
        $yearMonth = $year . $month; // YYMM format
        
        $maxAttempts = 10;
        $attempt = 0;

        do {
            // Get last member ID for current month
            $lastMember = MemberAppsMember::whereNotNull('member_id')
                ->where('member_id', 'like', $prefix . '-' . $yearMonth . '-%')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastMember && $lastMember->member_id) {
                // Extract sequence from last member ID
                // Format: JTS-YYMM-XXXXX
                $parts = explode('-', $lastMember->member_id);
                if (count($parts) == 3 && $parts[0] == $prefix && $parts[1] == $yearMonth && is_numeric($parts[2])) {
                    $lastSequence = (int) $parts[2];
                    $newSequence = $lastSequence + 1;
                } else {
                    // If format is wrong, start from 1
                    $newSequence = 1;
                }
            } else {
                // No existing member for this month, start from 1
                $newSequence = 1;
            }

            // Format: JTS-YYMM-XXXXX (5 digits for sequence)
            $memberId = $prefix . '-' . $yearMonth . '-' . str_pad($newSequence, 5, '0', STR_PAD_LEFT);

            // Check if member ID already exists (shouldn't happen, but just in case)
            $exists = MemberAppsMember::where('member_id', $memberId)->exists();

            if (!$exists) {
                return $memberId;
            }

            $attempt++;
            $newSequence++; // Try next sequence if exists

        } while ($attempt < $maxAttempts);

        // Fallback: Use timestamp if all attempts fail
        return $prefix . '-' . $yearMonth . '-' . str_pad(substr(time(), -5), 5, '0', STR_PAD_LEFT);
    }
}

