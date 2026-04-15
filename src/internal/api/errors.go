package api

import (
	"encoding/json"
	"net/http"
)

// ErrorEnvelope matches the OpenAPI ErrorEnvelope schema.
type ErrorEnvelope struct {
	Code    string         `json:"code"`
	Message string         `json:"message"`
	Details map[string]any `json:"details,omitempty"`
}

// ErrorResponse is the top-level error JSON body.
type ErrorResponse struct {
	Error ErrorEnvelope `json:"error"`
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func writeError(w http.ResponseWriter, status int, code, message string, details map[string]any) {
	writeJSON(w, status, ErrorResponse{
		Error: ErrorEnvelope{
			Code:    code,
			Message: message,
			Details: details,
		},
	})
}
