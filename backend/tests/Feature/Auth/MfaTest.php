<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_initiate_mfa_setup(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'web')
            ->postJson('/api/v1/auth/mfa/setup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'secret',
                'qr_code_url',
                'message',
            ]);

        $this->assertNotEmpty($response->json('secret'));
    }

    public function test_admin_can_confirm_mfa_setup_with_valid_totp(): void
    {
        $admin = Admin::factory()->create();
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(32);
        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($admin, 'web')
            ->withSession(['mfa.pending_secret' => $secret])
            ->postJson('/api/v1/auth/mfa/confirm', [
                'code' => $validCode,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'recovery_codes',
                'admin',
            ]);

        $this->assertCount(8, $response->json('recovery_codes'));

        $admin->refresh();
        $this->assertTrue($admin->hasMfaEnabled());
        $this->assertNotNull($admin->mfa_enabled_at);
    }

    public function test_mfa_verification_with_recovery_code_consumes_code(): void
    {
        $admin = Admin::factory()->create([
            'mfa_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => ['RECOV-1111', 'RECOV-2222'],
        ]);

        $response = $this->actingAs($admin, 'web')
            ->postJson('/api/v1/auth/mfa/verify', [
                'code' => 'RECOV-1111',
            ]);

        $response->assertStatus(200);

        $admin->refresh();
        // RECOV-1111 must be consumed, only RECOV-2222 remains
        $this->assertEquals(['RECOV-2222'], $admin->mfa_recovery_codes);
    }
}

