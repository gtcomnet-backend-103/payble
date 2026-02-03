<?php

declare(strict_types=1);

use App\Domains\Providers\Services\PaymentProvider;

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
