<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Services;

use App\Domains\Payouts\Actions\AuthorizePayout;
use App\Domains\Payouts\Actions\CreatePayout;
use App\Domains\Payouts\Actions\ProcessPayout;
use App\Models\Business;
use App\Models\Payout;
use App\Models\User;

final readonly class PayoutService
{
    public function __construct(
        private CreatePayout $createPayout,
        private ProcessPayout $processPayout,
        private AuthorizePayout $authorizePayout,
    ) {}

    public function create(Business $business, array $data): Payout
    {
        return $this->createPayout->execute($business, $data);
    }

    public function authorize(Payout $payout): Payout
    {
        return $this->authorizePayout->execute($payout);
    }

    public function process(Payout $payout): Payout
    {
        return $this->processPayout->execute($payout);
    }
}
