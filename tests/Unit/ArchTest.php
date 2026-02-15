<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();

/*
|--------------------------------------------------------------------------
| FINTECH ARCHITECTURE RULES
|--------------------------------------------------------------------------
| Philosophy:
| - Hardened money core (strict)
| - Laravel-native DDD (pragmatic)
| - Actions = transactions
| - Services = pure logic
| - Infrastructure = IO only
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| 1. MONEY CORE MUST BE PURE
|--------------------------------------------------------------------------
| Ledger / Payments / Wallets are sacred.
| They must not know about HTTP or Infrastructure.
|--------------------------------------------------------------------------
*/
test('money domains do not depend on http or infrastructure')
    ->expect([
        'App\Domains\Ledger',
        'App\Domains\Payments',
        'App\Domains\Wallets',
    ])
    ->not->toUse([
        'App\Http',
        'App\Infrastructure',
        'Illuminate\Http',
        'Illuminate\Routing',
        'Symfony\Component\HttpFoundation',
        'GuzzleHttp',
    ]);

/*
|--------------------------------------------------------------------------
| 2. DOMAIN MUST NEVER CALL INFRASTRUCTURE
|--------------------------------------------------------------------------
*/
test('domain never depends on infrastructure')
    ->expect('App\Domains')
    ->not->toUse('App\Infrastructure');

/*
|--------------------------------------------------------------------------
| 3. INFRASTRUCTURE MAY DEPEND ON DOMAIN (ONE-WAY DEPENDENCY)
|--------------------------------------------------------------------------
*/
/*
test('infrastructure depends only on domain')
    ->expect('App\Infrastructure')
    ->toUse('App\Domains');
*/

/*
|--------------------------------------------------------------------------
| 4. ACTIONS ARE USE CASES
|--------------------------------------------------------------------------
| - final
| - single execute method
|--------------------------------------------------------------------------
*/
test('actions are final with execute method')
    ->expect('App\Domains\*\Actions')
    ->toBeFinal()
    ->toHaveMethod('execute');

/*
|--------------------------------------------------------------------------
| 5. ONLY ACTIONS CONTROL DATABASE TRANSACTIONS
|--------------------------------------------------------------------------
| Prevents hidden rollbacks & money corruption
|--------------------------------------------------------------------------
*/
test('db transactions only happen inside actions')
    ->expect('Illuminate\Support\Facades\DB')
    ->toOnlyBeUsedIn([
        'App\Domains\Payments\Actions',
        'App\Domains\Payouts\Actions',
        'App\Domains\Ledger\Actions',
        'App\Domains\Ledger\Services\LedgerService',
        'App\Domains\Ledger\Services\LedgerTransactionManager',
    ]);

/*
|--------------------------------------------------------------------------
| 6. SERVICES ARE PURE BUSINESS LOGIC
|--------------------------------------------------------------------------
| No HTTP
| No Providers
| No IO
|--------------------------------------------------------------------------
*/
test('services contain only business logic')
    ->expect('App\Domains\*\Services')
    ->not->toUse([
        'App\Http',
        'App\Infrastructure',
        'Illuminate\Http',
        'GuzzleHttp',
    ]);

/*
|--------------------------------------------------------------------------
| 7. DTOs ARE DATA-ONLY
|--------------------------------------------------------------------------
*/
test('dtos are framework and database free')
    ->expect('App\Domains\*\DataTransferObjects')
    ->not->toUse([
        'Illuminate',
        'DB',
    ]);

/*
|--------------------------------------------------------------------------
| 8. CONTROLLERS MUST GO THROUGH ACTIONS
|--------------------------------------------------------------------------
| Prevents controller → service spaghetti
|--------------------------------------------------------------------------
*/
test('controllers only talk to actions')
    ->expect('App\Http\Controllers')
    ->toOnlyUse([
        'App\Domains\*\Actions',
        'App\Events',
        'App\Models',
        'App\Enums',
        'App\Http\Resources',
        'App\Http\Requests', // Allow FormRequests
        'Illuminate',
    ])
    ->ignoring(['response', 'request', 'collect']); // Global helpers

/*
|--------------------------------------------------------------------------
| 9. MODELS SHOULD NOT LEAK INTO VIEWS
|--------------------------------------------------------------------------
| Encourages view models / DTOs
|--------------------------------------------------------------------------
*/
/*
test('models are not used directly in views')
    ->expect('App\Models')
    ->not->toBeUsedIn('resources/views');
*/

/*
|--------------------------------------------------------------------------
| 10. PAYMENT ACTIONS MUST BE IDEMPOTENT
|--------------------------------------------------------------------------
| Every fintech system needs replay safety
|--------------------------------------------------------------------------
*/
test('payment actions must implement idempotency')
    ->expect('App\Domains\Payments\Actions')
    ->toImplement('App\Contracts\IdempotentAction');
