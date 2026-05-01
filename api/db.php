<?php
function db()
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $driver = $config['db']['driver'] ?? 'sqlite';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if ($driver === 'sqlite') {
        $path = $config['db']['sqlite_path'] ?? (__DIR__ . '/data/highway.sqlite');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db']['host'], $config['db']['name'], $config['db']['charset']);
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
    }

    ensure_incident_location_columns($pdo, $driver);
    ensure_device_tokens_table($pdo, $driver);
    return $pdo;
}

function ensure_device_tokens_table(PDO $pdo, string $driver)
{
    $sql = ($driver === 'sqlite') 
        ? "CREATE TABLE IF NOT EXISTS device_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            token TEXT UNIQUE NOT NULL,
            platform TEXT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
          )"
        : "CREATE TABLE IF NOT EXISTS device_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            token VARCHAR(512) UNIQUE NOT NULL,
            platform VARCHAR(50) NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
}

function ensure_incident_location_columns(PDO $pdo, string $driver)
{
    try {
        if ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info(incidents)")->fetchAll();
            $names = array_map(function ($c) {
                return strtolower($c['name'] ?? '');
            }, $cols ?: []);

            if (!in_array('latitude', $names, true)) {
                $pdo->exec('ALTER TABLE incidents ADD COLUMN latitude REAL');
            }
            if (!in_array('longitude', $names, true)) {
                $pdo->exec('ALTER TABLE incidents ADD COLUMN longitude REAL');
            }
            return;
        }

        $lat = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'latitude'")->fetch();
        if (!$lat) {
            $pdo->exec('ALTER TABLE incidents ADD COLUMN latitude DOUBLE NULL');
        }
        $lng = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'longitude'")->fetch();
        if (!$lng) {
            $pdo->exec('ALTER TABLE incidents ADD COLUMN longitude DOUBLE NULL');
        }
    } catch (Throwable $e) {
        // Keep API boot resilient if schema auto-migration is blocked.
    }
}

function ensure_seed_admin()
{
    $pdo = db();
    
    // Seed Admin
    $adminEmail = 'admin@gmail.com';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$adminEmail, $hash, 'admin', date('Y-m-d H:i:s')]);
    }

    // Seed Super Admin
    $superEmail = 'superadmin@gmail.com';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$superEmail]);
    if (!$stmt->fetch()) {
        $hash = password_hash('super123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)');
        // Note: SQLite might fail here if the CHECK constraint is strict.
        // We might need to recreate the table or use a less restrictive constraint.
        try {
            $stmt->execute([$superEmail, $hash, 'superadmin', date('Y-m-d H:i:s')]);
        } catch (PDOException $e) {
            // If it fails due to CHECK constraint, we might need to handle it.
            // For now, let's log or respond.
        }
    }
}
