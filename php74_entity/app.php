<?php

spl_autoload_register(fn($class) =>
    require __DIR__ . '/src/' . strtr($class, '\\', '/') . '.php'
);

$p = new \Entity\Person;
$p->name = new \Entity\PersonName('Dmytro');

var_dump($p);
