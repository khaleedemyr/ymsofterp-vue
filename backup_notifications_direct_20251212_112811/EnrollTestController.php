<?php

namespace App\Http\Controllers;

use App\Models\EnrollTest;
use App\Models\MasterSoal;
use App\Models\User;
use App\Models\TestResult;
use App\Models\TestAnswer;
use App\Models\SoalPertanyaan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;

class EnrollTestController extends Controller
{
    public function index(Request $request)
    {
        $query = EnrollTest::with(['masterSoal', 'user', 'creator'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('masterSoal', function ($sq) use ($request) {
                    $sq->where('judul', 'like', "%{$request->search}%");
                })->orWhereHas('user', function ($sq) use ($request) {
                    $sq->where('nama_lengkap', 'like', "%{$request->search}%");
                });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('master_soal_id')) {
            $query->where('master_soal_id', $request->master_soal_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Per page
        $perPage = $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50]) ? $perPage : 10;

        $enrollTests = $query->paginate($perPage)->withQueryString();

        // Get filter options
        $statusOptions = [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'enrolled', 'label' => 'Terdaftar'],
            ['value' => 'in_progress', 'label' => 'Sedang Test'],
            ['value' => 'completed', 'label' => 'Selesai'],
            ['value' => 'expired', 'label' => 'Kedaluwarsa'],
            ['value' => 'cancelled', 'label' => 'Dibatalkan']
        ];

        $masterSoals = MasterSoal::where('status', 'active')
            ->orderBy('judul')
            ->get(['id', 'judul']);

        $users = User::where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);

        return Inertia::render('EnrollTest/Index', [
            'enrollTests' => $enrollTests,
            'statusOptions' => $statusOptions,
            'masterSoals' => $masterSoals,
            'users' => $users,
            'filters' => $request->only(['search', 'status', 'master_soal_id', 'user_id', 'per_page'])
        ]);
    }

    public function create()
    {
        $masterSoals = MasterSoal::where('status', 'active')
            ->with('pertanyaans')
            ->orderBy('judul')
            ->get(['id', 'judul', 'deskripsi']);

        $users = User::where('status', 'A')
            ->with(['jabatan', 'outlet', 'divisi'])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'email', 'id_outlet', 'division_id', 'id_jabatan']);

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        return Inertia::render('EnrollTest/Create', [
            'masterSoals' => $masterSoals,
            'users' => $users,
            'outlets' => $outlets
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_soal_id' => 'required|exists:master_soal,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'expired_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            $enrollTests = [];
            $errors = [];
            $successCount = 0;

            foreach ($validated['user_ids'] as $userId) {
                // Check if user already enrolled for this soal
                $existingEnrollment = EnrollTest::where('master_soal_id', $validated['master_soal_id'])
                    ->where('user_id', $userId)
                    ->whereIn('status', ['enrolled', 'in_progress'])
                    ->first();

                if ($existingEnrollment) {
                    $user = User::find($userId);
                    $errors[] = "User {$user->nama_lengkap} sudah terdaftar untuk soal ini";
                    continue;
                }

                $enrollTest = EnrollTest::create([
                    'master_soal_id' => $validated['master_soal_id'],
                    'user_id' => $userId,
                    'outlet_id' => $validated['outlet_id'],
                    'max_attempts' => $validated['max_attempts'] ?? 1,
                    'expired_at' => $validated['expired_at'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => Auth::id()
                ]);

                $enrollTests[] = $enrollTest;
                $successCount++;
            }

            DB::commit();

            // Kirim notifikasi ke user yang di-enroll
            $this->sendEnrollmentNotifications($enrollTests, $validated);

            $message = "Enrollment berhasil dibuat untuk {$successCount} user";
            if (!empty($errors)) {
                $message .= ". " . count($errors) . " user gagal karena sudah terdaftar";
            }

            return redirect()->route('enroll-test.index')
                ->with('success', $message)
                ->with('warnings', $errors);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat membuat enrollment: ' . $e->getMessage()]);
        }
    }

    public function show(EnrollTest $enrollTest)
    {
        $enrollTest->load(['masterSoal.pertanyaans', 'user', 'testResults.testAnswers.soalPertanyaan']);

        return Inertia::render('EnrollTest/Show', [
            'enrollTest' => $enrollTest
        ]);
    }

    public function edit(EnrollTest $enrollTest)
    {
        $masterSoals = MasterSoal::where('status', 'active')
            ->orderBy('judul')
            ->get(['id', 'judul']);

        $users = User::where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);

        return Inertia::render('EnrollTest/Edit', [
            'enrollTest' => $enrollTest,
            'masterSoals' => $masterSoals,
            'users' => $users
        ]);
    }

    public function update(Request $request, EnrollTest $enrollTest)
    {
        $validated = $request->validate([
            'master_soal_id' => 'required|exists:master_soal,id',
            'user_id' => 'required|exists:users,id',
            'time_limit_minutes' => 'nullable|integer|min:1|max:480',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'expired_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000'
        ]);

        $enrollTest->update($validated);

        return redirect()->route('enroll-test.index')
            ->with('success', 'Enrollment berhasil diperbarui');
    }

    public function destroy(EnrollTest $enrollTest)
    {
        $enrollTest->delete();

        return redirect()->route('enroll-test.index')
            ->with('success', 'Enrollment berhasil dihapus');
    }

    public function cancel(EnrollTest $enrollTest)
    {
        $enrollTest->cancelTest();

        return back()->with('success', 'Enrollment berhasil dibatalkan');
    }

    public function expire(EnrollTest $enrollTest)
    {
        $enrollTest->expireTest();

        return back()->with('success', 'Enrollment berhasil di-expire');
    }

    // User methods
    public function myTests(Request $request)
    {
        $user = Auth::user();
        
        $query = EnrollTest::with(['masterSoal', 'latestTestResult'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $query->whereHas('masterSoal', function ($q) use ($request) {
                $q->where('judul', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Per page
        $perPage = $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50]) ? $perPage : 10;

        $enrollTests = $query->paginate($perPage)->withQueryString();

        $statusOptions = [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'enrolled', 'label' => 'Terdaftar'],
            ['value' => 'in_progress', 'label' => 'Sedang Test'],
            ['value' => 'completed', 'label' => 'Selesai'],
            ['value' => 'expired', 'label' => 'Kedaluwarsa'],
            ['value' => 'cancelled', 'label' => 'Dibatalkan']
        ];

        return Inertia::render('EnrollTest/MyTests', [
            'enrollTests' => $enrollTests,
            'statusOptions' => $statusOptions,
            'filters' => $request->only(['search', 'status', 'per_page'])
        ]);
    }

    public function startTest(EnrollTest $enrollTest)
    {
        if (!$enrollTest->can_start_test) {
            return back()->withErrors(['error' => 'Test tidak dapat dimulai']);
        }

        DB::beginTransaction();
        try {
            // Start test
            $enrollTest->startTest();

            // Ambil semua soal dari master soal
            $questions = $enrollTest->masterSoal->pertanyaans;
            
            // Acak urutan soal untuk user ini
            $questionIds = $questions->pluck('id')->toArray();
            shuffle($questionIds);

            // Buat test result dengan urutan soal yang diacak
            $testResult = TestResult::create([
                'enroll_test_id' => $enrollTest->id,
                'attempt_number' => $enrollTest->current_attempt,
                'question_order' => $questionIds,
                'current_question_index' => 0,
                'status' => 'in_progress'
            ]);

            DB::commit();

            return redirect()->route('enroll-test.take', $enrollTest);
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Gagal memulai test: ' . $e->getMessage()]);
        }
    }

    public function takeTest(EnrollTest $enrollTest)
    {
        if ($enrollTest->status !== 'in_progress') {
            return redirect()->route('enroll-test.my-tests')
                ->withErrors(['error' => 'Test tidak sedang berlangsung']);
        }

        // Ambil test result yang sedang berlangsung
        $testResult = TestResult::where('enroll_test_id', $enrollTest->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$testResult) {
            return redirect()->route('enroll-test.my-tests')
                ->withErrors(['error' => 'Test result tidak ditemukan']);
        }

        // Ambil soal berdasarkan urutan yang diacak
        $currentQuestionId = $testResult->question_order[$testResult->current_question_index];
        $currentQuestion = SoalPertanyaan::where('id', $currentQuestionId)->first();

        // Hitung total soal
        $totalQuestions = count($testResult->question_order);

        $enrollTest->load(['masterSoal']);

        return Inertia::render('EnrollTest/TakeTest', [
            'enrollTest' => $enrollTest,
            'testResult' => $testResult,
            'currentQuestion' => $currentQuestion,
            'currentIndex' => $testResult->current_question_index,
            'totalQuestions' => $totalQuestions
        ]);
    }

    public function nextQuestion(Request $request, EnrollTest $enrollTest)
    {
        $validated = $request->validate([
            'answer' => 'nullable|string',
            'time_taken' => 'nullable|integer|min:0'
        ]);

        // Ambil test result yang sedang berlangsung
        $testResult = TestResult::where('enroll_test_id', $enrollTest->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$testResult) {
            return back()->withErrors(['error' => 'Test result tidak ditemukan']);
        }

        // Simpan jawaban saat ini
        $currentQuestionId = $testResult->question_order[$testResult->current_question_index];
        $answers = $testResult->answers ?? [];
        $answers[$currentQuestionId] = [
            'answer' => $validated['answer'] ?? '',
            'time_taken' => $validated['time_taken'] ?? 0,
            'answered_at' => now()
        ];

        // Update jawaban di test_results
        $testResult->update(['answers' => $answers]);

        // Simpan jawaban ke tabel test_answers
        $this->saveAnswerToTable($testResult->id, $currentQuestionId, $validated['answer'] ?? '', $validated['time_taken'] ?? 0);

        // Cek apakah masih ada soal berikutnya
        $nextIndex = $testResult->current_question_index + 1;
        if ($nextIndex >= count($testResult->question_order)) {
            // Test selesai - hitung skor dan update status
            $this->calculateAndSaveTestScore($testResult);
            
            $testResult->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
            
            $enrollTest->update(['status' => 'completed']);
            
            // Clear localStorage untuk test ini (akan dihandle di frontend)
            return redirect()->route('enroll-test.result', $testResult->id)
                ->with('success', 'Test selesai!')
                ->with('clearLocalStorage', true);
        }

        // Pindah ke soal berikutnya
        $testResult->update(['current_question_index' => $nextIndex]);

        // Redirect ke halaman test dengan data baru
        return redirect()->route('enroll-test.take', $enrollTest);
    }

    public function submitTest(Request $request, EnrollTest $enrollTest)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.soal_pertanyaan_id' => 'required|exists:soal_pertanyaan,id',
            'answers.*.user_answer' => 'nullable|string',
            'time_taken_seconds' => 'required|integer|min:0'
        ]);

        DB::beginTransaction();
        try {
            // Create test result
            $testResult = $enrollTest->testResults()->create([
                'attempt_number' => $enrollTest->current_attempt,
                'started_at' => $enrollTest->started_at,
                'completed_at' => now(),
                'time_taken_seconds' => $validated['time_taken_seconds'],
                'status' => 'completed',
                'answers' => $validated['answers']
            ]);

            $totalScore = 0;
            $maxScore = 0;

            // Process each answer
            foreach ($validated['answers'] as $answer) {
                $pertanyaan = $enrollTest->masterSoal->pertanyaans
                    ->find($answer['soal_pertanyaan_id']);

                if (!$pertanyaan) continue;

                $maxScore += $pertanyaan->skor;

                // Check if answer is correct
                $isCorrect = false;
                $score = 0;

                if ($pertanyaan->tipe_soal === 'pilihan_ganda' || $pertanyaan->tipe_soal === 'yes_no') {
                    $isCorrect = strtolower($answer['user_answer']) === strtolower($pertanyaan->jawaban_benar);
                    $score = $isCorrect ? $pertanyaan->skor : 0;
                } elseif ($pertanyaan->tipe_soal === 'essay') {
                    // Essay answers need manual checking
                    $score = 0; // Will be updated manually later
                }

                $totalScore += $score;

                // Create test answer
                TestAnswer::create([
                    'test_result_id' => $testResult->id,
                    'soal_pertanyaan_id' => $pertanyaan->id,
                    'user_answer' => $answer['user_answer'],
                    'is_correct' => $isCorrect,
                    'score' => $score,
                    'max_score' => $pertanyaan->skor,
                    'time_taken_seconds' => 0, // Can be calculated if needed
                    'answered_at' => now()
                ]);
            }

            // Update test result with scores
            $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
            
            $testResult->update([
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'percentage' => $percentage
            ]);

            // Complete the enrollment
            $enrollTest->completeTest($totalScore, $maxScore, $validated['time_taken_seconds']);

            DB::commit();

            return redirect()->route('enroll-test.result', $testResult)
                ->with('success', 'Test berhasil disubmit');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat menyimpan jawaban']);
        }
    }

    public function result(TestResult $testResult)
    {
        $testResult->load([
            'enrollTest.masterSoal',
            'enrollTest.user'
        ]);

        // Hitung durasi test
        if ($testResult->started_at && $testResult->completed_at) {
            $timeTaken = $testResult->started_at->diffInSeconds($testResult->completed_at);
            $testResult->update(['time_taken_seconds' => $timeTaken]);
        }

        return Inertia::render('EnrollTest/Result', [
            'testResult' => $testResult
        ]);
    }

    public function report(Request $request)
    {
        $query = TestResult::with([
            'enrollTest.masterSoal',
            'enrollTest.user.jabatan',
            'enrollTest.user.divisi', 
            'enrollTest.user.outlet',
            'testAnswers.soalPertanyaan'
        ])
        ->where('status', 'completed')
        ->orderBy('completed_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $query->whereHas('enrollTest.user', function($q) use ($request) {
                $q->where('nama_lengkap', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhereHas('jabatan', function($subQ) use ($request) {
                      $subQ->where('nama_jabatan', 'like', "%{$request->search}%");
                  })
                  ->orWhereHas('outlet', function($subQ) use ($request) {
                      $subQ->where('nama_outlet', 'like', "%{$request->search}%");
                  })
                  ->orWhereHas('divisi', function($subQ) use ($request) {
                      $subQ->where('nama_divisi', 'like', "%{$request->search}%");
                  });
            });
        }

        if ($request->filled('master_soal_id')) {
            $query->whereHas('enrollTest.masterSoal', function($q) use ($request) {
                $q->where('id', $request->master_soal_id);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('completed_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('completed_at', '<=', $request->date_to);
        }

        // Per page
        $perPage = $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50]) ? $perPage : 10;

        $testResults = $query->paginate($perPage)->withQueryString();

        // Recalculate scores untuk semua test result yang ditampilkan
        foreach ($testResults->items() as $testResult) {
            $this->recalculateTestResultScore($testResult->id);
        }

        // Get filter options
        $masterSoals = MasterSoal::where('status', 'active')
            ->orderBy('judul')
            ->get(['id', 'judul']);

        return Inertia::render('EnrollTest/Report', [
            'testResults' => $testResults,
            'masterSoals' => $masterSoals,
            'filters' => $request->only(['search', 'master_soal_id', 'date_from', 'date_to', 'per_page'])
        ]);
    }

    public function recalculateAllScores()
    {
        try {
            $testResults = TestResult::where('status', 'completed')->get();
            $count = 0;
            
            foreach ($testResults as $testResult) {
                $this->recalculateTestResultScore($testResult->id);
                $count++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil recalculate {$count} test results dengan sistem bobot baru"
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal recalculate: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEssayScore(Request $request, TestAnswer $testAnswer)
    {
        $validated = $request->validate([
            'score' => 'required|numeric|min:1|max:4'
        ]);

        try {
            $testAnswer->update([
                'score' => $validated['score']
                // Essay tidak ada is_correct karena manual scoring
            ]);

            // Recalculate test result total score
            $this->recalculateTestResultScore($testAnswer->test_result_id);

            return response()->json([
                'success' => true,
                'message' => 'Score berhasil diupdate'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal update score: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdateEssayScores(Request $request)
    {
        $validated = $request->validate([
            'scores' => 'required|array',
            'scores.*.answer_id' => 'required|exists:test_answers,id',
            'scores.*.score' => 'required|numeric|min:1|max:4'
        ]);

        DB::beginTransaction();
        try {
            $updatedCount = 0;
            $testResultIds = [];

            foreach ($validated['scores'] as $scoreData) {
                $testAnswer = TestAnswer::find($scoreData['answer_id']);
                
                if ($testAnswer && $testAnswer->soalPertanyaan->tipe_soal === 'essay') {
                    $testAnswer->update([
                        'score' => $scoreData['score']
                    ]);
                    
                    $updatedCount++;
                    
                    // Collect test result IDs for recalculation
                    if (!in_array($testAnswer->test_result_id, $testResultIds)) {
                        $testResultIds[] = $testAnswer->test_result_id;
                    }
                }
            }

            // Recalculate scores for all affected test results
            foreach ($testResultIds as $testResultId) {
                $this->recalculateTestResultScore($testResultId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil update {$updatedCount} nilai essay",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal update scores: ' . $e->getMessage()
            ], 500);
        }
    }

    private function recalculateTestResultScore($testResultId)
    {
        $testResult = TestResult::find($testResultId);
        if (!$testResult) return;

        // Recalculate score dengan sistem bobot
        $testAnswers = TestAnswer::where('test_result_id', $testResultId)->get();
        
        // Hitung skor per kategori
        $essayScore = 0;
        $essayMaxScore = 0;
        $pgYesNoScore = 0;
        $pgYesNoMaxScore = 0;
        
        foreach ($testAnswers as $testAnswer) {
            // Ambil data soal untuk cek tipe
            $pertanyaan = DB::table('soal_pertanyaan')
                ->where('id', $testAnswer->soal_pertanyaan_id)
                ->first();
                
            if (!$pertanyaan) continue;
            
            if ($pertanyaan->tipe_soal === 'pilihan_ganda' || $pertanyaan->tipe_soal === 'yes_no') {
                // PG dan Yes/No: tidak diisi=0, salah=1, benar=4
                if (empty($testAnswer->user_answer)) {
                    $score = 0;
                } else {
                    $isCorrect = strtolower($testAnswer->user_answer) === strtolower($pertanyaan->jawaban_benar);
                    $score = $isCorrect ? 4 : 1;
                }
                $pgYesNoScore += $score;
                $pgYesNoMaxScore += 4; // Max score per soal adalah 4
            } elseif ($pertanyaan->tipe_soal === 'essay') {
                // Essay: gunakan score yang sudah diinput admin (1-4) - manual scoring
                $score = $testAnswer->score ?? 0;
                $essayScore += $score;
                $essayMaxScore += 4; // Max score per soal adalah 4
            }
        }
        
        // Hitung persentase dengan bobot
        $essayPercentage = $essayMaxScore > 0 ? ($essayScore / $essayMaxScore) * 100 : 0;
        $pgYesNoPercentage = $pgYesNoMaxScore > 0 ? ($pgYesNoScore / $pgYesNoMaxScore) * 100 : 0;
        
        // Bobot: Essay 70%, PG+Yes/No 30%
        $weightedPercentage = ($essayPercentage * 0.7) + ($pgYesNoPercentage * 0.3);
        
        // Konversi persentase ke GPA (1-4) seperti IPK
        $gpaScore = $this->convertPercentageToGPA($weightedPercentage);
        $gradeDescription = $this->getGradeDescription($gpaScore);
        
        // Total score untuk display (tanpa bobot)
        $totalScore = $essayScore + $pgYesNoScore;
        $maxScore = $essayMaxScore + $pgYesNoMaxScore;

        $testResult->update([
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $weightedPercentage,
            'gpa_score' => $gpaScore,
            'grade_description' => $gradeDescription
        ]);
    }

    /**
     * Konversi persentase ke GPA (1-4) seperti sistem IPK
     */
    private function convertPercentageToGPA($percentage)
    {
        if ($percentage >= 90) return 4.0;      // A (90-100%)
        if ($percentage >= 80) return 3.5;     // A- (80-89%)
        if ($percentage >= 75) return 3.0;     // B+ (75-79%)
        if ($percentage >= 70) return 2.5;     // B (70-74%)
        if ($percentage >= 65) return 2.0;    // B- (65-69%)
        if ($percentage >= 60) return 1.5;     // C+ (60-64%)
        if ($percentage >= 55) return 1.0;     // C (55-59%)
        if ($percentage >= 50) return 0.5;     // D (50-54%)
        return 0.0;                            // E (<50%)
    }

    /**
     * Mendapatkan deskripsi grade berdasarkan GPA
     */
    private function getGradeDescription($gpaScore)
    {
        if ($gpaScore >= 4.0) return 'A (Sempurna)';
        if ($gpaScore >= 3.5) return 'A- (Sangat Baik)';
        if ($gpaScore >= 3.0) return 'B+ (Baik Sekali)';
        if ($gpaScore >= 2.5) return 'B (Baik)';
        if ($gpaScore >= 2.0) return 'B- (Cukup Baik)';
        if ($gpaScore >= 1.5) return 'C+ (Cukup)';
        if ($gpaScore >= 1.0) return 'C (Kurang)';
        if ($gpaScore >= 0.5) return 'D (Sangat Kurang)';
        return 'E (Tidak Lulus)';
    }

    private function sendEnrollmentNotifications($enrollTests, $validated)
    {
        try {
            // Ambil data master soal
            $masterSoal = DB::table('master_soal')
                ->where('id', $validated['master_soal_id'])
                ->first();

            // Ambil data outlet
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $validated['outlet_id'])
                ->first();

            // Ambil data creator
            $creator = Auth::user();

            foreach ($enrollTests as $enrollTest) {
                // Ambil data user yang di-enroll
                $user = DB::table('users')
                    ->where('id', $enrollTest->user_id)
                    ->first();

                // Buat pesan notifikasi yang detail
                $message = "Anda telah di-enroll untuk test:\n\n";
                $message .= "📝 Judul Test: {$masterSoal->judul}\n";
                $message .= "🏢 Lokasi: {$outlet->nama_outlet}\n";
                $message .= "👤 Di-enroll oleh: {$creator->nama_lengkap}\n";
                $message .= "📅 Tanggal Enroll: " . now()->format('d/m/Y H:i') . "\n";
                
                if ($enrollTest->expired_at) {
                    $message .= "⏰ Batas Waktu: " . \Carbon\Carbon::parse($enrollTest->expired_at)->format('d/m/Y') . "\n";
                }
                
                $message .= "🔄 Maksimal Percobaan: {$enrollTest->max_attempts}\n";
                
                if ($enrollTest->notes) {
                    $message .= "📋 Catatan: {$enrollTest->notes}\n";
                }
                
                $message .= "\nSilakan login ke sistem untuk mengikuti test.";

                // Insert notifikasi ke database
                DB::table('notifications')->insert([
                    'user_id' => $enrollTest->user_id,
                    'type' => 'enroll_test',
                    'message' => $message,
                    'url' => config('app.url') . '/my-tests',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            \Log::info('Enrollment notifications sent successfully', [
                'enroll_count' => count($enrollTests),
                'master_soal_id' => $validated['master_soal_id'],
                'outlet_id' => $validated['outlet_id']
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending enrollment notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function saveAnswerToTable($testResultId, $questionId, $answer, $timeTaken)
    {
        try {
            // Cek apakah jawaban sudah ada
            $existingAnswer = TestAnswer::where('test_result_id', $testResultId)
                ->where('soal_pertanyaan_id', $questionId)
                ->first();

            if ($existingAnswer) {
                // Update jawaban yang sudah ada
                $existingAnswer->update([
                    'user_answer' => $answer,
                    'time_taken_seconds' => $timeTaken,
                    'answered_at' => now()
                ]);
            } else {
                // Buat jawaban baru
                TestAnswer::create([
                    'test_result_id' => $testResultId,
                    'soal_pertanyaan_id' => $questionId,
                    'user_answer' => $answer,
                    'time_taken_seconds' => $timeTaken,
                    'answered_at' => now()
                ]);
            }

            \Log::info('Answer saved to test_answers table', [
                'test_result_id' => $testResultId,
                'question_id' => $questionId,
                'answer_length' => strlen($answer),
                'time_taken' => $timeTaken
            ]);

        } catch (\Exception $e) {
            \Log::error('Error saving answer to test_answers table', [
                'error' => $e->getMessage(),
                'test_result_id' => $testResultId,
                'question_id' => $questionId
            ]);
        }
    }

    private function calculateAndSaveTestScore($testResult)
    {
        try {
            $totalScore = 0;
            $maxScore = 0;

            // Ambil semua jawaban dari test_answers
            $testAnswers = TestAnswer::where('test_result_id', $testResult->id)->get();

            foreach ($testAnswers as $testAnswer) {
                // Ambil data soal
                $pertanyaan = DB::table('soal_pertanyaan')
                    ->where('id', $testAnswer->soal_pertanyaan_id)
                    ->first();

                if (!$pertanyaan) continue;

                $maxScore += 4; // Max score per soal adalah 4

                // Hitung skor berdasarkan tipe soal dan jawaban
                $score = 0;
                $isCorrect = false;

                if ($pertanyaan->tipe_soal === 'pilihan_ganda' || $pertanyaan->tipe_soal === 'yes_no') {
                    // PG dan Yes/No: tidak diisi=0, salah=1, benar=4
                    if (empty($testAnswer->user_answer)) {
                        $score = 0;
                        $isCorrect = false;
                    } else {
                        $isCorrect = strtolower($testAnswer->user_answer) === strtolower($pertanyaan->jawaban_benar);
                        $score = $isCorrect ? 4 : 1;
                    }
                } elseif ($pertanyaan->tipe_soal === 'essay') {
                    // Essay: tidak diisi=0, diisi=admin input 1-4 (manual scoring)
                    if (empty($testAnswer->user_answer)) {
                        $score = 0;
                        $isCorrect = null; // Essay tidak ada is_correct
                    } else {
                        // Essay score akan diupdate manual oleh admin
                        $score = $testAnswer->score ?? 0;
                        $isCorrect = null; // Essay tidak ada is_correct
                    }
                }

                $totalScore += $score;

                // Update test_answer dengan skor
                $testAnswer->update([
                    'is_correct' => $isCorrect,
                    'score' => $score,
                    'max_score' => 4 // Max score per soal adalah 4
                ]);
                
                // Log untuk debugging
                \Log::info('Score calculated', [
                    'test_answer_id' => $testAnswer->id,
                    'tipe_soal' => $pertanyaan->tipe_soal,
                    'user_answer' => $testAnswer->user_answer,
                    'jawaban_benar' => $pertanyaan->jawaban_benar,
                    'is_correct' => $isCorrect,
                    'score' => $score
                ]);
            }

            // Hitung skor per kategori untuk sistem bobot
            $essayScore = 0;
            $essayMaxScore = 0;
            $pgYesNoScore = 0;
            $pgYesNoMaxScore = 0;
            
            foreach ($testAnswers as $testAnswer) {
                $pertanyaan = DB::table('soal_pertanyaan')
                    ->where('id', $testAnswer->soal_pertanyaan_id)
                    ->first();
                    
                if (!$pertanyaan) continue;
                
                if ($pertanyaan->tipe_soal === 'pilihan_ganda' || $pertanyaan->tipe_soal === 'yes_no') {
                    $pgYesNoScore += $testAnswer->score;
                    $pgYesNoMaxScore += 4;
                } elseif ($pertanyaan->tipe_soal === 'essay') {
                    $essayScore += $testAnswer->score;
                    $essayMaxScore += 4;
                }
            }
            
            // Hitung persentase dengan bobot
            $essayPercentage = $essayMaxScore > 0 ? ($essayScore / $essayMaxScore) * 100 : 0;
            $pgYesNoPercentage = $pgYesNoMaxScore > 0 ? ($pgYesNoScore / $pgYesNoMaxScore) * 100 : 0;
            
            // Bobot: Essay 70%, PG+Yes/No 30%
            $weightedPercentage = ($essayPercentage * 0.7) + ($pgYesNoPercentage * 0.3);
            
            // Update test_result dengan total skor dan bobot
            $testResult->update([
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'percentage' => $weightedPercentage
            ]);

            \Log::info('Test score calculated and saved', [
                'test_result_id' => $testResult->id,
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'percentage' => $percentage
            ]);

        } catch (\Exception $e) {
            \Log::error('Error calculating test score', [
                'error' => $e->getMessage(),
                'test_result_id' => $testResult->id
            ]);
        }
    }
}
