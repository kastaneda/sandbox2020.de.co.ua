<?php
namespace Entity;

class PostalAddress
{
    public string $streetAddress;
    public string $postalCode;
    public string $city;
    public ?string $state;
    public Country $country;

    public function __construct(
        string $streetAddress,
        string $postalCode,
        string $city,
        ?string $state = null,
        Country $country
    ) {
        $this->streetAddress = $streetAddress;
    }
}