<?php

declare(strict_types=1);

namespace App\Listeners\Payouts;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry;
use App\Domains\Ledger\Services\LedgerService;
use App\Enums\PayoutStatus;
use App\Events\Payouts\PayoutSucceeded;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

final readonly class PostPayoutFunds
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    public function handle(PayoutSucceeded $event): void
    {
        DB::transaction(function () use ($event) {
            $payout = Payout::findOrFail($event->payoutId);

            if ($payout->status->is(PayoutStatus::Succeeded)) {
                return;
            }

            // 1. Mark payout as succeeded
            $payout->update(['status' => PayoutStatus::Succeeded]);
            $payout->transaction->update(['status' => \App\Enums\TransactionStatus::Success]);

            // 2. Finalize ledger movement
            $payoutClearing = $this->ledgerService->externalPayoutClearing(
                $payout->currency->value,
                $payout->mode
            );
            $providerReceivable = $this->ledgerService->providerReceivable(
                $payout->provider,
                $payout->currency->value,
                $payout->mode
            );

            // Move funds from clearing to provider receivable (or settlement account)
            $this->ledgerService->transaction($payout->transaction, 'post_funds')->entries([
                LedgerEntry::credit($payoutClearing, $payout->totalDebit()),
                LedgerEntry::debit($providerReceivable, $payout->totalDebit()),
            ]);
        });
    }
}
