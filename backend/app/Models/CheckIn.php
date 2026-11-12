<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'check_ins';

    protected $fillable = [
        'ticket_id',
        'admin_id',
        'checked_in_at',
        'gate_identifier',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}

