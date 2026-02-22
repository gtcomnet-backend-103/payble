<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Enums\AuthorizationStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AuthorizeLedgerTransfer
{
    public function __construct(
        private DisbursementProviderInterface $disbursementProvider,
        private LedgerPostingServiceInterface $ledgerPostingService,
    ) {}

    public function execute(Payout $transfer): Payout
    {
        if ($transfer->status !== PayoutStatus::Pending) {
            throw ValidationException::withMessages([
                'transfer' => "Transfer is in {$transfer->status->value} state and cannot be authorized.",
            ]);
        }

        /** @var \App\Models\Provider $provider */
        $provider = $this->disbursementProvider->provider($transfer->mode);
        $disbursementAmount = $transfer->disbursement_amount;

        try {
            if ($disbursementAmount > 0) {
                $response = $this->disbursementProvider->transfer(
                    $provider,
                    $transfer->reference,
                    $transfer->bankAccount->account_number,
                    $transfer->bankAccount->bank_code,
                    $disbursementAmount
                );

                return DB::transaction(function () use ($transfer, $provider, $response) {
                    $transfer->update([
                        'provider_id' => $provider->id,
                        'provider_reference' => $response->providerReference,
                        'status' => match ($response->status) {
                            AuthorizationStatus::Success => PayoutStatus::Success,
                            AuthorizationStatus::Failed => PayoutStatus::Failed,
                            default => PayoutStatus::Processing,
                        },
                    ]);

                    if ($response->status === AuthorizationStatus::Success) {
                        if ($transfer->type === \App\Enums\PayoutType::Payout) {
                            $this->ledgerPostingService->postTransaction($transfer->transaction);
                        }

                        if ($transfer->type === \App\Enums\PayoutType::Advance) {
                            $this->ledgerPostingService->postDisbursement($transfer->transaction);
                        }
                    }

                    if ($response->status === AuthorizationStatus::Failed) {
                        $this->ledgerPostingService->reverse($transfer->transaction);
                    }

                    return $transfer->refresh();
                });
            } else {
                return DB::transaction(function () use ($transfer) {
                    $transfer->update([
                        'status' => PayoutStatus::Success,
                        'metadata' => array_merge($transfer->metadata ?? [], ['info' => 'Fully settled against outstanding advance.']),
                    ]);

                    if ($transfer->type === \App\Enums\PayoutType::Payout) {
                        $this->ledgerPostingService->postTransaction($transfer->transaction);
                    }

                    return $transfer->refresh();
                });
            }
        } catch (\Exception $e) {
            $transfer->update(['status' => PayoutStatus::Failed]);
            $this->ledgerPostingService->reverse($transfer->transaction);
            throw $e;
        }
    }
}
