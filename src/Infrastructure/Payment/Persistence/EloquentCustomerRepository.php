<?php

declare(strict_types=1);

namespace Infrastructure\Payment\Persistence;

use App\Models\Customer;
use Domain\Payment\Contracts\CustomerRepositoryInterface;
use Domain\Payment\DataTransferObjects\CustomerData;
use Domain\Payment\Entities\Customer as CustomerEntity;
use Support\Entities\Business as BusinessEntity;
use Support\ValueObjects\Email;
use Support\ValueObjects\Phone;

final class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    public function findOrCreateByData(BusinessEntity $business, CustomerData $customerData): CustomerEntity
    {
        $customer = Customer::query()
            ->whereHas('business', function ($query) use ($business) {
                $query->where('merchant_id', $business->id);
            })
            ->where(function ($q) use ($customerData) {
                $q->where('email', $customerData->email)
                    ->orWhere('phone', $customerData->phone);
            })
            ->first();

        if (! $customer) {
            $customer = CustomerEntity::create($customerData);
            $this->save($business, $customer);

            return $customer;
        }

        return $this->toDomain($customer);
    }

    public function toDomain(Customer $customer): CustomerEntity
    {
        return new CustomerEntity(
            id: $customer->customer_id,
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            email: $customer->email ? new Email($customer->email) : null,
            phone: $customer->phone ? new Phone($customer->phone) : null,
            createdAt: $customer->created_at,
            updatedAt: $customer->updated_at,
        );
    }

    public function save(BusinessEntity $business, CustomerEntity $customer)
    {
        $business = \App\Models\Business::where('merchant_id', $business->id)->firstOrFail();
        Customer::create([
            'business_id' => $business->id,
            'first_name' => $customer->firstName,
            'last_name' => $customer->lastName,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
        ]);
    }
}
