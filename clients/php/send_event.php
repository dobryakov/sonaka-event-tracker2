<?php

declare(strict_types=1);

$base = rtrim(getenv('INGEST_BASE_URL') ?: 'http://app:8080', '/');

$objectId = 'obj-' . bin2hex(random_bytes(4));
$metaVal = bin2hex(random_bytes(3));

$payload = [
    'system_name' => 'php-client',
    'object_id' => $objectId,
    'event_name' => 'PhpSend',
    'event_time' => gmdate('c'),
    'metadata' => [
        'run' => $metaVal,
        'n' => random_int(1, 1_000_000),
    ],
];

$body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$ch = curl_init($base . '/events');
if ($ch === false) {
    fwrite(STDERR, "curl_init failed\n");
    exit(1);
}
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
if ($response === false) {
    fwrite(STDERR, 'curl: ' . curl_error($ch) . "\n");
    curl_close($ch);
    exit(1);
}
$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    fwrite(STDERR, "HTTP {$code}: {$response}\n");
    exit(1);
}

echo "OK {$response}\n";
