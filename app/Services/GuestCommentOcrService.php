<?php

namespace App\Services;

/**
 * Stub OCR: ganti implementasi dengan provider OCR sungguhan bila siap.
 */
class GuestCommentOcrService
{
    /**
     * @return array{raw_text: string, fields: array<string, mixed>}
     */
    public function extract(string $absolutePath): array
    {
        return [
            'raw_text' => '',
            'fields' => [
                'rating_service' => null,
                'rating_food' => null,
                'rating_beverage' => null,
                'rating_cleanliness' => null,
                'rating_staff' => null,
                'rating_value' => null,
                'comment_text' => null,
                'guest_name' => null,
                'guest_address' => null,
                'guest_phone' => null,
                'guest_dob' => null,
                'visit_date' => null,
                'praised_staff_name' => null,
                'praised_staff_outlet' => null,
            ],
        ];
    }
}
