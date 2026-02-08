<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Listeners;

use App\Domains\Ledger\Actions\RecordPaymentLedgerPostings;
use App\Enums\FeeBearer;
use App\Events\TransactionSuccessful;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ProcessTransactionLedger implements ShouldQueue
{
    public function __construct(
        private RecordPaymentLedgerPostings $recordLedger
    ) {}

    public function handle(TransactionSuccessful $event): void
    {
        [$merchantFee, $customerFee] = $this->calculateFee($event->bearer, $event->totalFee);

        $this->recordLedger->execute(
            transaction: $event->transaction,
            provider: $event->provider,
            customerFee: $customerFee,
            businessFee: $merchantFee,
            providerFee: $event->providerFee
        );
    }

    private function calculateFee(FeeBearer $bearer, int $totalFee): array
    {
        return match ($bearer) {
            FeeBearer::Merchant => [$totalFee, 0],
            FeeBearer::Customer => [0, $totalFee],
        };
    }
}
