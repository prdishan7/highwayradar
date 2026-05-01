<?php
// Quick database connection test
// Access at: http://localhost/highway/api/test-db.php

header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/config.php';
    
    $driver = $config['db']['driver'] ?? 'sqlite';
    if ($driver === 'sqlite') {
        $path = $config['db']['sqlite_path'] ?? (__DIR__ . '/data/highway.sqlite');
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
            $config['db']['host'],
            $config['db']['name'],
            $config['db']['charset']
        );
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
    
    // Test query
    $stmt = $pdo->query('SELECT 1 as test');
    $result = $stmt->fetch();
    
    // Check if tables exist
    $tables = ['users', 'incidents', 'highway_status', 'audit_log'];
    $existing = [];
    foreach ($tables as $table) {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$table]);
            if ($stmt->fetch()) {
                $existing[] = $table;
            }
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existing[] = $table;
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection successful',
        'database' => $driver === 'sqlite' ? ($config['db']['sqlite_path'] ?? '') : $config['db']['name'],
        'tables_found' => $existing,
        'tables_missing' => array_diff($tables, $existing)
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'config' => [
            'driver' => $driver,
            'sqlite_path' => $config['db']['sqlite_path'] ?? '',
            'host' => $config['db']['host'],
            'database' => $config['db']['name'],
            'user' => $config['db']['user'],
            'password_set' => !empty($config['db']['pass'])
        ]
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}


