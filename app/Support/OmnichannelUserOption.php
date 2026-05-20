<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class OmnichannelUserOption
{
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
