<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class UserRoleOptions
{
    /**
     * Peran yang boleh dipilih aktor di form pengguna.
     * Admin Utama: "Admin" (Sub Admin) + "Viewer".
     * Sub Admin: hanya "Viewer".
     *
     * @return list<UserRole>
     */
    public static function assignableBy(User $actor): array
    {
        return $actor->isAdminUtama()
            ? [UserRole::SubAdmin, UserRole::Viewer]
            : [UserRole::Viewer];
    }

    /**
     * @return list<string>
     */
    public static function valuesFor(User $actor): array
    {
        return array_map(
            static fn (UserRole $role): string => $role->value,
            self::assignableBy($actor),
        );
    }
}
