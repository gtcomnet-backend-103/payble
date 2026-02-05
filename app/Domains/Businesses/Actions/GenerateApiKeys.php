<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Actions;

use App\Enums\AccessLevel;
use App\Enums\PaymentMode;
use App\Models\Business;
use App\Models\ApiToken;
use Illuminate\Support\Str;

final class GenerateApiKeys
{
    /**
     * Generate or regenerate API keys for a business.
     */
    public function execute(Business $business): void
    {
        // Generate for all combinations
        foreach (PaymentMode::cases() as $mode) {
            foreach (AccessLevel::cases() as $level) {
                $this->createKey($business, $mode, $level);
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

    /**
     * Construct the user-facing key string.
     */
    public static function formatKey(ApiToken $token): string
    {
        $prefix = $token->access_level === AccessLevel::Public ? 'pk' : 'sk';
        return "{$prefix}_{$token->mode->value}_{$token->lookup_key}";
    }
}
