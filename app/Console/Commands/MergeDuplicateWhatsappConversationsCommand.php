<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateWhatsappConversationsCommand extends Command
{
    protected $signature = 'omni:merge-whatsapp-conversations
                            {--dry-run : Tampilkan saja, tanpa mengubah data}';

    protected $description = 'Gabungkan thread WA duplikat (external_contact_id beda format, nomor sama)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $conversations = OmniConversation::query()
            ->where('channel', 'whatsapp')
            ->whereNotNull('phone_number_id')
            ->orderBy('phone_number_id')
            ->orderByDesc('last_message_at')
            ->get();

        $groups = [];
        foreach ($conversations as $conversation) {
            $key = $conversation->phone_number_id.'|'.$this->normalizePhone((string) $conversation->external_contact_id);
            if ($key === '|' || str_ends_with($key, '|')) {
                continue;
            }
            $groups[$key][] = $conversation;
        }

        $merged = 0;
        foreach ($groups as $key => $items) {
            if (count($items) < 2) {
                continue;
            }

            $canonical = $items[0];
            $canonicalPhone = $this->normalizePhone((string) $canonical->external_contact_id);
            $duplicates = array_slice($items, 1);

            $this->line("Grup {$key}: simpan #{$canonical->id}, gabung ".count($duplicates).' duplikat');

            if ($dryRun) {
                $merged += count($duplicates);

                continue;
            }

            DB::transaction(function () use ($canonical, $canonicalPhone, $duplicates, &$merged) {
                if ($canonical->external_contact_id !== $canonicalPhone) {
                    $canonical->external_contact_id = $canonicalPhone;
                    $canonical->save();
                }

                foreach ($duplicates as $dup) {
                    OmniMessage::query()
                        ->where('conversation_id', $dup->id)
                        ->update(['conversation_id' => $canonical->id]);

                    $dup->delete();
                    $merged++;
                }

                $latest = OmniMessage::query()
                    ->where('conversation_id', $canonical->id)
                    ->orderByDesc('sent_at')
                    ->first();

                if ($latest) {
                    $canonical->last_message_at = $latest->sent_at;
                    $canonical->last_message_preview = mb_substr($latest->body ?: '['.$latest->message_type.']', 0, 500);
                    if ($latest->direction === 'inbound') {
                        $canonical->last_customer_message_at = $latest->sent_at;
                    }
                }

                $canonical->unread_count = (int) OmniMessage::query()
                    ->where('conversation_id', $canonical->id)
                    ->where('direction', 'inbound')
                    ->where('status', 'received')
                    ->count();

                $canonical->save();
            });
        }

        if ($merged === 0) {
            $this->info('Tidak ada duplikat WA yang perlu digabung.');

            return self::SUCCESS;
        }

        $this->info($dryRun
            ? "Dry-run: {$merged} thread duplikat akan digabung. Jalankan tanpa --dry-run untuk menerapkan."
            : "Selesai: {$merged} thread duplikat digabung.");

        return self::SUCCESS;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
