<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Support\OmniWhatsappPhoneNormalizer;
use Illuminate\Console\Command;

class DiagnoseWhatsappInboxCommand extends Command
{
    protected $signature = 'omni:diagnose-whatsapp {phone : Nomor pelanggan (6013… / 013… / 136…)}';

    protected $description = 'Cek thread WA duplikat & jumlah pesan per conversation_id (debug inbox vs DB)';

    public function handle(): int
    {
        $normalized = OmniWhatsappPhoneNormalizer::normalize((string) $this->argument('phone'));
        if ($normalized === '') {
            $this->error('Nomor tidak valid.');

            return self::FAILURE;
        }

        $candidates = OmniWhatsappPhoneNormalizer::lookupCandidates((string) $this->argument('phone'), $normalized);

        $this->info("Nomor dinormalisasi: {$normalized}");
        $this->line('Lookup IDs: '.implode(', ', $candidates));
        $this->newLine();

        $conversations = OmniConversation::query()
            ->where('channel', 'whatsapp')
            ->where(function ($q) use ($candidates, $normalized) {
                $q->whereIn('external_contact_id', $candidates)
                    ->orWhere('external_contact_id', 'like', '%'.substr($normalized, -8));
            })
            ->orderByDesc('last_message_at')
            ->get();

        if ($conversations->isEmpty()) {
            $this->warn('Tidak ada omni_conversations untuk nomor ini.');

            return self::SUCCESS;
        }

        if ($conversations->count() > 1) {
            $this->warn("Ditemukan {$conversations->count()} thread — ini penyebab pesan ada di DB tapi tidak di chat yang dibuka.");
            $this->line('Jalankan: php artisan omni:merge-whatsapp-conversations');
            $this->newLine();
        }

        $rows = [];
        foreach ($conversations as $conversation) {
            $msgCount = OmniMessage::query()->where('conversation_id', $conversation->id)->count();
            $latest = OmniMessage::query()
                ->where('conversation_id', $conversation->id)
                ->orderByDesc('id')
                ->first();

            $rows[] = [
                $conversation->id,
                $conversation->external_contact_id,
                $conversation->phone_number_id,
                $msgCount,
                $latest?->id,
                mb_substr($latest?->body ?: '['.($latest?->message_type ?? '').']', 0, 40),
                $conversation->last_message_at?->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['conv_id', 'external_contact_id', 'phone_number_id', 'msg_count', 'last_msg_id', 'preview', 'last_message_at'],
            $rows
        );

        $this->newLine();
        $this->line('Buka inbox dengan URL: /crm/omnichannel-inbox?conversation=<conv_id> yang msg_count-nya paling besar.');

        return self::SUCCESS;
    }
}
