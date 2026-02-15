<?php

declare(strict_types=1);

namespace App\Supports\Providers\Facades;

use App\Models\Provider;
use App\Supports\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use App\Supports\Providers\DataTransferObjects\WebhookPayloadDTO;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Provider provider(string $channel)
 * @method static ProviderResponse authorize(Provider $provider, PaymentAuthorizeDTO $dto)
 * @method static bool verifyWebhook(Provider $provider, array $payload, array $headers)
 * @method static WebhookPayloadDTO normalizeWebhook(Provider $provider, array $payload)
 * @method static ProviderResponse verifyTransaction(Provider $provider, string $reference)
 * @method static int getFee(Provider $provider, \App\Enums\PaymentChannel $channel, int $amount)
 *
 * @see \App\Supports\Providers\Services\PaymentProvider
 */
final class PaymentProvider extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return 'payment-provider';
    }
}
