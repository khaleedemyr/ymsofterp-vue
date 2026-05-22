<?php

namespace App\Services\Meta;

use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Support\MetaPageTokens;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ambil nama & foto profil pengirim DM Messenger (Page-scoped ID / PSID).
 */
class MetaMessengerProfileService
{
    /**
     * @return array{name: ?string, profile_pic: ?string}
     */
    public function fetch(string $psid, string $pageId): array
    {
        $token = $this->resolvePageToken($pageId);
        if ($token === null) {
            return ['name' => null, 'profile_pic' => null];
        }

        $version = config('services.meta.graph_api_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$psid}", [
                'fields' => 'first_name,last_name,name,profile_pic',
            ]);

        if (! $response->successful()) {
            Log::debug('Messenger profile fetch failed', [
                'psid' => $psid,
                'page_id' => $pageId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return ['name' => null, 'profile_pic' => null];
        }

        $data = $response->json();
        $name = null;
        if (isset($data['name']) && is_string($data['name']) && $data['name'] !== '') {
            $name = $data['name'];
        } else {
            $first = (string) ($data['first_name'] ?? '');
            $last = (string) ($data['last_name'] ?? '');
            $combined = trim($first.' '.$last);
            $name = $combined !== '' ? $combined : null;
        }

        $pic = isset($data['profile_pic']) && is_string($data['profile_pic']) && $data['profile_pic'] !== ''
            ? $data['profile_pic']
            : null;

        return ['name' => $name, 'profile_pic' => $pic];
    }

    public function enrichContactAndConversation(
        OmniContact $contact,
        OmniConversation $conversation,
        string $senderPsid,
        string $pageId,
        ?string $fallbackName = null
    ): void {
        if ($senderPsid === '' || $pageId === '') {
            return;
        }

        $profile = $this->fetch($senderPsid, $pageId);

        $displayName = $profile['name'] ?: $fallbackName;
        if ($displayName) {
            $contact->display_name = $displayName;
            $conversation->contact_name = $displayName;
        }

        if ($profile['profile_pic']) {
            $contact->avatar_url = $profile['profile_pic'];
        }

        $contact->save();
        $conversation->save();
    }

    private function resolvePageToken(string $pageId): ?string
    {
        $tokens = MetaPageTokens::resolved();
        if (isset($tokens[$pageId])) {
            return $tokens[$pageId];
        }

        $defaultId = (string) config('services.meta.page_id', '');
        if ($defaultId === $pageId) {
            $token = config('services.meta.page_access_token');

            return is_string($token) && $token !== '' ? $token : null;
        }

        return $tokens !== [] ? reset($tokens) : null;
    }
}
