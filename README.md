# sonaka-event-tracker2

Сервис приёмки событий по HTTP с записью в PostgreSQL (фича `001-http-event-ingestion`).

## Документация

- Быстрый старт и команды Docker Compose: [specs/001-http-event-ingestion/quickstart.md](specs/001-http-event-ingestion/quickstart.md)
- Конституция проекта: [.specify/memory/constitution.md](.specify/memory/constitution.md)

## Кратко

1. Скопируйте `cp .env.example .env` и при необходимости поправьте значения.
2. `docker compose up -d --build`
3. Примеры отправки и автотесты — в [quickstart](specs/001-http-event-ingestion/quickstart.md) (`docker compose run --rm client-php` / `client-bash`).
