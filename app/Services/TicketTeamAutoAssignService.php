<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketHistory;
use App\Models\TicketTeamSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TicketTeamAutoAssignService
{
    /**
     * Resolve team setting and assign users when ticket is created.
     */
    public function assignIfMatch(Ticket $ticket, ?int $assignedBy = null): bool
    {
        $setting = $this->resolveForTicket($ticket);
        if (! $setting) {
            return false;
        }

        $setting->loadMissing(['users']);
        $userIds = $setting->users->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        if ($userIds->isEmpty()) {
            return false;
        }

        $primaryUserId = $setting->users->firstWhere('pivot.is_primary', true)?->id;
        $primaryUserId = $primaryUserId ? (int) $primaryUserId : $userIds->first();
        if (! $userIds->contains($primaryUserId)) {
            $primaryUserId = $userIds->first();
        }

        $this->assignUsers($ticket, $userIds, $primaryUserId, $assignedBy, $setting);

        return true;
    }

    public function resolveForTicket(Ticket $ticket): ?TicketTeamSetting
    {
        $ticket->loadMissing('outlet');
        $outletId = (int) $ticket->outlet_id;
        $regionId = $ticket->outlet?->region_id ? (int) $ticket->outlet->region_id : null;
        $categoryId = (int) $ticket->category_id;

        $candidates = TicketTeamSetting::query()
            ->active()
            ->where('category_id', $categoryId)
            ->with(['regions:id', 'outlets:id_outlet', 'users:id,nama_lengkap'])
            ->get();

        $best = null;
        $bestScore = -1;

        foreach ($candidates as $setting) {
            $score = $this->matchScore($setting, $outletId, $regionId);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $setting;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    private function matchScore(TicketTeamSetting $setting, int $outletId, ?int $regionId): int
    {
        $outletIds = $setting->outlets->pluck('id_outlet')->map(fn ($id) => (int) $id);
        $regionIds = $setting->regions->pluck('id')->map(fn ($id) => (int) $id);
        $hasOutlets = $outletIds->isNotEmpty();
        $hasRegions = $regionIds->isNotEmpty();

        if ($hasOutlets && $outletIds->contains($outletId)) {
            return 3;
        }
        if ($hasRegions && $regionId && $regionIds->contains($regionId)) {
            return 2;
        }
        if (! $hasOutlets && ! $hasRegions) {
            return 1;
        }

        return 0;
    }

    private function assignUsers(
        Ticket $ticket,
        Collection $userIds,
        int $primaryUserId,
        ?int $assignedBy,
        TicketTeamSetting $setting
    ): void {
        TicketAssignment::where('ticket_id', $ticket->id)->delete();

        foreach ($userIds as $userId) {
            TicketAssignment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'is_primary' => (int) $userId === (int) $primaryUserId,
            ]);
        }

        $assignedNames = User::whereIn('id', $userIds)->pluck('nama_lengkap')->toArray();
        $label = $setting->name ?: ('Setting #' . $setting->id);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $assignedBy,
            'action' => 'assigned',
            'field_name' => 'assigned_users',
            'old_value' => null,
            'new_value' => null,
            'description' => 'Auto-assign team (' . $label . '): ' . implode(', ', $assignedNames),
        ]);

        $this->sendAssignmentNotifications($ticket, $userIds->all(), $primaryUserId, $assignedBy);

        Log::info('Ticket auto-assigned from team setting', [
            'ticket_id' => $ticket->id,
            'setting_id' => $setting->id,
            'user_ids' => $userIds->all(),
            'primary_user_id' => $primaryUserId,
        ]);
    }

    private function sendAssignmentNotifications(
        Ticket $ticket,
        array $userIds,
        int $primaryUserId,
        ?int $assignedBy
    ): void {
        try {
            if (empty($userIds)) {
                return;
            }

            $ticket->loadMissing(['divisi', 'outlet']);
            $assignerName = $assignedBy
                ? (User::where('id', $assignedBy)->value('nama_lengkap') ?? 'System')
                : 'System (auto-assign)';
            $outletName = $ticket->outlet->nama_outlet ?? '-';
            $divisiName = $ticket->divisi->nama_divisi ?? '-';

            foreach ($userIds as $userId) {
                $isPrimary = (int) $userId === (int) $primaryUserId;
                $roleLabel = $isPrimary ? 'PIC Utama' : 'Team Support';

                $message = "Anda di-assign ke ticket:\n\n";
                $message .= "No: {$ticket->ticket_number}\n";
                $message .= "Judul: {$ticket->title}\n";
                $message .= "Peran: {$roleLabel}\n";
                $message .= "Divisi: {$divisiName}\n";
                $message .= "Outlet: {$outletName}\n";
                $message .= "Assigned by: {$assignerName}";

                NotificationService::insert([
                    'user_id' => $userId,
                    'task_id' => $ticket->id,
                    'type' => 'ticket_assigned',
                    'message' => $message,
                    'url' => config('app.url') . '/tickets/' . $ticket->id,
                    'is_read' => 0,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send auto-assign ticket notifications', [
                'ticket_id' => $ticket->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
