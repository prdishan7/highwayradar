<?php
// Comprehensive diagnostic script
// Access at: http://localhost/highway/diagnose-issues.php

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Highway GBBS - Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .warning { background: #fff3cd; border-color: #ffc107; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <h1>Highway GBBS - System Diagnostics</h1>
    
    <?php
    // Test 1: PHP Version
    echo '<div class="test ' . (version_compare(PHP_VERSION, '7.4.0', '>=') ? 'success' : 'warning') . '">';
    echo '<h2>1. PHP Version</h2>';
    echo '<p>Version: ' . PHP_VERSION . '</p>';
    echo '</div>';

    // Test 2: Database Configuration
    echo '<div class="test">';
    echo '<h2>2. Database Configuration</h2>';
    try {
        $config = require __DIR__ . '/api/config.php';
        echo '<pre>' . json_encode($config['db'], JSON_PRETTY_PRINT) . '</pre>';
        echo '<p class="success">✓ Configuration loaded</p>';
    } catch (Exception $e) {
        echo '<p class="error">✗ Error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';

    // Test 3: Database Connection
    echo '<div class="test">';
    echo '<h2>3. Database Connection</h2>';
    try {
        $config = require __DIR__ . '/api/config.php';
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', 
            $config['db']['host'], 
            $config['db']['name'], 
            $config['db']['charset']
        );
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo '<p class="success">✓ Database connection successful!</p>';
        
        // Check tables
        $tables = ['users', 'incidents', 'highway_status', 'audit_log'];
        $existing = [];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existing[] = $table;
            }
        }
        
        echo '<p>Tables found: ' . implode(', ', $existing) . '</p>';
        echo '<p>Tables missing: ' . implode(', ', array_diff($tables, $existing)) . '</p>';
        
        if (count($existing) < count($tables)) {
            echo '<p class="warning">⚠ Some tables are missing. Run setup-database.sql</p>';
        } else {
            echo '<p class="success">✓ All required tables exist</p>';
        }
        
    } catch (PDOException $e) {
        echo '<p class="error">✗ Database connection failed!</p>';
        echo '<pre>' . $e->getMessage() . '</pre>';
        echo '<p><strong>Common fixes:</strong></p>';
        echo '<ul>';
        echo '<li>Check if MySQL is running in WAMP</li>';
        echo '<li>Verify database name: ' . ($config['db']['name'] ?? 'highway_gbbs') . '</li>';
        echo '<li>Check username/password in api/config.php</li>';
        echo '<li>Create database in phpMyAdmin if it doesn\'t exist</li>';
        echo '</ul>';
    } catch (Exception $e) {
        echo '<p class="error">✗ Error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';

    // Test 4: API File Structure
    echo '<div class="test">';
    echo '<h2>4. API File Structure</h2>';
    $files = [
        'api/index.php',
        'api/config.php',
        'api/db.php',
        'api/util.php',
        'api/middleware.php',
        'api/jwt.php',
        'api/.htaccess'
    ];
    $missing = [];
    foreach ($files as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missing[] = $file;
        }
    }
    if (empty($missing)) {
        echo '<p class="success">✓ All API files exist</p>';
    } else {
        echo '<p class="error">✗ Missing files: ' . implode(', ', $missing) . '</p>';
    }
    echo '</div>';

    // Test 5: .htaccess
    echo '<div class="test">';
    echo '<h2>5. Apache .htaccess</h2>';
    if (file_exists(__DIR__ . '/api/.htaccess')) {
        echo '<p class="success">✓ .htaccess file exists</p>';
        echo '<pre>' . htmlspecialchars(file_get_contents(__DIR__ . '/api/.htaccess')) . '</pre>';
        echo '<p class="warning">⚠ Make sure mod_rewrite is enabled in Apache</p>';
    } else {
        echo '<p class="error">✗ .htaccess file missing!</p>';
    }
    echo '</div>';

    // Test 6: API Endpoint Test
    echo '<div class="test">';
    echo '<h2>6. API Endpoint Test</h2>';
    echo '<p>Test these URLs in your browser:</p>';
    echo '<ul>';
    echo '<li><a href="http://localhost/highway/api/status" target="_blank">http://localhost/highway/api/status</a></li>';
    echo '<li><a href="http://localhost/highway/api/test-db.php" target="_blank">http://localhost/highway/api/test-db.php</a></li>';
    echo '</ul>';
    echo '<p>If these return JSON, the API is working.</p>';
    echo '</div>';

    // Test 7: Frontend Proxy Test
    echo '<div class="test">';
    echo '<h2>7. Frontend Proxy Configuration</h2>';
    echo '<p>Make sure:</p>';
    echo '<ul>';
    echo '<li>Vite dev server is running: <code>cd app && npm run dev</code></li>';
    echo '<li>Dev server is on port 5173</li>';
    echo '<li>Proxy is configured in <code>app/vite.config.js</code></li>';
    echo '<li>Test proxy: <a href="http://localhost:5173/api/status" target="_blank">http://localhost:5173/api/status</a></li>';
    echo '</ul>';
    echo '</div>';
    ?>

    <div class="test">
        <h2>8. Quick Actions</h2>
        <p><strong>If database connection fails:</strong></p>
        <ol>
            <li>Open phpMyAdmin: <a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a></li>
            <li>Create database: <code>highway_gbbs</code></li>
            <li>Run SQL from <code>setup-database.sql</code></li>
            <li>Check credentials in <code>api/config.php</code></li>
        </ol>
        
        <p><strong>If API endpoints don't work:</strong></p>
        <ol>
            <li>Check WAMP Apache is running (green icon)</li>
            <li>Verify mod_rewrite is enabled</li>
            <li>Check Apache error logs</li>
        </ol>
        
        <p><strong>If frontend can't connect:</strong></p>
        <ol>
            <li>Restart Vite dev server: <code>Ctrl+C</code> then <code>npm run dev</code></li>
            <li>Clear browser cache: <code>Ctrl+Shift+R</code></li>
            <li>Check browser console for errors</li>
            <li>Check Vite terminal for proxy errors</li>
        </ol>
    </div>
</body>
</html>


 
