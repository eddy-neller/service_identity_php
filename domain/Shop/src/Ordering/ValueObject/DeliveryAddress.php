<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\ValueObject;

use App\Domain\Shop\Customer\Model\Address;
use InvalidArgumentException;

final class DeliveryAddress
{
    private function __construct(
        private readonly string $label,
        private readonly string $firstname,
        private readonly string $lastname,
        private readonly ?string $company,
        private readonly string $street,
        private readonly string $zipCode,
        private readonly string $city,
        private readonly string $country,
        private readonly string $phone,
    ) {
    }

    public static function fromValues(
        string $label,
        string $firstname,
        string $lastname,
        string $street,
        string $zipCode,
        string $city,
        string $country,
        string $phone,
        ?string $company = null,
    ): self {
        return new self(
            label: self::assertLength($label, 2, 100, 'Address label'),
            firstname: self::assertLength($firstname, 2, 32, 'Firstname'),
            lastname: self::assertLength($lastname, 2, 32, 'Lastname'),
            company: self::assertOptionalLength($company, 2, 50, 'Company'),
            street: self::assertLength($street, 2, 150, 'Street'),
            zipCode: self::assertLength($zipCode, 2, 30, 'Zip code'),
            city: self::assertLength($city, 2, 50, 'City'),
            country: self::assertLength($country, 2, 50, 'Country'),
            phone: self::assertLength($phone, 2, 30, 'Phone'),
        );
    }

    public static function fromAddress(Address $address): self
    {
        return self::fromValues(
            label: $address->getLabel(),
            firstname: $address->getFirstname(),
            lastname: $address->getLastname(),
            street: $address->getStreet(),
            zipCode: $address->getZipCode(),
            city: $address->getCity(),
            country: $address->getCountry(),
            phone: $address->getPhone(),
            company: $address->getCompany(),
        );
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getZipCode(): string
    {
        return $this->zipCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    private static function assertLength(string $value, int $min, int $max, string $label): string
    {
        $trimmed = trim($value);
        $length = strlen($trimmed);

        if ($length < $min || $length > $max) {
            throw new InvalidArgumentException(sprintf('%s must be between %d and %d characters.', $label, $min, $max));
        }

        return $trimmed;
    }

    private static function assertOptionalLength(?string $value, int $min, int $max, string $label): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::assertLength($value, $min, $max, $label);
    }
}
