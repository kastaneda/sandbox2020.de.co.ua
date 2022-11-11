<?php

spl_autoload_register(fn($class) =>
    require __DIR__ . '/src/' . strtr($class, '\\', '/') . '.php'
);
