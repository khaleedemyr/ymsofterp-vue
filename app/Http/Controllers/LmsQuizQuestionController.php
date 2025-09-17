<?php

namespace App\Http\Controllers;

use App\Models\LmsQuiz;
use App\Models\LmsQuizQuestion;
use App\Models\LmsQuizOption;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsQuizQuestionController extends Controller
{
    public function index(LmsQuiz $quiz)
    {
        $quiz->load(['questions.options', 'attempts.user']);
        
        // Calculate statistics
        $questionsCount = $quiz->questions()->count();
        $attemptsCount = $quiz->attempts()->count();
        
        // Calculate average score
        $averageScore = 0;
        if ($attemptsCount > 0) {
            $averageScore = round($quiz->attempts()->avg('score') ?? 0, 1);
        }
        
        // Calculate pass rate
        $passRate = 0;
        if ($attemptsCount > 0) {
            $passedAttempts = $quiz->attempts()
                ->when($quiz->passing_score !== null, function($query) use ($quiz) {
                    return $query->where('score', '>=', $quiz->passing_score);
                })
                ->count();
            $passRate = round(($passedAttempts / $attemptsCount) * 100, 1);
        }

        // Add calculated statistics to quiz object
        $quiz->questions_count = $questionsCount;
        $quiz->attempts_count = $attemptsCount;
        $quiz->average_score = $averageScore;
        $quiz->pass_rate = $passRate;
        
        // Transform questions to include accessors
        $questions = $quiz->questions->map(function ($question) {
            $question->image_url = $question->image_url;
            $question->has_image = !empty($question->image_path);
            
            // Transform options to include image_url accessors
            if ($question->options) {
                $question->options = $question->options->map(function ($option) {
                    $option->image_url = $option->image_url;
                    $option->has_image = !empty($option->image_path);
                    return $option;
                });
            }
            
            return $question;
        });
        
        return Inertia::render('Lms/Quizzes/ManageQuestions', [
            'quiz' => $quiz,
            'questions' => $questions
        ]);
    }

    public function store(Request $request, LmsQuiz $quiz)
    {
        // Comprehensive debug logging
        \Log::info('=== QUIZ QUESTION STORE REQUEST START ===');
        \Log::info('Quiz ID: ' . $quiz->id);
        \Log::info('Quiz Title: ' . $quiz->title);
        \Log::info('Request Method: ' . $request->method());
        \Log::info('Request URL: ' . $request->url());
        \Log::info('Request Headers: ' . json_encode($request->headers->all()));
        \Log::info('Request Content Type: ' . $request->header('Content-Type'));
        \Log::info('Request Data: ' . json_encode($request->all()));
        \Log::info('Has File: ' . ($request->hasFile('image') ? 'YES' : 'NO'));
        \Log::info('All Files: ' . json_encode($request->allFiles()));
        
        // Check if it's a multipart form
        if ($request->isMethod('post')) {
            \Log::info('Content Length: ' . $request->header('Content-Length'));
            \Log::info('Is Multipart: ' . (strpos($request->header('Content-Type'), 'multipart/form-data') !== false ? 'YES' : 'NO'));
        }

        \Log::info('=== VALIDATION START ===');
        
        // Pre-process options if it's a JSON string
        $requestData = $request->all();
        if (isset($requestData['options']) && is_string($requestData['options'])) {
            try {
                $decodedOptions = json_decode($requestData['options'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $requestData['options'] = $decodedOptions;
                    \Log::info('Options JSON decoded successfully: ' . json_encode($decodedOptions));
                } else {
                    \Log::warning('Failed to decode options JSON: ' . json_last_error_msg());
                }
            } catch (\Exception $e) {
                \Log::warning('Exception while decoding options JSON: ' . $e->getMessage());
            }
        }
        
        try {
            $validated = validator($requestData, [
                'question_text' => 'required|string',
                'question_type' => 'required|in:multiple_choice,essay,true_false',
                'points' => 'required|integer|min:1',
                'options' => 'nullable|array',
                'correct_answer' => 'nullable|in:true,false',
                'image_alt_text' => 'nullable|string'
            ])->validate();
            \Log::info('Validation passed successfully');
            \Log::info('Validated data: ' . json_encode($validated));
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed: ' . json_encode($e->errors()));
            throw $e;
        }

        \Log::info('=== IMAGE HANDLING START ===');
        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            \Log::info('Image file detected in request');
            $file = $request->file('image');
            \Log::info('File details: ' . json_encode([
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $file->getClientOriginalExtension()
            ]));
            
            try {
                $imagePath = $file->store('lms/quiz-questions', 'public');
                \Log::info('Image uploaded successfully to: ' . $imagePath);
            } catch (\Exception $e) {
                \Log::error('Image upload failed: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                throw $e;
            }
        } else {
            \Log::info('No image file in request');
            \Log::info('Request files count: ' . count($request->allFiles()));
        }

        \Log::info('=== DATABASE OPERATION START ===');
        // Create question
        $questionData = [
            'quiz_id' => $quiz->id,
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'points' => $validated['points'],
            'image_path' => $imagePath,
            'image_alt_text' => $validated['image_alt_text'] ?? null,
            'order_number' => $quiz->questions()->count() + 1
        ];
        
        \Log::info('Question data to be created: ' . json_encode($questionData));
        
        try {
            $question = LmsQuizQuestion::create($questionData);
            \Log::info('Question created successfully in database');
            \Log::info('Created question details: ' . json_encode([
                'id' => $question->id,
                'quiz_id' => $question->quiz_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => $question->points,
                'image_path' => $question->image_path,
                'image_alt_text' => $question->image_alt_text,
                'order_number' => $question->order_number,
                'created_at' => $question->created_at,
                'updated_at' => $question->updated_at
            ]));
        } catch (\Exception $e) {
            \Log::error('Failed to create question in database: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }

        \Log::info('=== OPTIONS HANDLING START ===');
        // Handle options based on question type
        if ($validated['question_type'] === 'multiple_choice' && $validated['options']) {
            \Log::info('Creating multiple choice options: ' . json_encode($validated['options']));
            \Log::info('All request files: ' . json_encode($request->allFiles()));
            
            foreach ($validated['options'] as $index => $optionData) {
                try {
                    \Log::info("Processing option {$index}: " . json_encode($optionData));
                    
                    // Handle option image upload
                    $optionImagePath = null;
                    if ($request->hasFile("option_image_{$index}")) {
                        \Log::info("Option {$index} has image file");
                        $optionImagePath = $request->file("option_image_{$index}")->store('lms/quiz-options', 'public');
                        \Log::info("Option {$index} image uploaded to: {$optionImagePath}");
                    } else {
                        \Log::info("Option {$index} has NO image file");
                        if (isset($optionData['image_path']) && $optionData['image_path']) {
                            // Keep existing image path
                            $optionImagePath = $optionData['image_path'];
                            \Log::info("Option {$index} keeping existing image: {$optionImagePath}");
                        }
                    }
                    
                    $option = LmsQuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => $optionData['option_text'],
                        'is_correct' => $optionData['is_correct'] ?? false,
                        'order_number' => $index + 1,
                        'image_path' => $optionImagePath,
                        'image_alt_text' => $optionData['image_alt_text'] ?? null
                    ]);
                    \Log::info('Option ' . ($index + 1) . ' created: ' . json_encode($option->toArray()));
                } catch (\Exception $e) {
                    \Log::error('Failed to create option ' . ($index + 1) . ': ' . $e->getMessage());
                    throw $e;
                }
            }
        } elseif ($validated['question_type'] === 'true_false' && $validated['correct_answer'] !== null) {
            \Log::info('Creating true/false options with correct answer: ' . $validated['correct_answer']);
            // Create Benar option
            try {
                $benarOption = LmsQuizOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Benar',
                    'is_correct' => $validated['correct_answer'] === 'true',
                    'order_number' => 1
                ]);
                \Log::info('Benar option created: ' . json_encode($benarOption->toArray()));
            } catch (\Exception $e) {
                \Log::error('Failed to create Benar option: ' . $e->getMessage());
                throw $e;
            }

            // Create Salah option
            try {
                $salahOption = LmsQuizOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Salah',
                    'is_correct' => $validated['correct_answer'] === 'false',
                    'order_number' => 2
                ]);
                \Log::info('Salah option created: ' . json_encode($salahOption->toArray()));
            } catch (\Exception $e) {
                \Log::error('Failed to create Salah option: ' . $e->getMessage());
                throw $e;
            }
        }

        \Log::info('=== RESPONSE PREPARATION ===');
        \Log::info('All operations completed successfully, preparing response');
        
        $response = redirect()->back()->with('success', 'Pertanyaan berhasil ditambahkan');
        \Log::info('Response prepared: ' . get_class($response));
        \Log::info('=== QUIZ QUESTION STORE REQUEST END ===');
        
        return $response;
    }

    public function update(Request $request, LmsQuiz $quiz, LmsQuizQuestion $question)
    {
        \Log::info('=== QUIZ QUESTION UPDATE REQUEST START ===');
        \Log::info('Quiz ID: ' . $quiz->id);
        \Log::info('Question ID: ' . $question->id);
        \Log::info('Request Data: ' . json_encode($request->all()));
        \Log::info('Has File: ' . ($request->hasFile('image') ? 'YES' : 'NO'));
        \Log::info('Current Image Path: ' . $question->image_path);
        
        // Pre-process options if it's a JSON string
        $requestData = $request->all();
        if (isset($requestData['options']) && is_string($requestData['options'])) {
            try {
                $decodedOptions = json_decode($requestData['options'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $requestData['options'] = $decodedOptions;
                    \Log::info('Options JSON decoded successfully: ' . json_encode($decodedOptions));
                } else {
                    \Log::warning('Failed to decode options JSON: ' . json_last_error_msg());
                }
            } catch (\Exception $e) {
                \Log::warning('Exception while decoding options JSON: ' . $e->getMessage());
            }
        }
        
        $validated = validator($requestData, [
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,essay,true_false',
            'points' => 'required|integer|min:1',
            'options' => 'nullable|array',
            'correct_answer' => 'nullable|in:true,false',
            'image_alt_text' => 'nullable|string'
        ])->validate();

        \Log::info('Validation passed: ' . json_encode($validated));

        // Handle image upload
        $imagePath = $question->image_path; // Keep existing image by default
        if ($request->hasFile('image')) {
            \Log::info('New image file detected, handling upload...');
            // Delete old image if exists
            if ($question->image_path) {
                \Log::info('Deleting old image: ' . $question->image_path);
                \Storage::disk('public')->delete($question->image_path);
            }
            try {
                $imagePath = $request->file('image')->store('lms/quiz-questions', 'public');
                \Log::info('New image uploaded to: ' . $imagePath);
            } catch (\Exception $e) {
                \Log::error('Image upload failed: ' . $e->getMessage());
                throw $e;
            }
        } else {
            \Log::info('No new image, keeping existing: ' . $imagePath);
        }

        \Log::info('=== UPDATE OPERATION START ===');
        // Update question
        try {
            $updateData = [
                'question_text' => $validated['question_text'],
                'question_type' => $validated['question_type'],
                'points' => $validated['points'],
                'image_path' => $imagePath,
                'image_alt_text' => $validated['image_alt_text'] ?? null
            ];
            \Log::info('Updating question with data: ' . json_encode($updateData));
            
            $question->update($updateData);
            \Log::info('Question updated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to update question: ' . $e->getMessage());
            throw $e;
        }

        \Log::info('=== OPTIONS UPDATE START ===');
        // Delete existing options
        \Log::info('Deleting existing options...');
        $deletedOptions = $question->options()->delete();
        \Log::info('Deleted ' . $deletedOptions . ' existing options');

        // Handle options based on question type
        if ($validated['question_type'] === 'multiple_choice' && $validated['options']) {
            \Log::info('Creating new multiple choice options: ' . json_encode($validated['options']));
            foreach ($validated['options'] as $index => $optionData) {
                try {
                    // Handle option image upload
                    $optionImagePath = null;
                    if ($request->hasFile("option_image_{$index}")) {
                        $optionImagePath = $request->file("option_image_{$index}")->store('lms/quiz-options', 'public');
                        \Log::info("Option {$index} image uploaded to: {$optionImagePath}");
                    } elseif (isset($optionData['image_path']) && $optionData['image_path']) {
                        // Keep existing image path
                        $optionImagePath = $optionData['image_path'];
                        \Log::info("Option {$index} keeping existing image: {$optionImagePath}");
                    }
                    
                    $option = LmsQuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => $optionData['option_text'],
                        'is_correct' => $optionData['is_correct'] ?? false,
                        'order_number' => $index + 1,
                        'image_path' => $optionImagePath,
                        'image_alt_text' => $optionData['image_alt_text'] ?? null
                    ]);
                    \Log::info('Option ' . ($index + 1) . ' created: ' . json_encode($option->toArray()));
                } catch (\Exception $e) {
                    \Log::error('Failed to create option ' . ($index + 1) . ': ' . $e->getMessage());
                    throw $e;
                }
            }
        } elseif ($validated['question_type'] === 'true_false' && $validated['correct_answer'] !== null) {
            \Log::info('Creating new true/false options with correct answer: ' . $validated['correct_answer']);
            // Create Benar option
            try {
                $benarOption = LmsQuizOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Benar',
                    'is_correct' => $validated['correct_answer'] === 'true',
                    'order_number' => 1
                ]);
                \Log::info('Benar option created: ' . json_encode($benarOption->toArray()));
            } catch (\Exception $e) {
                \Log::error('Failed to create Benar option: ' . $e->getMessage());
                throw $e;
            }

            // Create Salah option
            try {
                $salahOption = LmsQuizOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Salah',
                    'is_correct' => $validated['correct_answer'] === 'false',
                    'order_number' => 2
                ]);
                \Log::info('Salah option created: ' . json_encode($salahOption->toArray()));
            } catch (\Exception $e) {
                \Log::error('Failed to create Salah option: ' . $e->getMessage());
                throw $e;
            }
        }

        \Log::info('=== UPDATE COMPLETED ===');
        \Log::info('Question update completed successfully');
        \Log::info('=== QUIZ QUESTION UPDATE REQUEST END ===');
        
        return redirect()->back()->with('success', 'Pertanyaan berhasil diperbarui');
    }

    public function destroy(LmsQuiz $quiz, LmsQuizQuestion $question)
    {
        \Log::info('=== QUIZ QUESTION DESTROY REQUEST START ===');
        \Log::info('Quiz ID: ' . $quiz->id);
        \Log::info('Question ID: ' . $question->id);
        \Log::info('Question Text: ' . $question->question_text);
        
        try {
            // Delete options first (cascade)
            \Log::info('Deleting options for question...');
            $deletedOptions = $question->options()->delete();
            \Log::info('Deleted ' . $deletedOptions . ' options');
            
            // Delete question
            \Log::info('Deleting question...');
            $question->delete();
            \Log::info('Question deleted successfully');
            
            \Log::info('=== QUIZ QUESTION DESTROY REQUEST END ===');
            return redirect()->back()->with('success', 'Pertanyaan berhasil dihapus');
            
        } catch (\Exception $e) {
            \Log::error('Failed to delete question: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
