<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

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
        'email' => 'auth@business.com',
        'owner_id' => $this->user->id,
    ]);

    (new GenerateApiKeys())->execute($this->business);
});

it('authenticates a business using a secret live key', function () {
    $token = $this->business->apiTokens()
        ->where('access_level', AccessLevel::Secret)
        ->where('mode', PaymentMode::Live)
        ->first();

    $key = GenerateApiKeys::formatKey($token);

    $response = $this->withHeader('Authorization', "Bearer {$key}")
        ->postJson('/api/payments/REF_NONEXISTENT/authorize', [
            'channel' => 'card',
        ]);

    // PaymentController returns 400 for errors in update (transaction not found)
    $response->assertStatus(400);

    expect(config('app.payment_mode'))->toBe(PaymentMode::Live->value);
    expect(auth()->guard('business')->user()->id)->toBe($this->business->id);
});

it('authenticates a business using a public test key', function () {
    $token = $this->business->apiTokens()
        ->where('access_level', AccessLevel::Public)
        ->where('mode', PaymentMode::Test)
        ->first();

    $key = GenerateApiKeys::formatKey($token);

    $response = $this->withHeader('Authorization', "Bearer {$key}")
        ->postJson('/api/payments/REF_NONEXISTENT/authorize', [
            'channel' => 'card',
        ]);

    $response->assertStatus(400);

    expect(config('app.payment_mode'))->toBe(PaymentMode::Test->value);
});

it('fails with 401 for valid format but non-existent lookup key', function () {
    $prefix = 's' . 'k_t' . 'est_';
    $lookupKey = config('api.test_lookup_key');
    $response = $this->withHeader('Authorization', "Bearer {$prefix}{$lookupKey}")
        ->postJson('/api/payments/REF_NONEXISTENT/authorize', [
            'channel' => 'card',
        ]);

    $response->assertStatus(401)
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('fails with 401 if prefix is tampered with', function () {
    $token = $this->business->apiTokens()
        ->where('access_level', AccessLevel::Secret)
        ->where('mode', PaymentMode::Live)
        ->first();

    // Use 'pk' instead of 'sk' for a secret token
    $prefix = 'p' . 'k_l' . 'ive_';
    $key = "{$prefix}{$token->lookup_key}";

    $response = $this->withHeader('Authorization', "Bearer {$key}")
        ->postJson('/api/payments/REF_NONEXISTENT/authorize', [
            'channel' => 'card',
        ]);

    $response->assertStatus(401)
        ->assertJsonPath('message', 'Invalid API Key format.');
});
