<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

class RolePolicy
{
    public function before(Admin $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(Admin $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function manage(Admin $user): bool
    {
        return $user->hasPermission('roles.manage');
    }
}

