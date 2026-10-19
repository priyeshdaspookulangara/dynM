<?php
// Configuration file for DB Connection and Application Settings

return [
    'db' => [
        'driver' => 'sqlite', // 'sqlite' or 'mysql'

        // SQLite configuration
        'sqlite' => [
            'path' => __DIR__ . '/database/app.sqlite',
        ],

        // MySQL configuration
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'dbname' => 'mlm_app',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4'
        ]
    ],
    'app' => [
        'name' => 'Multi-Tier Direct Selling App',
        'currency_symbol' => '₹', // INR
        'currency_code' => 'INR',
    ]
];
