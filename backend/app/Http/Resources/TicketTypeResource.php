<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketType
 */
class TicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'description' => $this->description,
            'price_minor' => $this->price_minor,
            'price_major' => number_format($this->price_minor / 100, 2, '.', ''),
            'currency' => $this->currency,
            'quantity_total' => $this->quantity_total,
            'quantity_sold' => $this->quantity_sold,
            'quantity_reserved' => $this->quantity_reserved,
            'available_quantity' => $this->available_quantity,
            'min_per_order' => $this->min_per_order,
            'max_per_order' => $this->max_per_order,
            'sales_starts_at' => $this->sales_starts_at?->toIso8601String(),
            'sales_ends_at' => $this->sales_ends_at?->toIso8601String(),
            'is_within_sale_window' => $this->isWithinSaleWindow(),
            'is_sold_out' => $this->available_quantity === 0,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

