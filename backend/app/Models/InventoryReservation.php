<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'inventory_reservations';

    protected $fillable = [
        'order_id',
        'ticket_type_id',
        'quantity',
        'status',
        'expires_at',
        'converted_at',
        'released_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'status' => ReservationStatus::class,
        'expires_at' => 'datetime',
        'converted_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function isExpired(): bool
    {
        return now()->gt($this->expires_at);
    }
}

