# План реализации: Приёмка и учёт событий по HTTP

**Branch**: `001-http-event-ingestion` | **Date**: 2026-04-15 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/home/ubuntu/sonaka-event-tracker2/specs/001-http-event-ingestion/spec.md`

**Note**: Заполняется командой `/speckit.plan`. Шаблон процесса: `.specify/templates/plan-template.md`.

## Summary

Реализовать сервис приёмки событий по **HTTP** с телом **JSON** (`Content-Type: application/json`), сохранением в **PostgreSQL** и ответом **2xx** с **`id`** записи. Клиентские примеры на **PHP 8.3+** и **bash (curl)** запускаются через **`docker compose exec`**. Интеграционные **автотесты на PHP** выполняются в контейнере **`client-php`**: POST к приёмке и проверка строки в БД по `id` через SQL. Конфигурация через **`.env`** и **Compose**. Технический подход: Go 1.22+ (`net/http`, `database/sql`), драйвер **pgx** (stdlib), миграции **golang-migrate**, контракт API в OpenAPI (см. `contracts/`). Детали и обоснования — в [research.md](./research.md).

## Technical Context

**Language/Version**: Go 1.22+ (сервер приложения); PHP 8.3+ (пример клиента); bash для curl-сценариев  
**Primary Dependencies**: `net/http`, `database/sql`, `github.com/jackc/pgx/v5/stdlib`, `github.com/golang-migrate/migrate/v4`  
**Storage**: PostgreSQL (единственное постоянное хранилище событий в объёме фичи)  
**Testing**: PHP-скрипты в `clients/php/tests/`, запуск в сервисе `client-php` (`docker compose exec`): HTTP POST к `app` и проверка строки в PostgreSQL по `id`  
**Target Platform**: Linux-контейнеры под docker compose  
**Project Type**: web-service (HTTP ingestion) + контейнерные клиенты и тесты  
**Performance Goals**: не формализованы сверх спека; SC-001 (запись видна в БД в течение 5 с после успешного ответа)  
**Constraints**: без auth на эндпоинте приёмки (FR-008); лимит тела запроса 1 MiB (см. research.md); команды только в контейнерах  
**Scale/Scope**: одна таблица событий, один основной POST-эндпоинт приёмки; дедупликация вне объёма

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Принцип | Статус | Проверка |
|--------|--------|----------|
| I. Назначение — приём/хранение/проверка событий | PASS | Фича целиком про HTTP-приём и запись в PostgreSQL |
| II. Docker compose: PostgreSQL, Go, PHP и bash | PASS | План и quickstart предполагают сервисы `postgres`, приложение Go, контейнеры/образы для PHP и bash-клиентов |
| III. Команды только в контейнерах | PASS | Документация и тесты — через `docker compose exec` |
| IV. Автотесты обязательны | PASS | Интеграционные тесты PHP в `client-php` + проверка БД по FR-005 |
| V. `.env` + прокидывание в compose | PASS | research и quickstart задают DATABASE_URL/DB_* и HTTP_ADDR из `.env` |

**Gate result (pre Phase 0)**: PASS — нарушений нет.

### Constitution Check (post Phase 1)

Повторная проверка после проектирования: артефакты `data-model.md`, `contracts/http-event-ingestion.openapi.yaml`, `quickstart.md` не расширяют scope за пределы спека и не противоречат конституции. **Gate result: PASS.**

## Project Structure

### Documentation (this feature)

```text
specs/001-http-event-ingestion/
├── plan.md              # Этот файл
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/           # Phase 1
│   └── http-event-ingestion.openapi.yaml
└── tasks.md             # Phase 2: очередь реализации (генерация/обновление — speckit.tasks)
```

### Source Code (repository root)

Репозиторий на момент планирования не содержит `src/`; ниже — целевая структура для реализации фичи.

```text
src/
├── cmd/
│   └── server/
│       └── main.go
└── internal/
    ├── api/             # HTTP-обработчики, валидация тела
    ├── config/        # разбор env
    └── storage/       # работа с БД / репозиторий событий

migrations/
└── *.sql

clients/
├── php/
│   ├── send_event.php
│   └── tests/
│       └── run_integration_tests.php   # и др. сценарии приёмочных тестов
└── bash/
    └── send_event.sh

docker-compose.yml
.env.example
```

**Structure Decision**: один модуль Go под `src/` с `internal` для API и хранилища; SQL-миграции отдельно; клиенты и **интеграционные автотесты на PHP** вне `src/` (`clients/php/tests/`). Frontend отсутствует.

## Complexity Tracking

> Заполнять только при нарушении Constitution Check с обоснованием.

Нарушений нет — таблица не используется.

## Generated artifacts (Phase 0–1)

| Артефакт | Путь |
|----------|------|
| Исследование | `/home/ubuntu/sonaka-event-tracker2/specs/001-http-event-ingestion/research.md` |
| Модель данных | `/home/ubuntu/sonaka-event-tracker2/specs/001-http-event-ingestion/data-model.md` |
| Контракт API | `/home/ubuntu/sonaka-event-tracker2/specs/001-http-event-ingestion/contracts/http-event-ingestion.openapi.yaml` |
| Quickstart | `/home/ubuntu/sonaka-event-tracker2/specs/001-http-event-ingestion/quickstart.md` |

## Phase 2

Очередь задач по фиче — в [tasks.md](./tasks.md): формируется и уточняется командой **speckit.tasks**; при реализации сверяться с `spec.md`, этим планом и артефактами Phase 0–1.
