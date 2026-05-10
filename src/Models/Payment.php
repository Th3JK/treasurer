<?php

namespace Th3JK\Treasurer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Th3JK\Treasurer\Enums\PaymentMethod;
use Th3JK\Treasurer\Enums\PaymentState;

class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'provider',
        'provider_payment_id',
        'amount',
        'currency',
        'status',
        'method',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PaymentState::class,
        'method' => PaymentMethod::class,
        'metadata' => 'array',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
