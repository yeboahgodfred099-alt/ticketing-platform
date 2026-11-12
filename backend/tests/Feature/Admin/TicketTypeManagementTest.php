<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Event;
use App\Models\Role;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TicketTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedAdmin(): Admin
    {
        $admin = Admin::factory()->create([
            'mfa_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'mfa_enabled_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_admin_can_create_ticket_type(): void
    {
        $admin = $this->createAuthenticatedAdmin();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->postJson("/api/v1/admin/events/{$event->id}/ticket-types", [
                'name' => 'Early Bird Tier 1',
                'description' => 'Limited discounted passes',
                'price_minor' => 8000, // GH₵ 80.00
                'quantity_total' => 150,
                'min_per_order' => 1,
                'max_per_order' => 6,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('ticket_type.name', 'Early Bird Tier 1')
            ->assertJsonPath('ticket_type.price_minor', 8000)
            ->assertJsonPath('ticket_type.available_quantity', 150);

        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $event->id,
            'name' => 'Early Bird Tier 1',
            'price_minor' => 8000,
        ]);
    }

    public function test_cannot_reduce_quantity_below_sold_plus_reserved(): void
    {
        $admin = $this->createAuthenticatedAdmin();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'quantity_total' => 100,
            'quantity_sold' => 40,
            'quantity_reserved' => 20, // total committed = 60
        ]);

        // Attempting to set quantity_total to 50 (below 60) MUST fail validation
        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->patchJson("/api/v1/admin/events/{$event->id}/ticket-types/{$ticketType->id}", [
                'quantity_total' => 50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity_total']);

        $this->assertEquals(100, $ticketType->fresh()->quantity_total);
    }
}

