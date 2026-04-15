CREATE TABLE events (
    id BIGSERIAL PRIMARY KEY,
    system_name TEXT NOT NULL,
    object_id TEXT NOT NULL,
    event_name TEXT NOT NULL,
    metadata JSONB NOT NULL,
    event_time TIMESTAMPTZ NOT NULL
);
