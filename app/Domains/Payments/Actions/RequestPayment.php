<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Contracts\IdempotentAction;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Enums\Currency;
use App\Enums\FeeBearer;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

final class RequestPayment implements IdempotentAction
{
    public function __construct(private LedgerPostingServiceInterface $ledgerPostingService)
    {
    }

    /**
     * @param array{
     *   amount: int,
     *   email?: string,
     *   phone?: string,
     *   currency?: string,
     *   reference?: string,
     *   bearer?: string,
     *   metadata?: array<string, mixed>,
     *   mode?: string
     * } $data
     *
     * @throws Throwable
     */
    public function execute(Business $business, array $data): Transaction
    {
        $data = Validator::make($data, [
            'amount' => ['required', 'integer', 'min:100'], // Assume minor units, min 1.00
            'email' => ['required_without:phone', 'nullable', 'email'],
            'phone' => ['required_without:email', 'nullable', 'string'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', Rule::enum(Currency::class)],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('transactions', 'reference')->where('business_id', $business->getKey())],
            'bearer' => ['nullable', 'string', 'in:account'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        return DB::transaction(function () use ($business, $data) {
            $customer = $this->resolveCustomer($business, $data);

            $currency = Currency::tryFrom($data['currency'] ?? 'NGN') ?? Currency::NGN;
            $amount = $data['amount'] ?? 0;
            $mode = PaymentMode::tryFrom(config('app.payment_mode') ?? ($data['mode'] ?? 'test')) ?? PaymentMode::Test;
            $bearer = FeeBearer::tryFrom($data['bearer'] ?? 'account') ?? FeeBearer::ACCOUNT;
            $reference = $data['reference'] ?? 'TRX_'.Str::random(10);

            $paymentIntent = PaymentIntent::create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'status' => PaymentStatus::Initiated,
                'bearer' => $bearer,
                'mode' => $mode,
                'metadata' => $data['metadata'] ?? [],
            ]);

            return $this->ledgerPostingService->recordTransaction($paymentIntent);
        });
    }

    private function resolveCustomer(Business $business, array $data): Customer
    {
        $query = Customer::query()->where('business_id', $business->id);

        if (isset($data['email'])) {
            $query->where('email', $data['email']);
        } elseif (isset($data['phone'])) {
            $query->where('phone', $data['phone']);
        }

        /** @var Customer|null $customer */
        $customer = $query->first();

        if ($customer) {
            return $customer;
        }

        return Customer::create([
            'business_id' => $business->id,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
        ]);
    }
}
