<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@clevawave.com',
            'password' => Hash::make('Password123!Secure'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@clevawave.com',
            'password' => 'Password123!Secure',
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($admin, 'web');

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'admin.login',
        ]);
    }

    public function test_login_fails_with_invalid_credentials_generically(): void
    {
        Admin::factory()->create([
            'email' => 'admin@clevawave.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@clevawave.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials.',
                'code' => 'INVALID_CREDENTIALS',
            ]);

        $this->assertGuest('web');
    }

    public function test_inactive_admin_cannot_login(): void
    {
        Admin::factory()->create([
            'email' => 'inactive@clevawave.com',
            'password' => Hash::make('Password123!Secure'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@clevawave.com',
            'password' => 'Password123!Secure',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'ACCOUNT_INACTIVE',
            ]);

        $this->assertGuest('web');
    }

    public function test_super_admin_login_prompts_mfa_setup_when_unconfigured(): void
    {
        $superAdmin = Admin::factory()->create([
            'email' => 'super@clevawave.com',
            'password' => Hash::make('Password123!Secure'),
            'mfa_secret' => null,
        ]);

        $role = Role::create(['name' => 'Super Admin']);
        $superAdmin->roles()->attach($role->id);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'super@clevawave.com',
            'password' => 'Password123!Secure',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'requires_mfa' => true,
                'mfa_setup_required' => true,
            ]);
    }

    public function test_login_rate_limiting_after_excessive_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'victim@clevawave.com',
                'password' => 'BadPassword',
            ]);
        }

        // 6th attempt must be rate limited
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@clevawave.com',
            'password' => 'BadPassword',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'code' => 'RATE_LIMITED',
            ]);
    }
}

