<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Services\Meta\MetaMessengerProfileService;
use App\Support\MetaPageAccountRegistry;
use Illuminate\Console\Command;

class EnrichMetaMessengerProfilesCommand extends Command
{
    protected $signature = 'meta:enrich-messenger-profiles {--limit=100 : Maks percakapan diproses}';

    protected $description = 'Ambil nama & avatar Facebook untuk percakapan Messenger yang belum lengkap';

    public function handle(MetaMessengerProfileService $profiles): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $conversations = OmniConversation::query()
            ->with('omniContact')
            ->whereIn('channel', ['messenger', 'facebook'])
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

            $psid = (string) $conversation->external_contact_id;
            $pageId = (string) $conversation->phone_number_id;
            if ($psid === '' || $pageId === '') {
                continue;
            }

            $fallback = (string) ($contact->display_name ?: $conversation->contact_name ?: '');
            $profiles->enrichContactAndConversation(
                $contact,
                $conversation,
                $psid,
                $pageId,
                $fallback !== '' ? $fallback : null
            );
            $updated++;
            $fresh = $conversation->fresh();
            $this->line('OK #'.$conversation->id.' → '.($fresh->contact_name ?: '(tanpa nama)').' ['.MetaPageAccountRegistry::displayLabel($pageId).']');
        }

        $this->info("Selesai: {$updated} percakapan diproses.");

        return self::SUCCESS;
    }
}
