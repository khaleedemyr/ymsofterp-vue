<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaQuiz;
use App\Models\JustAcademy\JaQuizOption;
use App\Models\JustAcademy\JaQuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = JaQuiz::withCount('questions')->orderByDesc('id');
        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        return Inertia::render('JustAcademy/Quizzes/Index', [
            'quizzes' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create()
    {
        return Inertia::render('JustAcademy/Quizzes/Form', ['quiz' => null]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuiz($request);

        DB::transaction(function () use ($request, $validated) {
            $quiz = JaQuiz::create([
                'title' => $validated['title'],
                'pass_score' => $validated['pass_score'],
                ...$this->timeLimitAttributes($validated),
                'questions_per_attempt' => $validated['questions_per_attempt'] ?? null,
                'randomize_questions' => $request->boolean('randomize_questions'),
                'randomize_options' => $request->boolean('randomize_options'),
                'is_active' => $request->boolean('is_active', true),
                'created_by' => $request->user()->id,
            ]);

            $this->syncQuestions($quiz, $validated['questions']);
        });

        return redirect()
            ->route('just-academy.quizzes.index')
            ->with('success', 'Quiz berhasil ditambahkan.');
    }

    public function edit(JaQuiz $quiz)
    {
        $quiz->load('questions.options');

        return Inertia::render('JustAcademy/Quizzes/Form', ['quiz' => $quiz]);
    }

    public function update(Request $request, JaQuiz $quiz)
    {
        $validated = $this->validateQuiz($request);

        DB::transaction(function () use ($request, $quiz, $validated) {
            $quiz->update([
                'title' => $validated['title'],
                'pass_score' => $validated['pass_score'],
                ...$this->timeLimitAttributes($validated),
                'questions_per_attempt' => $validated['questions_per_attempt'] ?? null,
                'randomize_questions' => $request->boolean('randomize_questions'),
                'randomize_options' => $request->boolean('randomize_options'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $quiz->questions()->each(function (JaQuizQuestion $q) {
                $q->options()->delete();
            });
            $quiz->questions()->delete();
            $this->syncQuestions($quiz, $validated['questions']);
        });

        return redirect()
            ->route('just-academy.quizzes.index')
            ->with('success', 'Quiz berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $quiz = JaQuiz::findOrFail($id);

        if ($quiz->programItems()->exists()) {
            return back()->with('error', 'Quiz masih dipakai di program. Hapus dari curriculum program terlebih dahulu.');
        }

        $quiz->delete();

        return redirect()
            ->route('just-academy.quizzes.index')
            ->with('success', 'Quiz berhasil dihapus.');
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();

        $instructionSheet = $spreadsheet->getActiveSheet();
        $instructionSheet->setTitle('Instruction');
        $instructionSheet->fromArray([
            ['Quizzes Import Template'],
            [''],
            ['Cara pakai'],
            ['1. Isi sheet "Template_Data".'],
            ['2. Satu baris = satu pertanyaan. Quiz yang sama bisa diulang di beberapa baris.'],
            ['3. Jika title quiz sudah ada, import akan UPDATE quiz tsb dan replace semua soal lama.'],
            ['4. Untuk type=mcq, isi minimal 2 opsi (A-D) dan correct_option (A/B/C/D).'],
            ['5. Untuk type=essay, opsi & correct_option boleh kosong.'],
            [''],
            ['Kolom wajib: quiz_title, pass_score, question_text.'],
            ['Kolom mode waktu: time_limit_mode = none/quiz/question.'],
            ['Jika time_limit_mode=quiz, isi time_limit_min. Jika question, isi time_limit_question_sec.'],
            [''],
            ['Boolean bisa diisi: 1/0, true/false, yes/no, y/n.'],
        ]);
        $instructionSheet->mergeCells('A1:Q1');
        $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructionSheet->getColumnDimension('A')->setWidth(120);

        $masterSheet = $spreadsheet->createSheet();
        $masterSheet->setTitle('Master_Reference');
        $masterSheet->fromArray([
            ['field', 'allowed_value'],
            ['time_limit_mode', 'none'],
            ['time_limit_mode', 'quiz'],
            ['time_limit_mode', 'question'],
            ['question_type', 'mcq'],
            ['question_type', 'essay'],
            ['correct_option', 'A/B/C/D (khusus mcq)'],
            ['boolean', '1/0, true/false, yes/no, y/n'],
        ], null, 'A1');
        $masterSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $masterSheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $masterSheet->getColumnDimension('A')->setWidth(28);
        $masterSheet->getColumnDimension('B')->setWidth(45);

        $dataSheet = $spreadsheet->createSheet();
        $dataSheet->setTitle('Template_Data');
        $headers = [
            'quiz_title',
            'pass_score',
            'time_limit_mode',
            'time_limit_min',
            'time_limit_question_sec',
            'questions_per_attempt',
            'randomize_questions',
            'randomize_options',
            'is_active',
            'question_text',
            'question_type',
            'points',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'correct_option',
        ];
        $dataSheet->fromArray([$headers], null, 'A1');
        $dataSheet->getStyle('A1:Q1')->getFont()->setBold(true);
        $dataSheet->getStyle('A1:Q1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');

        $sampleRows = [
            [
                'Pre Test Service Basic', 70, 'quiz', 30, '', 10, 1, 1, 1,
                'Greeting standard ke customer?', 'mcq', 1,
                'Senyum + sapa', 'Diam saja', 'Langsung tawarkan menu', '', 'A',
            ],
            [
                'Pre Test Service Basic', 70, 'quiz', 30, '', 10, 1, 1, 1,
                'Apa arti hospitality menurut kamu?', 'essay', 5,
                '', '', '', '', '',
            ],
        ];
        $dataSheet->fromArray($sampleRows, null, 'A2');

        foreach (range('A', 'Q') as $col) {
            $dataSheet->getColumnDimension($col)->setWidth(22);
        }

        $fileName = 'quizzes_template_' . now()->format('Ymd_His') . '.xlsx';
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName);
    }

    public function importFromExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $sheet = IOFactory::load($request->file('file')->getRealPath())->getSheetByName('Template_Data');
        if (!$sheet) {
            return back()->withErrors(['file' => 'Sheet "Template_Data" tidak ditemukan.']);
        }

        $rows = $sheet->toArray(null, true, true, true);
        if (count($rows) <= 1) {
            return back()->withErrors(['file' => 'Sheet "Template_Data" kosong.']);
        }

        $errors = [];
        $grouped = [];
        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue;
            }

            $quizTitle = trim((string) ($row['A'] ?? ''));
            $passScoreRaw = trim((string) ($row['B'] ?? ''));
            $timeLimitModeRaw = strtolower(trim((string) ($row['C'] ?? '')));
            $timeLimitMinRaw = trim((string) ($row['D'] ?? ''));
            $timeLimitQuestionRaw = trim((string) ($row['E'] ?? ''));
            $questionsPerAttemptRaw = trim((string) ($row['F'] ?? ''));
            $randomizeQuestionsRaw = trim((string) ($row['G'] ?? ''));
            $randomizeOptionsRaw = trim((string) ($row['H'] ?? ''));
            $isActiveRaw = trim((string) ($row['I'] ?? ''));
            $questionText = trim((string) ($row['J'] ?? ''));
            $questionTypeRaw = strtolower(trim((string) ($row['K'] ?? '')));
            $pointsRaw = trim((string) ($row['L'] ?? ''));
            $optionA = trim((string) ($row['M'] ?? ''));
            $optionB = trim((string) ($row['N'] ?? ''));
            $optionC = trim((string) ($row['O'] ?? ''));
            $optionD = trim((string) ($row['P'] ?? ''));
            $correctOption = strtoupper(trim((string) ($row['Q'] ?? '')));

            if (
                $quizTitle === '' && $passScoreRaw === '' && $questionText === ''
                && $optionA === '' && $optionB === '' && $optionC === '' && $optionD === ''
            ) {
                continue;
            }

            if ($quizTitle === '') {
                $errors[] = "Baris {$rowNumber}: quiz_title wajib diisi.";
                continue;
            }
            if ($questionText === '') {
                $errors[] = "Baris {$rowNumber}: question_text wajib diisi.";
                continue;
            }
            if ($passScoreRaw === '' || !is_numeric($passScoreRaw)) {
                $errors[] = "Baris {$rowNumber}: pass_score wajib numeric (0-100).";
                continue;
            }
            $passScore = (float) $passScoreRaw;
            if ($passScore < 0 || $passScore > 100) {
                $errors[] = "Baris {$rowNumber}: pass_score harus 0-100.";
                continue;
            }

            $timeLimitMode = in_array($timeLimitModeRaw, ['none', 'quiz', 'question'], true) ? $timeLimitModeRaw : 'none';
            $timeLimitMin = null;
            $timeLimitQuestionSec = null;
            if ($timeLimitMode === 'quiz') {
                if ($timeLimitMinRaw === '' || !ctype_digit($timeLimitMinRaw) || (int) $timeLimitMinRaw < 1) {
                    $errors[] = "Baris {$rowNumber}: time_limit_min wajib integer >= 1 jika mode=quiz.";
                    continue;
                }
                $timeLimitMin = (int) $timeLimitMinRaw;
            } elseif ($timeLimitMode === 'question') {
                if ($timeLimitQuestionRaw === '' || !ctype_digit($timeLimitQuestionRaw) || (int) $timeLimitQuestionRaw < 5) {
                    $errors[] = "Baris {$rowNumber}: time_limit_question_sec wajib integer >= 5 jika mode=question.";
                    continue;
                }
                $timeLimitQuestionSec = (int) $timeLimitQuestionRaw;
            }

            $questionsPerAttempt = null;
            if ($questionsPerAttemptRaw !== '') {
                if (!ctype_digit($questionsPerAttemptRaw) || (int) $questionsPerAttemptRaw < 1) {
                    $errors[] = "Baris {$rowNumber}: questions_per_attempt harus integer >= 1.";
                    continue;
                }
                $questionsPerAttempt = (int) $questionsPerAttemptRaw;
            }

            $questionType = in_array($questionTypeRaw, ['mcq', 'essay'], true) ? $questionTypeRaw : 'mcq';
            $points = ($pointsRaw !== '' && is_numeric($pointsRaw)) ? (float) $pointsRaw : 1.0;
            if ($points < 0) {
                $errors[] = "Baris {$rowNumber}: points tidak boleh negatif.";
                continue;
            }

            $options = [];
            if ($questionType === 'mcq') {
                $optionMap = [
                    'A' => $optionA,
                    'B' => $optionB,
                    'C' => $optionC,
                    'D' => $optionD,
                ];
                $filtered = array_filter($optionMap, fn ($txt) => $txt !== '');
                if (count($filtered) < 2) {
                    $errors[] = "Baris {$rowNumber}: type mcq butuh minimal 2 opsi.";
                    continue;
                }
                if (!array_key_exists($correctOption, $filtered)) {
                    $errors[] = "Baris {$rowNumber}: correct_option harus salah satu opsi yang terisi (A/B/C/D).";
                    continue;
                }
                foreach ($filtered as $code => $txt) {
                    $options[] = [
                        'option_text' => $txt,
                        'is_correct' => $code === $correctOption,
                    ];
                }
            }

            if (!isset($grouped[$quizTitle])) {
                $grouped[$quizTitle] = [
                    'title' => $quizTitle,
                    'pass_score' => $passScore,
                    'time_limit_mode' => $timeLimitMode,
                    'time_limit_min' => $timeLimitMin,
                    'time_limit_question_sec' => $timeLimitQuestionSec,
                    'questions_per_attempt' => $questionsPerAttempt,
                    'randomize_questions' => $this->parseExcelBool($randomizeQuestionsRaw, false),
                    'randomize_options' => $this->parseExcelBool($randomizeOptionsRaw, false),
                    'is_active' => $this->parseExcelBool($isActiveRaw, true),
                    'questions' => [],
                ];
            }

            $grouped[$quizTitle]['questions'][] = [
                'question' => $questionText,
                'type' => $questionType,
                'points' => $points,
                'options' => $options,
            ];
        }

        foreach ($grouped as $title => $payload) {
            if (!empty($payload['questions_per_attempt']) && $payload['questions_per_attempt'] > count($payload['questions'])) {
                $errors[] = "Quiz \"{$title}\": questions_per_attempt melebihi total soal.";
            }
        }

        if (!empty($errors)) {
            return back()->withErrors([
                'file' => implode("\n", array_slice($errors, 0, 20)) . (count($errors) > 20 ? "\n..." : ''),
            ]);
        }
        if (empty($grouped)) {
            return back()->withErrors(['file' => 'Tidak ada data valid untuk diimport.']);
        }

        DB::transaction(function () use ($grouped, $request) {
            foreach ($grouped as $payload) {
                $quiz = JaQuiz::where('title', $payload['title'])->first();
                $attributes = [
                    'title' => $payload['title'],
                    'pass_score' => $payload['pass_score'],
                    'time_limit_mode' => $payload['time_limit_mode'],
                    'time_limit_min' => $payload['time_limit_mode'] === 'quiz' ? $payload['time_limit_min'] : null,
                    'time_limit_question_sec' => $payload['time_limit_mode'] === 'question' ? $payload['time_limit_question_sec'] : null,
                    'questions_per_attempt' => $payload['questions_per_attempt'],
                    'randomize_questions' => $payload['randomize_questions'],
                    'randomize_options' => $payload['randomize_options'],
                    'is_active' => $payload['is_active'],
                ];

                if ($quiz) {
                    $quiz->update($attributes);
                    $quiz->questions()->each(function (JaQuizQuestion $q) {
                        $q->options()->delete();
                    });
                    $quiz->questions()->delete();
                } else {
                    $quiz = JaQuiz::create([
                        ...$attributes,
                        'created_by' => $request->user()->id,
                    ]);
                }

                $this->syncQuestions($quiz, $payload['questions']);
            }
        });

        return redirect()
            ->route('just-academy.quizzes.index')
            ->with('success', 'Import quizzes berhasil diproses.');
    }

    private function validateQuiz(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'pass_score' => 'required|numeric|min:0|max:100',
            'time_limit_mode' => 'required|in:none,quiz,question',
            'time_limit_min' => 'nullable|integer|min:1|required_if:time_limit_mode,quiz',
            'time_limit_question_sec' => 'nullable|integer|min:5|max:3600|required_if:time_limit_mode,question',
            'questions_per_attempt' => 'nullable|integer|min:1',
            'randomize_questions' => 'boolean',
            'randomize_options' => 'boolean',
            'is_active' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:mcq,essay',
            'questions.*.points' => 'nullable|numeric|min:0',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.option_text' => 'required_with:questions.*.options|string|max:500',
            'questions.*.options.*.is_correct' => 'boolean',
        ]);

        if (
            !empty($validated['questions_per_attempt'])
            && $validated['questions_per_attempt'] > count($validated['questions'])
        ) {
            throw ValidationException::withMessages([
                'questions_per_attempt' => 'Jumlah soal per tes tidak boleh melebihi total pertanyaan di bank (' . count($validated['questions']) . ').',
            ]);
        }

        return $validated;
    }

    private function timeLimitAttributes(array $validated): array
    {
        $mode = $validated['time_limit_mode'] ?? 'none';

        return [
            'time_limit_mode' => $mode,
            'time_limit_min' => $mode === 'quiz' ? ($validated['time_limit_min'] ?? null) : null,
            'time_limit_question_sec' => $mode === 'question' ? ($validated['time_limit_question_sec'] ?? null) : null,
        ];
    }

    private function syncQuestions(JaQuiz $quiz, array $questions): void
    {
        foreach ($questions as $i => $qData) {
            $question = JaQuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => $qData['question'],
                'type' => $qData['type'],
                'sort_order' => $i,
                'points' => $qData['points'] ?? 1,
            ]);

            if ($qData['type'] === 'mcq' && !empty($qData['options'])) {
                foreach ($qData['options'] as $j => $opt) {
                    if (trim((string) ($opt['option_text'] ?? '')) === '') {
                        continue;
                    }
                    JaQuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => $opt['option_text'],
                        'is_correct' => !empty($opt['is_correct']),
                        'sort_order' => $j,
                    ]);
                }
            }
        }
    }

    private function parseExcelBool(?string $value, bool $default = false): bool
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }
        $raw = strtolower(trim($value));
        return in_array($raw, ['1', 'true', 'yes', 'y'], true);
    }
}
