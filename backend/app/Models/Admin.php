<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $table = 'admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'email_verified_at',
        'mfa_secret',
        'mfa_enabled_at',
        'mfa_recovery_codes',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'mfa_enabled_at' => 'datetime',
        'mfa_recovery_codes' => 'array',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Roles assigned to this admin.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_roles', 'admin_id', 'role_id')
            ->withPivot('assigned_at');
    }

    /**
     * Events created by this admin.
     */
    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    /**
     * Check-ins performed by this staff member.
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'admin_id');
    }

    /**
     * Audit logs authored by this admin.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'admin_id');
    }

    /**
     * Determine if admin has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    /**
     * Determine if admin has a specific permission (either directly or via role).
     */
    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('name', $permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if MFA is enabled for this admin.
     */
    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null && !empty($this->mfa_secret);
    }
}

