<?php

spl_autoload_register(function ($className) {
    require __DIR__ . '/src/' . strtr($className, '\\', '/') . '.php';
});

$p = new \Entity\Person;
$p->name = new \Entity\PersonName('Dmytro');

var_dump($p);