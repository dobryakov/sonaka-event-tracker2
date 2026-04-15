<?php

declare(strict_types=1);

function scenario_018_validation(): void
{
    $base = [
        'system_name' => 'v',
        'object_id' => 'o',
        'event_name' => 'e',
        'event_time' => '2026-04-15T12:00:00Z',
        'metadata' => [],
    ];

    $missing = $base;
    unset($missing['system_name']);
    [$c] = http_post_json('/events', $missing);
    assert_true($c === 400, 'missing system_name should be 400');

    $badMeta = $base;
    $badMeta['metadata'] = 'scalar';
    [$c2, , $j2] = http_post_json('/events', $badMeta);
    assert_true($c2 === 400, 'scalar metadata should be 400');
    assert_true(is_array($j2) && isset($j2['error']['code']), 'expected error envelope');

    [$c3, , $j3] = http_post_raw('/events', '{"a":1}', ['Content-Type: application/json']);
    assert_true($c3 === 400, 'unknown fields should be 400 (disallow unknown)');

    [$c4] = http_post_json('/events', $base, ['Content-Type: text/plain']);
    assert_true($c4 === 400, 'wrong content-type should be 400');

    [$c5, , $j5] = http_post_raw('/events', '{not json', ['Content-Type: application/json']);
    assert_true($c5 === 400, 'invalid json should be 400');
    assert_true(is_array($j5) && isset($j5['error']['code']), 'expected error envelope');

    $big = str_repeat('a', 2 * 1024 * 1024);
    [$c6, , $j6] = http_post_raw('/events', $big, ['Content-Type: application/json']);
    assert_true($c6 === 413, 'oversized body should be 413, got ' . $c6);
    assert_true(is_array($j6) && isset($j6['error']['code']), 'expected error envelope on 413');
}
