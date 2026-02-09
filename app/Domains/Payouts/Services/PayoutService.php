<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Services;

use App\Domains\Payouts\Actions\CreatePayout;
use App\Domains\Payouts\Actions\ProcessPayout;
use App\Models\Business;
use App\Models\Payout;
use App\Models\User;

final class PayoutService
{
    public function __construct(
        private readonly CreatePayout $createPayout,
        private readonly ProcessPayout $processPayout
    ) {}

    public function create(Business $business, array $data): Payout
    {
        return $this->createPayout->execute($business, $data);
    }

    public function process(Payout $payout, ?string $otp = null, ?User $user = null): Payout
    {
        return $this->processPayout->execute($payout, $otp, $user);
    }
}
