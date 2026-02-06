<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Actions;

use App\Enums\AccessLevel;
use App\Enums\PaymentMode;
use App\Models\ApiToken;
use App\Models\Business;
use Illuminate\Support\Str;
use RuntimeException;

final class GenerateApiKeys
{
    /**
     * Construct the user-facing key string.
     */
    public static function formatKey(ApiToken $token): string
    {
        $prefix = $token->access_level === AccessLevel::Public ? 'pk' : 'sk';

        return "{$prefix}_{$token->mode->value}_{$token->lookup_key}";
    }

    /**
     * Generate or regenerate API keys for a business.
     *
     * @param  PaymentMode|null  $mode  If specified, only generate keys for this mode
     *
     * @throws RuntimeException if attempting to generate live keys for unverified business
     */
    public function execute(Business $business, ?PaymentMode $mode = null): void
    {
        // If mode is specified, only generate for that mode
        if ($mode !== null) {
            // Validate: can only generate live keys if business is verified
            if ($mode === PaymentMode::Live && ! $business->isVerified()) {
                throw new RuntimeException('Cannot generate live API keys for unverified business');
            }

            foreach (AccessLevel::cases() as $level) {
                $this->createKey($business, $mode, $level);
            }

            return;
        }

        // Backward compatibility: generate for all modes based on verification
        foreach (PaymentMode::cases() as $paymentMode) {
            // Skip live mode if business is not verified
            if ($paymentMode === PaymentMode::Live && ! $business->isVerified()) {
                continue;
            }

            foreach (AccessLevel::cases() as $level) {
                $this->createKey($business, $paymentMode, $level);
            }
        }
    }

    private function createKey(Business $business, PaymentMode $mode, AccessLevel $level): void
    {
        $lookupKey = Str::random(64);

        // Create a Sanctum token for this specific access level and mode
        $tokenName = "{$level->value}_{$mode->value}_token";
        $sanctumToken = $business->createToken($tokenName);

        ApiToken::updateOrCreate(
            [
                'business_id' => $business->id,
                'access_level' => $level,
                'mode' => $mode,
            ],
            [
                'lookup_key' => $lookupKey,
                'auth_key' => $sanctumToken->plainTextToken,
            ]
        );
    }
}
