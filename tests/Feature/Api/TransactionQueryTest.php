<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Business;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Web Business',
        'email' => 'web@business.com',
        'owner_id' => $this->user->id,
    ]);

    $this->user->businesses()->attach($this->business->id);

    $this->payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'reference' => 'REF_123',
        'status' => PaymentStatus::Pending,
    ]);

    $this->transaction = Transaction::create([
        'business_id' => $this->business->id,
        'reference' => $this->payment->reference,
        'amount' => 1000,
        'currency' => 'NGN',
        'status' => TransactionStatus::Pending,
        'mode' => 'test',
    ]);
});

it('can query a transaction by reference', function () {
    Sanctum::actingAs($this->business, ['*'], 'business');
    $response = $this->getJson('/api/transactions/REF_123');

    $response->assertStatus(200)
        ->assertJsonPath('data.reference', 'REF_123')
        ->assertJsonPath('data.amount', 1000)
        ->assertJsonPath('data.status', 'pending');
});

it('returns 404 if transaction not found', function () {
    Sanctum::actingAs($this->business, ['*'], 'business');
    $response = $this->getJson('/api/transactions/NON_EXISTENT');

    $response->assertStatus(404);
});

it('returns 401 if unauthenticated', function () {
    $response = $this->getJson('/api/transactions/REF_123');

    $response->assertStatus(401);
});

it('returns 404 if business tries to access another business transaction', function () {
    $otherBusiness = Business::create([
        'name' => 'Other Business',
        'email' => 'other@business.com',
        'owner_id' => User::factory()->create()->id,
    ]);
    Sanctum::actingAs($otherBusiness, ['*'], 'business');

    $response = $this->getJson('/api/transactions/REF_123');

    $response->assertStatus(404);
});
