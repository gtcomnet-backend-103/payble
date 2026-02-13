<?php

namespace App\Supports\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OtpServiceInterface
{
    public function send(Model $model): void;

    public function verify(Model $model, string $otp): bool;
}
