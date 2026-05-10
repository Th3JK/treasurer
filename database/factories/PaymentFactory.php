<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Models\Payment;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'provider' => $this->faker->randomElement(['gopay', 'comgate']),
            'provider_payment_id' => $this->faker->uuid(),
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'currency' => $this->faker->randomElement(['CZK', 'EUR']),
            'status' => PaymentState::CREATED,
            'method' => null,
            'metadata' => [],
        ];
    }
}
