<?php

declare(strict_types=1);

function scenario_026_invalid_event_time(): void
{
    $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $payload = [
        'system_name' => 'time-fallback',
        'object_id' => 'obj-026',
        'event_name' => 'BadTime',
        'event_time' => 'this-is-not-rfc3339',
        'metadata' => ['ok' => true],
    ];

    [$code, $raw, $json] = http_post_json('/events', $payload);
    assert_true($code >= 200 && $code < 300, 'expected 2xx for invalid event_time with valid rest: ' . $raw);
    assert_true(is_array($json) && isset($json['id']), 'expected id: ' . $raw);
    $id = (int) $json['id'];

    $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $pdo = integration_pdo();
    $stmt = $pdo->prepare('SELECT event_time FROM events WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_true(is_array($row), 'row missing');
    $stored = new DateTimeImmutable((string) $row['event_time'], new DateTimeZone('UTC'));

    assert_true($stored >= $before->modify('-2 seconds'), 'stored time should be around ingest (after before-2s)');
    assert_true($stored <= $after->modify('+5 seconds'), 'stored time should be around ingest (before after+5s)');
}
