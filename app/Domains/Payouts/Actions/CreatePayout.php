<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Contracts\IdempotentAction;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Enums\TransactionStatus;
use App\Models\Business;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

final class CreatePayout implements IdempotentAction
{
    /**
     * @param array{
     *   amount: int,
     *   currency: string,
     *   bank_code: string,
     *   account_number: string,
     *   account_name: string,
     *   reference?: string,
     *   requires_otp?: bool,
     *   metadata?: array<string, mixed>,
     *   mode?: string
     * } $data
     *
     * @throws Throwable
     */
    public function execute(Business $business, array $data): Payout
    {
        $data = Validator::make($data, [
            'amount' => ['required', 'integer', 'min:100'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'bank_code' => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'account_name' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('payouts', 'reference')->where('business_id', $business->getKey())],
            'requires_otp' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'mode' => ['nullable', 'string', Rule::enum(PaymentMode::class)],
        ])->validate();

        return DB::transaction(function () use ($business, $data) {
            $currency = Currency::tryFrom($data['currency']);
            $mode = isset($data['mode'])
                ? PaymentMode::tryFrom($data['mode'])
                : (PaymentMode::tryFrom(config('app.payment_mode') ?? 'test') ?? PaymentMode::Test);

            $reference = $data['reference'] ?? 'PAY_'.Str::random(10);
            $requiresOtp = $data['requires_otp'] ?? false;

            $metadata = $data['metadata'] ?? [];
            $metadata['bank_details'] = [
                'bank_code' => $data['bank_code'],
                'account_number' => $data['account_number'],
                'account_name' => $data['account_name'],
            ];

            $payout = Payout::create([
                'business_id' => $business->id,
                'amount' => $data['amount'],
                'currency' => $currency,
                'reference' => $reference,
                'status' => PayoutStatus::Pending,
                'mode' => $mode,
                'requires_otp' => $requiresOtp,
                'metadata' => $metadata,
            ]);

            $payout->transaction()->create([
                'business_id' => $business->id,
                'reference' => $payout->reference,
                'currency' => $currency,
                'status' => TransactionStatus::Pending,
                'mode' => $mode,
                'metadata' => $metadata,
            ]);

            return $payout;
        });
    }
}
