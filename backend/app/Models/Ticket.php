<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tickets';

    protected $fillable = [
        'order_item_id',
        'event_id',
        'ticket_number',
        'qr_token_hash',
        'holder_name',
        'holder_email',
        'status',
        'issued_at',
        'used_at',
    ];

    protected $hidden = [
        'qr_token_hash',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'issued_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class, 'ticket_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'ticket_id');
    }

    /**
     * Check if ticket is eligible for check-in admission.
     */
    public function canBeCheckedIn(): bool
    {
        return $this->status === TicketStatus::ACTIVE && $this->used_at === null;
    }
}

