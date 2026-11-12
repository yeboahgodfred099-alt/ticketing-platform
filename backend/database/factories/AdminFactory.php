<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('Password123!Secure'),
            'is_active' => true,
            'email_verified_at' => now(),
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
            'mfa_recovery_codes' => null,
            'last_login_at' => null,
        ];
    }

    public function mfaEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'mfa_secret' => 'SECRET2FAKEYSAMPLE',
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => ['CODE1-1111', 'CODE2-2222'],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

