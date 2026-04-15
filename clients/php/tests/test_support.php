<?php

declare(strict_types=1);

function integration_base_url(): string
{
    $u = getenv('INGEST_BASE_URL') ?: '';
    if ($u === '') {
        throw new RuntimeException('INGEST_BASE_URL is not set');
    }

    return rtrim($u, '/');
}

function integration_pdo(): PDO
{
    $host = getenv('DB_HOST') ?: 'postgres';
    $port = getenv('DB_PORT') ?: '5432';
    $db = getenv('DB_NAME') ?: 'events';
    $user = getenv('DB_USER') ?: 'sonaka';
    $pass = getenv('DB_PASSWORD') ?: 'sonaka';
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $db);

    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/**
 * @return array{0: int, 1: string, 2: mixed}
 */
function http_post_json(string $path, array $payload, array $extraHeaders = []): array
{
    $url = integration_base_url() . $path;
    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $headers = ['Accept: application/json'];
    $hasContentType = false;
    foreach ($extraHeaders as $line) {
        if (stripos((string) $line, 'Content-Type:') === 0) {
            $hasContentType = true;
            break;
        }
    }
    if (!$hasContentType) {
        $headers[] = 'Content-Type: application/json';
    }
    $headers = array_merge($headers, $extraHeaders);
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('curl_init failed');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('curl: ' . $err);
    }
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $response, true);

    return [$code, (string) $response, $decoded];
}

/**
 * @param list<string> $headers full header lines
 * @return array{0: int, 1: string, 2: mixed}
 */
function http_post_raw(string $path, string $rawBody, array $headers): array
{
    $url = integration_base_url() . $path;
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('curl_init failed');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $rawBody,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('curl: ' . $err);
    }
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $response, true);

    return [$code, (string) $response, $decoded];
}

function assert_true(bool $cond, string $message): void
{
    if (!$cond) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}
