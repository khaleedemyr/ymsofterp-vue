<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\TicketTeamAutoAssignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTicketTeamAssignments extends Command
{
    protected $signature = 'tickets:backfill-team-assignments
                            {--dry-run : Preview tickets that would be assigned}
                            {--notify : Send assignment notifications (default: off for backfill)}
                            {--ticket= : Only process a specific ticket ID}
                            {--limit= : Max tickets to process}';

    protected $description = 'Backfill assigned team on old tickets using ticketing team settings';

    public function handle(TicketTeamAutoAssignService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sendNotifications = (bool) $this->option('notify');
        $ticketId = $this->option('ticket');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = Ticket::query()
            ->with(['outlet:id_outlet,region_id', 'category:id,name'])
            ->whereDoesntHave('assignments')
            ->orderBy('id');

        if ($ticketId) {
            $query->where('id', (int) $ticketId);
        }

        if ($limit && $limit > 0) {
            $query->limit($limit);
        }

        $tickets = $query->get();

        if ($tickets->isEmpty()) {
            $this->info('No unassigned tickets found.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$tickets->count()} unassigned ticket(s).");

        $stats = [
            'assigned' => 0,
            'no_match' => 0,
            'no_users' => 0,
            'already_assigned' => 0,
            'failed' => 0,
        ];

        $previewRows = [];

        foreach ($tickets as $ticket) {
            if ($dryRun) {
                $setting = $service->resolveForTicket($ticket);
                $matchedLabel = 'NO MATCH';
                $resultKey = 'no_match';

                if ($setting) {
                    $setting->loadMissing('users');
                    if ($setting->users->isEmpty()) {
                        $matchedLabel = ($setting->name ?: ('Setting #' . $setting->id)) . ' (no users)';
                        $resultKey = 'no_users';
                    } else {
                        $matchedLabel = $setting->name ?: ('Setting #' . $setting->id);
                        $resultKey = 'assigned';
                    }
                }

                $previewRows[] = [
                    $ticket->id,
                    $ticket->ticket_number,
                    $ticket->category?->name ?? '-',
                    $ticket->outlet?->nama_outlet ?? '-',
                    $matchedLabel,
                ];
                $stats[$resultKey]++;

                continue;
            }

            try {
                DB::beginTransaction();

                $result = $service->backfillUnassignedTicket(
                    $ticket,
                    $ticket->created_by ? (int) $ticket->created_by : null,
                    $sendNotifications
                );
                $stats[$result] = ($stats[$result] ?? 0) + 1;

                DB::commit();

                if ($result === 'assigned') {
                    $this->line("Assigned: {$ticket->ticket_number} (#{$ticket->id})");
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $stats['failed']++;
                $this->error("Failed {$ticket->ticket_number} (#{$ticket->id}): {$e->getMessage()}");
            }
        }

        if ($dryRun && $previewRows !== []) {
            $this->table(
                ['ID', 'Ticket', 'Category', 'Outlet', 'Matched Setting'],
                $previewRows
            );
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line('  Assigned: ' . $stats['assigned']);
        $this->line('  No team setting match: ' . $stats['no_match']);
        $this->line('  Setting has no users: ' . $stats['no_users']);
        $this->line('  Already assigned (skipped): ' . $stats['already_assigned']);
        $this->line('  Failed: ' . $stats['failed']);

        if ($dryRun) {
            $this->warn('Dry run only. Re-run without --dry-run to apply assignments.');
        } elseif (! $sendNotifications) {
            $this->comment('Notifications were not sent. Use --notify if you want to notify assigned users.');
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
