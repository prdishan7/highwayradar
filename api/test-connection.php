<?php
// Simple connection test
// Access at: http://localhost/highway/api/test-connection.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/util.php';

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Config file
try {
    $config = require __DIR__ . '/config.php';
    $driver = $config['db']['driver'] ?? 'sqlite';
    $results['tests']['config'] = [
        'status' => 'ok',
        'db_driver' => $driver,
        'db_name' => $driver === 'sqlite' ? ($config['db']['sqlite_path'] ?? '') : $config['db']['name']
    ];
} catch (Exception $e) {
    $results['tests']['config'] = ['status' => 'error', 'message' => $e->getMessage()];
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}

// Test 2: Database connection
try {
    require_once __DIR__ . '/db.php';
    $pdo = db();
    $results['tests']['database'] = ['status' => 'ok', 'message' => 'Connected successfully'];
    
    // Test query
    $stmt = $pdo->query('SELECT 1 as test');
    $results['tests']['database']['query_test'] = 'ok';
    
    // Check tables
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
    $results['tests']['database']['tables'] = [
        'found' => $existing,
        'missing' => array_diff($tables, $existing),
        'all_present' => count($existing) === count($tables)
    ];
    
} catch (PDOException $e) {
    $results['tests']['database'] = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ];
} catch (Exception $e) {
    $results['tests']['database'] = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// Test 3: Routing
$results['tests']['routing'] = [
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not set',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'not set',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'not set'
];

echo json_encode($results, JSON_PRETTY_PRINT);
