<?php

declare(strict_types=1);

namespace Domain\Payment\Entities;

use DateTimeImmutable;
use Domain\Payment\DataTransferObjects\CustomerData;
use Illuminate\Support\Str;
use Support\Exceptions\InvalidArgumentException;
use Support\ValueObjects\Email;
use Support\ValueObjects\Phone;

final class Customer
{
    public function __construct(
        public string $id,
        public ?string $firstName,
        public ?string $lastName,
        public readonly ?Email $email,
        public readonly ?Phone $phone,
        public readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
        if ($this->email && $this->phone) {
            throw new InvalidArgumentException('email and phone must be provided');
        }
    }

    public static function create(CustomerData $data): self
    {
        return new self(
            id: Str::ulid()->toString(),
            firstName: $data->firstName,
            lastName: $data->lastName,
            email: $data->email ? new Email($data->email) : null,
            phone: $data->phone ? new Phone($data->phone) : null,
            createdAt: now()->toDateTimeImmutable(),
            updatedAt: now()->toDateTimeImmutable(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => (string) $this->email,
            'phone' => (string) $this->phone,
            'created_at' => $this->createdAt,
        ];
    }
}
