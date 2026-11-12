<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Role;
use App\Services\Admin\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super';

    protected $description = 'Interactively create the initial Super Administrator with secure password validation and forced MFA';

    public function handle(AuditService $auditService): int
    {
        $this->info('====================================================');
        $this->info('  ClevaWave Ticketing — Super Admin Bootstrap');
        $this->info('====================================================');

        $name = (string) $this->ask('Enter Administrator Full Name');
        $email = strtolower(trim((string) $this->ask('Enter Administrator Email Address')));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $password = (string) $this->secret('Enter Strong Password (min 12 chars, mixed case, numbers, symbols)');
        $passwordConfirm = (string) $this->secret('Confirm Password');

        if ($password !== $passwordConfirm) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }

        $passwordValidator = Validator::make(
            ['password' => $password],
            [
                'password' => [
                    'required',
                    'string',
                    Password::min(12)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ],
            ]
        );

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            ['id' => (string) Str::uuid(), 'description' => 'Full administrative privileges']
        );

        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'email_verified_at' => now(),
            'mfa_secret' => null, // Forces MFA setup on first login
            'mfa_enabled_at' => null,
        ]);

        $admin->roles()->attach($superAdminRole->id, ['assigned_at' => now()]);

        $auditService->log(
            action: 'admin.super_admin_bootstrapped',
            target: $admin,
            metadata: ['email' => $email, 'bootstrapped_via' => 'cli']
        );

        $this->newLine();
        $this->info("✓ Super Administrator [{$email}] created successfully!");
        $this->comment('Note: MFA setup will be strictly enforced upon first web login.');

        return self::SUCCESS;
    }
}
