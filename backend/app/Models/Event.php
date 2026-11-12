<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'events';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner_path',
        'venue_name',
        'venue_address',
        'starts_at',
        'ends_at',
        'sales_starts_at',
        'sales_ends_at',
        'timezone',
        'status',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sales_starts_at' => 'datetime',
        'sales_ends_at' => 'datetime',
        'status' => EventStatus::class,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class, 'event_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'event_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'event_id');
    }

    /**
     * Scope for public queries: only published events that have not ended.
     */
    public function scopePubliclyVisible($query)
    {
        return $query->where('status', EventStatus::PUBLISHED->value)
            ->where('ends_at', '>', now());
    }
}

