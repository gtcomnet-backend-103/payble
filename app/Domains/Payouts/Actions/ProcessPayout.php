<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Enums\AuthorizationStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

final readonly class ProcessPayout
{
    public function __construct(
        private DisbursementProviderInterface $disbursementProvider,
        private LedgerServiceInterface $ledger,
    ) {}

    public function execute(Payout $payout): Payout
    {
        if (! $payout->status->is(PayoutStatus::Processing)) {
            return $payout;
        }

        $response = $this->disbursementProvider->verify($payout->provider, $payout->provider_reference);

        if ($response->status->isFinal()) {
            return DB::transaction(function () use ($payout, $response): Payout {
                if ($response->status === AuthorizationStatus::Success) {
                    $payout->update(['status' => PayoutStatus::Success]);
                    $this->ledger->postTransaction($payout->transaction);
                }

                if ($response->status === AuthorizationStatus::Failed) {
                    $payout->update(['status' => PayoutStatus::Failed]);
                    $this->ledger->reverse($payout->transaction);
                }

                return $payout->refresh();
            });
        }

        return $payout->refresh();
    }
}
