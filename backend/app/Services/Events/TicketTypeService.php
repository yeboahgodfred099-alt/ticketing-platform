<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Enums\TicketTypeStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\Admin\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TicketTypeService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Create a new ticket category for an event.
     *
     * @param array<string, mixed> $data
     */
    public function createTicketType(Event $event, array $data): TicketType
    {
        $ticketType = TicketType::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_minor' => $data['price_minor'],
            'currency' => $data['currency'] ?? 'GHS',
            'quantity_total' => $data['quantity_total'],
            'quantity_sold' => 0,
            'quantity_reserved' => 0,
            'min_per_order' => $data['min_per_order'] ?? 1,
            'max_per_order' => $data['max_per_order'] ?? null,
            'sales_starts_at' => $data['sales_starts_at'] ?? null,
            'sales_ends_at' => $data['sales_ends_at'] ?? null,
            'status' => TicketTypeStatus::ACTIVE,
        ]);

        $this->auditService->log('ticket_type.created', $ticketType, [
            'event_id' => $event->id,
            'name' => $ticketType->name,
            'price_minor' => $ticketType->price_minor,
            'quantity_total' => $ticketType->quantity_total,
        ]);

        return $ticketType;
    }

    /**
     * Update an existing ticket category with strict inventory reduction protection.
     *
     * @param array<string, mixed> $data
     */
    public function updateTicketType(TicketType $ticketType, array $data): TicketType
    {
        return DB::transaction(function () use ($ticketType, $data) {
            // Lock row for update
            /** @var TicketType $locked */
            $locked = TicketType::where('id', $ticketType->id)->lockForUpdate()->firstOrFail();

            if (isset($data['quantity_total'])) {
                $committedStock = $locked->quantity_sold + $locked->quantity_reserved;
                if ($data['quantity_total'] < $committedStock) {
                    throw new InvalidArgumentException(
                        "Cannot reduce total quantity below {$committedStock} (already sold or actively reserved)."
                    );
                }
            }

            $locked->update($data);

            $this->auditService->log('ticket_type.updated', $locked, [
                'updated_fields' => array_keys($data),
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Archive ticket type.
     */
    public function archiveTicketType(TicketType $ticketType): TicketType
    {
        $ticketType->update(['status' => TicketTypeStatus::ARCHIVED]);

        $this->auditService->log('ticket_type.archived', $ticketType);

        return $ticketType;
    }
}

