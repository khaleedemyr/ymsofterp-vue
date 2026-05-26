<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class OmnichannelUserOption
{
    /**
     * User yang boleh dipilih di inbox / flow / tim omnichannel (hanya aktif).
     *
     * @return Builder<User>
     */
    public static function assignableQuery(): Builder
    {
        return User::query()
            ->active()
            ->with(['jabatan', 'outlet'])
            ->orderBy('nama_lengkap');
    }

    /**
     * @return list<array{id: int, name: string, jabatan: ?string, outlet: ?string}>
     */
    public static function assignableOptions(): array
    {
        return self::toOptions(self::assignableQuery()->get());
    }

    /**
     * Validasi array ID user untuk penugasan omnichannel.
     *
     * @return array<string, list<mixed>>
     */
    public static function assignableUserIdRules(string $key = 'user_ids'): array
    {
        return [
            $key => ['nullable', 'array'],
            $key.'.*' => [
                'integer',
                Rule::exists('users', 'id')->where('status', 'A'),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, jabatan: ?string, outlet: ?string}
     */
    public static function toArray(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->nama_lengkap ?? $user->email ?? '',
            'jabatan' => $user->jabatan?->nama_jabatan,
            'outlet' => $user->outlet?->nama_outlet,
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<array{id: int, name: string, jabatan: ?string, outlet: ?string}>
     */
    public static function toOptions(Collection $users): array
    {
        return $users->map(fn (User $u) => self::toArray($u))->values()->all();
    }
}
