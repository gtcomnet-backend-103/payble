<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Contracts\IdempotentAction;
use App\Domains\Payouts\Contracts\LedgerInterface;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Models\Business;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class CreatePayout implements IdempotentAction
{
    public function __construct(private LedgerInterface $ledger) {}

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
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('transactions', 'reference')->where('business_id', $business->getKey())],
            'requires_otp' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        if ($bankAccount = $business->bankAccount()->first()) {
            throw ValidationException::withMessages([
                'reference' => 'No bank account specified for this business',
            ]);
        }

        return DB::transaction(function () use ($bankAccount, $business, $data) {
            $currency = Currency::tryFrom($data['currency']);
            $mode = PaymentMode::tryFrom(config('app.payment_mode') ?? PaymentMode::Test->value);
            $reference = $data['reference'] ?? Str::uuid()->toString();
            $requiresOtp = $data['requires_otp'] ?? false;
            $metadata = $data['metadata'] ?? [];
            $metadata['account'] = $bankAccount->only(['account_name', 'account_number', 'bank_code']);

            $payout = $bankAccount->payouts()->create([
                'business_id' => $business->id,
                'amount' => $data['amount'],
                'currency' => $currency,
                'reference' => $reference,
                'status' => PayoutStatus::Draft,
                'mode' => $mode,
                'requires_otp' => $requiresOtp,
                'metadata' => $metadata,
            ]);

            $this->ledger->recordPayoutTransaction($payout);

            $payout->update(['status' => PayoutStatus::Pending]);

            return $payout;
        });
    }
}
