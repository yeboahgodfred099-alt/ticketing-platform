<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class PublicEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeTicketTypes = $this->relationLoaded('ticketTypes')
            ? $this->ticketTypes->where('status.value', 'active')
            : collect();

        $lowestPrice = $activeTicketTypes->isNotEmpty()
            ? $activeTicketTypes->min('price_minor')
            : 0;

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
            'currency' => 'GHS',
            'lowest_price_minor' => $lowestPrice,
            'lowest_price_formatted' => number_format($lowestPrice / 100, 2, '.', ''),
            'is_purchasable' => $this->status->isPurchasable(),
            'is_sold_out' => $activeTicketTypes->isNotEmpty() && $activeTicketTypes->sum('available_quantity') === 0,
            'ticket_types' => TicketTypeResource::collection($activeTicketTypes),
        ];
    }
}

