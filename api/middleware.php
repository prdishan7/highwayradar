<?php
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/util.php';

function current_user()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    // Try multiple ways to get the Authorization header (Apache sometimes doesn't set HTTP_AUTHORIZATION)
    $authHeader = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }
    }
    
    if (!preg_match('/Bearer\s+(.*)/i', $authHeader, $matches)) {
        return null;
    }
    $token = trim($matches[1]);
    $payload = jwt_decode($token);
    if (!$payload) {
        return null;
    }

    $cached = [
        'id' => $payload['id'],
        'email' => $payload['email'],
        'role' => $payload['role'],
    ];
    return $cached;
}

function require_auth(array $roles = null)
{
    $user = current_user();
    if (!$user) {
        respond(401, ['error' => 'Unauthorized']);
    }

    if ($roles && !in_array($user['role'], $roles, true)) {
        respond(403, ['error' => 'Forbidden']);
    }

    return $user;
}
