<?php

declare(strict_types=1);

namespace App\Supports\Providers\Services;

use App\Models\Provider;
use App\Supports\Providers\Adapters\TestAdapter;
use App\Supports\Providers\Contracts\ProviderAdapter;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;

final class ProviderResolver
{
    public function resolve(Provider $provider): ProviderAdapter
    {
        if ($provider->mode && $provider->mode->isTest()) {
            return App::make(TestAdapter::class);
        }

        $map = config('payment.adapters', []);

        $class = $map[$provider->identifier] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("No adapter found for provider: {$provider->identifier}");
        }

        return App::make($class);
    }
}
