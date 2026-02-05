<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Facades;

use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Models\Provider;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ProviderResponse authorize(Provider $provider, PaymentAuthorizeDTO $dto)
 * @method static bool verifyWebhook(Provider $provider, array $payload, array $headers)
 * @method static WebhookPayloadDTO normalizeWebhook(Provider $provider, array $payload)
 * @method static ProviderResponse verifyTransaction(Provider $provider, string $reference)
 * @method static int getFee(Provider $provider, \App\Enums\PaymentChannel $channel, int $amount)
 *
 * @see \App\Domains\Payments\Providers\Services\PaymentProvider
 */
final class PaymentProvider extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'payment-provider';
    }
}
