<?php

namespace App\Services\Omni;

use App\Models\OmniContact;
use App\Models\OmniConversation;
use RuntimeException;

class OmniContactProfileService
{
    /**
     * @param  array{marital_status?: ?string, preferred_outlet_id?: ?int, preferred_area?: ?string}  $data
     */
    public function updateForConversation(OmniConversation $conversation, array $data): OmniContact
    {
        if ($data === []) {
            $contact = $conversation->omniContact;
            if (! $contact) {
                throw new RuntimeException('Kontak belum terhubung ke percakapan ini.');
            }

            return $contact;
        }

        $contact = $conversation->omniContact;
        if (! $contact) {
            $phoneKey = $this->resolvePhoneNormalized($conversation);
            if ($phoneKey === null || $phoneKey === '') {
                throw new RuntimeException('Kontak belum terhubung. Tunggu pesan masuk atau sinkronisasi inbox.');
            }

            $contact = OmniContact::query()->firstOrCreate(
                ['phone_normalized' => $phoneKey],
                ['display_name' => $conversation->contact_name]
            );
            $conversation->omni_contact_id = $contact->id;
            $conversation->save();
        }

        if (array_key_exists('marital_status', $data)) {
            $contact->marital_status = $data['marital_status'] ?: null;
        }
        if (array_key_exists('preferred_outlet_id', $data)) {
            $contact->preferred_outlet_id = $data['preferred_outlet_id'];
        }
        if (array_key_exists('preferred_area', $data)) {
            $area = $data['preferred_area'];
            $contact->preferred_area = $area !== null && trim($area) !== '' ? trim($area) : null;
        }

        $contact->save();

        return $contact->fresh(['preferredOutlet']);
    }

    private function resolvePhoneNormalized(OmniConversation $conversation): ?string
    {
        $external = (string) $conversation->external_contact_id;
        if ($external === '') {
            return null;
        }

        return match ($conversation->channel) {
            'whatsapp' => preg_replace('/\D+/', '', $external) ?: $external,
            'instagram' => "instagram_{$external}",
            'messenger', 'facebook' => "{$conversation->channel}_{$external}",
            default => "omni_{$conversation->channel}_{$external}",
        };
    }
}
