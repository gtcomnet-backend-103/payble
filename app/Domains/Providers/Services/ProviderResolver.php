<?php

declare(strict_types=1);

namespace App\Domains\Providers\Services;

use App\Domains\Providers\Adapters\PaystackAdapter;
use App\Domains\Providers\Contracts\ProviderAdapter;
use App\Models\Provider;
use Illuminate\Support\Facades\App;

final class ProviderResolver
{
    /** @var array<string, class-string<ProviderAdapter>> */
    private array $map = [
        'paystack' => PaystackAdapter::class,
    ];

    public function resolve(Provider $provider): ProviderAdapter
    {
        $class = $this->map[$provider->identifier] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("No adapter found for provider: {$provider->identifier}");
        }

        return App::make($class);
    }
}
