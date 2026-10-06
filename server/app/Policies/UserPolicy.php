<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $this->canManageTarget($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->canManageTarget($actor, $target);
    }

    public function toggleActive(User $actor, User $target): bool
    {
        return $this->canManageTarget($actor, $target);
    }

    /**
     * Aturan pengelolaan akun:
     * - Hanya Admin (Utama/Sub) yang boleh mengelola.
     * - Akun Admin Utama terkunci dari UI (dikelola lewat halaman Profil
     *   masing-masing) — label keduanya memang sama di UI.
     * - Akun sendiri tidak dikelola dari halaman Pengguna.
     * - Sub Admin hanya boleh mengelola akun Viewer.
     */
    private function canManageTarget(User $actor, User $target): bool
    {
        if (! $actor->isAdmin() || $target->isAdminUtama() || $actor->is($target)) {
            return false;
        }

        return $actor->isAdminUtama() || $target->isViewer();
    }
}
