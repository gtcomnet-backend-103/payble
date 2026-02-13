<?php

declare(strict_types=1);

namespace App\Listeners\Payouts;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry;
use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Payouts\Actions\SelectPayoutProvider;
use App\Enums\PayoutStatus;
use App\Events\Payouts\PayoutCreated;
use App\Models\Payout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class ReservePayoutFunds
{
    public function __construct(
        private LedgerService $ledgerService,
        private SelectPayoutProvider $selectProvider,
    ) {}

    public function handle(PayoutCreated $event): void
    {
        DB::transaction(function () use ($event) {
            $payout = Payout::findOrFail($event->payoutId);

            if (! $payout->status->is(PayoutStatus::Pending)) {
                return;
            }

            // 1. Reserve funds in ledger
            $businessWallet = $this->ledgerService->businessReceivable(
                $payout->business,
                $payout->currency->value,
                $payout->mode
            );
            $payoutClearing = $this->ledgerService->externalPayoutClearing(
                $payout->currency->value,
                $payout->mode
            );

            $this->ledgerService->transaction($payout->transaction, 'reserve_funds')->entries([
                LedgerEntry::credit($businessWallet, $payout->totalDebit()),
                LedgerEntry::debit($payoutClearing, $payout->totalDebit()),
            ]);

            // 2. Handle OTP if required
            if ($payout->requires_otp) {
                $this->initiateOtpFlow($payout);
            }
        });
    }

    private function initiateOtpFlow(Payout $payout): void
    {
        $otp = (string) rand(100000, 999999);

        // Store OTP in cache for 10 minutes
        Cache::put("payout:otp:{$payout->id}", $otp, now()->addMinutes(10));

        // TODO: Fire notification event to send OTP
        // event(new \App\Events\Payouts\SendPayoutOtp($payout->id, $otp));
    }
}
