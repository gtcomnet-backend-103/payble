<?php

declare(strict_types=1);

use App\Enums\FeeBearer;
use App\Models\Business;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Test Business',
        'email' => 'test@business.com',
        'owner_id' => $this->user->id,
    ]);
    $this->user->businesses()->attach($this->business);
    Sanctum::actingAs($this->business, ['*'], 'business');
});

it('validates required fields', function () {

    $this->postJson('/api/payments', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount', 'email']);
});

it('creates a payment request with email', function () {
    $response = $this->postJson('/api/payments', [
        'amount' => 500000,
        'email' => 'customer@example.com',
        'reference' => 'REF-123456',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'amount',
                'currency',
                'transaction_date',
                'status',
                'reference',
                'mode',
                'metadata',
                'channel',
                'fees',
                'customer' => ['first_name', 'last_name', 'email', 'phone'],
            ],
        ])
        ->assertJsonPath('data.amount', 500000)
        ->assertJsonPath('data.customer.email', 'customer@example.com');

    $this->assertDatabaseHas('payment_intents', [
        'amount' => 500000,
        'reference' => 'REF-123456',
    ]);

    $this->assertDatabaseHas('transactions', [
        'amount' => 500000,
        'reference' => 'REF-123456',
    ]);

    $this->assertDatabaseHas('customers', [
        'email' => 'customer@example.com',
    ]);
});

it('creates a payment request with phone', function () {
    $response = $this->postJson('/api/payments', [
        'amount' => 100000,
        'phone' => '2348000000000',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.customer.phone', '2348000000000');

    $this->assertDatabaseHas('customers', [
        'phone' => '2348000000000',
    ]);
});

it('creates a payment request with reference in another business', function () {
    $this->actingAs(Business::factory()->create(), 'business')->postJson('/api/payments', [
        'amount' => 100000,
        'email' => 'test@email.com',
        'reference' => 'REF-1234',
    ]);

    $response = $this->actingAs(Business::factory()->create(), 'business')->postJson('/api/payments', [
        'amount' => 100000,
        'email' => 'test@email.com',
        'reference' => 'REF-1234',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.reference', 'REF-1234')
        ->assertJsonPath('data.customer.email', 'test@email.com');
});

it('creates a payment request with reference', function () {
    $response = $this->postJson('/api/payments', [
        'amount' => 100000,
        'email' => 'test@email.com',
        'reference' => 'REF-1234',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.reference', 'REF-1234')
        ->assertJsonPath('data.customer.email', 'test@email.com');
});

it('cannot create a payment request with existing reference', function () {
    $this->postJson('/api/payments', [
        'amount' => 100000,
        'email' => 'test@email.com',
        'reference' => 'REF-1234',
    ]);

    $this->postJson('/api/payments', [
        'amount' => 100000,
        'email' => 'test@email.com',
        'reference' => 'REF-1234',
    ])->assertJsonValidationErrors(['reference']);
});

it('creates a payment request with currency', function () {
    $response = $this->postJson('/api/payments', [
        'amount' => 100000,
        'email' => 'test@email.com',
        'currency' => 'USD',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.currency', 'USD');
});

it('creates a payment request with bearer', function (FeeBearer $bearer) {
    $this->postJson('/api/payments', [
        'amount' => 100000,
        'email' => 'test@email.com',
        'bearer' => $bearer->value,
    ]);

    assertDatabaseHas('payment_intents', [
        'bearer' => $bearer->value,
    ]);
})->with(FeeBearer::cases());

it('creates a payment request with metadata', function () {
    $response = $this->postJson('/api/payments', [
        'amount' => 100000,
        'email' => 'test@email.com',
        'metadata' => [
            'foo' => 'bar',
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.metadata', ['foo' => 'bar'])
        ->assertJsonPath('data.customer.email', 'test@email.com');
});
