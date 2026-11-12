<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_type_id' => $this->ticket_type_id,
            'ticket_name' => $this->ticket_name_snapshot,
            'quantity' => $this->quantity,
            'unit_price_minor' => $this->unit_price_minor,
            'unit_price_major' => number_format($this->unit_price_minor / 100, 2, '.', ''),
            'total_minor' => $this->total_minor,
            'total_major' => number_format($this->total_minor / 100, 2, '.', ''),
            'currency' => $this->currency,
        ];
    }
}

