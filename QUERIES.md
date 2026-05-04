# Примеры SQL-запросов

## Интервалы между последовательными событиями по `object_id`

События одного объекта упорядочиваются по `event_time`, при совпадении времени — по `id`. Для каждой строки через `LAG` подставляются **имя и время предыдущего** события; колонка `interval_between_events` — разница времени от предыдущего к **текущему** (от `previous_event_name` к `current_event_name`). У первой строки для данного `object_id` предыдущего события нет — там `NULL`.

**Параметр:** подставьте нужный `object_id` вместо литерала в `WHERE`.

```sql
SELECT
  id,
  previous_event_name,
  event_name AS current_event_name,
  previous_event_time,
  event_time AS current_event_time,
  event_time - previous_event_time AS interval_between_events
FROM (
  SELECT
    id,
    event_name,
    event_time,
    LAG(event_name) OVER w AS previous_event_name,
    LAG(event_time) OVER w AS previous_event_time
  FROM events
  WHERE object_id = '9608db18-3743-4f67-af57-004f3fb0f78a'
  WINDOW w AS (ORDER BY event_time, id)
) sub
ORDER BY event_time, id;
```

### Вывод (`psql`, данные из `clients/php/tests/fixtures/events_dataset.n2.csv`)

```
 id | previous_event_name | current_event_name |  previous_event_time   |   current_event_time   | interval_between_events 
----+---------------------+--------------------+------------------------+------------------------+-------------------------
  1 |                     | InvoiceCreated     |                        | 2026-03-30 06:37:56+00 | 
  2 | InvoiceCreated      | PaymentCaptured    | 2026-03-30 06:37:56+00 | 2026-03-30 06:41:25+00 | 00:03:29
  3 | PaymentCaptured     | RefundIssued       | 2026-03-30 06:41:25+00 | 2026-03-30 06:46:04+00 | 00:04:39
  4 | RefundIssued        | StockAdjusted      | 2026-03-30 06:46:04+00 | 2026-03-30 06:48:14+00 | 00:02:10
  5 | StockAdjusted       | ReorderSuggested   | 2026-03-30 06:48:14+00 | 2026-03-30 06:51:57+00 | 00:03:43
  6 | ReorderSuggested    | LoginSucceeded     | 2026-03-30 06:51:57+00 | 2026-03-30 06:54:12+00 | 00:02:15
  7 | LoginSucceeded      | SessionRefreshed   | 2026-03-30 06:54:12+00 | 2026-03-30 06:58:23+00 | 00:04:11
  8 | SessionRefreshed    | LogoutCompleted    | 2026-03-30 06:58:23+00 | 2026-03-30 06:59:10+00 | 00:00:47
  9 | LogoutCompleted     | EmailQueued        | 2026-03-30 06:59:10+00 | 2026-03-30 07:03:56+00 | 00:04:46
(9 rows)
```

## Среднее время между последовательными парами событий (по всей таблице)

Цепочка «предыдущее → текущее» строится **отдельно для каждого** `object_id` (по `event_time`, затем `id`), чтобы не смешивать соседей из разных объектов при общей сортировке. Затем для каждой пары имён `(Событие1, Событие2)` по всем объектам считается **среднее** длительности интервала. Если у какого-то объекта нет такого перехода, он не участвует в среднем для этой пары; наборы шагов могут отличаться — в результат попадут только те пары, которые реально встретились как последовательные.

```sql
WITH ordered AS (
  SELECT
    object_id,
    event_name,
    event_time,
    LAG(event_name) OVER (PARTITION BY object_id ORDER BY event_time, id) AS prev_event_name,
    LAG(event_time) OVER (PARTITION BY object_id ORDER BY event_time, id) AS prev_event_time
  FROM events
),
pairs AS (
  SELECT
    prev_event_name,
    event_name,
    event_time - prev_event_time AS gap
  FROM ordered
  WHERE prev_event_name IS NOT NULL
)
SELECT
  prev_event_name AS "Событие1",
  event_name AS "Событие2",
  AVG(gap) AS "среднее время"
FROM pairs
GROUP BY prev_event_name, event_name
ORDER BY prev_event_name, event_name;
```

### Вывод (`psql`, те же данные в `events`: два `object_id` из `events_dataset.n2.csv`)

```
     Событие1     |     Событие2     | среднее время 
------------------+------------------+---------------
 InvoiceCreated   | PaymentCaptured  | 00:02:43.5
 LoginSucceeded   | SessionRefreshed | 00:04:02
 LogoutCompleted  | EmailQueued      | 00:02:57
 PaymentCaptured  | RefundIssued     | 00:04:00.5
 RefundIssued     | StockAdjusted    | 00:01:18.5
 ReorderSuggested | LoginSucceeded   | 00:01:43.5
 SessionRefreshed | LogoutCompleted  | 00:01:40
 StockAdjusted    | ReorderSuggested | 00:02:35
(8 rows)
```
