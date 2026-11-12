<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'webhook_events';

    protected $fillable = [
        'provider',
        'event_identifier',
        'event_type',
        'payment_id',
        'payload_hash',
        'processing_status',
        'received_at',
        'processed_at',
        'last_error_code',
    ];

    protected $casts = [
        'processing_status' => WebhookStatus::class,
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}

