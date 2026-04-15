<?php

declare(strict_types=1);

function scenario_017_duplicate_delivery(): void
{
    $payload = [
        'system_name' => 'dup-test',
        'object_id' => 'obj-dup',
        'event_name' => 'DupEvent',
        'event_time' => '2026-04-15T11:00:00Z',
        'metadata' => ['x' => 1],
    ];

    [$c1, $r1, $j1] = http_post_json('/events', $payload);
    [$c2, $r2, $j2] = http_post_json('/events', $payload);
    assert_true($c1 >= 200 && $c1 < 300 && $c2 >= 200 && $c2 < 300, 'expected 2xx for both: ' . $r1 . ' / ' . $r2);
    assert_true(is_array($j1) && is_array($j2), 'invalid json');
    $id1 = (int) $j1['id'];
    $id2 = (int) $j2['id'];
    assert_true($id1 !== $id2, 'expected distinct ids');

    $pdo = integration_pdo();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE id IN (:a, :b)');
    $stmt->execute(['a' => $id1, 'b' => $id2]);
    $cnt = (int) $stmt->fetchColumn();
    assert_true($cnt === 2, 'expected two rows');
}
