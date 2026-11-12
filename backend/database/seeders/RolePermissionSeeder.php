<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Events
            'events.view' => 'View event catalog and details',
            'events.create' => 'Create new events',
            'events.update' => 'Edit event details',
            'events.publish' => 'Publish or unpublish events',
            'events.cancel' => 'Cancel events',
            'events.archive' => 'Archive events',

            // Ticket Types
            'ticket_types.view' => 'View ticket categories',
            'ticket_types.create' => 'Create ticket types',
            'ticket_types.update' => 'Modify ticket types and quantities',
            'ticket_types.archive' => 'Archive ticket types',

            // Orders
            'orders.view' => 'View customer orders',
            'orders.export' => 'Export order records',

            // Payments
            'payments.view' => 'View payment records and attempts',
            'payments.reconcile' => 'Execute reconciliation workflows',

            // Attendees & Tickets
            'attendees.view' => 'View attendee lists and ticket states',
            'attendees.export' => 'Export attendee data with data minimization',

            // Reports
            'reports.view' => 'Access operational and revenue reports',
            'reports.export' => 'Export financial and attendee reports',

            // Administrator Management
            'admins.view' => 'View administrator accounts',
            'admins.create' => 'Create administrator accounts',
            'admins.update' => 'Update administrator accounts',
            'admins.disable' => 'Disable or re-enable admin accounts',

            // Role Management
            'roles.manage' => 'Manage roles and permission assignments',

            // Audit Logs
            'audit_logs.view' => 'Inspect administrative audit trails',

            // Gate Check-in
            'check_in.scan' => 'Scan QR tickets and perform check-ins',

            // Settings
            'settings.view' => 'View operational settings',
            'settings.manage' => 'Update operational settings',
        ];

        $permissionModels = [];
        foreach ($permissions as $name => $description) {
            $permissionModels[$name] = Permission::firstOrCreate(
                ['name' => $name],
                ['id' => (string) Str::uuid(), 'description' => $description]
            );
        }

        // Roles definition
        $roles = [
            'Super Admin' => [
                'description' => 'Full administrative privileges across all modules and settings',
                'permissions' => array_keys($permissions),
            ],
            'Event Manager' => [
                'description' => 'Manage events, ticket types, orders, and attendees',
                'permissions' => [
                    'events.view', 'events.create', 'events.update', 'events.publish', 'events.cancel',
                    'ticket_types.view', 'ticket_types.create', 'ticket_types.update',
                    'orders.view', 'orders.export',
                    'attendees.view', 'attendees.export',
                    'reports.view',
                ],
            ],
            'Finance Manager' => [
                'description' => 'View payments, reconciliation, financial reports and audit trails',
                'permissions' => [
                    'orders.view', 'orders.export',
                    'payments.view', 'payments.reconcile',
                    'reports.view', 'reports.export',
                    'audit_logs.view',
                ],
            ],
            'Gate Staff' => [
                'description' => 'Scan tickets and perform gate check-ins for assigned events',
                'permissions' => [
                    'events.view',
                    'check_in.scan',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['id' => (string) Str::uuid(), 'description' => $roleData['description']]
            );

            $rolePermissionIds = collect($roleData['permissions'])
                ->map(fn (string $permName) => $permissionModels[$permName]->id)
                ->all();

            $role->permissions()->sync($rolePermissionIds);
        }
    }
}

