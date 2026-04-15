# Быстрый старт: приёмка событий по HTTP

Документ описывает контур фичи `001-http-event-ingestion`. Команды выполняются **внутри контейнеров** через Docker Compose (см. конституцию проекта).

## Предварительные требования

- Docker и Docker Compose v2
- Файл `.env` в корне репозитория (скопируйте из `.env.example`)

## Установка и первый запуск

1. Скопировать переменные окружения:
   ```bash
   cp .env.example .env
   ```
2. Поднять **постоянный стек** (PostgreSQL и приложение; клиентские образы при этом только соберутся при необходимости):
   ```bash
   docker compose up -d --build
   ```
3. Дождаться статуса **healthy** у сервисов `postgres` и `app` (см. `docker compose ps`).
4. Отправить пробное событие одним из способов ниже и убедиться в ответе `2xx` с JSON, содержащим `id`.

## Имена сервисов в Compose

| Сервис       | Назначение                                      |
|-------------|--------------------------------------------------|
| `postgres`  | PostgreSQL 16                                    |
| `app`       | HTTP-приёмка (Go), порт хоста по умолчанию `8080` |
| `client-php`| PHP 8.3 + PDO pgsql (примеры и автотесты), **разовый запуск** |
| `client-bash` | bash + curl (пример curl), **разовый запуск** |

Сервисы **`client-php`** и **`client-bash`** объявлены с профилем **`client`**: они **не держатся запущенными** после `docker compose up` и предназначены для **`docker compose run --rm`** (контейнер стартует, выполняет команду и завершается).

Базовый URL приёмки внутри сети Compose задаётся переменной **`INGEST_BASE_URL`** (по умолчанию в `.env.example`: `http://app:8080`).

## Пример: клиент на bash (curl) в контейнере

```bash
docker compose run --rm client-bash bash clients/bash/send_event.sh
```

Скрипт отправляет JSON с полями `system_name`, `object_id`, `event_name`, `metadata`, `event_time` (UTC RFC 3339) и завершается с ненулевым кодом при неуспешном HTTP.

## Пример: клиент на PHP в контейнере

```bash
docker compose run --rm client-php php clients/php/send_event.php
```

## Псевдослучайные данные в примерах

Клиенты генерируют случайные `object_id` и фрагменты `metadata` встроенными средствами; рантаймы на хосте не требуются.

## Запуск автотестов

**Перед прогоном** убедитесь, что в **`.env`** задано **`ALLOW_DB_FAILURE_SIMULATION=true`** (как в `.env.example`): иначе сценарий T027 (симуляция ошибки БД) получит успешный ответ вместо **503**. После смены переменной пересоздайте приложение:

```bash
docker compose up -d app --force-recreate
```

Интеграционные тесты выполняются одноразовым контейнером **`client-php`**:

```bash
docker compose run --rm client-php php clients/php/tests/run_integration_tests.php
```

Критерий успеха — код выхода `0` и прохождение проверок строки в PostgreSQL по `id` из ответа приёмки (FR-005).

### Имитация недоступности БД (без остановки PostgreSQL)

При **`ALLOW_DB_FAILURE_SIMULATION=true`** у сервиса `app`:

- `POST /events` с заголовком **`X-Simulate-DB-Failure: 1`** возвращает **503** с телом ошибки по контракту, без записи в БД;
- тот же запрос без заголовка после этого снова успешен.

В **продакшене** задайте **`ALLOW_DB_FAILURE_SIMULATION=false`**, чтобы этот тестовый путь был отключён.

Автотест `scenario_027_db_failure_simulation.php` использует этот механизм.

### Ручной сценарий: остановка PostgreSQL

Для проверки «хранилище недоступно» вручную:

1. `docker compose stop postgres`
2. Отправьте валидный `POST /events` (например `docker compose run --rm client-php php clients/php/send_event.php` или `curl` к `app`) — ожидается **503** или **500** с JSON ошибки, событие не должно считаться принятым.
3. `docker compose start postgres`, дождитесь `healthy`, повторите POST — ожидается **2xx** и появление строки в БД.

## Проверка записи в PostgreSQL (отладка)

```bash
docker compose exec postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "SELECT id, system_name, object_id, event_name, metadata, event_time FROM events ORDER BY id DESC LIMIT 5;"
```

Переменные `POSTGRES_USER` и `POSTGRES_DB` должны быть заданы в окружении (например `docker compose exec -e POSTGRES_USER=...` или через `.env` на хосте для подстановки в команде).
