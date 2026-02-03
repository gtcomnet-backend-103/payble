<?php

declare(strict_types=1);

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a provider using its factory', function () {
    $provider = Provider::factory()->create();

    expect($provider)->toBeInstanceOf(Provider::class);
    expect($provider->name)->not->toBeEmpty();
    expect($provider->supported_channels)->toBeArray();
    expect($provider->is_active)->toBeTrue();
});
