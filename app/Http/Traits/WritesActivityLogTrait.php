<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait WritesActivityLogTrait
{
    protected function resolveActivityUserMeta(?int $userId): array
    {
        if (!$userId) {
            return ['user_id' => null, 'user_name' => '-'];
        }

        $name = DB::table('users')->where('id', $userId)->value('nama_lengkap');

        return [
            'user_id' => $userId,
            'user_name' => $name ?: ('User #' . $userId),
        ];
    }

    protected function writeActivityLog(
        Request $request,
        string $module,
        string $activityType,
        string $description,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        try {
            $user = $request->user() ?? auth()->user();
            if (!$user) {
                return;
            }

            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => $activityType,
                'module' => $module,
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $oldData !== null ? json_encode($oldData) : null,
                'new_data' => $newData !== null ? json_encode($newData) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Activity log write failed', [
                'module' => $module,
                'activity_type' => $activityType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function enrichDeleteSnapshot(array $record, string $creatorField = 'created_by'): array
    {
        $creatorId = $record[$creatorField] ?? null;
        $creatorMeta = $this->resolveActivityUserMeta($creatorId ? (int) $creatorId : null);

        $record['created_by_name'] = $creatorMeta['user_name'];

        $deleter = auth()->user();
        if ($deleter) {
            $record['deleted_by'] = $deleter->id;
            $record['deleted_by_name'] = $deleter->nama_lengkap ?? null;
        }

        return $record;
    }
}
