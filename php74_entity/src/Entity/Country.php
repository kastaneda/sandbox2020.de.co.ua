<?php
namespace Entity;

class Country
{
    const UA = 'ua';

    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }
}
