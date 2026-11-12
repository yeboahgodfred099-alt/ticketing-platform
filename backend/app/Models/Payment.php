<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payments';

    protected $fillable = [
        'order_id',
        'reference',
        'provider',
        'provider_transaction_id',
        'channel',
        'mobile_money_provider',
        'customer_phone',
        'amount_minor',
        'fees_minor',
        'currency',
        'status',
        'provider_response_sanitized',
        'failure_reason',
        'verified_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'fees_minor' => 'integer',
        'status' => PaymentStatus::class,
        'provider_response_sanitized' => 'array',
        'verified_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class, 'payment_id');
    }
}

