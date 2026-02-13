<?php

declare(strict_types=1);

namespace Infrastructure\Payment\Persistence;

use App\Models\Business;
use App\Models\Customer;
use App\Models\PaymentIntent;
use Domain\Payment\Contracts\PaymentRepositoryInterface;
use Domain\Payment\Entities\PaymentRequest as PaymentEntity;
use Support\Exceptions\DuplicateReferenceErrorException;
use Support\ValueObjects\Money;
use Support\ValueObjects\Reference;
use Support\ValueObjects\Ulid;

final class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function save(PaymentEntity $request): void
    {
        $business = Business::where('merchant_id', $request->business->id)->firstOrFail();
        $customer = Customer::where('customer_id', $request->customer->id)->firstOrFail();

        if (PaymentIntent::where(['reference' => $request->reference, 'business_id' => $business->id])->first()) {
            throw new DuplicateReferenceErrorException();
        }

        PaymentIntent::updateOrCreate(
            ['payment_id' => $request->id],
            [
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'reference' => (string) $request->reference,
                'amount' => $request->amount->value,
                'currency' => $request->amount->currency,
                'status' => $request->getStatus(),
                'bearer' => $request->bearer,
                'mode' => $request->mode,
            ]
        );
    }

    public function findByReference(Reference $reference): ?PaymentEntity
    {
        $model = PaymentIntent::where('reference', $reference)->first();

        if (! $model) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function toDomain(PaymentIntent $model): PaymentEntity
    {
        return new PaymentEntity(
            id: Ulid::fromString($model->payment_id),
            reference: Reference::fromString((string) $model->reference),
            amount: new Money($model->amount, $model->currency),
            business: $model->business->toDomain(),
            customer: $model->customer->toDomain(),
            bearer: $model->bearer,
            status: $model->status,
            mode: $model->mode,
            metadata: $model->metadata ?? [],
            createdAt: $model->created_at->toDateTimeImmutable(),
            updatedAt: $model->updated_at->toDateTimeImmutable()
        );
    }
}
