<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\FeeCalculatorInterface;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Enums\FeeChannel;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Enums\PayoutType;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Payout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class InitiateLedgerTransfer
{
    public function __construct(
        private LedgerPostingServiceInterface $ledgerPostingService,
        private LedgerServiceInterface $ledgerService,
        private FeeCalculatorInterface $feeCalculator
    ) {}

    /**
     * @param array{
     *   bank_account_id: int,
     *   amount: int,
     *   reference?: string,
     *   metadata?: array<string, mixed>
     * } $data
     *
     * @throws Throwable
     */
    public function execute(Business $business, Authenticatable $user, array $data): Payout
    {
        $data = Validator::make($data, [
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'amount' => ['required', 'integer', 'min:100'],
            'reference' => ['sometimes', 'string', 'max:100'],
            'metadata' => ['sometimes', 'array'],
        ])->validate();

        $bankAccount = BankAccount::findOrFail($data['bank_account_id']);

        if ($bankAccount->business_id !== $business->id) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'This bank account does not belong to your business',
            ]);
        }

        return DB::transaction(function () use ($user, $bankAccount, $business, $data) {
            $currency = $bankAccount->currency;
            $mode = PaymentMode::tryFrom(config('app.payment_mode') ?? PaymentMode::Live->value);
            $reference = $data['reference'] ?? Str::uuid()->toString();
            $requestedAmount = $data['amount'];
            $metadata = $data['metadata'] ?? [];
            $metadata['account'] = $bankAccount->only(['account_name', 'account_number', 'bank_code']);

            $account = $this->ledgerService->receivable($business, $currency->value, $mode);
            $balance = $this->ledgerService->getBalance($account);

            if ($balance >= 0 || abs($balance) < $requestedAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient balance for this transfer',
                ]);
            }

            $fee = $this->feeCalculator->calculate($requestedAmount, $currency, FeeChannel::Payout);

            $transfer = $bankAccount->payouts()->create([
                'business_id' => $business->id,
                'originator_id' => $user instanceof Model ? $user->getKey() : null,
                'originator_type' => $user instanceof Model ? $user->getMorphClass() : null,
                'amount' => $requestedAmount,
                'fee' => $fee,
                'currency' => $currency,
                'type' => PayoutType::Transfer,
                'reference' => $reference,
                'status' => PayoutStatus::Pending,
                'mode' => $mode,
                'metadata' => $metadata,
            ]);

            $transaction = $this->ledgerPostingService->recordTransaction($transfer);
            $this->ledgerPostingService->reserve($transaction);

            return $transfer;
        });
    }
}
