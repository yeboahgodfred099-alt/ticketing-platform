<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(Admin $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(Admin $user): bool
    {
        return $user->hasPermission('admins.view');
    }

    public function view(Admin $user, Admin $target): bool
    {
        return $user->id === $target->id || $user->hasPermission('admins.view');
    }

    public function create(Admin $user): bool
    {
        return $user->hasPermission('admins.create');
    }

    public function update(Admin $user, Admin $target): bool
    {
        return $user->id === $target->id || $user->hasPermission('admins.update');
    }

    public function disable(Admin $user, Admin $target): bool
    {
        // An admin cannot disable themselves
        if ($user->id === $target->id) {
            return false;
        }

        // Cannot disable if they are the only active Super Admin
        if ($target->hasRole('Super Admin')) {
            $activeSuperAdmins = Admin::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
                ->count();

            if ($activeSuperAdmins <= 1) {
                return false;
            }
        }

        return $user->hasPermission('admins.disable');
    }
}

