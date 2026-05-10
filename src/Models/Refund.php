<?php

namespace Th3JK\Treasurer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Th3JK\Treasurer\Enums\RefundState;

class Refund extends Model
{
    protected $fillable = [
        'payment_id',
        'provider',
        'provider_refund_id',
        'amount',
        'currency',
        'status',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => RefundState::class,
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
