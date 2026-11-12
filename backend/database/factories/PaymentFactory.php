<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $amount = 10000;

        return [
            'id' => (string) Str::uuid(),
            'order_id' => Order::factory(),
            'reference' => 'PAY-' . strtoupper(Str::random(12)),
            'provider' => 'paystack',
            'provider_transaction_id' => (string) fake()->numberBetween(100000000, 999999999),
            'channel' => 'mobile_money',
            'mobile_money_provider' => 'mtn',
            'customer_phone' => '0551234567',
            'amount_minor' => $amount,
            'fees_minor' => (int) round($amount * 0.0195),
            'currency' => 'GHS',
            'status' => PaymentStatus::PENDING,
            'provider_response_sanitized' => [
                'status' => 'pay_offline',
                'message' => 'Charge attempted',
            ],
            'failure_reason' => null,
            'verified_at' => null,
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::SUCCESS,
            'verified_at' => now(),
        ]);
    }
}

