<?php

declare(strict_types=1);

function scenario_016_happy_path(): void
{
    $payload = [
        'system_name' => 'integration-test',
        'object_id' => 'obj-016',
        'event_name' => 'Scenario016',
        'event_time' => '2026-04-15T10:00:00Z',
        'metadata' => ['k' => 'v', 'n' => 1],
    ];

    $t0 = microtime(true);
    [$code, $raw, $json] = http_post_json('/events', $payload);
    assert_true($code >= 200 && $code < 300, 'expected 2xx, got ' . $code . ' body=' . $raw);
    assert_true(is_array($json) && isset($json['id']), 'expected id in response: ' . $raw);
    $id = (int) $json['id'];

    $deadline = $t0 + 5.0;
    $pdo = integration_pdo();
    $row = null;
    while (microtime(true) < $deadline) {
        $stmt = $pdo->prepare('SELECT system_name, object_id, event_name, metadata, event_time FROM events WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            break;
        }
        usleep(50_000);
    }
    assert_true($row !== false && is_array($row), 'row not visible within 5s for id=' . $id);
    assert_true($row['system_name'] === $payload['system_name'], 'system_name mismatch');
    assert_true($row['object_id'] === $payload['object_id'], 'object_id mismatch');
    assert_true($row['event_name'] === $payload['event_name'], 'event_name mismatch');

    $metaDecoded = json_decode((string) $row['metadata'], true, 512, JSON_THROW_ON_ERROR);
    assert_true($metaDecoded == $payload['metadata'], 'metadata mismatch');

    $dbTime = new DateTimeImmutable((string) $row['event_time'], new DateTimeZone('UTC'));
    $expected = new DateTimeImmutable($payload['event_time'], new DateTimeZone('UTC'));
    assert_true($dbTime->format('c') === $expected->format('c'), 'event_time mismatch');
}
