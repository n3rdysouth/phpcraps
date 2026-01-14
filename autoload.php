<?php
/**
 * Simple PSR-4 autoloader
 * If you have Composer installed, run 'composer install' and use vendor/autoload.php instead
 */

spl_autoload_register(function ($class) {
    // PSR-4 namespace mappings
    $prefixes = [
        'Craps\\Game\\' => __DIR__ . '/src/Game/',
        'Craps\\Database\\' => __DIR__ . '/src/Database/',
    ];

    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
