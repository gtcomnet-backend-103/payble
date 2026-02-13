<?php

declare(strict_types=1);

namespace App\Listeners\Payouts;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry;
use App\Domains\Ledger\Services\LedgerService;
use App\Enums\PayoutStatus;
use App\Events\Payouts\PayoutFailed;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

final readonly class ReversePayoutFunds
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    public function handle(PayoutFailed $event): void
    {
        DB::transaction(function () use ($event) {
            $payout = Payout::findOrFail($event->payoutId);

            if ($payout->status->is(PayoutStatus::Failed)) {
                return;
            }

            // 1. Mark payout as failed
            $payout->update(['status' => PayoutStatus::Failed]);
            $payout->transaction->update(['status' => \App\Enums\TransactionStatus::Failed]);

            // 2. Reverse ledger movement
            $payoutClearing = $this->ledgerService->externalPayoutClearing(
                $payout->currency->value,
                $payout->mode
            );
            $businessWallet = $this->ledgerService->businessReceivable(
                $payout->business,
                $payout->currency->value,
                $payout->mode
            );

            // Move funds back from clearing to business wallet
            $this->ledgerService->transaction($payout->transaction, 'reverse_funds')->entries([
                LedgerEntry::credit($payoutClearing, $payout->totalDebit()),
                LedgerEntry::debit($businessWallet, $payout->totalDebit()),
            ]);
        });
    }
}
