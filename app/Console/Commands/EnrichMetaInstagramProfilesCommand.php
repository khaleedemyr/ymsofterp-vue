<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Services\Meta\MetaInstagramProfileService;
use Illuminate\Console\Command;

class EnrichMetaInstagramProfilesCommand extends Command
{
    protected $signature = 'meta:enrich-instagram-profiles {--limit=100 : Maks percakapan diproses}';

    protected $description = 'Ambil nama & avatar Instagram untuk percakapan yang belum lengkap';

    public function handle(MetaInstagramProfileService $profiles): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $conversations = OmniConversation::query()
            ->with('omniContact')
            ->where('channel', 'instagram')
            ->where(function ($q) {
                $q->whereNull('contact_name')
                    ->orWhere('contact_name', '')
                    ->orWhereHas('omniContact', fn ($c) => $c->whereNull('avatar_url')->orWhere('avatar_url', ''));
            })
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get();

        $updated = 0;

        foreach ($conversations as $conversation) {
            $contact = $conversation->omniContact;
            if (! $contact) {
                continue;
            }

            $igsid = (string) $conversation->external_contact_id;
            $igAccount = (string) $conversation->phone_number_id;
            if ($igsid === '' || $igAccount === '') {
                continue;
            }

            $profiles->enrichContactAndConversation($contact, $conversation, $igsid, $igAccount);
            $updated++;
            $this->line("OK #{$conversation->id} → ".($conversation->fresh()->contact_name ?: '(tanpa nama)'));
        }

        $this->info("Selesai: {$updated} percakapan diproses.");

        return self::SUCCESS;
    }
}
