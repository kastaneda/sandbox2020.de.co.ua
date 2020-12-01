<?php
namespace Entity;

class Person
{
    public PersonName $name;
    public ?string $email;
    public ?PostalAddress $address;
    public ?\DateTime $dateOfBirth;
}
