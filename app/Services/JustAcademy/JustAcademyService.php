<?php

namespace App\Services\JustAcademy;

use App\Models\JustAcademy\JaAttendance;
use App\Models\JustAcademy\JaMaterial;
use App\Models\JustAcademy\JaMaterialProgress;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaProgramItem;
use App\Models\JustAcademy\JaQuiz;
use App\Models\JustAcademy\JaQuizAnswer;
use App\Models\JustAcademy\JaQuizAttempt;
use App\Models\JustAcademy\JaSchedule;
use App\Models\JustAcademy\JaScheduleInviteLog;
use App\Models\JustAcademy\JaScheduleParticipant;
use App\Models\JustAcademy\JaScheduleTrainer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JustAcademyService
{
    /** Status jadwal yang boleh dilihat peserta (bukan draft/cancelled). */
    public const PARTICIPANT_VISIBLE_STATUSES = ['published', 'ongoing', 'completed'];

    public function participantSchedulesForUser(int $userId)
    {
        return JaSchedule::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('status', self::PARTICIPANT_VISIBLE_STATUSES);
    }

    public function resolveUserIds(array $userIds = [], array $jabatanIds = [], array $outletIds = []): Collection
    {
        $ids = collect($userIds)->filter()->map(fn ($id) => (int) $id);

        if ($jabatanIds !== []) {
            $ids = $ids->merge(
                User::active()->whereIn('id_jabatan', $jabatanIds)->pluck('id')
            );
        }

        if ($outletIds !== []) {
            $ids = $ids->merge(
                User::active()->whereIn('id_outlet', $outletIds)->pluck('id')
            );
        }

        return $ids->unique()->values();
    }

    public function inviteParticipants(
        JaSchedule $schedule,
        array $userIds,
        array $jabatanIds,
        array $outletIds,
        int $invitedBy,
    ): int {
        $resolved = $this->resolveUserIds($userIds, $jabatanIds, $outletIds);

        if ($resolved->isEmpty()) {
            return 0;
        }

        $filterType = 'mixed';
        if ($userIds !== [] && $jabatanIds === [] && $outletIds === []) {
            $filterType = 'users';
        } elseif ($jabatanIds !== [] && $userIds === [] && $outletIds === []) {
            $filterType = 'jabatan';
        } elseif ($outletIds !== [] && $userIds === [] && $jabatanIds === []) {
            $filterType = 'outlet';
        }

        $inviteSource = match ($filterType) {
            'jabatan' => 'jabatan',
            'outlet' => 'outlet',
            'mixed' => 'mixed',
            default => 'manual',
        };

        $added = 0;
        DB::transaction(function () use ($schedule, $resolved, $inviteSource, $filterType, $invitedBy, $userIds, $jabatanIds, $outletIds, &$added) {
            foreach ($resolved as $userId) {
                $participant = JaScheduleParticipant::firstOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'user_id' => $userId,
                    ],
                    [
                        'invite_source' => $inviteSource,
                        'status' => 'invited',
                        'invited_at' => now(),
                        'invited_by' => $invitedBy,
                    ]
                );

                if ($participant->wasRecentlyCreated) {
                    $added++;
                }
            }

            JaScheduleInviteLog::create([
                'schedule_id' => $schedule->id,
                'invited_by' => $invitedBy,
                'filter_type' => $filterType,
                'filter_payload' => [
                    'user_ids' => array_values($userIds),
                    'jabatan_ids' => array_values($jabatanIds),
                    'outlet_ids' => array_values($outletIds),
                ],
                'participants_added' => $added,
                'created_at' => now(),
            ]);
        });

        return $added;
    }

    public function syncParticipants(JaSchedule $schedule, array $userIds, int $invitedBy): void
    {
        $userIds = collect($userIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $existing = $schedule->participants()->pluck('user_id');

        $toRemove = $existing->diff($userIds);
        if ($toRemove->isNotEmpty()) {
            $schedule->participants()->whereIn('user_id', $toRemove)->delete();
        }

        foreach ($userIds->diff($existing) as $userId) {
            JaScheduleParticipant::create([
                'schedule_id' => $schedule->id,
                'user_id' => $userId,
                'invite_source' => 'manual',
                'status' => 'invited',
                'invited_at' => now(),
                'invited_by' => $invitedBy,
            ]);
        }
    }

    public function syncTrainers(JaSchedule $schedule, array $internalUserIds, array $externalNames): void
    {
        $internalUserIds = collect($internalUserIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $externalNames = collect($externalNames)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($schedule, $internalUserIds, $externalNames) {
            $schedule->trainers()
                ->where(function ($query) use ($internalUserIds, $externalNames) {
                    $query->where(function ($internal) use ($internalUserIds) {
                        $internal->where('trainer_type', 'internal')
                            ->whereNotIn('user_id', $internalUserIds->isEmpty() ? [-1] : $internalUserIds);
                    })->orWhere(function ($external) use ($externalNames) {
                        $external->where('trainer_type', 'external')
                            ->whereNotIn('external_name', $externalNames->isEmpty() ? [''] : $externalNames);
                    });
                })
                ->delete();

            $isFirst = true;
            foreach ($internalUserIds as $userId) {
                JaScheduleTrainer::updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'trainer_type' => 'internal',
                        'user_id' => $userId,
                    ],
                    [
                        'external_name' => null,
                        'role' => $isFirst ? 'primary' : 'assistant',
                    ]
                );
                $isFirst = false;
            }

            foreach ($externalNames as $name) {
                JaScheduleTrainer::firstOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'trainer_type' => 'external',
                        'external_name' => $name,
                    ],
                    [
                        'user_id' => null,
                        'role' => 'assistant',
                    ]
                );
            }
        });
    }

    public function ensureParticipant(JaSchedule $schedule, int $userId): JaScheduleParticipant
    {
        $participant = JaScheduleParticipant::where('schedule_id', $schedule->id)
            ->where('user_id', $userId)
            ->first();

        if (!$participant) {
            throw ValidationException::withMessages([
                'schedule' => 'Anda tidak terdaftar sebagai peserta training ini.',
            ]);
        }

        return $participant;
    }

    public function trainingHasStarted(JaSchedule $schedule): bool
    {
        if (!$schedule->start_at) {
            return true;
        }

        return now()->gte($schedule->start_at);
    }

    public function ensureTrainingStarted(JaSchedule $schedule): void
    {
        if ($this->trainingHasStarted($schedule)) {
            return;
        }

        $startsAt = $schedule->start_at->timezone(config('app.timezone'))->format('d/m/Y H:i');

        throw ValidationException::withMessages([
            'schedule' => "Training belum dimulai. Materi dapat dibuka mulai {$startsAt}.",
        ]);
    }

    public function ensureQrToken(JaSchedule $schedule): string
    {
        if ($schedule->qr_token) {
            return $schedule->qr_token;
        }

        $token = Str::random(40);
        $schedule->update(['qr_token' => $token]);

        return $token;
    }

    public function checkIn(JaSchedule $schedule, int $userId, ?string $qrToken = null, string $method = 'qr', ?int $markedBy = null): JaAttendance
    {
        if ($method === 'qr') {
            if (!$qrToken || $qrToken !== $schedule->qr_token) {
                throw ValidationException::withMessages(['qr_token' => 'QR code tidak valid.']);
            }
            $this->ensureParticipant($schedule, $userId);
        } else {
            $this->ensureParticipant($schedule, $userId);
        }

        return JaAttendance::updateOrCreate(
            ['schedule_id' => $schedule->id, 'user_id' => $userId],
            [
                'check_in_at' => now(),
                'method' => $method,
                'marked_by' => $method === 'manual' ? $markedBy : null,
            ]
        );
    }

    public function checkOut(JaSchedule $schedule, int $userId): JaAttendance
    {
        $this->ensureParticipant($schedule, $userId);

        $attendance = JaAttendance::firstOrCreate(
            ['schedule_id' => $schedule->id, 'user_id' => $userId],
            ['check_in_at' => now(), 'method' => 'qr']
        );

        $attendance->update(['check_out_at' => now()]);

        return $attendance->fresh();
    }

    public function programHasMaterial(JaProgram $program, int $materialId): bool
    {
        return JaProgramItem::where('program_id', $program->id)
            ->where('item_type', 'material')
            ->where('material_id', $materialId)
            ->exists();
    }

    public function programHasQuiz(JaProgram $program, int $quizId): bool
    {
        return JaProgramItem::where('program_id', $program->id)
            ->where('item_type', 'quiz')
            ->where('quiz_id', $quizId)
            ->exists();
    }

    public function getProgramCurriculum(JaProgram $program): \Illuminate\Support\Collection
    {
        return $program->items()
            ->with([
                'material' => fn ($q) => $q->where('is_active', true),
                'quiz' => fn ($q) => $q->where('is_active', true)->with('questions.options'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (JaProgramItem $item) => $item->material || $item->quiz)
            ->values();
    }

    public function buildScheduleCurriculumOverview(JaProgram $program): \Illuminate\Support\Collection
    {
        return $this->getProgramCurriculum($program)->map(function (JaProgramItem $item) {
            if ($item->item_type === 'material' && $item->material) {
                $material = $item->material;

                return [
                    'item_type' => 'material',
                    'id' => $material->id,
                    'title' => $material->title,
                    'type' => $material->type,
                    'description' => $material->description,
                    'file_path' => $material->file_path ? asset('storage/' . $material->file_path) : null,
                    'url' => $material->url,
                    'is_required' => $item->is_required,
                ];
            }

            if ($item->item_type === 'quiz' && $item->quiz) {
                $quiz = $item->quiz;

                return [
                    'item_type' => 'quiz',
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'pass_score' => $quiz->pass_score,
                    'question_count' => $quiz->questions?->count() ?? 0,
                    'is_required' => $item->is_required,
                ];
            }

            return null;
        })->filter()->values();
    }

    public function syncProgramCurriculum(JaProgram $program, array $items): void
    {
        DB::transaction(function () use ($program, $items) {
            $program->items()->delete();

            foreach ($items as $i => $row) {
                $type = $row['item_type'];
                $refId = (int) $row['ref_id'];

                if ($type === 'material') {
                    JaMaterial::where('id', $refId)->where('is_active', true)->firstOrFail();
                    JaProgramItem::create([
                        'program_id' => $program->id,
                        'item_type' => 'material',
                        'material_id' => $refId,
                        'sort_order' => $i,
                        'is_required' => !empty($row['is_required']),
                    ]);
                } else {
                    JaQuiz::where('id', $refId)->where('is_active', true)->firstOrFail();
                    JaProgramItem::create([
                        'program_id' => $program->id,
                        'item_type' => 'quiz',
                        'quiz_id' => $refId,
                        'sort_order' => $i,
                        'is_required' => !empty($row['is_required']),
                    ]);
                }
            }
        });
    }

    public function buildParticipantCurriculum(JaSchedule $schedule, int $userId, ?bool $trainingStarted = null): \Illuminate\Support\Collection
    {
        $trainingStarted ??= $this->trainingHasStarted($schedule);

        return $this->getProgramCurriculum($schedule->program)->map(function (JaProgramItem $item) use ($schedule, $userId, $trainingStarted) {
            if ($item->item_type === 'material' && $item->material) {
                $m = $item->material;
                $completed = JaMaterialProgress::where([
                    'schedule_id' => $schedule->id,
                    'user_id' => $userId,
                    'material_id' => $m->id,
                ])->exists();

                return [
                    'item_type' => 'material',
                    'id' => $m->id,
                    'title' => $m->title,
                    'type' => $m->type,
                    'file_path' => $trainingStarted && $m->file_path ? asset('storage/' . $m->file_path) : null,
                    'url' => $trainingStarted ? $m->url : null,
                    'is_required' => $item->is_required,
                    'completed' => $completed,
                    'locked' => !$trainingStarted,
                ];
            }

            if ($item->item_type === 'quiz' && $item->quiz) {
                $quiz = $item->quiz;
                $poolSize = $quiz->questions->count();

                if (!$trainingStarted) {
                    return [
                        'item_type' => 'quiz',
                        'id' => $quiz->id,
                        'title' => $quiz->title,
                        'pass_score' => $quiz->pass_score,
                        'is_required' => $item->is_required,
                        'question_pool_size' => $poolSize,
                        'locked' => true,
                        'questions' => [],
                        'attempt' => null,
                    ];
                }

                $submittedAttempt = JaQuizAttempt::where('schedule_id', $schedule->id)
                    ->where('quiz_id', $quiz->id)
                    ->where('user_id', $userId)
                    ->whereNotNull('submitted_at')
                    ->latest('id')
                    ->first();

                $poolSize = $quiz->questions->count();

                if ($submittedAttempt) {
                    return [
                        'item_type' => 'quiz',
                        'id' => $quiz->id,
                        'title' => $quiz->title,
                        'pass_score' => $quiz->pass_score,
                        'is_required' => $item->is_required,
                        'question_pool_size' => $poolSize,
                        'questions_shown' => count($submittedAttempt->question_ids ?? []),
                        'randomize_questions' => $quiz->randomize_questions,
                        'randomize_options' => $quiz->randomize_options,
                        'time_limit' => $this->buildQuizTimePayload($quiz),
                        'attempt' => [
                            'score' => $submittedAttempt->score,
                            'passed' => $submittedAttempt->passed,
                            'submitted_at' => $submittedAttempt->submitted_at,
                        ],
                        'questions' => [],
                    ];
                }

                $openAttempt = $this->ensureOpenQuizAttempt($schedule, $quiz, $userId);
                $questions = $this->resolveQuestionsByIds($quiz, $openAttempt->question_ids ?? []);

                return [
                    'item_type' => 'quiz',
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'pass_score' => $quiz->pass_score,
                    'is_required' => $item->is_required,
                    'question_pool_size' => $poolSize,
                    'questions_shown' => count($questions),
                    'randomize_questions' => $quiz->randomize_questions,
                    'randomize_options' => $quiz->randomize_options,
                    'time_limit' => $this->buildQuizTimePayload($quiz),
                    'session' => [
                        'started_at' => $openAttempt->started_at?->toIso8601String(),
                        'quiz_progress' => $openAttempt->quiz_progress,
                    ],
                    'attempt' => null,
                    'questions' => $this->formatQuestionsForParticipant($questions, $openAttempt->option_orders ?? []),
                ];
            }

            return null;
        })->filter()->values();
    }

    public function markMaterialComplete(JaSchedule $schedule, int $userId, int $materialId): JaMaterialProgress
    {
        $this->ensureParticipant($schedule, $userId);
        $this->ensureTrainingStarted($schedule);

        if (!$this->programHasMaterial($schedule->program, $materialId)) {
            throw ValidationException::withMessages(['material' => 'Materi tidak ditemukan pada program ini.']);
        }

        return JaMaterialProgress::updateOrCreate(
            [
                'schedule_id' => $schedule->id,
                'user_id' => $userId,
                'material_id' => $materialId,
            ],
            ['completed_at' => now()]
        );
    }

    public function submitQuiz(JaSchedule $schedule, JaQuiz $quiz, int $userId, array $answers): JaQuizAttempt
    {
        $this->ensureParticipant($schedule, $userId);
        $this->ensureTrainingStarted($schedule);

        if (!$this->programHasQuiz($schedule->program, $quiz->id)) {
            throw ValidationException::withMessages(['quiz' => 'Quiz tidak sesuai program jadwal.']);
        }

        $attempt = JaQuizAttempt::where('schedule_id', $schedule->id)
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->whereNull('submitted_at')
            ->latest('id')
            ->first();

        if (!$attempt) {
            throw ValidationException::withMessages(['quiz' => 'Sesi quiz tidak aktif. Muat ulang halaman training.']);
        }

        $questionIds = $attempt->question_ids ?? [];
        $questions = $this->resolveQuestionsByIds($quiz, $questionIds);
        $this->assertAttemptWithinTimeLimit($quiz, $attempt, $questions->count());
        $totalPoints = max(1, (float) $questions->sum('points'));
        $earned = 0.0;

        return DB::transaction(function () use ($attempt, $answers, $questions, $totalPoints, $quiz, &$earned) {
            foreach ($questions as $question) {
                $payload = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
                $optionId = null;
                $answerText = null;
                $isCorrect = null;
                $pointsEarned = 0.0;

                if ($question->type === 'mcq') {
                    $optionId = is_array($payload) ? ($payload['option_id'] ?? null) : $payload;
                    $option = $question->options->firstWhere('id', (int) $optionId);
                    $isCorrect = $option ? (bool) $option->is_correct : false;
                    $pointsEarned = $isCorrect ? (float) $question->points : 0.0;
                } else {
                    $answerText = is_array($payload) ? ($payload['answer_text'] ?? '') : (string) $payload;
                    $pointsEarned = 0.0;
                }

                $earned += $pointsEarned;

                JaQuizAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'option_id' => $optionId,
                    'answer_text' => $answerText,
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
                ]);
            }

            $score = round(($earned / $totalPoints) * 100, 2);
            $passed = $score >= (float) $quiz->pass_score;

            $attempt->update([
                'submitted_at' => now(),
                'score' => $score,
                'passed' => $passed,
            ]);

            return $attempt->fresh('answers');
        });
    }

    public function pickQuestionIds(JaQuiz $quiz): array
    {
        $ordered = $quiz->questions()->orderBy('sort_order')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ordered === []) {
            return [];
        }

        $perAttempt = $quiz->questions_per_attempt;
        $want = ($perAttempt !== null && $perAttempt > 0)
            ? min((int) $perAttempt, count($ordered))
            : count($ordered);

        if ($quiz->randomize_questions) {
            $pool = $ordered;
            shuffle($pool);
            $ids = array_slice($pool, 0, $want);
            shuffle($ids);
        } else {
            $ids = array_slice($ordered, 0, $want);
        }

        return array_values($ids);
    }

    public function ensureOpenQuizAttempt(JaSchedule $schedule, JaQuiz $quiz, int $userId): JaQuizAttempt
    {
        $this->ensureTrainingStarted($schedule);

        $existing = JaQuizAttempt::where('schedule_id', $schedule->id)
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->whereNull('submitted_at')
            ->latest('id')
            ->first();

        if ($existing) {
            $updates = [];

            if (empty($existing->question_ids)) {
                $updates['question_ids'] = $this->pickQuestionIds($quiz);
            }

            if ($quiz->randomize_options && empty($existing->option_orders)) {
                $questionIds = $updates['question_ids'] ?? $existing->question_ids ?? [];
                $questions = $this->resolveQuestionsByIds($quiz, $questionIds);
                $updates['option_orders'] = $this->pickOptionOrders($quiz, $questions);
            }

            if ($quiz->effectiveTimeLimitMode() === 'question' && empty($existing->quiz_progress)) {
                $updates['quiz_progress'] = $this->initialQuizProgress();
            }

            if ($updates !== []) {
                $existing->update($updates);
                $existing->refresh();
            }

            return $existing;
        }

        $questionIds = $this->pickQuestionIds($quiz);
        $questions = $this->resolveQuestionsByIds($quiz, $questionIds);

        return JaQuizAttempt::create([
            'schedule_id' => $schedule->id,
            'quiz_id' => $quiz->id,
            'user_id' => $userId,
            'started_at' => now(),
            'question_ids' => $questionIds,
            'option_orders' => $this->pickOptionOrders($quiz, $questions) ?: null,
            'quiz_progress' => $quiz->effectiveTimeLimitMode() === 'question'
                ? $this->initialQuizProgress()
                : null,
        ]);
    }

    public function syncQuizProgress(JaSchedule $schedule, JaQuiz $quiz, int $userId, int $currentIndex): JaQuizAttempt
    {
        $this->ensureParticipant($schedule, $userId);
        $this->ensureTrainingStarted($schedule);

        if ($quiz->effectiveTimeLimitMode() !== 'question') {
            throw ValidationException::withMessages(['quiz' => 'Quiz ini tidak memakai mode waktu per soal.']);
        }

        $attempt = JaQuizAttempt::where('schedule_id', $schedule->id)
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->whereNull('submitted_at')
            ->latest('id')
            ->first();

        if (!$attempt) {
            throw ValidationException::withMessages(['quiz' => 'Sesi quiz tidak aktif.']);
        }

        $maxIndex = max(0, count($attempt->question_ids ?? []) - 1);
        $currentIndex = min(max(0, $currentIndex), $maxIndex);

        $attempt->update([
            'quiz_progress' => [
                'current_index' => $currentIndex,
                'question_started_at' => now()->toIso8601String(),
            ],
        ]);

        return $attempt->fresh();
    }

    private function initialQuizProgress(): array
    {
        return [
            'current_index' => 0,
            'question_started_at' => now()->toIso8601String(),
        ];
    }

    private function buildQuizTimePayload(JaQuiz $quiz): array
    {
        $mode = $quiz->effectiveTimeLimitMode();

        return [
            'mode' => $mode,
            'quiz_minutes' => $mode === 'quiz' ? $quiz->time_limit_min : null,
            'question_seconds' => $mode === 'question' ? $quiz->time_limit_question_sec : null,
        ];
    }

    private function assertAttemptWithinTimeLimit(JaQuiz $quiz, JaQuizAttempt $attempt, int $questionCount): void
    {
        if (!$attempt->started_at) {
            return;
        }

        $mode = $quiz->effectiveTimeLimitMode();
        $elapsed = $attempt->started_at->diffInSeconds(now());

        if ($mode === 'quiz' && $quiz->time_limit_min) {
            $maxSeconds = ((int) $quiz->time_limit_min * 60) + 15;
            if ($elapsed > $maxSeconds) {
                throw ValidationException::withMessages(['quiz' => 'Waktu quiz sudah habis.']);
            }

            return;
        }

        if ($mode === 'question' && $quiz->time_limit_question_sec && $questionCount > 0) {
            $maxSeconds = ((int) $quiz->time_limit_question_sec * $questionCount) + 30;
            if ($elapsed > $maxSeconds) {
                throw ValidationException::withMessages(['quiz' => 'Waktu quiz sudah habis.']);
            }
        }
    }

    public function pickOptionOrders(JaQuiz $quiz, Collection $questions): array
    {
        if (!$quiz->randomize_options) {
            return [];
        }

        $orders = [];
        foreach ($questions as $question) {
            if ($question->type !== 'mcq') {
                continue;
            }

            $optionIds = $question->options->sortBy('sort_order')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($optionIds) < 2) {
                continue;
            }

            shuffle($optionIds);
            $orders[(string) $question->id] = $optionIds;
        }

        return $orders;
    }

    private function resolveQuestionsByIds(JaQuiz $quiz, array $questionIds): Collection
    {
        if ($questionIds === []) {
            return collect();
        }

        $loaded = $quiz->relationLoaded('questions')
            ? $quiz->questions
            : $quiz->questions()->with('options')->get();

        return collect($questionIds)
            ->map(fn ($id) => $loaded->firstWhere('id', (int) $id))
            ->filter()
            ->values();
    }

    private function formatQuestionsForParticipant(Collection $questions, array $optionOrders = []): array
    {
        return $questions->map(function ($question) use ($optionOrders) {
            $options = $question->options;
            $order = $optionOrders[(string) $question->id] ?? $optionOrders[$question->id] ?? null;

            if (is_array($order) && $order !== []) {
                $options = collect($order)
                    ->map(fn ($optionId) => $options->firstWhere('id', (int) $optionId))
                    ->filter();
            } else {
                $options = $options->sortBy('sort_order');
            }

            return [
                'id' => $question->id,
                'question' => $question->question,
                'type' => $question->type,
                'points' => $question->points,
                'options' => $options->map(fn ($option) => [
                    'id' => $option->id,
                    'option_text' => $option->option_text,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
