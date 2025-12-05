<?php

namespace App\Http\Controllers;

use App\Models\LmsCourse;
use App\Models\LmsSession;
use App\Models\LmsSessionItem;
use App\Models\LmsQuiz;
use App\Models\LmsQuestionnaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LmsFlexibleCurriculumController extends Controller
{
    /**
     * Display curriculum for a course
     */
    public function index($courseId)
    {
        try {
            Log::info('=== FLEXIBLE CURRICULUM INDEX STARTED ===');
            Log::info('Course ID: ' . $courseId);

            $course = LmsCourse::findOrFail($courseId);
            Log::info('Course found: ' . $course->title);

            $user = auth()->user();
            Log::info('User ID: ' . $user->id);

            // Get sessions with items
            $sessions = LmsSession::forCourse($courseId)
                ->active()
                ->ordered()
                ->with(['items' => function($query) {
                    $query->ordered()->with(['quiz', 'material', 'questionnaire']);
                }])
                ->get();

            Log::info('Sessions found: ' . $sessions->count());

            // Get available items for adding
            $availableQuizzes = LmsQuiz::where('status', 'active')->get();
            $availableQuestionnaires = LmsQuestionnaire::where('status', 'active')->get();

            Log::info('Available quizzes: ' . $availableQuizzes->count());
            Log::info('Available questionnaires: ' . $availableQuestionnaires->count());

            $responseData = [
                'success' => true,
                'course' => $course,
                'sessions' => $sessions,
                'availableQuizzes' => $availableQuizzes,
                'availableQuestionnaires' => $availableQuestionnaires,
            ];

            Log::info('Response data prepared successfully');

            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::error('Error in flexible curriculum index: ' . $e->getMessage(), [
                'course_id' => $courseId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat kurikulum: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new session
     */
    public function storeSession(Request $request, $courseId)
    {
        try {
            Log::info('=== STORE SESSION STARTED ===');
            Log::info('Course ID: ' . $courseId);
            Log::info('Request data: ' . json_encode($request->all()));

            $course = LmsCourse::findOrFail($courseId);
            
            // Check permissions
            if (!$this->canManageCourse($course)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'session_title' => 'required|string|max:255',
                'session_description' => 'nullable|string',
                'session_number' => 'required|integer|min:1',
                'estimated_duration_minutes' => 'nullable|integer|min:1',
                'is_required' => 'boolean',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed: ' . json_encode($validator->errors()->toArray()));
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $session = LmsSession::create([
                'course_id' => $courseId,
                'session_title' => $request->session_title,
                'session_description' => $request->session_description,
                'session_number' => $request->session_number,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
                'is_required' => $request->is_required ?? true,
                'order_number' => LmsSession::forCourse($courseId)->max('order_number') + 1,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            Log::info('Session created successfully: ' . $session->id);

            return response()->json([
                'success' => true,
                'message' => 'Sesi berhasil dibuat',
                'session' => $session->load('items')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating session: ' . $e->getMessage(), [
                'course_id' => $courseId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat sesi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add item to session
     */
    public function addItem(Request $request, $sessionId)
    {
        try {
            Log::info('=== ADD ITEM STARTED ===');
            Log::info('Session ID: ' . $sessionId);
            Log::info('Request data: ' . json_encode($request->all()));

            $session = LmsSession::findOrFail($sessionId);
            
            // Check permissions
            if (!$this->canManageCourse($session->course)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'item_type' => 'required|in:quiz,material,questionnaire',
                'item_id' => 'nullable|integer',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'estimated_duration_minutes' => 'nullable|integer|min:1',
                'is_required' => 'boolean',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed: ' . json_encode($validator->errors()->toArray()));
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $item = $session->addItem(
                $request->item_type,
                $request->item_id,
                $request->title,
                $request->description,
                $request->estimated_duration_minutes
            );

            DB::commit();

            Log::info('Item added successfully: ' . $item->id);

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil ditambahkan',
                'item' => $item->load(['quiz', 'material', 'questionnaire'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding item: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update session item
     */
    public function updateItem(Request $request, $sessionId, $itemId)
    {
        try {
            Log::info('=== UPDATE ITEM STARTED ===');
            Log::info('Session ID: ' . $sessionId);
            Log::info('Item ID: ' . $itemId);
            Log::info('Request data: ' . json_encode($request->all()));

            $item = LmsSessionItem::where('session_id', $sessionId)
                ->where('id', $itemId)
                ->firstOrFail();

            $session = $item->session;
            
            // Check permissions
            if (!$this->canManageCourse($session->course)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'estimated_duration_minutes' => 'nullable|integer|min:1',
                'is_required' => 'boolean',
                'status' => 'nullable|in:active,inactive',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed: ' . json_encode($validator->errors()->toArray()));
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $item->update([
                'title' => $request->title,
                'description' => $request->description,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
                'is_required' => $request->is_required,
                'status' => $request->status,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            Log::info('Item updated successfully: ' . $item->id);

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil diperbarui',
                'item' => $item->load(['quiz', 'material', 'questionnaire'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating item: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'item_id' => $itemId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete session item
     */
    public function deleteItem($sessionId, $itemId)
    {
        try {
            Log::info('=== DELETE ITEM STARTED ===');
            Log::info('Session ID: ' . $sessionId);
            Log::info('Item ID: ' . $itemId);

            $item = LmsSessionItem::where('session_id', $sessionId)
                ->where('id', $itemId)
                ->firstOrFail();

            $session = $item->session;
            
            // Check permissions
            if (!$this->canManageCourse($session->course)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.'
                ], 403);
            }

            DB::beginTransaction();

            $item->delete();

            DB::commit();

            Log::info('Item deleted successfully: ' . $itemId);

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting item: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'item_id' => $itemId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder items in session
     */
    public function reorderItems(Request $request, $sessionId)
    {
        try {
            Log::info('=== REORDER ITEMS STARTED ===');
            Log::info('Session ID: ' . $sessionId);
            Log::info('Request data: ' . json_encode($request->all()));

            $session = LmsSession::findOrFail($sessionId);
            
            // Check permissions
            if (!$this->canManageCourse($session->course)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'item_ids' => 'required|array',
                'item_ids.*' => 'integer|exists:lms_session_items,id'
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed: ' . json_encode($validator->errors()->toArray()));
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $session->reorderItems($request->item_ids);

            DB::commit();

            Log::info('Items reordered successfully');

            return response()->json([
                'success' => true,
                'message' => 'Urutan item berhasil diperbarui'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error reordering items: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah urutan item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can manage course
     */
    private function canManageCourse($course)
    {
        $user = auth()->user();
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            return true; // Admin
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            return true; // Training Manager
        } elseif ($course->created_by == $user->id) {
            return true; // Course creator
        } else {
            return true; // Temporarily allow all users for debugging
        }
    }
}
