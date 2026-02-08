<?php

declare(strict_types=1);

use App\Filament\Pages\Tenancy\RegisterBusiness;
use App\Models\Business;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

it('requires business onboarding after registration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirectContains('/dashboard/new');
});

it('can onboard a business', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(RegisterBusiness::class)
        ->fillForm([
            'name' => 'Test Business',
            'email' => 'test@business.com',
        ])
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Business::class, [
        'name' => 'Test Business',
        'email' => 'test@business.com',
        'owner_id' => $user->id,
    ]);

    expect($user->fresh()->businesses)->toHaveCount(1);
});
