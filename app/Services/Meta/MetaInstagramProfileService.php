<?php

namespace App\Services\Meta;

use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Support\MetaInstagramTokens;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ambil nama & foto profil pengirim DM (Instagram Scoped ID).
 */
class MetaInstagramProfileService
{
    /**
     * @return array{name: ?string, username: ?string, profile_pic: ?string}
     */
    public function fetch(string $igsid, string $igProfessionalId): array
    {
        $tokens = MetaInstagramTokens::resolved();
        $token = $tokens[$igProfessionalId] ?? config('services.meta.instagram_login_access_token');

        if (! $token) {
            return ['name' => null, 'username' => null, 'profile_pic' => null];
        }

        $version = config('services.meta.instagram_graph_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/{$igsid}", [
                'fields' => 'name,username,profile_pic',
            ]);

        if (! $response->successful()) {
            Log::debug('Instagram profile fetch failed', [
                'igsid' => $igsid,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return ['name' => null, 'username' => null, 'profile_pic' => null];
        }

        $data = $response->json();

        return [
            'name' => isset($data['name']) && is_string($data['name']) ? $data['name'] : null,
            'username' => isset($data['username']) && is_string($data['username']) ? $data['username'] : null,
            'profile_pic' => isset($data['profile_pic']) && is_string($data['profile_pic']) ? $data['profile_pic'] : null,
        ];
    }

    public function enrichContactAndConversation(
        OmniContact $contact,
        OmniConversation $conversation,
        string $senderIgsid,
        string $igProfessionalId,
        ?string $fallbackUsername = null
    ): void {
        $profile = $this->fetch($senderIgsid, $igProfessionalId);

        $displayName = $profile['name']
            ?: ($profile['username'] ? '@'.$profile['username'] : null)
            ?: ($fallbackUsername ? '@'.$fallbackUsername : null);

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
}
