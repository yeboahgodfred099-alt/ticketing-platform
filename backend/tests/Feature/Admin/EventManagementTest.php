<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Models\Admin;
use App\Models\Event;
use App\Models\Role;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EventManagementTest extends TestCase
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

    public function test_admin_can_create_event_as_draft(): void
    {
        $admin = $this->createAuthenticatedAdmin();

        $startsAt = now()->addDays(20);
        $endsAt = (clone $startsAt)->addHours(6);

        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->postJson('/api/v1/admin/events', [
                'name' => 'Afro Nation Ghana 2026',
                'venue_name' => 'Black Star Square',
                'venue_address' => 'Independence Ave, Accra',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('event.name', 'Afro Nation Ghana 2026')
            ->assertJsonPath('event.slug', 'afro-nation-ghana-2026')
            ->assertJsonPath('event.status', 'draft');

        $this->assertDatabaseHas('events', [
            'name' => 'Afro Nation Ghana 2026',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
    }

    public function test_publishing_event_requires_active_ticket_category(): void
    {
        $admin = $this->createAuthenticatedAdmin();
        $event = Event::factory()->draft()->create(['created_by' => $admin->id]);

        // Attempt publish with 0 ticket categories
        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->postJson("/api/v1/admin/events/{$event->id}/publish");

        // Should be rejected by Service validation
        $response->assertStatus(500); // InvalidArgumentException thrown
        $this->assertEquals(EventStatus::DRAFT, $event->fresh()->status);

        // Add ticket category and publish again
        TicketType::factory()->create(['event_id' => $event->id, 'status' => 'active']);

        $successResponse = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->postJson("/api/v1/admin/events/{$event->id}/publish");

        $successResponse->assertStatus(200)
            ->assertJsonPath('event.status', 'published');

        $this->assertEquals(EventStatus::PUBLISHED, $event->fresh()->status);
    }

    public function test_admin_can_cancel_event(): void
    {
        $admin = $this->createAuthenticatedAdmin();
        $event = Event::factory()->create(['created_by' => $admin->id, 'status' => 'published']);

        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->postJson("/api/v1/admin/events/{$event->id}/cancel", [
                'reason' => 'Severe weather forecast',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('event.status', 'cancelled');

        $this->assertEquals(EventStatus::CANCELLED, $event->fresh()->status);
    }
}

