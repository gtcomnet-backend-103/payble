<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Contracts\IdempotentAction;
use App\Domains\Payouts\Contracts\FeeCalculatorInterface;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Domains\Payouts\NewPayoutEvent;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Models\Business;
use App\Models\Payout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class CreatePayout implements IdempotentAction
{
    public function __construct(
        private LedgerServiceInterface $ledgerService,
        private FeeCalculatorInterface $feeCalculator
    ) {}

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
    public function execute(Business $business, Authenticatable $user, array $data): Payout
    {
        $data = Validator::make($data, [
            'requires_otp' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ])->validate();

        if (! $bankAccount = $business->bankAccount()->first()) {
            throw ValidationException::withMessages([
                'reference' => 'No bank account specified for this business',
            ]);
        }

        return DB::transaction(function () use ($user, $bankAccount, $business, $data) {
            $currency = $bankAccount->currency;
            $mode = PaymentMode::tryFrom(config('app.payment_mode') ?? PaymentMode::Test->value);
            $reference = $data['reference'] ?? Str::uuid()->toString();
            $providerReference = 'PRV_'.Str::random(12);
            $requiresOtp = $data['requires_otp'] ?? false;
            $metadata = $data['metadata'] ?? [];
            $metadata['account'] = $bankAccount->only(['account_name', 'account_number', 'bank_code']);


            $balance = $bankAccount->ledgerEntries()
                ->selectRaw("
                    SUM(
                        CASE
                            WHEN direction = 'debit' THEN -amount
                            ELSE amount
                        END
                    ) as balance
                ")->value('balance');

            dd($balance);

            $fee = $this->feeCalculator->calculate($data['amount'], $currency);
            $payout = $bankAccount->payouts()->create([
                'business_id' => $business->id,
                'originator_id' => $user->getKey(),
                'originator_type' => $user->getMorphClass(),
                'amount' => $data['amount'],
                'fee' => $fee,
                'currency' => $currency,
                'reference' => $reference,
                'provider_reference' => $providerReference,
                'status' => PayoutStatus::Draft,
                'mode' => $mode,
                'requires_otp' => $requiresOtp,
                'metadata' => $metadata,
            ]);

            $transaction = $this->ledgerService->recordPayoutTransaction($payout);
            $this->ledgerService->reserve($transaction);

            $payout->update(['status' => PayoutStatus::Pending]);

            event(new NewPayoutEvent($payout));

            return $payout;
        });
    }
}
