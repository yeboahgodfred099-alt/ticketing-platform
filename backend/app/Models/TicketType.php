<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketTypeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ticket_types';

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price_minor',
        'currency',
        'quantity_total',
        'quantity_sold',
        'quantity_reserved',
        'min_per_order',
        'max_per_order',
        'sales_starts_at',
        'sales_ends_at',
        'status',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'quantity_total' => 'integer',
        'quantity_sold' => 'integer',
        'quantity_reserved' => 'integer',
        'min_per_order' => 'integer',
        'max_per_order' => 'integer',
        'sales_starts_at' => 'datetime',
        'sales_ends_at' => 'datetime',
        'status' => TicketTypeStatus::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'ticket_type_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'ticket_type_id');
    }

    /**
     * Authoritative available inventory calculation.
     */
    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity_total - ($this->quantity_sold + $this->quantity_reserved));
    }

    /**
     * Check if ticket type is currently open for purchase.
     */
    public function isWithinSaleWindow(): bool
    {
        $now = now();

        if ($this->sales_starts_at && $now->lt($this->sales_starts_at)) {
            return false;
        }

        if ($this->sales_ends_at && $now->gt($this->sales_ends_at)) {
            return false;
        }

        return true;
    }
}

