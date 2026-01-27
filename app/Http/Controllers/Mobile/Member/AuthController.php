<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsOccupation;
use App\Services\MemberTierService;
use App\Services\FCMService;
use App\Mail\MemberEmailVerification;
use App\Mail\MemberPasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Register new member
     */
    public function register(Request $request)
    {
        // Normalize email (lowercase) and mobile phone (remove spaces, dashes, etc)
        $normalizedEmail = strtolower(trim($request->email));
        $normalizedMobile = preg_replace('/[^0-9+]/', '', trim($request->mobile_phone)); // Keep only numbers and +
        
        // Check if email already exists (case-insensitive)
        $existingEmail = MemberAppsMember::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($normalizedEmail))])->first();
        if ($existingEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Email ini sudah terdaftar. Silakan gunakan email lain atau login dengan email tersebut.',
                'error_type' => 'email_already_registered',
                'errors' => [
                    'email' => ['Email ini sudah terdaftar. Silakan gunakan email lain atau login dengan email tersebut.']
                ],
                'suggestion' => 'Jika Anda sudah memiliki akun, silakan login menggunakan email dan password yang sudah terdaftar. Jika lupa password, gunakan fitur "Lupa Password".'
            ], 422);
        }
        
        // Check if mobile phone already exists (normalized)
        // Check exact match first
        $existingMobileExact = MemberAppsMember::where('mobile_phone', $normalizedMobile)->first();
        if ($existingMobileExact) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor HP ini sudah terdaftar. Silakan gunakan nomor HP lain atau login dengan nomor HP tersebut.',
                'error_type' => 'mobile_phone_already_registered',
                'errors' => [
                    'mobile_phone' => ['Nomor HP ini sudah terdaftar. Silakan gunakan nomor HP lain atau login dengan nomor HP tersebut.']
                ],
                'suggestion' => 'Jika Anda sudah memiliki akun, silakan login menggunakan nomor HP dan password yang sudah terdaftar. Jika lupa password, gunakan fitur "Lupa Password".'
            ], 422);
        }
        
        // Check normalized match (remove common formatting characters)
        // Get all members and check normalized mobile phones
        $allMembers = MemberAppsMember::select('id', 'mobile_phone')
            ->whereNotNull('mobile_phone')
            ->where('mobile_phone', '!=', '')
            ->get();
        
        foreach ($allMembers as $existingMember) {
            $existingMobileNormalized = preg_replace('/[^0-9+]/', '', trim($existingMember->mobile_phone ?? ''));
            if ($existingMobileNormalized === $normalizedMobile && $normalizedMobile !== '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor HP ini sudah terdaftar. Silakan gunakan nomor HP lain atau login dengan nomor HP tersebut.',
                    'error_type' => 'mobile_phone_already_registered',
                    'errors' => [
                        'mobile_phone' => ['Nomor HP ini sudah terdaftar. Silakan gunakan nomor HP lain atau login dengan nomor HP tersebut.']
                    ],
                    'suggestion' => 'Jika Anda sudah memiliki akun, silakan login menggunakan nomor HP dan password yang sudah terdaftar. Jika lupa password, gunakan fitur "Lupa Password".'
                ], 422);
            }
        }
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'nama_lengkap' => 'required|string|max:255',
            'mobile_phone' => 'required|string|max:20',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'pekerjaan_id' => 'required|exists:member_apps_occupations,id',
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
                'email' => $normalizedEmail, // Store normalized (lowercase) email
                'nama_lengkap' => $request->nama_lengkap,
                'mobile_phone' => $normalizedMobile, // Store normalized mobile phone
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'pekerjaan_id' => $request->pekerjaan_id, // Required field
                'password' => Hash::make($request->password),
                'pin' => $request->pin ? Hash::make($request->pin) : null,
                'member_level' => 'Silver', // Default level untuk member baru (Silver, Loyal, Elite)
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

            // Send email verification
            try {
                $verificationToken = Str::random(64);
                $expiresAt = now()->addHours(24);
                
                // Store verification token in password_reset_tokens table (reuse existing table)
                DB::table('password_reset_tokens')->where('email', $member->email)->delete();
                DB::table('password_reset_tokens')->insert([
                    'email' => $member->email,
                    'token' => Hash::make($verificationToken),
                    'created_at' => now(),
                ]);
                
                // Generate verification URL
                $verificationUrl = url("/member/verify-email/{$member->id}/{$verificationToken}");
                
                // Send verification email
                Mail::to($member->email)->send(new MemberEmailVerification($member, $verificationUrl));
                
                Log::info('Email verification sent to new member', [
                    'member_id' => $member->id,
                    'email' => $member->email,
                ]);
            } catch (\Exception $e) {
                // Log error but don't fail registration
                Log::error('Failed to send email verification', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

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
                'message' => 'Registration successful. Please verify your email before logging in.',
                'data' => [
                    'member' => [
                        'id' => $member->id,
                        'member_id' => $member->member_id,
                        'email' => $member->email,
                        'nama_lengkap' => $member->nama_lengkap,
                        'mobile_phone' => $member->mobile_phone,
                        'member_level' => $member->member_level,
                        'just_points' => $member->just_points,
                        'email_verified_at' => $member->email_verified_at,
                    ],
                    'token' => $token,
                    'requires_email_verification' => true,
                    'email_verification_sent' => true,
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
                // Normalize mobile phone (remove spaces, dashes, etc)
                $normalizedPhone = preg_replace('/[^0-9+]/', '', trim($mobilePhone));
                
                // Try exact match first
                $member = MemberAppsMember::where('mobile_phone', $normalizedPhone)->first();
                
                // If not found, try normalized match (remove all non-numeric except +)
                if (!$member) {
                    $allMembers = MemberAppsMember::select('id', 'mobile_phone')
                        ->whereNotNull('mobile_phone')
                        ->where('mobile_phone', '!=', '')
                        ->get();
                    
                    foreach ($allMembers as $existingMember) {
                        $existingPhoneNormalized = preg_replace('/[^0-9+]/', '', trim($existingMember->mobile_phone ?? ''));
                        if ($existingPhoneNormalized === $normalizedPhone && $normalizedPhone !== '') {
                            $member = MemberAppsMember::find($existingMember->id);
                            break;
                        }
                    }
                }
            }
            
            // Auto-detect: if member_id looks like a phone number (only digits, 10-15 chars), try as phone
            if (!$member && $memberId) {
                $cleanMemberId = trim($memberId);
                // Check if it looks like a phone number (only digits, length 10-15)
                if (preg_match('/^[0-9]{10,15}$/', $cleanMemberId)) {
                    $normalizedPhone = preg_replace('/[^0-9+]/', '', $cleanMemberId);
                    
                    // Try exact match first
                    $member = MemberAppsMember::where('mobile_phone', $normalizedPhone)->first();
                    
                    // If not found, try normalized match
                    if (!$member) {
                        $allMembers = MemberAppsMember::select('id', 'mobile_phone')
                            ->whereNotNull('mobile_phone')
                            ->where('mobile_phone', '!=', '')
                            ->get();
                        
                        foreach ($allMembers as $existingMember) {
                            $existingPhoneNormalized = preg_replace('/[^0-9+]/', '', trim($existingMember->mobile_phone ?? ''));
                            if ($existingPhoneNormalized === $normalizedPhone && $normalizedPhone !== '') {
                                $member = MemberAppsMember::find($existingMember->id);
                                break;
                            }
                        }
                    }
                }
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

            // Check if email is verified
            if (!$member->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email before logging in. Check your inbox for the verification link.',
                    'requires_email_verification' => true,
                    'email' => $member->email,
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

            // Rate limiting: Check if there's a recent token request (within last 1 minute)
            // Reduced to 1 minute to be more user-friendly, especially when SMTP rate limit occurs
            // This prevents spam while allowing legitimate retries if email fails
            $recentToken = DB::table('password_reset_tokens')
                ->where('email', $member->email)
                ->where('created_at', '>=', now()->subMinute())
                ->first();

            if ($recentToken) {
                $secondsSinceLastRequest = now()->diffInSeconds(\Carbon\Carbon::parse($recentToken->created_at));
                $remainingSeconds = max(0, 60 - $secondsSinceLastRequest);
                
                Log::warning('Password reset rate limit exceeded', [
                    'email' => $member->email,
                    'member_id' => $member->id,
                    'last_request' => $recentToken->created_at,
                    'seconds_since_last_request' => $secondsSinceLastRequest,
                    'remaining_seconds' => $remainingSeconds
                ]);
                
                // Still return success for security, but don't create new token
                $remainingMessage = $remainingSeconds > 0 
                    ? 'Please wait ' . ceil($remainingSeconds) . ' second(s) before requesting again.'
                    : 'Please wait a moment before requesting again.';
                    
                return response()->json([
                    'success' => true,
                    'message' => 'If the email exists, a password reset link has been sent. ' . $remainingMessage
                ]);
            }

            // Generate reset token (shorter token to fit in string column)
            $token = Str::random(60);
            
            // Store token in password_reset_tokens table using updateOrInsert to avoid race condition
            // This ensures atomic operation: delete old and insert new in one query
            $createdAt = now();
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $member->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => $createdAt
                ]
            );

            // Send email with reset link (URL encode token to handle special characters)
            $resetUrl = url('/reset-password?token=' . urlencode($token) . '&email=' . urlencode($member->email));
            
            Log::info('Password reset token created', [
                'email' => $member->email,
                'member_id' => $member->id,
                'created_at' => $createdAt->toDateTimeString(),
                'expires_at' => $createdAt->copy()->addMinutes(60)->toDateTimeString(),
                'token_length' => strlen($token)
            ]);
            
            // Try to send email with rate limit protection
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
                
                // Add small delay to avoid rate limiting (0.5 seconds)
                usleep(500000);
                
                // Log mail configuration for debugging
                Log::info('Mail configuration check', [
                    'mailer' => config('mail.default'),
                    'mail_host' => config('mail.mailers.smtp.host'),
                    'mail_port' => config('mail.mailers.smtp.port'),
                    'mail_username' => config('mail.mailers.smtp.username'),
                    'mail_username_length' => strlen(config('mail.mailers.smtp.username', '')),
                    'mail_encryption' => config('mail.mailers.smtp.encryption'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name')
                ]);
                
                // Send email using Mailable class that forces sync (no queue)
                try {
                    Mail::to($member->email)->send(new MemberPasswordReset($member, $resetUrl));
                    
                    Log::info('Password reset email sent (direct, not queued)', [
                        'email' => $member->email,
                        'member_id' => $member->id,
                        'sent_directly' => true
                    ]);
                } catch (\Exception $sendException) {
                    // Log any exception during sending (but don't fail the request)
                    Log::error('Exception during email send (but may have succeeded)', [
                        'email' => $member->email,
                        'member_id' => $member->id,
                        'error' => $sendException->getMessage(),
                        'trace' => $sendException->getTraceAsString()
                    ]);
                    // Continue - email might still be sent
                }
            } catch (\Exception $mailException) {
                // Check if it's a rate limit error from SMTP
                $isRateLimitError = strpos($mailException->getMessage(), 'Ratelimit') !== false 
                    || strpos($mailException->getMessage(), '451') !== false
                    || strpos($mailException->getMessage(), 'rate limit') !== false;
                
                // Log email error but don't fail the request (for security)
                Log::error('Failed to send password reset email: ' . $mailException->getMessage(), [
                    'email' => $member->email,
                    'member_id' => $member->id,
                    'is_rate_limit' => $isRateLimitError,
                    'trace' => $mailException->getTraceAsString()
                ]);
                
                // If rate limit error from SMTP, delete the token so user can retry immediately
                // This allows user to request again without waiting, since the email wasn't sent anyway
                // The token will be recreated on next request, and email will be sent when SMTP rate limit resets
                if ($isRateLimitError) {
                    DB::table('password_reset_tokens')->where('email', $member->email)->delete();
                    
                    Log::warning('SMTP rate limit exceeded - token deleted to allow retry', [
                        'email' => $member->email,
                        'member_id' => $member->id,
                        'action' => 'Token deleted. User can request again immediately. Email will be sent when SMTP rate limit resets.',
                        'suggestion' => 'Consider setting up queue (QUEUE_CONNECTION=database) and queue worker for better email handling during rate limits.'
                    ]);
                }
                // Continue - if not rate limit, token is still saved for user to use later
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

            // Log incoming request for debugging
            Log::info('Password reset attempt', [
                'email' => $request->email,
                'token_length' => strlen($request->token),
                'token_preview' => substr($request->token, 0, 20) . '...',
                'token_has_percent' => strpos($request->token, '%') !== false
            ]);

            // Check if token exists and is valid
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$passwordReset) {
                Log::warning('Password reset token not found', [
                    'email' => $request->email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            // Parse created_at to Carbon instance for accurate time comparison
            $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
            $expiresAt = $createdAt->copy()->addMinutes(60);
            
            // Check if token is expired (60 minutes)
            if (now()->isAfter($expiresAt)) {
                Log::warning('Password reset token expired', [
                    'email' => $request->email,
                    'created_at' => $createdAt->toDateTimeString(),
                    'expires_at' => $expiresAt->toDateTimeString(),
                    'now' => now()->toDateTimeString(),
                    'minutes_elapsed' => now()->diffInMinutes($createdAt)
                ]);
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Reset token has expired. Please request a new one.'
                ], 400);
            }

            // Decode token if it's URL encoded (handle double encoding)
            $tokenToVerify = $request->token;
            if (strpos($tokenToVerify, '%') !== false) {
                $tokenToVerify = urldecode($tokenToVerify);
            }
            
            // Verify token
            if (!Hash::check($tokenToVerify, $passwordReset->token)) {
                // Try with original token as well (in case it wasn't encoded)
                if ($tokenToVerify !== $request->token && !Hash::check($request->token, $passwordReset->token)) {
                    Log::warning('Password reset token mismatch', [
                        'email' => $request->email,
                        'token_provided' => substr($request->token, 0, 20) . '...',
                        'token_provided_length' => strlen($request->token),
                        'token_decoded_length' => strlen($tokenToVerify),
                        'token_stored_length' => strlen($passwordReset->token),
                        'token_has_percent' => strpos($request->token, '%') !== false,
                        'created_at' => $createdAt->toDateTimeString()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid reset token'
                    ], 400);
                }
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
            Log::error('Reset Password Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'email' => $request->input('email'),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request, $id, $token)
    {
        try {
            $member = MemberAppsMember::find($id);
            
            if (!$member) {
                return Inertia::render('Auth/MemberVerifyEmail', [
                    'success' => false,
                    'message' => 'Member not found'
                ]);
            }
            
            // Check if already verified
            if ($member->email_verified_at) {
                return Inertia::render('Auth/MemberVerifyEmail', [
                    'success' => true,
                    'message' => 'Email has already been verified',
                    'alreadyVerified' => true,
                    'member' => [
                        'id' => $member->id,
                        'member_id' => $member->member_id,
                        'nama_lengkap' => $member->nama_lengkap,
                        'email' => $member->email,
                    ]
                ]);
            }
            
            // Get verification token from database
            $verificationRecord = DB::table('password_reset_tokens')
                ->where('email', $member->email)
                ->first();
            
            if (!$verificationRecord) {
                return Inertia::render('Auth/MemberVerifyEmail', [
                    'success' => false,
                    'message' => 'Verification token not found. Please request a new verification email.',
                    'member' => [
                        'id' => $member->id,
                        'member_id' => $member->member_id,
                        'nama_lengkap' => $member->nama_lengkap,
                        'email' => $member->email,
                    ]
                ]);
            }
            
            // Check if token is expired (24 hours)
            if (now()->diffInHours($verificationRecord->created_at) > 24) {
                DB::table('password_reset_tokens')->where('email', $member->email)->delete();
                return Inertia::render('Auth/MemberVerifyEmail', [
                    'success' => false,
                    'message' => 'Verification token has expired. Please request a new verification email.',
                    'member' => [
                        'id' => $member->id,
                        'member_id' => $member->member_id,
                        'nama_lengkap' => $member->nama_lengkap,
                        'email' => $member->email,
                    ]
                ]);
            }
            
            // Verify token
            if (!Hash::check($token, $verificationRecord->token)) {
                return Inertia::render('Auth/MemberVerifyEmail', [
                    'success' => false,
                    'message' => 'Invalid verification token',
                    'member' => [
                        'id' => $member->id,
                        'member_id' => $member->member_id,
                        'nama_lengkap' => $member->nama_lengkap,
                        'email' => $member->email,
                    ]
                ]);
            }
            
            // Mark email as verified
            $member->update([
                'email_verified_at' => now()
            ]);
            
            // Delete used token
            DB::table('password_reset_tokens')->where('email', $member->email)->delete();
            
            Log::info('Email verified successfully', [
                'member_id' => $member->id,
                'email' => $member->email
            ]);
            
            return Inertia::render('Auth/MemberVerifyEmail', [
                'success' => true,
                'message' => 'Email verified successfully! You can now use all features of the app.',
                'member' => [
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'nama_lengkap' => $member->nama_lengkap,
                    'email' => $member->email,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Email verification error', [
                'member_id' => $id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('Auth/MemberVerifyEmail', [
                'success' => false,
                'message' => 'An error occurred during email verification. Please try again later.'
            ]);
        }
    }

    /**
     * Resend email verification
     */
    public function resendVerificationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:member_apps_members,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $member = MemberAppsMember::where('email', $request->email)->first();
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }
            
            // Check if already verified
            if ($member->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email has already been verified'
                ], 400);
            }
            
            // Generate new verification token
            $verificationToken = Str::random(64);
            
            // Store verification token
            DB::table('password_reset_tokens')->where('email', $member->email)->delete();
            DB::table('password_reset_tokens')->insert([
                'email' => $member->email,
                'token' => Hash::make($verificationToken),
                'created_at' => now(),
            ]);
            
            // Generate verification URL
            $verificationUrl = url("/member/verify-email/{$member->id}/{$verificationToken}");
            
            // Send verification email
            Mail::to($member->email)->send(new MemberEmailVerification($member, $verificationUrl));
            
            Log::info('Email verification resent', [
                'member_id' => $member->id,
                'email' => $member->email,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Verification email has been sent. Please check your inbox.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to resend email verification', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Generate unique member ID
     * Format: JT + 2 angka tahun + 2 angka bulan + 4 karakter random
     * Total: 10 karakter
     * Example: JT251201A2, JT250105XY, JT2512123B
     * Kombinasi per bulan: 36^4 = 1,679,616 (lebih dari 1.6 juta per bulan)
     */
    private function generateMemberId()
    {
        $maxAttempts = 1000; // Prevent infinite loop
        $attempts = 0;
        $memberId = null;
        
        do {
            // Prefix: JT (2 karakter)
            $prefix = 'JT';
            
            // 2 angka tahun (2 digit terakhir tahun)
            $year = date('y'); // e.g., 25 for 2025
            
            // 2 angka bulan (2 digit bulan dengan leading zero)
            $month = date('m'); // e.g., 01, 02, ..., 12
            
            // 4 karakter random kombinasi angka dan huruf (uppercase)
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $random = '';
            for ($i = 0; $i < 4; $i++) {
                $random .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            // Combine: JT + tahun + bulan + random = 10 karakter total
            $memberId = $prefix . $year . $month . $random;
            
            $attempts++;
            
            // Safety check to prevent infinite loop
            if ($attempts >= $maxAttempts) {
                \Log::error('Failed to generate unique member_id after ' . $maxAttempts . ' attempts', [
                    'last_attempted_id' => $memberId
                ]);
                throw new \Exception('Failed to generate unique member_id. Please try again.');
            }
            
            // Check for duplicate (case-insensitive and trimmed)
            // Check exact match and case-insensitive match
            $exists = MemberAppsMember::where('member_id', $memberId)
                ->orWhereRaw('UPPER(TRIM(member_id)) = ?', [strtoupper(trim($memberId))])
                ->exists();
            
            // If duplicate found, log and continue loop
            if ($exists) {
                \Log::warning('Duplicate member_id detected during generation, retrying', [
                    'member_id' => $memberId,
                    'attempt' => $attempts
                ]);
            }
            
        } while ($exists);

        // Final verification before returning (extra safety check)
        $finalCheck = MemberAppsMember::where('member_id', $memberId)
            ->orWhereRaw('UPPER(TRIM(member_id)) = ?', [strtoupper(trim($memberId))])
            ->first();
        
        if ($finalCheck) {
            \Log::error('CRITICAL: Duplicate member_id detected after generation - this should not happen', [
                'member_id' => $memberId,
                'existing_member_id' => $finalCheck->id,
                'existing_member_member_id' => $finalCheck->member_id,
                'attempts' => $attempts
            ]);
            // This is a critical error - throw exception
            throw new \Exception('Critical error: Duplicate member_id detected. Please contact administrator.');
        }

        // Log successful generation
        \Log::info('Generated unique member_id', [
            'member_id' => $memberId,
            'year' => $year,
            'month' => $month,
            'attempts' => $attempts
        ]);

        return $memberId;
    }
}
