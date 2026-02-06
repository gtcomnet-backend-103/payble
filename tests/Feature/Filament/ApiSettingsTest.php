<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domains\Businesses\Actions\GenerateApiKeys;
use App\Enums\AccessLevel;
use App\Enums\PaymentMode;
use App\Filament\Pages\ApiSettings;
use App\Models\Business;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Test Business',
        'email' => 'test@business.com',
        'owner_id' => $this->user->id,
        'verified_at' => now(), // Verified by default for most tests
    ]);

    $this->business->users()->attach($this->user);

    (new GenerateApiKeys())->execute($this->business);

    actingAs($this->user);
    Filament::setTenant($this->business);
});

it('can render the api settings page', function () {
    Livewire::test(ApiSettings::class)
        ->assertOk();
});

it('can update webhook url', function () {
    $webhookUrl = 'https://example.com/webhook';

    Livewire::test(ApiSettings::class)
        ->fillForm([
            'webhook_url' => $webhookUrl,
        ])
        ->call('save')
        ->assertNotified();

    expect($this->business->fresh()->webhook_url)->toBe($webhookUrl);
});

it('validates webhook url must be a valid url', function () {
    Livewire::test(ApiSettings::class)
        ->fillForm([
            'webhook_url' => 'not-a-valid-url',
        ])
        ->call('save')
        ->assertHasFormErrors(['webhook_url' => 'url']);
});

it('displays all api keys correctly', function () {
    $page = Livewire::test(ApiSettings::class);

    foreach ([PaymentMode::Live, PaymentMode::Test] as $mode) {
        foreach ([AccessLevel::Public, AccessLevel::Secret] as $level) {
            $token = $this->business->apiTokens()
                ->where('mode', $mode)
                ->where('access_level', $level)
                ->first();

            $formattedKey = GenerateApiKeys::formatKey($token);

            $page->assertSee($formattedKey);
        }
    }
});

it('can regenerate test keys', function () {
    $oldTokens = $this->business->apiTokens()->where('mode', PaymentMode::Test)->get();

    Livewire::test(ApiSettings::class)
        ->callAction('generateTestKeys')
        ->assertNotified();

    $newTokens = $this->business->fresh()->apiTokens()->where('mode', PaymentMode::Test)->get();

    // Ensure we still have 2 test tokens
    expect($newTokens)->toHaveCount(2);

    // Ensure lookup keys have changed
    foreach ($oldTokens as $oldToken) {
        $newToken = $newTokens->firstWhere('access_level', $oldToken->access_level);
        expect($newToken->lookup_key)->not->toBe($oldToken->lookup_key);
    }
});

it('can regenerate live keys when verified', function () {
    $oldTokens = $this->business->apiTokens()->where('mode', PaymentMode::Live)->get();

    Livewire::test(ApiSettings::class)
        ->callAction('generateLiveKeys')
        ->assertNotified();

    $newTokens = $this->business->fresh()->apiTokens()->where('mode', PaymentMode::Live)->get();

    // Ensure we still have 2 live tokens
    expect($newTokens)->toHaveCount(2);

    // Ensure lookup keys have changed
    foreach ($oldTokens as $oldToken) {
        $newToken = $newTokens->firstWhere('access_level', $oldToken->access_level);
        expect($newToken->lookup_key)->not->toBe($oldToken->lookup_key);
    }
});

it('loads existing webhook url into form', function () {
    $this->business->update(['webhook_url' => 'https://existing.com/webhook']);

    Livewire::test(ApiSettings::class)
        ->assertFormSet([
            'webhook_url' => 'https://existing.com/webhook',
        ]);
});

it('cannot regenerate live keys when unverified', function () {
    $this->business->update(['verified_at' => null]);

    Livewire::test(ApiSettings::class)
        ->assertActionDisabled('generateLiveKeys');
});

it('shows verification warning for unverified business', function () {
    $this->business->update(['verified_at' => null]);
    $this->business->apiTokens()->delete();

    Livewire::test(ApiSettings::class)
        ->assertSee('Business Verification Required')
        ->assertSee('Verification required');
});

it('displays live keys for verified business', function () {
    Livewire::test(ApiSettings::class)
        ->assertDontSee('Business Verification Required')
        ->assertDontSee('Verification required');

    $liveToken = $this->business->apiTokens()
        ->where('mode', PaymentMode::Live)
        ->where('access_level', AccessLevel::Public)
        ->first();

    $formattedKey = GenerateApiKeys::formatKey($liveToken);

    Livewire::test(ApiSettings::class)
        ->assertSee($formattedKey);
});
