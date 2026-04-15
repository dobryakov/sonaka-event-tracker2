<?php

declare(strict_types=1);

function scenario_027_db_failure_simulation(): void
{
    $payload = [
        'system_name' => 'db-fail',
        'object_id' => 'obj-027',
        'event_name' => 'DbFail',
        'event_time' => '2026-04-15T13:00:00Z',
        'metadata' => ['t' => 27],
    ];

    [$code, $raw, $json] = http_post_json('/events', $payload, ['X-Simulate-DB-Failure: 1']);
    if ($code >= 200 && $code < 300) {
        $flag = getenv('ALLOW_DB_FAILURE_SIMULATION') ?: '';
        $simEnabled = $flag === 'true' || $flag === '1';
        if (!$simEnabled) {
            throw new RuntimeException(
                'T027: ожидался 503 при X-Simulate-DB-Failure, получен 2xx. Задайте в .env ALLOW_DB_FAILURE_SIMULATION=true '
                . 'и пересоздайте сервис app: docker compose up -d app --force-recreate. Тело ответа: ' . $raw
            );
        }
        throw new RuntimeException(
            'T027: симуляция сбоя БД не сработала (2xx). Проверьте, что контейнер app собран из текущего кода и получает ALLOW_DB_FAILURE_SIMULATION=true. Ответ: ' . $raw
        );
    }
    assert_true($code === 503 || $code === 500, 'expected 503 or 500, got ' . $code . ' body=' . $raw);
    assert_true(is_array($json) && isset($json['error']['code']), 'expected error envelope: ' . $raw);

    [$code2, $raw2, $json2] = http_post_json('/events', $payload);
    assert_true($code2 >= 200 && $code2 < 300, 'expected success after simulation: ' . $raw2);
    assert_true(is_array($json2) && isset($json2['id']), 'expected id: ' . $raw2);
}
