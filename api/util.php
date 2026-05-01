<?php
function json_input()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($code, $payload = [])
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function require_fields(array $body, array $fields)
{
    foreach ($fields as $field) {
        if (!isset($body[$field]) || $body[$field] === '') {
            respond(400, ['error' => "Missing field: {$field}"]);
        }
    }
}

function sanitize_text($value)
{
    $value = trim((string)$value);
    // Strip control characters and limit length.
    $value = preg_replace('/[[:cntrl:]]/', '', $value);
    return substr($value, 0, 2000);
}

function cors()
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
