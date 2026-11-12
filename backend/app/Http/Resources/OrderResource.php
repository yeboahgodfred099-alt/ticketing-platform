<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    protected ?string $rawAccessToken = null;

    public function withAccessToken(string $token): self
    {
        $this->rawAccessToken = $token;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'subtotal_minor' => $this->subtotal_minor,
            'subtotal_major' => number_format($this->subtotal_minor / 100, 2, '.', ''),
            'discount_minor' => $this->discount_minor,
            'discount_major' => number_format($this->discount_minor / 100, 2, '.', ''),
            'total_amount_minor' => $this->total_amount_minor,
            'total_amount_major' => number_format($this->total_amount_minor / 100, 2, '.', ''),
            'currency' => $this->currency,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'event' => $this->whenLoaded('event', function () {
                return [
                    'id' => $this->event->id,
                    'name' => $this->event->name,
                    'slug' => $this->event->slug,
                    'venue_name' => $this->event->venue_name,
                    'starts_at' => $this->event->starts_at->toIso8601String(),
                    'ends_at' => $this->event->ends_at->toIso8601String(),
                    'timezone' => $this->event->timezone,
                ];
            }),
            'access_token' => $this->when($this->rawAccessToken !== null, $this->rawAccessToken),
        ];
    }
}

