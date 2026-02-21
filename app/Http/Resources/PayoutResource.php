<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payout
 */
final class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'net_amount' => $this->net_amount,
            'currency' => $this->currency->value,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'bank_details' => $this->metadata['account'] ?? null,
            'requires_otp' => $this->requires_otp,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
