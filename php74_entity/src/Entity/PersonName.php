<?php
namespace Entity;

class PersonName
{
    public string $first;
    public ?string $last;

    public function __construct(string $first, string $last = null)
    {
        $this->first = $first;
        if (!empty($last)) {
            $this->last = $last;
        }
    }
}