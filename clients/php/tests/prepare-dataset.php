<?php

declare(strict_types=1);

/**
 * Генерация CSV для таблицы events (без id): object_id, system_name, event_name, metadata, event_time.
 *
 * Для каждого из n циклов: один object_id, старт event_time в случайной точке прошлого,
 * затем ровно один проход по каталогу — каждая пара (system_name, event_name) не более одного раза;
 * между событиями сдвиг времени на 5 с … 5 мин.
 *
 * Пример:
 *   php clients/php/tests/prepare-dataset.php --count=50 --seed=42 --output=clients/php/tests/fixtures/events_dataset.csv
 */

const EVENTS_CSV_COLUMNS = [
    'object_id',
    'system_name',
    'event_name',
    'metadata',
    'event_time',
];

/**
 * Системы и 1–3 имени событий на систему. Порядок ключей и элементов задаёт очередь при обходе.
 *
 * @var array<string, list<string>>
 */
const SYSTEM_EVENT_DEFINITIONS = [
    'billing' => ['InvoiceCreated', 'PaymentCaptured', 'RefundIssued'],
    'inventory' => ['StockAdjusted', 'ReorderSuggested'],
    'auth' => ['LoginSucceeded', 'SessionRefreshed', 'LogoutCompleted'],
    'notifications' => ['EmailQueued'],
];

const PAST_WINDOW_MIN_SECONDS = 3600;
const PAST_WINDOW_MAX_SECONDS = 86400 * 90;
const STEP_MIN_SECONDS = 5;
const STEP_MAX_SECONDS = 300;

/**
 * @return list<array{system_name: string, event_name: string}>
 */
function event_pair_catalog(): array
{
    $pairs = [];
    foreach (SYSTEM_EVENT_DEFINITIONS as $systemName => $eventNames) {
        foreach ($eventNames as $eventName) {
            $pairs[] = [
                'system_name' => $systemName,
                'event_name' => $eventName,
            ];
        }
    }

    return $pairs;
}

function random_object_id(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/** @return array<string, false|string> */
function parse_cli_args(): array
{
    $parsed = getopt('n:', [
        'count:',
        'output:',
        'seed:',
        'help',
    ]);
    if ($parsed === false) {
        throw new RuntimeException('getopt failed');
    }

    return is_array($parsed) ? $parsed : [];
}

function print_usage_and_exit(int $code = 0): never
{
    $msg = <<<'TXT'
Usage: php prepare-dataset.php [options]

  -n N | --count=N   Число циклов (объектов): на цикл один object_id и по одному событию на каждую пару каталога
  --output=PATH      CSV (по умолчанию: clients/php/tests/fixtures/events_dataset.csv)
  --seed=N           Зерно mt_rand для воспроизводимости
  --help

TXT;
    fwrite($code === 0 ? STDOUT : STDERR, $msg);
    exit($code);
}

function default_output_path(): string
{
    return __DIR__ . '/fixtures/events_dataset.csv';
}

function resolve_cycle_count(array $args): int
{
    if (isset($args['n']) && $args['n'] !== false && $args['n'] !== '') {
        return max(0, (int) $args['n']);
    }
    if (isset($args['count']) && $args['count'] !== false && $args['count'] !== '') {
        return max(0, (int) $args['count']);
    }

    return -1;
}

/** @param resource $fh */
function write_header($fh): void
{
    fputcsv($fh, EVENTS_CSV_COLUMNS);
}

/**
 * Случайный момент в прошлом (не ближе чем PAST_WINDOW_MIN_SECONDS назад).
 */
function random_past_epoch(): int
{
    $now = time();
    $lo = $now - PAST_WINDOW_MAX_SECONDS;
    $hi = $now - PAST_WINDOW_MIN_SECONDS;

    return mt_rand($lo, $hi);
}

/**
 * @param resource $fh
 * @param list<array{system_name: string, event_name: string}> $catalog
 */
function write_timelines($fh, int $cycles, array $catalog): int
{
    if ($catalog === []) {
        throw new RuntimeException('Каталог system_name/event_name пуст');
    }

    $catalogLen = count($catalog);
    $written = 0;

    for ($cycle = 0; $cycle < $cycles; $cycle++) {
        $objectId = random_object_id();
        $t = random_past_epoch();

        for ($step = 0; $step < $catalogLen; $step++) {
            $pair = $catalog[$step];
            $meta = [
                'cycle' => $cycle,
                'step' => $step,
                'object_id' => $objectId,
            ];
            $metadataJson = json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            fputcsv($fh, [
                $objectId,
                $pair['system_name'],
                $pair['event_name'],
                $metadataJson,
                gmdate('c', $t),
            ]);
            ++$written;

            if ($step < $catalogLen - 1) {
                $t += mt_rand(STEP_MIN_SECONDS, STEP_MAX_SECONDS);
            }
        }
    }

    return $written;
}

// --- main ---

$args = parse_cli_args();
if (isset($args['help'])) {
    print_usage_and_exit(0);
}

$cycles = resolve_cycle_count($args);
if ($cycles < 0) {
    fwrite(STDERR, "Укажите число циклов: -n N или --count=N\n");
    print_usage_and_exit(1);
}

$output = isset($args['output']) && $args['output'] !== '' && $args['output'] !== false
    ? (string) $args['output']
    : default_output_path();

$seed = isset($args['seed']) && $args['seed'] !== false && $args['seed'] !== ''
    ? (int) $args['seed']
    : random_int(1, PHP_INT_MAX);
mt_srand($seed);

$catalog = event_pair_catalog();

$dir = dirname($output);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Cannot create directory: {$dir}\n");
    exit(1);
}

$fh = fopen($output, 'wb');
if ($fh === false) {
    fwrite(STDERR, "Cannot open for write: {$output}\n");
    exit(1);
}

$dataRows = 0;
try {
    write_header($fh);
    if ($cycles > 0) {
        $dataRows = write_timelines($fh, $cycles, $catalog);
    }
} finally {
    fclose($fh);
}

fwrite(
    STDOUT,
    "Wrote CSV: {$output} (cycles: {$cycles}, data rows: {$dataRows}, catalog pairs: " . count($catalog) . ", seed: {$seed})\n"
);
