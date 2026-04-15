<?php

declare(strict_types=1);

require_once __DIR__ . '/test_support.php';

$scenarios = [
    'scenario_016_happy_path',
    'scenario_017_duplicate_delivery',
    'scenario_018_validation',
    'scenario_026_invalid_event_time',
    'scenario_027_db_failure_simulation',
];

foreach ($scenarios as $name) {
    require_once __DIR__ . '/' . $name . '.php';
}

foreach ($scenarios as $name) {
    fwrite(STDOUT, "Running {$name}...\n");
    $name();
}

fwrite(STDOUT, "All integration tests passed.\n");
