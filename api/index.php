<?php
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/middleware.php';

cors();

try {
    $config = require __DIR__ . '/config.php';
    $pdo = db();
    ensure_seed_admin();
} catch (PDOException $e) {
    respond(500, ['error' => 'Database connection failed', 'message' => $e->getMessage()]);
} catch (Exception $e) {
    respond(500, ['error' => 'Server error', 'message' => $e->getMessage()]);
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$relative = '/' . ltrim(str_replace($base, '', $uri), '/');
$segments = array_values(array_filter(explode('/', trim($relative, '/'))));
$first = $segments[0] ?? null;
if ($first === 'index.php' || $first === 'api') {
    array_shift($segments);
}
$method = $_SERVER['REQUEST_METHOD'];

$categories = ['collision', 'landslide', 'flooding', 'obstacle', 'pothole', 'fire', 'sos', 'other'];
$severities = ['low', 'medium', 'high'];

function fetch_firebase($path)
{
    $config = require __DIR__ . '/config.php';
    $url = rtrim($config['firebase_db_url'], '/') . '/' . ltrim($path, '/') . '.json';
    $result = @file_get_contents($url);
    if ($result === false) {
        return null;
    }
    return json_decode($result, true);
}

function latest_incident(PDO $pdo, bool $verifiedOnly = false)
{
    $where = $verifiedOnly ? "WHERE i.status = 'verified'" : "";
    $stmt = $pdo->query("SELECT i.id, i.user_id, i.category, i.severity, i.description, i.latitude, i.longitude, i.image_base64, i.status, i.created_at,
                                u.email AS reporter_email, u.role AS reporter_role
                         FROM incidents i
                         LEFT JOIN users u ON u.id = i.user_id
                         $where
                         ORDER BY i.created_at DESC
                         LIMIT 1");
    $row = $stmt->fetch();
    return $row ?: null;
}

function latest_incidents(PDO $pdo, int $limit = 3, bool $verifiedOnly = false)
{
    $limit = max(1, min(20, $limit));
    $where = $verifiedOnly ? "WHERE i.status = 'verified'" : "";
    $stmt = $pdo->prepare("SELECT i.id, i.user_id, i.category, i.severity, i.description, i.latitude, i.longitude, i.image_base64, i.status, i.created_at,
                                  u.email AS reporter_email, u.role AS reporter_role
                           FROM incidents i
                           LEFT JOIN users u ON u.id = i.user_id
                           $where
                           ORDER BY i.created_at DESC
                           LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function latest_active_incident(PDO $pdo)
{
    // Only incidents that are still active (not resolved/false)
    $stmt = $pdo->query("SELECT i.id, i.user_id, i.category, i.severity, i.description, i.latitude, i.longitude, i.image_base64, i.status, i.created_at,
                                u.email AS reporter_email, u.role AS reporter_role
                         FROM incidents i
                         LEFT JOIN users u ON u.id = i.user_id
                         WHERE i.status NOT IN ('resolved','false')
                         ORDER BY i.created_at DESC
                         LIMIT 1");
    $row = $stmt->fetch();
    return $row ?: null;
}

function compute_status(PDO $pdo, bool $write = false)
{
    $sensor = fetch_firebase('live/sensor') ?: [];
    $sos = fetch_firebase('alerts/sos') ?: [];
    $incident = latest_incident($pdo, true); // latest for display (verified only for public)
    $recent = latest_incidents($pdo, 3, true); // latest few for UI (verified only for public)
    $activeIncident = latest_active_incident($pdo); // for risk decision fallback

    // Normalize timestamps to valid epoch ms; fallback to "now" if device only sends uptime ms.
    $nowMs = (int) round(microtime(true) * 1000);
    if (!isset($sensor['timestampMs']) || (int)$sensor['timestampMs'] < 100000000000) {
        $sensor['timestampMs'] = $nowMs;
    }
    if (!isset($sos['timestampMs']) || (int)$sos['timestampMs'] < 100000000000) {
        $sos['timestampMs'] = $nowMs;
    }

    $risk = strtoupper($sensor['riskLevel'] ?? '');
    $sosActive = !empty($sos['active']);
    // Use latest incident severity only if it is not resolved/false; else fall back to active incident
    $incidentSeverity = null;
    if ($incident && !in_array(strtolower($incident['status'] ?? ''), ['resolved', 'false'], true)) {
        $incidentSeverity = strtolower($incident['severity']);
    } elseif ($activeIncident) {
        $incidentSeverity = strtolower($activeIncident['severity']);
    }

    $status = 'Open & Safe';
    $source = 'auto';

    if ($sosActive || $risk === 'HIGH' || $incidentSeverity === 'high') {
        $status = 'Closed';
    } elseif ($risk === 'MEDIUM' || $incidentSeverity === 'medium') {
        $status = 'Warning';
    }

    $row = $pdo->query('SELECT * FROM highway_status ORDER BY updated_at DESC LIMIT 1')->fetch();

    if ($row && (int)$row['overridden_by_admin'] === 1) {
        $status = $row['status_text'];
        $source = 'admin';
    } elseif ($write) {
        $stmt = $pdo->prepare('REPLACE INTO highway_status (id, status_text, source, overridden_by_admin, updated_at) VALUES (1, ?, ?, 0, ?)');
        $stmt->execute([$status, $source, date('Y-m-d H:i:s')]);
    }

    return [
        'status_text' => $status,
        'source' => $source,
        'overridden_by_admin' => isset($row['overridden_by_admin']) ? (bool)$row['overridden_by_admin'] : false,
        'sensor' => $sensor,
        'sos' => $sos,
        'latest_incident' => $incident,
        'latest_incidents' => $recent,
        'updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
    ];
}

function handle_auth_login(PDO $pdo)
{
    $body = json_input();
    require_fields($body, ['email', 'password']);

    $stmt = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($body['email']))]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($body['password'], $user['password_hash'])) {
        respond(401, ['error' => 'Invalid credentials']);
    }

    $token = jwt_encode(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
    respond(200, ['token' => $token, 'user' => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]]);
}

function handle_auth_register(PDO $pdo, array $categories, array $severities)
{
    $body = json_input();
    require_fields($body, ['email', 'password', 'role']);

    $role = $body['role'];
    if (!in_array($role, ['driver', 'local'], true)) {
        respond(400, ['error' => 'Role must be driver or local']);
    }

    if (strlen($body['password']) < 6) {
        respond(400, ['error' => 'Password must be at least 6 characters']);
    }

    $email = strtolower(trim($body['email']));
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        respond(400, ['error' => 'Email already registered']);
    }

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $role, date('Y-m-d H:i:s')]);
    $userId = $pdo->lastInsertId();

    $token = jwt_encode(['id' => $userId, 'email' => $email, 'role' => $role]);
    respond(201, ['token' => $token, 'user' => ['id' => $userId, 'email' => $email, 'role' => $role]]);
}

function handle_me()
{
    $user = require_auth();
    respond(200, ['user' => $user]);
}

function handle_status(PDO $pdo, $method)
{
    if ($method === 'GET') {
        $payload = compute_status($pdo, true);
        respond(200, $payload);
    }

    if ($method === 'POST') {
        $user = require_auth(['admin', 'superadmin']);
        $body = json_input();
        if (!empty($body['clear_override'])) {
            // Reset to open/auto and clear any prior closed override
            $stmt = $pdo->prepare('REPLACE INTO highway_status (id, status_text, source, overridden_by_admin, updated_at) VALUES (1, ?, ?, 0, ?)');
            $stmt->execute(['Open & Safe', 'auto', date('Y-m-d H:i:s')]);
            $fresh = compute_status($pdo, false);
            respond(200, $fresh);
        }

        require_fields($body, ['status_text']);
        $status = sanitize_text($body['status_text']);
        $stmt = $pdo->prepare('REPLACE INTO highway_status (id, status_text, source, overridden_by_admin, updated_at) VALUES (1, ?, ?, 1, ?)');
        $stmt->execute([$status, 'admin', date('Y-m-d H:i:s')]);

        respond(200, ['status_text' => $status, 'source' => 'admin', 'overridden_by_admin' => true, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    respond(405, ['error' => 'Method not allowed']);
}

function validate_image(?string $dataUrl)
{
    if (!$dataUrl) {
        return null;
    }

    $clean = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
    $bytes = strlen($clean) * 3 / 4;

    if ($bytes > 20 * 1024 * 1024) {
        respond(400, ['error' => 'Image exceeds 20MB limit']);
    }

    if ($bytes > 230 * 1024) {
        respond(400, ['error' => 'Image must be client-compressed to around 150-200KB']);
    }

    return strpos($dataUrl, 'data:image') === 0 ? $dataUrl : 'data:image/jpeg;base64,' . $clean;
}

function normalize_coordinate($value, float $min, float $max)
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        respond(400, ['error' => 'Invalid coordinate value']);
    }
    $num = (float)$value;
    if ($num < $min || $num > $max) {
        respond(400, ['error' => 'Coordinate out of range']);
    }
    return $num;
}

function handle_incidents(PDO $pdo, $method, array $categories, array $severities, array $segments)
{
    if ($method === 'GET') {
        $latest = isset($_GET['latest']) ? (int)$_GET['latest'] : 0;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
        $verifiedOnly = isset($_GET['verified_only']) && $_GET['verified_only'] === '1';
        $whereClause = $verifiedOnly ? "WHERE i.status = 'verified'" : "";

        if ($latest > 0) {
            $rows = latest_incidents($pdo, $latest, $verifiedOnly);
            respond(200, ['data' => $rows]);
        }

        $stmt = $pdo->prepare("SELECT i.id, i.user_id, i.category, i.severity, i.description, i.latitude, i.longitude, i.image_base64, i.status, i.created_at,
                                      u.email AS reporter_email, u.role AS reporter_role
                               FROM incidents i
                               LEFT JOIN users u ON u.id = i.user_id
                               $whereClause
                               ORDER BY i.created_at DESC
                               LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        respond(200, ['data' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        $user = require_auth();
        $body = json_input();
        require_fields($body, ['category', 'severity', 'description']);

        $category = strtolower($body['category']);
        $severity = strtolower($body['severity']);

        if (!in_array($category, $categories, true)) {
            respond(400, ['error' => 'Invalid category']);
        }
        if (!in_array($severity, $severities, true)) {
            respond(400, ['error' => 'Invalid severity']);
        }

        $image = isset($body['image_base64']) ? validate_image($body['image_base64']) : null;
        $desc = sanitize_text($body['description']);
        $latitude = normalize_coordinate($body['latitude'] ?? null, -90.0, 90.0);
        $longitude = normalize_coordinate($body['longitude'] ?? null, -180.0, 180.0);

        $stmt = $pdo->prepare('INSERT INTO incidents (user_id, category, severity, description, latitude, longitude, image_base64, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $category, $severity, $desc, $latitude, $longitude, $image, 'new', date('Y-m-d H:i:s')]);
        if ($severity === 'high') {
            broadcast_notification($pdo, "Incident Reported: " . ucfirst($category), $desc);
        }

        respond(201, ['id' => $id]);
    }

    if ($method === 'PATCH') {
        require_auth(['admin', 'superadmin']);
        if (count($segments) < 2 || !is_numeric($segments[1])) {
            respond(400, ['error' => 'Incident id required']);
        }
        $id = (int)$segments[1];
        $body = json_input();

        $fields = [];
        $values = [];
        if (isset($body['status'])) {
            $fields[] = 'status = ?';
            $values[] = sanitize_text($body['status']);
        }
        if (isset($body['severity'])) {
            $severity = strtolower($body['severity']);
            if (!in_array($severity, $severities, true)) {
                respond(400, ['error' => 'Invalid severity']);
            }
            $fields[] = 'severity = ?';
            $values[] = $severity;
        }
        if (isset($body['category'])) {
            $category = strtolower($body['category']);
            if (!in_array($category, $categories, true)) {
                respond(400, ['error' => 'Invalid category']);
            }
            $fields[] = 'category = ?';
            $values[] = $category;
        }
        if (isset($body['description'])) {
            $fields[] = 'description = ?';
            $values[] = sanitize_text($body['description']);
        }
        if (array_key_exists('latitude', $body)) {
            $fields[] = 'latitude = ?';
            $values[] = normalize_coordinate($body['latitude'], -90.0, 90.0);
        }
        if (array_key_exists('longitude', $body)) {
            $fields[] = 'longitude = ?';
            $values[] = normalize_coordinate($body['longitude'], -180.0, 180.0);
        }
        if (isset($body['image_base64'])) {
            $fields[] = 'image_base64 = ?';
            $values[] = validate_image($body['image_base64']);
        }

        if (empty($fields)) {
            respond(400, ['error' => 'No updates provided']);
        }

        $values[] = $id;
        $sql = 'UPDATE incidents SET ' . implode(',', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if (isset($body['status']) && $body['status'] === 'verified') {
             // Fetch details for broadcast
             $stmtDetail = $pdo->prepare("SELECT category, description FROM incidents WHERE id = ?");
             $stmtDetail->execute([$id]);
             $detail = $stmtDetail->fetch();
             if ($detail) {
                 broadcast_notification($pdo, "Hazard Verified: " . ucfirst($detail['category']), $detail['description']);
             }
        }

        respond(200, ['updated' => true]);
    }

    if ($method === 'DELETE') {
        require_auth(['admin', 'superadmin']);
        if (count($segments) >= 2) {
            if (!is_numeric($segments[1])) {
                respond(400, ['error' => 'Incident id must be numeric']);
            }

            $id = (int)$segments[1];
            $stmt = $pdo->prepare('DELETE FROM incidents WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                respond(404, ['error' => 'Incident not found']);
            }

            respond(200, ['deleted' => true]);
        }

        $deleted = $pdo->exec('DELETE FROM incidents');
        respond(200, ['deleted' => true, 'count' => (int)$deleted]);
    }

    respond(405, ['error' => 'Method not allowed']);
}


function handle_users(PDO $pdo, $method, $segments)
{
    if ($method === 'GET') {
        require_auth(['superadmin']);
        $stmt = $pdo->query("SELECT id, email, role, created_at FROM users WHERE role = 'admin'");
        respond(200, ['data' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        require_auth(['superadmin']);
        $body = json_input();
        require_fields($body, ['email', 'password']);

        $email = strtolower(trim($body['email']));
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            respond(400, ['error' => 'Email already registered']);
        }

        $hash = password_hash($body['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, $hash, 'admin', date('Y-m-d H:i:s')]);

        respond(201, ['id' => $pdo->lastInsertId()]);
    }

    if ($method === 'DELETE') {
        require_auth(['superadmin']);
        if (count($segments) < 2 || !is_numeric($segments[1])) {
            respond(400, ['error' => 'User id required']);
        }
        $id = (int)$segments[1];
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "admin"');
        $stmt->execute([$id]);
        respond(200, ['deleted' => true]);
    }

    respond(405, ['error' => 'Method not allowed']);
}
function handle_notifications(PDO $pdo, $method)
{
    if ($method === 'POST') {
        $user = require_auth();
        $body = json_input();
        require_fields($body, ['token']);

        $token = sanitize_text($body['token']);
        $platform = sanitize_text($body['platform'] ?? 'web');

        $stmt = $pdo->prepare('INSERT INTO device_tokens (user_id, token, platform, created_at) 
                              VALUES (?, ?, ?, ?)
                              ON CONFLICT(token) DO UPDATE SET user_id = EXCLUDED.user_id');
        $stmt->execute([$user['id'], $token, $platform, date('Y-m-d H:i:s')]);

        respond(200, ['registered' => true]);
    }
    respond(405, ['error' => 'Method not allowed']);
}

function broadcast_notification(PDO $pdo, $title, $body) {
    $config = require __DIR__ . '/config.php';
    $serverKey = $config['fcm_server_key'] ?? '';
    
    if (empty($serverKey) || $serverKey === 'YOUR_FCM_SERVER_KEY_HERE') {
        return;
    }

    $stmt = $pdo->query("SELECT token FROM device_tokens");
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tokens)) return;

    $url = 'https://fcm.googleapis.com/fcm/send';
    $notification = [
        'title' => $title,
        'body' => $body,
        'sound' => 'default',
        'badge' => '1'
    ];

    foreach (array_chunk($tokens, 1000) as $chunk) {
        $payload = [
            'registration_ids' => $chunk,
            'notification' => $notification,
            'priority' => 'high'
        ];

        $headers = [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_exec($ch);
        curl_close($ch);
    }
}

// Routing
if (count($segments) >= 2 && $segments[0] === 'auth' && $segments[1] === 'login' && $method === 'POST') {
    handle_auth_login($pdo);
}

if (count($segments) >= 2 && $segments[0] === 'auth' && $segments[1] === 'register' && $method === 'POST') {
    handle_auth_register($pdo, $categories, $severities);
}

if (count($segments) === 1 && $segments[0] === 'me') {
    handle_me();
}

if (count($segments) === 1 && $segments[0] === 'status') {
    handle_status($pdo, $method);
}

if (count($segments) >= 1 && $segments[0] === 'incidents') {
    handle_incidents($pdo, $method, $categories, $severities, $segments);
}

if (count($segments) >= 1 && $segments[0] === 'notifications') {
    handle_notifications($pdo, $method);
}

if (count($segments) >= 1 && $segments[0] === 'users') {
    handle_users($pdo, $method, $segments);
}

respond(404, ['error' => 'Not found']);

