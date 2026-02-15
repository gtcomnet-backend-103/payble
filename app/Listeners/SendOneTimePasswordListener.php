<?php

namespace App\Listeners;


use App\Domains\Payouts\NewPayoutEvent;
use App\Supports\Services\OtpService;

class SendOneTimePasswordListener
{
    public function __construct(private OtpService $otpService)
    {
    }

    public function handle(NewPayoutEvent $event): void
    {
        $this->otpService->send($event->payout->originator);
    }
}
