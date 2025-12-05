<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    /**
     * Submit feedback
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'rating' => 'nullable|integer|min:1|max:5',
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
            
            $feedback = MemberAppsFeedback::create([
                'member_id' => $member->id,
                'outlet_id' => $request->outlet_id,
                'subject' => $request->subject,
                'message' => $request->message,
                'rating' => $request->rating,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully',
                'data' => [
                    'id' => $feedback->id,
                    'subject' => $feedback->subject,
                    'message' => $feedback->message,
                    'rating' => $feedback->rating,
                    'status' => $feedback->status,
                    'created_at' => $feedback->created_at,
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Submit Feedback Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get outlets list for feedback form
     */
    public function getOutlets(Request $request)
    {
        try {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->where('is_outlet', 1)
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet', 'asc')
                ->get();

            Log::info('Get Outlets - Found ' . $outlets->count() . ' outlets');

            return response()->json([
                'success' => true,
                'data' => $outlets->toArray()
            ]);
        } catch (\Exception $e) {
            Log::error('Get Outlets Error: ' . $e->getMessage());
            Log::error('Get Outlets Stack Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get outlets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member's feedback list
     */
    public function index(Request $request)
    {
        try {
            $member = $request->user();
            
            $feedbacks = MemberAppsFeedback::where('member_id', $member->id)
                ->whereNull('parent_id') // Only get main feedbacks, not replies
                ->with(['replies.member', 'replies'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($feedback) {
                    return [
                        'id' => $feedback->id,
                        'subject' => $feedback->subject,
                        'message' => $feedback->message,
                        'rating' => $feedback->rating,
                        'status' => $feedback->status,
                        'outlet_id' => $feedback->outlet_id,
                        'outlet_name' => $feedback->outlet_id ? DB::table('tbl_data_outlet')
                            ->where('id_outlet', $feedback->outlet_id)
                            ->value('nama_outlet') : null,
                        'created_at' => $feedback->created_at,
                        'replies' => $feedback->replies->map(function ($reply) {
                            return [
                                'id' => $reply->id,
                                'message' => $reply->message,
                                'is_admin' => $reply->replied_by !== null,
                                'created_at' => $reply->created_at,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $feedbacks
            ]);
        } catch (\Exception $e) {
            Log::error('Get Feedbacks Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get feedbacks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reply to feedback (can be from member or admin)
     */
    public function reply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1',
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
            $parentFeedback = MemberAppsFeedback::findOrFail($id);
            
            // Check if user is the owner of the parent feedback
            // For mobile app, only the member who created the feedback can reply
            if ($parentFeedback->member_id !== $member->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only reply to your own feedback'
                ], 403);
            }

            // Validate required fields
            if (!$parentFeedback->subject) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid feedback: subject is missing'
                ], 400);
            }
            
            // Ensure outlet_id is set (can be null)
            $outletId = $parentFeedback->outlet_id ?? null;

            // This is a member reply
            $reply = MemberAppsFeedback::create([
                'parent_id' => $id,
                'member_id' => $member->id,
                'outlet_id' => $outletId,
                'subject' => 'Re: ' . $parentFeedback->subject,
                'message' => trim($request->message),
                'status' => 'pending',
            ]);

            // If member replies, reset status to pending
            $parentFeedback->status = 'pending';
            $parentFeedback->save();

            return response()->json([
                'success' => true,
                'message' => 'Reply sent successfully',
                'data' => [
                    'id' => $reply->id,
                    'message' => $reply->message,
                    'is_admin' => false,
                    'created_at' => $reply->created_at,
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Reply Feedback Error: ' . $e->getMessage(), [
                'feedback_id' => $id,
                'member_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

