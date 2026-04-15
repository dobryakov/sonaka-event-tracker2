# Быстрый старт: приёмка событий по HTTP

Документ описывает целевой контур после реализации фичи `001-http-event-ingestion`. Команды выполняются **внутри контейнеров** через Docker Compose (см. конституцию проекта).

## Предварительные требования

- Docker и Docker Compose v2
- Клонирование репозитория и файл `.env` на основе `.env.example` в корне проекта

## Установка и первый запуск

1. Скопировать переменные окружения:
   ```bash
   cp .env.example .env
   ```
2. Поднять сервисы:
   ```bash
   docker compose up -d --build
   ```
3. Дождаться готовности PostgreSQL и приложения (при наличии healthcheck — статус healthy).
4. Отправить пробное событие одним из способов ниже и убедиться в ответе `2xx` с JSON, содержащим `id`.

## Пример: клиент на bash (curl) в контейнере

После появления скрипта в репозитории (например `clients/bash/send_event.sh`):

```bash
docker compose exec client-bash ./clients/bash/send_event.sh
```

(Точное имя сервиса и путь уточняются в `docker-compose.yml` реализации.)

## Пример: клиент на PHP в контейнере

```bash
docker compose exec client-php php clients/php/send_event.php
```

Скрипт должен формировать JSON с полями `system_name`, `object_id`, `event_name`, `metadata`, `event_time` (RFC 3339), заголовком `Content-Type: application/json` и завершать процесс после ответа.

## Псевдослучайные данные в примерах

В реализации рекомендуется:

- генерировать `object_id` и фрагменты `metadata` через встроенные средства языка (`$RANDOM`, `random_bytes` в PHP и т.д.);
- фиксировать в документации одну команду `docker compose exec ...` без необходимости ставить рантаймы на хост.

## Запуск автотестов

Интеграционные тесты на **PHP** выполняются в контейнере **`client-php`** (там же, где клиентские примеры), например:

```bash
docker compose exec client-php php clients/php/tests/run_integration_tests.php
```

Имя сервиса и имя раннера задаются в репозитории после добавления кода; критерий успеха — завершение с кодом 0 и проверка строки в PostgreSQL по `id` из ответа приёмки (FR-005).

## Проверка записи в PostgreSQL (отладка)

При необходимости вручную (из контейнера клиента или `postgres`):

```bash
docker compose exec postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "SELECT id, system_name, object_id, event_name, metadata, event_time FROM events ORDER BY id DESC LIMIT 5;"
```

Имена пользователя и БД берутся из `.env`.
