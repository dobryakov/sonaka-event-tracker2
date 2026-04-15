package storage

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"time"
)

// EventRepository persists ingest payloads to PostgreSQL.
type EventRepository struct {
	db *sql.DB
}

// NewEventRepository constructs a repository using the shared sql.DB pool.
func NewEventRepository(db *sql.DB) *EventRepository {
	return &EventRepository{db: db}
}

// InsertEvent stores one event row and returns the generated primary key.
func (r *EventRepository) InsertEvent(ctx context.Context, systemName, objectID, eventName string, metadata json.RawMessage, eventTime time.Time) (int64, error) {
	const q = `
		INSERT INTO events (system_name, object_id, event_name, metadata, event_time)
		VALUES ($1, $2, $3, $4::jsonb, $5)
		RETURNING id`
	var id int64
	err := r.db.QueryRowContext(ctx, q, systemName, objectID, eventName, metadata, eventTime.UTC()).Scan(&id)
	if err != nil {
		return 0, fmt.Errorf("insert event: %w", err)
	}
	return id, nil
}

// Ping checks database connectivity.
func (r *EventRepository) Ping(ctx context.Context) error {
	if r.db == nil {
		return errors.New("nil db")
	}
	return r.db.PingContext(ctx)
}
