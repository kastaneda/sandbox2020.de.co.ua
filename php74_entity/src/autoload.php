<?php

// PSR-4
spl_autoload_register(function ($className) {
    require __DIR__ . strtr($className, '\\', '/') . '.php';
});
