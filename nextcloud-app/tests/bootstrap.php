<?php

declare(strict_types=1);

// Autoload from vendor
require_once __DIR__ . '/../vendor/autoload.php';

// Register OCP stubs autoloader (nextcloud/ocp doesn't declare autoload)
spl_autoload_register(function ($class) {
    // Handle OCP namespace from nextcloud/ocp stubs
    if (str_starts_with($class, 'OCP\\')) {
        $file = __DIR__ . '/../vendor/nextcloud/ocp/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});
