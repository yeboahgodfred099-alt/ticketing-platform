<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedSuperAdmin(): Admin
    {
        $superAdmin = Admin::factory()->create([
            'mfa_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'mfa_enabled_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->roles()->attach($role->id);

        return $superAdmin;
    }

    public function test_super_admin_can_create_new_administrator(): void
    {
        $superAdmin = $this->createAuthenticatedSuperAdmin();
        Role::firstOrCreate(['name' => 'Event Manager']);

        $response = $this->actingAs($superAdmin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->postJson('/api/v1/admin/users', [
                'name' => 'Kofi Mensah',
                'email' => 'kofi@clevawave.com',
                'password' => 'Str0ng!Pass#2026',
                'roles' => ['Event Manager'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('admin.email', 'kofi@clevawave.com')
            ->assertJsonPath('admin.name', 'Kofi Mensah');

        $this->assertDatabaseHas('admins', [
            'email' => 'kofi@clevawave.com',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.created',
        ]);
    }

    public function test_cannot_create_admin_with_weak_password(): void
    {
        $superAdmin = $this->createAuthenticatedSuperAdmin();
        Role::firstOrCreate(['name' => 'Event Manager']);

        $response = $this->actingAs($superAdmin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->postJson('/api/v1/admin/users', [
                'name' => 'Kofi Mensah',
                'email' => 'kofi@clevawave.com',
                'password' => 'weak', // Violates password requirements
                'roles' => ['Event Manager'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(401);
    }

    public function test_cannot_deactivate_only_active_super_admin(): void
    {
        $superAdmin = $this->createAuthenticatedSuperAdmin();

        $response = $this->actingAs($superAdmin, 'web')
            ->withSession([
                'auth.mfa_verified' => true,
                'auth.password_confirmed_at' => time(),
            ])
            ->patchJson("/api/v1/admin/users/{$superAdmin->id}/toggle-active");

        $response->assertStatus(403);
        $this->assertTrue($superAdmin->fresh()->is_active);
    }
}
