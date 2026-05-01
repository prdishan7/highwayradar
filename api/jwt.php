<?php
function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data)
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_encode(array $payload)
{
    $config = require __DIR__ . '/config.php';
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];

    $payload['iss'] = $config['jwt_issuer'];
    $payload['iat'] = time();
    $payload['exp'] = time() + 60 * 60 * 24 * 5; // 5 days

    $segments = [
        base64url_encode(json_encode($header)),
        base64url_encode(json_encode($payload)),
    ];

    $signing_input = implode('.', $segments);
    $signature = hash_hmac('sha256', $signing_input, $config['jwt_secret'], true);
    $segments[] = base64url_encode($signature);

    return implode('.', $segments);
}

function jwt_decode($token)
{
    $config = require __DIR__ . '/config.php';
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$header64, $payload64, $sig64] = $parts;
    $payload = json_decode(base64url_decode($payload64), true);
    $signature = base64url_decode($sig64);
    $expected = hash_hmac('sha256', $header64 . '.' . $payload64, $config['jwt_secret'], true);

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    if (!isset($payload['exp']) || $payload['exp'] < time()) {
        return null;
    }

    return $payload;
}
