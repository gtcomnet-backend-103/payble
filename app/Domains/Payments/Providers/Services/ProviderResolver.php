<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Services;

use App\Domains\Payments\Providers\Adapters\PaystackAdapter;
use App\Domains\Payments\Providers\Contracts\ProviderAdapter;
use App\Models\Provider;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;

final class ProviderResolver
{
    public function resolve(Provider $provider): ProviderAdapter
    {
        $map = config('payment.adapters', [
            'paystack' => PaystackAdapter::class,
        ]);

        $class = $map[$provider->identifier] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("No adapter found for provider: {$provider->identifier}");
        }

        return App::make($class);
    }
}
