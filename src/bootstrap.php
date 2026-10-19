<?php

// Autoloader and Application Bootstrap

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;

// Auto-initialize SQLite database schema on first run if database file or tables don't exist
try {
    Database::initSchema();
} catch (\Exception $e) {
    // Schema already initialized or connection error handled in Database::getConnection
}
