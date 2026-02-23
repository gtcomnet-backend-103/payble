<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Contracts\IdempotentAction;
use App\Domains\Payouts\Contracts\FeeCalculatorInterface;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Domains\Payouts\NewPayoutEvent;
use App\Enums\EntryDirection;
use App\Enums\FeeChannel;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Models\Business;
use App\Models\Payout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class CreatePayout implements IdempotentAction
{
    public function __construct(
        private LedgerPostingServiceInterface $ledgerPostingService,
        private LedgerServiceInterface $ledgerService,
        private FeeCalculatorInterface $feeCalculator
    ) {}

    /**
     * @param array{
     *   date?: string,
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
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'requires_otp' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
            'reference' => ['sometimes', 'string', 'max:100'],
        ])->validate();

        if (! $bankAccount = $business->bankAccount()->first()) {
            throw ValidationException::withMessages([
                'bank_account' => 'No bank account specified for this business',
            ]);
        }

        return DB::transaction(function () use ($user, $bankAccount, $business, $data) {
            $currency = $bankAccount->currency;
            $mode = PaymentMode::tryFrom(config('app.payment_mode') ?? PaymentMode::Live->value);
            $reference = $data['reference'] ?? Str::uuid()->toString();
            $providerReference = 'PRV_' . Str::random(12);
            $requiresOtp = $data['requires_otp'] ?? false;
            $metadata = $data['metadata'] ?? [];
            $metadata['account'] = $bankAccount->only(['account_name', 'account_number', 'bank_code']);

            $account = $this->ledgerService->receivable($business, $currency->value, $mode);

            // Determine target settlement date
            $date = isset($data['date'])
                ? Carbon::make($data['date'])->format('Y-m-d')
                : now()->subDay()->format('Y-m-d');

            // Calculate available earnings from the ledger (credits - debits for the date)
            $availableEarnings = (int) $account->entries()
                ->whereDate('created_at', $date)
                ->selectRaw(
                    'SUM(CASE WHEN direction = ? THEN amount ELSE -amount END) as net',
                    [EntryDirection::CREDIT->value]
                )
                ->value('net');

            if ($availableEarnings <= 0) {
                throw ValidationException::withMessages([
                    'date' => "No available earnings for the specified date: {$date}",
                ]);
            }

            $requestedAmount = isset($data['amount'])
                ? min((int) $data['amount'], $availableEarnings)
                : $availableEarnings;

            $balance = $this->ledgerService->getBalance($account);

            /**
             * Check if the business has enough balance for this payout.
             */
            if ($balance >= 0 || abs($balance) < $requestedAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient balance for this payout',
                ]);
            }

            $fee = $this->feeCalculator->calculate($requestedAmount, $currency, FeeChannel::Payout);

            $payout = $bankAccount->payouts()->create([
                'business_id' => $business->id,
                'originator_id' => $user->getKey(),
                'originator_type' => $user->getMorphClass(),
                'amount' => $requestedAmount,
                'fee' => $fee,
                'currency' => $currency,
                'reference' => $reference,
                'provider_reference' => $providerReference,
                'status' => PayoutStatus::Draft,
                'mode' => $mode,
                'requires_otp' => $requiresOtp,
                'metadata' => $metadata,
            ]);

            $transaction = $this->ledgerPostingService->recordTransaction($payout);
            $this->ledgerPostingService->reserve($transaction);

            $payout->update(['status' => PayoutStatus::Pending]);

            event(new NewPayoutEvent($payout));

            return $payout;
        });
    }
}
