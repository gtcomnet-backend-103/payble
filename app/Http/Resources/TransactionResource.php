<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'transaction_date' => $this->created_at?->toIso8601String(),
            'status' => $this->status->value,
            'reference' => $this->reference,
            'mode' => $this->mode->value,
            'metadata' => $this->metadata,
            'message' => $this->message,
            'channel' => $this->channel?->value,
            'ip_address' => $this->ip_address,
            'fees' => $this->fees,
            'authorization' => $this->authorization,
            'customer' => [
                'first_name' => $this->paymentIntent->customer->first_name,
                'last_name' => $this->paymentIntent->customer->last_name,
                'email' => $this->paymentIntent->customer->email,
                'phone' => $this->paymentIntent->customer->phone,
            ],
        ];
    }
}
