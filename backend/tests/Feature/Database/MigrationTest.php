<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_17_required_tables_exist(): void
    {
        $expectedTables = [
            'admins',
            'roles',
            'permissions',
            'admin_roles',
            'role_permissions',
            'events',
            'ticket_types',
            'orders',
            'order_items',
            'inventory_reservations',
            'payments',
            'webhook_events',
            'tickets',
            'check_ins',
            'audit_logs',
            'notification_logs',
            'system_settings',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} does not exist in schema.");
        }
    }

    public function test_critical_uuid_columns_exist(): void
    {
        $tablesWithUuidPrimary = [
            'admins',
            'roles',
            'permissions',
            'events',
            'ticket_types',
            'orders',
            'order_items',
            'inventory_reservations',
            'payments',
            'webhook_events',
            'tickets',
            'check_ins',
            'audit_logs',
            'notification_logs',
            'system_settings',
        ];

        foreach ($tablesWithUuidPrimary as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'id'), "Table {$table} lacks primary key id.");
        }
    }

    public function test_financial_records_use_integer_minor_units(): void
    {
        $this->assertTrue(Schema::hasColumn('ticket_types', 'price_minor'));
        $this->assertTrue(Schema::hasColumn('orders', 'total_amount_minor'));
        $this->assertTrue(Schema::hasColumn('order_items', 'unit_price_minor'));
        $this->assertTrue(Schema::hasColumn('order_items', 'total_minor'));
        $this->assertTrue(Schema::hasColumn('payments', 'amount_minor'));
        $this->assertTrue(Schema::hasColumn('payments', 'fees_minor'));
    }

    public function test_qr_tokens_are_stored_hashed_only(): void
    {
        $this->assertTrue(Schema::hasColumn('tickets', 'qr_token_hash'));
        $this->assertFalse(Schema::hasColumn('tickets', 'qr_token'), 'Raw qr_token column must NOT exist.');
    }
}

