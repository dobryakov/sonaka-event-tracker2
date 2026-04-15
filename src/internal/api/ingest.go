package api

import (
	"bytes"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"strings"
	"time"

	"sonaka-event-tracker2/src/internal/config"
	"sonaka-event-tracker2/src/internal/storage"
)

const simulateDBFailureHeader = "X-Simulate-DB-Failure"

// IngestHandler handles POST /events JSON ingestion.
type IngestHandler struct {
	Repo   *storage.EventRepository
	Config config.Config
}

type ingestRequest struct {
	SystemName string          `json:"system_name"`
	ObjectID   string          `json:"object_id"`
	EventName  string          `json:"event_name"`
	EventTime  string          `json:"event_time"`
	Metadata   json.RawMessage `json:"metadata"`
}

// ServeHTTP implements http.Handler.
func (h *IngestHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeError(w, http.StatusMethodNotAllowed, "method_not_allowed", "only POST is allowed", nil)
		return
	}
	if h.Config.AllowDBFailureSimulation && r.Header.Get(simulateDBFailureHeader) == "1" {
		writeError(w, http.StatusServiceUnavailable, "storage_unavailable", "database write simulated as failed", nil)
		return
	}
	ct := r.Header.Get("Content-Type")
	if ct == "" || !strings.HasPrefix(strings.TrimSpace(strings.ToLower(ct)), "application/json") {
		writeError(w, http.StatusBadRequest, "invalid_content_type", "Content-Type must be application/json", nil)
		return
	}

	bodyBytes, err := io.ReadAll(http.MaxBytesReader(w, r.Body, h.Config.MaxBodyBytes))
	if err != nil {
		var maxErr *http.MaxBytesError
		if errors.As(err, &maxErr) {
			writeError(w, http.StatusRequestEntityTooLarge, "payload_too_large", "request body exceeds maximum size", nil)
			return
		}
		writeError(w, http.StatusBadRequest, "invalid_body", "could not read request body", nil)
		return
	}
	if len(bytes.TrimSpace(bodyBytes)) == 0 {
		writeError(w, http.StatusBadRequest, "invalid_json", "empty request body", nil)
		return
	}

	dec := json.NewDecoder(bytes.NewReader(bodyBytes))
	dec.DisallowUnknownFields()

	var raw ingestRequest
	if err := dec.Decode(&raw); err != nil {
		writeError(w, http.StatusBadRequest, "invalid_json", "request body is not valid JSON", nil)
		return
	}
	if err := dec.Decode(&struct{}{}); err != io.EOF {
		writeError(w, http.StatusBadRequest, "invalid_json", "request body must be a single JSON object", nil)
		return
	}

	if !hasTopLevelJSONKey(bodyBytes, "system_name") || strings.TrimSpace(raw.SystemName) == "" {
		writeError(w, http.StatusBadRequest, "validation_error", "system_name is required", map[string]any{"field": "system_name"})
		return
	}
	if !hasTopLevelJSONKey(bodyBytes, "object_id") || strings.TrimSpace(raw.ObjectID) == "" {
		writeError(w, http.StatusBadRequest, "validation_error", "object_id is required", map[string]any{"field": "object_id"})
		return
	}
	if !hasTopLevelJSONKey(bodyBytes, "event_name") || strings.TrimSpace(raw.EventName) == "" {
		writeError(w, http.StatusBadRequest, "validation_error", "event_name is required", map[string]any{"field": "event_name"})
		return
	}
	if !hasTopLevelJSONKey(bodyBytes, "event_time") {
		writeError(w, http.StatusBadRequest, "validation_error", "event_time is required", map[string]any{"field": "event_time"})
		return
	}
	if strings.TrimSpace(raw.EventTime) == "" {
		writeError(w, http.StatusBadRequest, "validation_error", "event_time must be a non-empty string", map[string]any{"field": "event_time"})
		return
	}
	if !hasTopLevelJSONKey(bodyBytes, "metadata") {
		writeError(w, http.StatusBadRequest, "validation_error", "metadata is required", map[string]any{"field": "metadata"})
		return
	}
	if raw.Metadata == nil || string(raw.Metadata) == "null" {
		writeError(w, http.StatusBadRequest, "validation_error", "metadata must be a JSON object or array", map[string]any{"field": "metadata"})
		return
	}
	if !isJSONObjectOrArray(raw.Metadata) {
		writeError(w, http.StatusBadRequest, "validation_error", "metadata must be a JSON object or array", map[string]any{"field": "metadata"})
		return
	}

	eventTime := parseEventTimeOrServer(raw.EventTime, time.Now().UTC())

	ctx := r.Context()
	id, err := h.Repo.InsertEvent(ctx, raw.SystemName, raw.ObjectID, raw.EventName, raw.Metadata, eventTime)
	if err != nil {
		writeError(w, http.StatusServiceUnavailable, "storage_error", "failed to persist event", nil)
		return
	}
	writeJSON(w, http.StatusOK, map[string]int64{"id": id})
}

func hasTopLevelJSONKey(raw []byte, key string) bool {
	var m map[string]json.RawMessage
	if err := json.Unmarshal(raw, &m); err != nil {
		return false
	}
	_, ok := m[key]
	return ok
}

func isJSONObjectOrArray(raw json.RawMessage) bool {
	s := bytes.TrimSpace(raw)
	if len(s) == 0 {
		return false
	}
	c := s[0]
	return c == '{' || c == '['
}

func parseEventTimeOrServer(value string, serverNow time.Time) time.Time {
	value = strings.TrimSpace(value)
	if value == "" {
		return serverNow.UTC()
	}
	layouts := []string{
		time.RFC3339Nano,
		time.RFC3339,
		"2006-01-02T15:04:05Z0700",
		"2006-01-02 15:04:05Z07:00",
	}
	for _, layout := range layouts {
		if t, err := time.Parse(layout, value); err == nil {
			return t.UTC()
		}
	}
	if t, err := time.ParseInLocation("2006-01-02T15:04:05", value, time.UTC); err == nil {
		return t.UTC()
	}
	return serverNow.UTC()
}
