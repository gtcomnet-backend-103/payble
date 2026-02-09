<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Services;

use App\Domains\Payments\Providers\Adapters\PaystackAdapter;
use App\Domains\Payments\Providers\Contracts\ProviderAdapter;
use App\Models\Provider;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;

class ProviderResolver
{
    public function resolve(Provider $provider): ProviderAdapter
    {
        // If provider is in test mode or app is local/testing, use TestPayoutAdapter for Payouts?
        // Wait, ProviderResolver is generic for Payments too.
        // If I force TestPayoutAdapter for EVERYTHING in test mode, I break Payments which might use a different test adapter (e.g. Paystack Test Mode).
        // PaystackAdapter handles both test and live keys via config.
        // BUT for Payouts, the user said "We're just going to make another adapter to use on test mode".
        // Does this apply to Payments too?
        // The guide context was Payouts.
        // I should only use TestPayoutAdapter if the operation is Payout?
        // `resolve` doesn't know the operation.
        // However, `TestPayoutAdapter` implements `ProviderAdapter` which has `authorize` (Payment) and `initiateTransfer` (Payout).
        // My `TestPayoutAdapter` stubbed `authorize` with Exception!
        // So if I return `TestPayoutAdapter` for a Payment, it will crash.

        // Strategy:
        // 1. Add context to `resolve`? No, it breaks strict interface.
        // 2. Different resolver for Payouts? `PayoutProviderResolver`?
        // 3. Check if `is_payout_enabled` is true? No.

        // The user said: "I dont think you need to touch this [PaystackAdapter], we're just going to make another adapter to use on test mode, so every adapter has live and test implementation".
        // This implies `PaystackAdapter` handles Live, and `TestPayoutAdapter` handles Test?
        // IF so, how do we distinguish?
        // Maybe `Provider` model has a flag or we look at `payout_adapter` column (which I didn't add).

        // Plan B:
        // PayoutService uses a specific logic to resolve adapter.
        // `ProcessPayout` uses `ProviderResolver`.
        // If I modify `ProviderResolver`, it affects Payments.

        // Solution:
        // Check if `TestPayoutAdapter` handles payments?
        // I implemented it to THROW on authorize.
        // So I cannot return it for generic `resolve` unless I know it's a Payout.
        // But `ProviderResolver` signature is `resolve(Provider $provider): ProviderAdapter`.

        // I will create `PayoutProviderResolver` extends `ProviderResolver` or similar?
        // Or just add logic in `ProcessPayout` to use a different resolver or manual resolution?
        // "Update ProviderResolver::resolve to support test mode redirection." - Plan.

        // Maybe I should update `TestPayoutAdapter` to support Payments too (Mock Payment)?
        // Or checks `config('payment.mode')`?

        // Let's assume for now that if `provider->mode->isTest()`, we use `TestPayoutAdapter`.
        // BUT this breaks `Paystack` test mode for payments if `TestPayoutAdapter` throws.
        // `PaystackAdapter` handles test/live via keys.

        // I will MODIFY `ProviderResolver` to check if the class exists in config.
        // `config('payment.adapters')` maps identifier -> class.
        // I can add `test_payout` provider?
        // OR better: `ProcessPayout` calls `resolveForPayout`?

        // Use `resolve` but if it's test mode AND strictly for payout... 
        // I can't know it's for payout.

        // I'll update `TestPayoutAdapter` to implement `authorize` properly (Mock Payment) so it's safe to use generally in Test Mode?
        // Or I add `resolveForPayout` to `ProviderResolver`?

        // I'll add `resolveForPayout` method to `ProviderResolver`? No, keeping it clean.
        // I'll check `implementation_plan.md` again.
        // "Update ProviderResolver::resolve to support test mode redirection."

        // User said: "so every adapter has live and test implementation".
        // Maybe `paystack` -> `PaystackAdapter` (Live)
        // `paystack_test` -> `TestPayoutAdapter` (Test)?
        // But Provider identifier is `paystack` in DB.

        // Valid approach:
        // In `ProviderResolver`:
        // if ($provider->mode->isTest()) {
        //    return App::make(TestPayoutAdapter::class);
        // }
        // AND `TestPayoutAdapter` must support `authorize` (Mock).
        // This unifies Test Mode behavior.

        // I will update `TestPayoutAdapter` to support `authorize` (return success).
        // Then I can safely use it in `ProviderResolver`.

        if ($provider->mode->isTest()) {
            return App::make(\App\Domains\Payments\Providers\Adapters\TestPayoutAdapter::class);
        }

        $map = config('payment.adapters', [
            'paystack' => PaystackAdapter::class,
        ]);

        $class = $map[$provider->identifier] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("No adapter found for provider: {$provider->identifier}");
        }

        return App::make($class);
    }
}
