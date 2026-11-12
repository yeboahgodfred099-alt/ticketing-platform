<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'banner_url' => $this->banner_path ? url('storage/' . $this->banner_path) : null,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'sales_starts_at' => $this->sales_starts_at?->toIso8601String(),
            'sales_ends_at' => $this->sales_ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'status' => $this->status->value,
            'is_purchasable' => $this->status->isPurchasable(),
            'created_by' => $this->created_by,
            'creator' => new AdminResource($this->whenLoaded('creator')),
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            'total_capacity' => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes->sum('quantity_total')),
            'total_sold' => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes->sum('quantity_sold')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

