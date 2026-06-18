<?php

namespace App\Services\JustAcademy;

use App\Models\JustAcademy\JaAttendance;
use App\Models\JustAcademy\JaMaterialProgress;
use App\Models\JustAcademy\JaQuiz;
use App\Models\JustAcademy\JaQuizAnswer;
use App\Models\JustAcademy\JaQuizAttempt;
use App\Models\JustAcademy\JaSchedule;
use App\Models\JustAcademy\JaScheduleInviteLog;
use App\Models\JustAcademy\JaScheduleParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JustAcademyService
{
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

        $inviteSource = $filterType === 'mixed' ? 'mixed' : $filterType;

        $added = 0;
        DB::transaction(function () use ($schedule, $resolved, $inviteSource, $invitedBy, $userIds, $jabatanIds, $outletIds, &$added) {
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

    public function markMaterialComplete(JaSchedule $schedule, int $userId, int $materialId): JaMaterialProgress
    {
        $this->ensureParticipant($schedule, $userId);

        $materialBelongs = $schedule->program->materials()->where('id', $materialId)->exists();
        if (!$materialBelongs) {
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

        if ($quiz->program_id !== $schedule->program_id) {
            throw ValidationException::withMessages(['quiz' => 'Quiz tidak sesuai program jadwal.']);
        }

        $questions = $quiz->questions()->with('options')->get();
        $totalPoints = max(1, (float) $questions->sum('points'));
        $earned = 0.0;

        return DB::transaction(function () use ($schedule, $quiz, $userId, $answers, $questions, $totalPoints, &$earned) {
            $attempt = JaQuizAttempt::create([
                'schedule_id' => $schedule->id,
                'quiz_id' => $quiz->id,
                'user_id' => $userId,
                'started_at' => now(),
                'submitted_at' => now(),
            ]);

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
                'score' => $score,
                'passed' => $passed,
            ]);

            return $attempt->fresh('answers');
        });
    }
}
