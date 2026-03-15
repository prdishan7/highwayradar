<?php
// Basic configuration for the "highway gbbs" PHP API.
// Fill in the database credentials before deploying to cPanel.

return [
    'app_name' => 'highway gbbs',
    'db' => [
        'driver' => getenv('HG_DB_DRIVER') ?: 'sqlite',
        'sqlite_path' => getenv('HG_SQLITE_PATH') ?: __DIR__ . '/data/highway.sqlite',
        'host' => getenv('HG_DB_HOST') ?: '127.0.0.1',
        'name' => getenv('HG_DB_NAME') ?: 'highway_gbbs',
        'user' => getenv('HG_DB_USER') ?: 'root',  // WAMP default
        'pass' => getenv('HG_DB_PASS') ?: '',      // WAMP default (usually empty)
        'charset' => 'utf8mb4',
    ],
    'jwt_secret' => getenv('HG_JWT_SECRET') ?: 'local-dev-secret-change-me',
    'jwt_issuer' => 'highway-gbbs-api',
    'firebase_db_url' => getenv('HG_FIREBASE_DB_URL') ?: 'https://highway-123e3-default-rtdb.asia-southeast1.firebasedatabase.app/',
    'fcm_server_key' => getenv('HG_FCM_SERVER_KEY') ?: 'YOUR_FCM_SERVER_KEY_HERE',
];
 
