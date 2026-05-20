<?php

namespace App\Support;

final class OmniLeadStages
{
    /** @return list<array{value: string, label: string, color: string}> */
    public static function all(): array
    {
        return [
            ['value' => 'new_lead', 'label' => 'New lead', 'color' => 'red'],
            ['value' => 'contacted', 'label' => 'Contacted', 'color' => 'orange'],
            ['value' => 'qualified', 'label' => 'Qualified', 'color' => 'yellow'],
            ['value' => 'pending_payment', 'label' => 'Pending payment', 'color' => 'blue'],
            ['value' => 'customer', 'label' => 'Customer', 'color' => 'green'],
            ['value' => 'lost', 'label' => 'Lost', 'color' => 'purple'],
            ['value' => 'no_reply', 'label' => 'No reply', 'color' => 'slate'],
        ];
    }

    public static function values(): array
    {
        return array_column(self::all(), 'value');
    }

    public static function isValid(string $stage): bool
    {
        return in_array($stage, self::values(), true);
    }
}
