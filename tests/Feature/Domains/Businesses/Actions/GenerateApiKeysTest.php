<?php

declare(strict_types=1);

use App\Domains\Businesses\Actions\GenerateApiKeys;
use App\Enums\AccessLevel;
use App\Enums\PaymentMode;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Test Business',
        'email' => 'test@business.com',
        'owner_id' => $this->user->id,
        'verified_at' => now(), // Verified by default
    ]);
});

it('generates only test keys when mode is Test', function () {
    (new GenerateApiKeys())->execute($this->business, PaymentMode::Test);

    // Should have 2 test keys (Public + Secret)
    expect($this->business->apiTokens()->where('mode', PaymentMode::Test)->count())->toBe(2);

    // Should not generate any live keys
    expect($this->business->apiTokens()->where('mode', PaymentMode::Live)->count())->toBe(0);
});

it('generates only live keys when mode is Live and business is verified', function () {
    (new GenerateApiKeys())->execute($this->business, PaymentMode::Live);

    // Should have 2 live keys (Public + Secret)
    expect($this->business->apiTokens()->where('mode', PaymentMode::Live)->count())->toBe(2);

    // Should not generate any test keys
    expect($this->business->apiTokens()->where('mode', PaymentMode::Test)->count())->toBe(0);
});

it('throws exception when generating live keys for unverified business', function () {
    $this->business->update(['verified_at' => null]);

    (new GenerateApiKeys())->execute($this->business, PaymentMode::Live);
})->throws(RuntimeException::class, 'Cannot generate live API keys for unverified business');

it('generates both test and live keys when no mode specified and business is verified', function () {
    (new GenerateApiKeys())->execute($this->business);

    // Should have 4 keys total (2 test + 2 live)
    expect($this->business->apiTokens()->count())->toBe(4);
    expect($this->business->apiTokens()->where('mode', PaymentMode::Test)->count())->toBe(2);
    expect($this->business->apiTokens()->where('mode', PaymentMode::Live)->count())->toBe(2);
});

it('generates only test keys when no mode specified and business is unverified', function () {
    $this->business->update(['verified_at' => null]);

    (new GenerateApiKeys())->execute($this->business);

    // Should have only 2 test keys
    expect($this->business->apiTokens()->count())->toBe(2);
    expect($this->business->apiTokens()->where('mode', PaymentMode::Test)->count())->toBe(2);
    expect($this->business->apiTokens()->where('mode', PaymentMode::Live)->count())->toBe(0);
});

it('generates both public and secret keys for each mode', function () {
    (new GenerateApiKeys())->execute($this->business, PaymentMode::Test);

    $testKeys = $this->business->apiTokens()->where('mode', PaymentMode::Test)->get();

    expect($testKeys->pluck('access_level')->toArray())->toContain(AccessLevel::Public, AccessLevel::Secret);
});

it('regenerates keys when called multiple times', function () {
    // First generation
    (new GenerateApiKeys())->execute($this->business, PaymentMode::Test);
    $firstKeys = $this->business->apiTokens()->where('mode', PaymentMode::Test)->get();

    // Second generation (regenerate)
    (new GenerateApiKeys())->execute($this->business, PaymentMode::Test);
    $secondKeys = $this->business->fresh()->apiTokens()->where('mode', PaymentMode::Test)->get();

    // Should still have 2 keys
    expect($secondKeys->count())->toBe(2);

    // Keys should be different (new lookup_key)
    foreach ($firstKeys as $index => $firstKey) {
        expect($secondKeys[$index]->lookup_key)->not->toBe($firstKey->lookup_key);
    }
});
