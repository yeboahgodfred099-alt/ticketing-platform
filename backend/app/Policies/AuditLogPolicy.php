<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

class AuditLogPolicy
{
    public function viewAny(Admin $user): bool
    {
        return $user->hasRole('Super Admin') || $user->hasPermission('audit_logs.view');
    }

    public function create(Admin $user): bool
    {
        return false; // Only system services create audit logs
    }

    public function update(Admin $user): bool
    {
        return false; // Audit logs are immutable
    }

    public function delete(Admin $user): bool
    {
        return false; // Audit logs cannot be deleted
    }
}
