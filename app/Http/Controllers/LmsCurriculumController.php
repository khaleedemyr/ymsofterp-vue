<?php

namespace App\Http\Controllers;

use App\Models\LmsCourse;
use App\Models\LmsCurriculumItem;
use App\Models\LmsCurriculumMaterial;
use App\Models\LmsQuiz;
use App\Models\LmsQuestionnaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class LmsCurriculumController extends Controller
{
    /**
     * Display curriculum for a course
     */
    public function index($courseId)
    {
        try {
            \Log::info('Curriculum index called for course: ' . $courseId);
            
            $course = LmsCourse::findOrFail($courseId);
            \Log::info('Course found: ' . $course->title);

            // Check if user has permission to view this course
            \Log::info('User ID: ' . auth()->id());
            
            // Simplified permission check - remove problematic getAllPermissions() call
            try {
                // Check if user is admin or course creator
                $user = auth()->user();
                $canView = false;
                
                if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                    $canView = true; // Admin
                } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
                    $canView = true; // Training Manager
                } elseif ($course->created_by == $user->id) {
                    $canView = true; // Course creator
                } else {
                    $canView = true; // Temporarily allow all users for debugging
                }
                
                if (!$canView) {
                    \Log::warning('User not authorized to view course');
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized action.',
                        'curriculum' => [],
                        'availableQuizzes' => [],
                        'availableQuestionnaires' => []
                    ], 403);
                }
                
            } catch (\Exception $e) {
                \Log::error('Permission check error: ' . $e->getMessage());
                // For now, allow access and log the error
            }

            $curriculumItems = $course->curriculumItems()
                ->with(['quiz', 'questionnaire', 'materials'])
                ->orderBy('order_number')
                ->get();
            
            \Log::info('Curriculum items found: ' . $curriculumItems->count());

            // Get available quizzes and questionnaires for selection
            $availableQuizzes = LmsQuiz::where(function($query) use ($courseId) {
                $query->where('status', 'published')
                      ->where(function($q) use ($courseId) {
                          $q->whereNull('course_id')
                            ->orWhere('course_id', $courseId);
                      });
            })->get(['id', 'title', 'description']);

            $availableQuestionnaires = LmsQuestionnaire::where(function($query) use ($courseId) {
                $query->where('status', 'published')
                      ->where(function($q) use ($courseId) {
                          $q->whereNull('course_id')
                            ->orWhere('course_id', $courseId);
                      });
            })->get(['id', 'title', 'description']);

            \Log::info('Available quizzes found: ' . $availableQuizzes->count());
            \Log::info('Available questionnaires found: ' . $availableQuestionnaires->count());
            
            if ($availableQuizzes->count() > 0) {
                \Log::info('First quiz: ' . $availableQuizzes->first()->title);
            }
            if ($availableQuestionnaires->count() > 0) {
                \Log::info('First questionnaire: ' . $availableQuestionnaires->first()->title);
            }

            $response = [
                'success' => true,
                'curriculum' => $curriculumItems,
                'availableQuizzes' => $availableQuizzes,
                'availableQuestionnaires' => $availableQuestionnaires,
            ];

            \Log::info('Response data:', $response);
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            \Log::error('Error in curriculum index: ' . $e->getMessage(), [
                'course_id' => $courseId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat kurikulum: ' . $e->getMessage(),
                'curriculum' => [],
                'availableQuizzes' => [],
                'availableQuestionnaires' => []
            ], 500);
        }
    }

    /**
     * Store a new curriculum session
     */
    public function storeSession(Request $request, $courseId)
    {
        try {
            $course = LmsCourse::findOrFail($courseId);
            \Log::info('Creating curriculum session for course: ' . $course->title);

            // Check if user has permission to manage this course
            $user = auth()->user();
            $canManage = false;
            
            if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                $canManage = true; // Admin
            } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
                $canManage = true; // Training Manager
            } elseif ($course->created_by == $user->id) {
                $canManage = true; // Course creator
            } else {
                $canManage = true; // Temporarily allow all users for debugging
            }
            
            if (!$canManage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.',
                    'curriculum' => [],
                    'availableQuizzes' => [],
                    'availableQuestionnaires' => []
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'session_number' => 'required|integer|min:1',
                'session_title' => 'required|string|max:255',
                'session_description' => 'nullable|string',
                'order_number' => 'required|integer|min:1',
                'is_required' => 'boolean',
                'estimated_duration_minutes' => 'nullable|integer|min:1',
                'quiz_id' => 'nullable|exists:lms_quizzes,id',
                'questionnaire_id' => 'nullable|exists:lms_questionnaires,id',
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Check if session number already exists for this course
            $existingSession = LmsCurriculumItem::where('course_id', $courseId)
                ->where('session_number', $request->session_number)
                ->first();

            if ($existingSession) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor sesi sudah ada untuk course ini'
                ], 422);
            }

            $curriculumItem = LmsCurriculumItem::create([
                'course_id' => $courseId,
                'session_number' => $request->session_number,
                'session_title' => $request->session_title,
                'session_description' => $request->session_description,
                'order_number' => $request->order_number,
                'is_required' => $request->is_required ?? true,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
                'quiz_id' => $request->quiz_id,
                'questionnaire_id' => $request->questionnaire_id,
                'status' => 'active',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            \Log::info('Curriculum item created successfully:', $curriculumItem->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sesi kurikulum berhasil dibuat',
                'curriculum_item' => $curriculumItem->load(['quiz', 'questionnaire', 'materials'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating curriculum session: ' . $e->getMessage(), [
                'course_id' => $courseId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat sesi kurikulum: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a curriculum session
     */
    public function updateSession(Request $request, $courseId, $itemId)
    {
        try {
            \Log::info('=== UPDATE SESSION STARTED ===');
            \Log::info('Course ID: ' . $courseId);
            \Log::info('Item ID: ' . $itemId);
            \Log::info('Request data: ' . json_encode($request->all()));
            
            $course = LmsCourse::findOrFail($courseId);
            \Log::info('Course found: ' . $course->title);
            
            $curriculumItem = LmsCurriculumItem::where('course_id', $courseId)
                ->where('id', $itemId)
                ->firstOrFail();
            
            \Log::info('Curriculum item found: ' . json_encode($curriculumItem->toArray()));

            // Check if user has permission to manage this course
            $user = auth()->user();
            $canManage = false;
            
            if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
                $canManage = true; // Admin
            } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
                $canManage = true; // Training Manager
            } elseif ($course->created_by == $user->id) {
                $canManage = true; // Course creator
            } else {
                $canManage = true; // Temporarily allow all users for debugging
            }
            
            if (!$canManage) {
                \Log::warning('User not authorized to manage course');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'session_number' => 'required|integer|min:1',
                'session_title' => 'required|string|max:255',
                'session_description' => 'nullable|string',
                'order_number' => 'required|integer|min:1',
                'is_required' => 'boolean',
                'estimated_duration_minutes' => 'nullable|integer|min:1',
                'quiz_id' => 'nullable|exists:lms_quizzes,id',
                'questionnaire_id' => 'nullable|exists:lms_questionnaires,id',
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed: ' . json_encode($validator->errors()->toArray()));
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            \Log::info('Validation passed successfully');

            try {
                DB::beginTransaction();
                
                \Log::info('Database transaction started');

                // Log the data that will be updated
                $updateData = [
                    'session_number' => $request->session_number,
                    'session_title' => $request->session_title,
                    'session_description' => $request->session_description,
                    'order_number' => $request->order_number,
                    'is_required' => $request->is_required ?? true,
                    'estimated_duration_minutes' => $request->estimated_duration_minutes,
                    'quiz_id' => $request->quiz_id,
                    'questionnaire_id' => $request->questionnaire_id,
                    'updated_by' => auth()->id(),
                ];
                
                \Log::info('Data to update: ' . json_encode($updateData));

                $curriculumItem->update($updateData);
                
                \Log::info('Curriculum item updated successfully');
                \Log::info('Updated item data: ' . json_encode($curriculumItem->fresh()->toArray()));

                DB::commit();
                \Log::info('Database transaction committed successfully');

                return response()->json([
                    'success' => true,
                    'message' => 'Sesi kurikulum berhasil diperbarui',
                    'curriculum_item' => $curriculumItem->fresh()->load(['quiz', 'questionnaire', 'materials'])
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Database transaction failed: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat memperbarui sesi kurikulum: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Error in updateSession: ' . $e->getMessage(), [
                'course_id' => $courseId,
                'item_id' => $itemId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui sesi kurikulum: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a curriculum session
     */
    public function destroySession($courseId, $itemId)
    {
        $course = LmsCourse::findOrFail($courseId);
        $curriculumItem = LmsCurriculumItem::where('course_id', $courseId)
            ->where('id', $itemId)
            ->firstOrFail();

        // Check if user has permission to manage this course
        $user = auth()->user();
        $canManage = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canManage = true; // Admin
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canManage = true; // Training Manager
        } elseif ($course->created_by == $user->id) {
            $canManage = true; // Course creator
        } else {
            $canManage = true; // Temporarily allow all users for debugging
        }
        
        if (!$canManage) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Delete associated materials first
            $curriculumItem->materials()->delete();
            
            // Delete the curriculum item
            $curriculumItem->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sesi kurikulum berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus sesi kurikulum: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store materials for a curriculum session
     */
    public function storeMaterial(Request $request, $courseId, $itemId)
    {
        $course = LmsCourse::findOrFail($courseId);
        $curriculumItem = LmsCurriculumItem::where('course_id', $courseId)
            ->where('id', $itemId)
            ->firstOrFail();

        // Check if user has permission to manage this course
        $user = auth()->user();
        $canManage = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canManage = true; // Admin
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canManage = true; // Training Manager
        } elseif ($course->created_by == $user->id) {
            $canManage = true; // Course creator
        } else {
            $canManage = true; // Temporarily allow all users for debugging
        }
        
        if (!$canManage) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'material_type' => 'required|in:pdf,image,video,document,link',
            'file' => 'nullable|file|max:10240', // 10MB max
            'external_url' => 'nullable|url',
            'order_number' => 'required|integer|min:1',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'is_downloadable' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $materialData = [
                'curriculum_item_id' => $itemId,
                'title' => $request->title,
                'description' => $request->description,
                'material_type' => $request->material_type,
                'order_number' => $request->order_number,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
                'is_downloadable' => $request->is_downloadable ?? false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ];

            // Handle file upload
            if ($request->hasFile('file')) {
                $filePath = $request->file('file')->store('lms/materials', 'public');
                $materialData['file_path'] = $filePath;
            }

            // Handle external URL
            if ($request->external_url) {
                $materialData['external_url'] = $request->external_url;
            }

            $material = LmsCurriculumMaterial::create($materialData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Materi berhasil ditambahkan',
                'material' => $material
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan materi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a material
     */
    public function updateMaterial(Request $request, $courseId, $itemId, $materialId)
    {
        $course = LmsCourse::findOrFail($courseId);
        $curriculumItem = LmsCurriculumItem::where('course_id', $courseId)
            ->where('id', $itemId)
            ->firstOrFail();
        $material = LmsCurriculumMaterial::where('curriculum_item_id', $itemId)
            ->where('id', $materialId)
            ->firstOrFail();

        // Check if user has permission to manage this course
        $user = auth()->user();
        $canManage = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canManage = true; // Admin
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canManage = true; // Training Manager
        } elseif ($course->created_by == $user->id) {
            $canManage = true; // Course creator
        } else {
            $canManage = true; // Temporarily allow all users for debugging
        }
        
        if (!$canManage) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'material_type' => 'required|in:pdf,image,video,document,link',
            'file' => 'nullable|file|max:10240',
            'external_url' => 'nullable|url',
            'order_number' => 'required|integer|min:1',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'is_downloadable' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $materialData = [
                'title' => $request->title,
                'description' => $request->description,
                'material_type' => $request->material_type,
                'order_number' => $request->order_number,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
                'is_downloadable' => $request->is_downloadable ?? false,
                'updated_by' => auth()->id(),
            ];

            // Handle file upload
            if ($request->hasFile('file')) {
                // Delete old file if exists
                if ($material->file_path) {
                    Storage::disk('public')->delete($material->file_path);
                }
                
                $filePath = $request->file('file')->store('lms/materials', 'public');
                $materialData['file_path'] = $filePath;
            }

            // Handle external URL
            if ($request->external_url) {
                $materialData['external_url'] = $request->external_url;
            }

            $material->update($materialData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Materi berhasil diperbarui',
                'material' => $material->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui materi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a material
     */
    public function destroyMaterial($courseId, $itemId, $materialId)
    {
        $course = LmsCourse::findOrFail($courseId);
        $curriculumItem = LmsCurriculumItem::where('course_id', $courseId)
            ->where('id', $itemId)
            ->firstOrFail();
        $material = LmsCurriculumMaterial::where('curriculum_item_id', $itemId)
            ->where('id', $materialId)
            ->firstOrFail();

        // Check if user has permission to manage this course
        $user = auth()->user();
        $canManage = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canManage = true; // Admin
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canManage = true; // Training Manager
        } elseif ($course->created_by == $user->id) {
            $canManage = true; // Course creator
        } else {
            $canManage = true; // Temporarily allow all users for debugging
        }
        
        if (!$canManage) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Delete file if exists
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }

            $material->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Materi berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus materi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder curriculum items
     */
    public function reorderItems(Request $request, $courseId)
    {
        $course = LmsCourse::findOrFail($courseId);

        // Check if user has permission to manage this course
        $user = auth()->user();
        $canManage = false;
        
        if ($user->id_role === '5af56935b011a' && $user->status === 'A') {
            $canManage = true; // Admin
        } elseif ($user->id_jabatan === 170 && $user->status === 'A') {
            $canManage = true; // Training Manager
        } elseif ($course->created_by == $user->id) {
            $canManage = true; // Course creator
        } else {
            $canManage = true; // Temporarily allow all users for debugging
        }
        
        if (!$canManage) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:lms_curriculum_items,id',
            'items.*.order_number' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->items as $item) {
                LmsCurriculumItem::where('id', $item['id'])
                    ->where('course_id', $courseId)
                    ->update(['order_number' => $item['order_number']]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Urutan kurikulum berhasil diperbarui'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui urutan: ' . $e->getMessage()
            ], 500);
        }
    }
}
