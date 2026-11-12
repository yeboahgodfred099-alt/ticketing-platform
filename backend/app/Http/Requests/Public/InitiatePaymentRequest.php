<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Models\Order;
use App\Services\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InitiatePaymentRequest extends FormRequest
{
    protected ?Order $resolvedOrder = null;

    public function authorize(): bool
    {
        $orderNumber = $this->input('order_number');
        if (!is_string($orderNumber) || empty($orderNumber)) {
            return false;
        }

        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order) {
            return false;
        }

        // Verify high-entropy access token
        $token = $this->header('X-Order-Token') ?? $this->input('token') ?? $this->query('token');
        if (!is_string($token) || empty($token)) {
            return false;
        }

        $providedHash = hash('sha256', $token);
        if (!$order->access_token_hash || !hash_equals($order->access_token_hash, $providedHash)) {
            return false;
        }

        $this->resolvedOrder = $order;
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string'],
            'provider' => ['required', 'string', 'in:mtn,vod,atl'],
            'phone' => ['required', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $phone = $this->input('phone');
            if ($phone && !PhoneNormalizer::isValid((string) $phone)) {
                $validator->errors()->add(
                    'phone',
                    'Please provide a valid Ghanaian phone number (e.g. 024XXXXXXX or +23324XXXXXXX).'
                );
            }
        });
    }

    public function getOrder(): Order
    {
        return $this->resolvedOrder!;
    }
}
