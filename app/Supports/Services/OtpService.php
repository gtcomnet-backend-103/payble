<?php

namespace App\Supports\Services;

use App\Supports\Contracts\OtpServiceInterface;
use Illuminate\Database\Eloquent\Model;

class OtpService implements OtpServiceInterface
{
    public function send(Model $model): void
    {
        $model->sendOneTimePassword();
    }

    public function verify(Model $model, string $otp): bool
    {
        $result = $model->consumeOneTimePassword($otp);

        return $result->isOk();
    }
}
