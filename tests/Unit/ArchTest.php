<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();

// 1. Domain Encapsulation: The Domain should never depend on the Infrastructure
test('domain should not depend on infrastructure or app layers')
    ->expect('App\Domains')
    ->toOnlyUse([
        'App\Domains', // Sub-domains can talk to each other
        'App\Enums',   // Global Enums
        'App\Models',  // Domain Models (if following Laravel-style DDD)
        'Illuminate\Support', // Helpers and Collections
        'Illuminate\Support\Facades\DB', // For Transactions
    ])
    ->ignoring('Illuminate\Support\Facades\Log');

// 2. Strict DTOs: DTOs must be data-only and should not know about Eloquent
test('dtos should not depend on models or database')
    ->expect('App\Domains\*\DataTransferObjects')
    ->not->toUse([
        'Illuminate\Database\Eloquent\Model',
        'App\Models',
    ]);

// 3. Service Layer Responsibility
test('services should be the only ones handling ledger transactions')
    ->expect('App\Domains\Ledger\Services')
    ->toOnlyBeUsedIn([
        'App\Domains\Ledger\Actions',
        'App\Domains\Ledger\Facades',
        'App\Http\Controllers', // Optionally restrict this to Actions only
    ]);

// 4. Action Strictness
test('actions should be final and have a single execute method')
    ->expect('App\Domains\*\Actions')
    ->toBeFinal()
    ->toHaveMethod('execute');

// 5. Infrastructure Layer
test('infrastructure should handle external providers')
    ->expect('App\Infrastructure')
    ->toUse([
        'App\Domains',
        'Illuminate\Support',
        'GuzzleHttp',
    ]);

// 6. Model Protection
test('models should not be used in the view layer')
    ->expect('App\Models')
    ->not->toBeUsedIn('resources/views');
