<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsOccupation;
use App\Services\MemberTierService;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
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
            'referral_member_id' => 'nullable|string|exists:member_apps_members,member_id',
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

            // Award registration bonus points (100 points)
            try {
                $pointEarningService = app(\App\Services\PointEarningService::class);
                $pointEarningService->earnBonusPoints(
                    $member->id,
                    'registration',
                    null, // Use default 100 points
                    null, // Use default validity (1 year)
                    $member->member_id // Reference ID
                );
            } catch (\Exception $e) {
                // Log error but don't fail registration
                \Log::error('Failed to award registration bonus points', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Handle referral bonus (50 points to referrer)
            if ($request->has('referral_member_id') && $request->referral_member_id) {
                try {
                    // Find referrer member by member_id
                    $referrer = MemberAppsMember::where('member_id', $request->referral_member_id)
                        ->where('is_active', true)
                        ->first();

                    if ($referrer) {
                        // Award 50 points to referrer
                        $pointEarningService = app(\App\Services\PointEarningService::class);
                        $pointEarningService->earnBonusPoints(
                            $referrer->id,
                            'referral',
                            50, // 50 points for referral
                            null, // Use default validity (1 year)
                            $member->member_id // Reference ID: new member's member_id
                        );

                        \Log::info('Referral bonus awarded', [
                            'referrer_id' => $referrer->id,
                            'referrer_member_id' => $referrer->member_id,
                            'new_member_id' => $member->id,
                            'new_member_member_id' => $member->member_id,
                            'points_awarded' => 50
                        ]);
                    } else {
                        \Log::warning('Referrer not found or inactive', [
                            'referral_member_id' => $request->referral_member_id,
                            'new_member_id' => $member->id
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail registration
                    \Log::error('Failed to award referral bonus points', [
                        'referral_member_id' => $request->referral_member_id,
                        'new_member_id' => $member->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Refresh member to get updated points
            $member->refresh();

            // Send welcome notification
            try {
                $fcmService = app(FCMService::class);
                $fcmService->sendToMember(
                    $member,
                    'Welcome to Justus Group',
                    'Welcome to Justus Group! Enjoy exclusive member benefits, member challenges, and personalized offers.',
                    [
                        'type' => 'welcome',
                        'member_id' => $member->id,
                        'member_level' => $member->member_level,
                        'points' => $member->just_points ?? 0,
                    ]
                );
                
                Log::info('Welcome notification sent to new member', [
                    'member_id' => $member->id,
                    'member_member_id' => $member->member_id,
                ]);
            } catch (\Exception $e) {
                // Log error but don't fail registration
                Log::error('Failed to send welcome notification', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

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
     * Get member data by ID or phone number (for POS)
     * Public endpoint - no auth required
     */
    public function getMemberData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_id' => 'nullable|string',
                'mobile_phone' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $memberId = $request->input('member_id');
            $mobilePhone = $request->input('mobile_phone');

            if (!$memberId && !$mobilePhone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member ID or mobile phone is required'
                ], 400);
            }

            // Log the search attempt
            Log::info('Searching for member', [
                'member_id' => $memberId,
                'mobile_phone' => $mobilePhone
            ]);

            // Find member by member_id or mobile_phone
            $member = null;
            
            if ($memberId) {
                // Clean and prepare member ID variations
                $cleanMemberId = trim($memberId);
                $cleanMemberIdNoU = preg_replace('/^U/i', '', $cleanMemberId);
                $cleanMemberIdNoJTS = preg_replace('/^JTS-/i', '', $cleanMemberId);
                $cleanMemberIdNoPrefix = preg_replace('/^(U|JTS-)/i', '', $cleanMemberId);
                
                Log::info('Searching for member', [
                    'original' => $cleanMemberId,
                    'no_u' => $cleanMemberIdNoU,
                    'no_jts' => $cleanMemberIdNoJTS,
                    'no_prefix' => $cleanMemberIdNoPrefix
                ]);
                
                // Try exact match first (case insensitive) - most common case
                $member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberId))])->first();
                
                // Try without JTS- prefix
                if (!$member && $cleanMemberIdNoJTS !== $cleanMemberId) {
                    $member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberIdNoJTS))])->first();
                }
                
                // Try without U prefix
                if (!$member && $cleanMemberIdNoU !== $cleanMemberId) {
                    $member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberIdNoU))])->first();
                }
                
                // Try without any prefix
                if (!$member && $cleanMemberIdNoPrefix !== $cleanMemberId) {
                    $member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberIdNoPrefix))])->first();
                }
                
                // Try exact match (case sensitive) as fallback
                if (!$member) {
                    $member = MemberAppsMember::where('member_id', $cleanMemberId)->first();
                }
                
                // Try LIKE search (case insensitive) for partial match
                if (!$member) {
                    $member = MemberAppsMember::whereRaw('LOWER(member_id) LIKE ?', ['%' . strtolower($cleanMemberIdNoPrefix) . '%'])->first();
                }
                
                // Try as numeric ID if the cleaned ID is numeric
                if (!$member && is_numeric($cleanMemberIdNoPrefix)) {
                    $member = MemberAppsMember::where('id', (int)$cleanMemberIdNoPrefix)->first();
                }
                
                Log::info('Member search result', [
                    'found' => $member ? 'yes' : 'no',
                    'member_id' => $member ? $member->member_id : null,
                    'id' => $member ? $member->id : null
                ]);
            }
            
            // If member not found by member_id, try mobile_phone
            if (!$member && $mobilePhone) {
                $member = MemberAppsMember::where('mobile_phone', trim($mobilePhone))->first();
            }

            if (!$member) {
                Log::warning('Member not found', [
                    'member_id' => $memberId,
                    'mobile_phone' => $mobilePhone
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }

            Log::info('Member found', [
                'member_id' => $member->member_id,
                'id' => $member->id,
                'nama_lengkap' => $member->nama_lengkap
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Member data retrieved successfully',
                'data' => [
                    'member_id' => $member->member_id,
                    'id' => $member->id,
                    'nama_lengkap' => $member->nama_lengkap,
                    'email' => $member->email,
                    'mobile_phone' => $member->mobile_phone,
                    'member_level' => $member->member_level,
                    'just_points' => $member->just_points ?? 0,
                    'is_active' => $member->is_active ?? true,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get Member Data Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get member data',
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

            // Delete old tokens (optional - untuk security, hapus token lama)
            // $member->tokens()->delete();

            // Create token
            $token = $member->createToken('mobile-app')->plainTextToken;
            
            Log::info('Login successful', [
                'member_id' => $member->id,
                'email' => $member->email,
                'token_preview' => substr($token, 0, 20) . '...'
            ]);

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
            Log::error('Login Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
            $user = $request->user();
            
            if ($user) {
                // Delete current token
                $request->user()->currentAccessToken()->delete();
                
                Log::info('Logout successful', [
                    'member_id' => $user->id
                ]);
            }

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
            
            if (!$member) {
                Log::warning('Auth me called but no user found', [
                    'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : 'no token'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            Log::info('Auth me successful', [
                'member_id' => $member->id,
                'email' => $member->email
            ]);

            // Get tier progress with rolling 12-month spending
            $tierProgress = MemberTierService::getTierProgress($member->id);
            
            // Format tanggal_lahir ke Y-m-d untuk menghindari masalah timezone
            $tanggalLahir = $member->tanggal_lahir 
                ? \Carbon\Carbon::parse($member->tanggal_lahir)->format('Y-m-d')
                : null;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'email' => $member->email,
                    'nama_lengkap' => $member->nama_lengkap,
                    'mobile_phone' => $member->mobile_phone,
                    'tanggal_lahir' => $tanggalLahir,
                    'jenis_kelamin' => $member->jenis_kelamin,
                    'member_level' => $member->member_level,
                    'just_points' => $member->just_points,
                    'total_spending' => $member->total_spending, // Lifetime spending (for backward compatibility)
                    'rolling_12_month_spending' => $tierProgress['rolling_12_month_spending'] ?? 0, // Rolling 12-month spending
                    'is_exclusive_member' => $member->is_exclusive_member,
                    'allow_notification' => $member->allow_notification ?? true,
                    'occupation' => $member->occupation ? [
                        'id' => $member->occupation->id,
                        'name' => $member->occupation->name,
                    ] : null,
                    'photo' => $member->photo ? 'https://ymsofterp.com/storage/' . $member->photo : null,
                    'tier_progress' => $tierProgress,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get Profile Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : 'no token'
            ]);
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
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Delete old photo if exists
            if ($member->photo) {
                \Storage::disk('public')->delete($member->photo);
            }

            // Store new photo
            $photoPath = $request->file('photo')->store('member-apps/photos', 'public');
            
            $member->update([
                'photo' => $photoPath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Photo uploaded successfully',
                'data' => [
                    'photo' => 'https://ymsofterp.com/storage/' . $photoPath
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
     * Update notification preference
     */
    public function updateNotificationPreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'allow_notification' => 'required|boolean',
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
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $member->update([
                'allow_notification' => $request->allow_notification
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification preference updated successfully',
                'data' => [
                    'allow_notification' => $member->allow_notification
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Update Notification Preference Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification preference',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
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
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $member->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            // Update password
            $member->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Change Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change mobile number
     */
    public function changeMobileNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('member_apps_members', 'mobile_phone')->ignore($request->user()->id),
            ],
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
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $member->update([
                'mobile_phone' => $request->mobile_phone
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mobile number changed successfully',
                'data' => [
                    'mobile_phone' => $member->mobile_phone
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Change Mobile Number Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change mobile number',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'pekerjaan_id' => 'nullable|exists:member_apps_occupations,id',
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
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $member->update([
                'nama_lengkap' => $request->nama_lengkap,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'pekerjaan_id' => $request->pekerjaan_id,
            ]);

            // Format tanggal_lahir ke Y-m-d untuk menghindari masalah timezone
            $tanggalLahir = $member->tanggal_lahir 
                ? \Carbon\Carbon::parse($member->tanggal_lahir)->format('Y-m-d')
                : null;
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'email' => $member->email,
                    'nama_lengkap' => $member->nama_lengkap,
                    'mobile_phone' => $member->mobile_phone,
                    'tanggal_lahir' => $tanggalLahir,
                    'jenis_kelamin' => $member->jenis_kelamin,
                    'member_level' => $member->member_level,
                    'just_points' => $member->just_points,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Update Profile Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get occupations list
     */
    public function getOccupations()
    {
        try {
            $occupations = MemberAppsOccupation::where('is_active', true)
                ->orderBy('name', 'asc')
                ->get();

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
     * Request password reset (Forgot Password)
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find member by email
            $member = MemberAppsMember::where('email', $request->email)->first();

            // Always return success message for security (don't reveal if email exists)
            if (!$member) {
                return response()->json([
                    'success' => true,
                    'message' => 'If the email exists, a password reset link has been sent.'
                ]);
            }

            // Generate reset token (shorter token to fit in string column)
            $token = Str::random(60);
            
            // Store token in password_reset_tokens table
            // Delete old token first if exists
            DB::table('password_reset_tokens')->where('email', $member->email)->delete();
            
            // Insert new token
            DB::table('password_reset_tokens')->insert([
                'email' => $member->email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]);

            // Send email with reset link
            $resetUrl = url('/reset-password?token=' . $token . '&email=' . urlencode($member->email));
            
            // Try to send email, but don't fail if email sending fails (for security, still return success)
            try {
                $emailBody = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <h2>Reset Password Request</h2>
                        <p>Hello {$member->nama_lengkap},</p>
                        <p>You have requested to reset your password. Click the link below to reset your password:</p>
                        <p><a href='{$resetUrl}' style='background-color: #FFD700; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a></p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p>{$resetUrl}</p>
                        <p>This link will expire in 60 minutes.</p>
                        <p>If you did not request this, please ignore this email.</p>
                        <p>Best regards,<br>JUSTUS GROUP</p>
                    </body>
                    </html>
                ";
                
                Mail::raw('', function ($message) use ($member, $resetUrl, $emailBody) {
                    $message->to($member->email)
                        ->subject('Reset Password - JUSTUS GROUP')
                        ->html($emailBody);
                });
                
                Log::info('Password reset email sent', [
                    'email' => $member->email,
                    'member_id' => $member->id
                ]);
            } catch (\Exception $mailException) {
                // Log email error but don't fail the request (for security)
                Log::error('Failed to send password reset email: ' . $mailException->getMessage(), [
                    'email' => $member->email,
                    'member_id' => $member->id,
                    'trace' => $mailException->getTraceAsString()
                ]);
                // Continue - token is still saved, user can request again if needed
            }

            Log::info('Password reset requested', [
                'email' => $member->email,
                'member_id' => $member->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'If the email exists, a password reset link has been sent.'
            ]);
        } catch (\Exception $e) {
            Log::error('Forgot Password Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset link',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Reset password with token (for web landing page)
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find member by email
            $member = MemberAppsMember::where('email', $request->email)->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email address'
                ], 404);
            }

            // Check if token exists and is valid
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            // Check if token is expired (60 minutes)
            if (now()->diffInMinutes($passwordReset->created_at) > 60) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Reset token has expired. Please request a new one.'
                ], 400);
            }

            // Verify token
            if (!Hash::check($request->token, $passwordReset->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token'
                ], 400);
            }

            // Update password
            $member->update([
                'password' => Hash::make($request->password)
            ]);

            // Delete used token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            Log::info('Password reset successful', [
                'email' => $member->email,
                'member_id' => $member->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully. You can now login with your new password.'
            ]);
        } catch (\Exception $e) {
            Log::error('Reset Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique member ID
     * Format: JTS{2 angka tahun}{2 angka bulan}{4 kombinasi acak angka dan huruf}
     * Example: JTS251201A2B3
     */
    private function generateMemberId()
    {
        $maxAttempts = 1000; // Prevent infinite loop
        $attempts = 0;
        
        do {
            // Prefix: JTS
            $prefix = 'JTS';
            
            // 2 angka tahun (2 digit terakhir tahun)
            $year = date('y'); // e.g., 25 for 2025
            
            // 2 angka bulan (2 digit bulan dengan leading zero)
            $month = date('m'); // e.g., 01, 02, ..., 12
            
            // 4 kombinasi acak angka dan huruf (uppercase)
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $random = '';
            for ($i = 0; $i < 4; $i++) {
                $random .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            // Combine: JTS + tahun + bulan + random
            $memberId = $prefix . $year . $month . $random;
            
            $attempts++;
            
            // Safety check to prevent infinite loop
            if ($attempts >= $maxAttempts) {
                \Log::error('Failed to generate unique member_id after ' . $maxAttempts . ' attempts');
                throw new \Exception('Failed to generate unique member_id. Please try again.');
            }
            
        } while (MemberAppsMember::where('member_id', $memberId)->exists());

        return $memberId;
    }
}
