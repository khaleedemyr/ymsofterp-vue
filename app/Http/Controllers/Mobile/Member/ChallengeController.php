<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsChallenge;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    /**
     * Get all active challenges
     */
    public function index()
    {
        try {
            // Get all active challenges
            // For now, show all active challenges regardless of date (for testing)
            // TODO: Add proper date filtering if needed
            $allChallenges = MemberAppsChallenge::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();
            
            \Log::info('Challenges API - Total active challenges: ' . $allChallenges->count());
            \Log::info('Current date: ' . now()->toDateString());
            
            // Filter by date: show challenges that haven't ended yet
            $challenges = $allChallenges->filter(function ($challenge) {
                // If end_date exists and is in the past, exclude it
                if ($challenge->end_date && $challenge->end_date->isPast()) {
                    return false;
                }
                // Include all others (no end_date, or end_date in future, or start_date in future)
                return true;
            });
            
            \Log::info('Challenges API - After date filter: ' . $challenges->count() . ' challenges');
            
            $challenges = $challenges
                ->map(function ($challenge) {
                    // Build full URL for image
                    $imageUrl = null;
                    if ($challenge->image) {
                        // If image path already contains full URL, use it as is
                        if (str_starts_with($challenge->image, 'http://') || str_starts_with($challenge->image, 'https://')) {
                            $imageUrl = $challenge->image;
                        } else {
                            // Build full URL: https://ymsofterp.com/storage/{path}
                            $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($challenge->image, '/');
                        }
                    }

                    // Format dates
                    $startDate = $challenge->start_date ? $challenge->start_date->format('Y-m-d') : null;
                    $endDate = $challenge->end_date ? $challenge->end_date->format('Y-m-d') : null;

                    // Format valid until text
                    $validUntil = null;
                    if ($endDate) {
                        $endDateObj = \Carbon\Carbon::parse($endDate);
                        $validUntil = 'VALID UNTIL ' . strtoupper($endDateObj->format('d F Y'));
                    }

                    // Decode rules if it's a JSON string
                    $rules = $challenge->rules;
                    if (is_string($rules)) {
                        $decodedRules = json_decode($rules, true);
                        $rules = $decodedRules !== null ? $decodedRules : [];
                    } elseif (is_array($rules)) {
                        // Already decoded
                        $rules = $rules;
                    } else {
                        $rules = null;
                    }

                    return [
                        'id' => $challenge->id,
                        'title' => $challenge->title,
                        'description' => $challenge->description,
                        'image' => $imageUrl,
                        'points_reward' => $challenge->points_reward,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'valid_until' => $validUntil,
                        'challenge_type_id' => $challenge->challenge_type_id,
                        'validity_period_days' => $challenge->validity_period_days,
                        'rules' => $rules,
                    ];
                });

            $result = $challenges->toArray();
            \Log::info('Challenges API - Returning ' . count($result) . ' challenges');
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Challenges retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve challenges: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get challenge detail by ID
     */
    public function show($id)
    {
        try {
            $challenge = MemberAppsChallenge::findOrFail($id);

            // Build full URL for image
            $imageUrl = null;
            if ($challenge->image) {
                if (str_starts_with($challenge->image, 'http://') || str_starts_with($challenge->image, 'https://')) {
                    $imageUrl = $challenge->image;
                } else {
                    $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($challenge->image, '/');
                }
            }

            // Format dates
            $startDate = $challenge->start_date ? $challenge->start_date->format('Y-m-d') : null;
            $endDate = $challenge->end_date ? $challenge->end_date->format('Y-m-d') : null;

            // Format valid until text
            $validUntil = null;
            if ($endDate) {
                $endDateObj = \Carbon\Carbon::parse($endDate);
                $validUntil = 'VALID UNTIL ' . strtoupper($endDateObj->format('d F Y'));
            }

            // Decode rules if it's a JSON string
            $rules = $challenge->rules;
            if (is_string($rules)) {
                $decodedRules = json_decode($rules, true);
                $rules = $decodedRules !== null ? $decodedRules : [];
            } elseif (is_array($rules)) {
                // Already decoded
                $rules = $rules;
            } else {
                $rules = null;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'description' => $challenge->description,
                    'image' => $imageUrl,
                    'points_reward' => $challenge->points_reward,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'valid_until' => $validUntil,
                    'challenge_type_id' => $challenge->challenge_type_id,
                    'validity_period_days' => $challenge->validity_period_days,
                    'rules' => $rules,
                ],
                'message' => 'Challenge retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve challenge: ' . $e->getMessage()
            ], 500);
        }
    }
}

