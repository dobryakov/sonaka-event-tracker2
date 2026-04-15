package config

import (
	"fmt"
	"os"
	"strconv"
)

const defaultMaxBodyBytes = 1048576

// Config holds runtime configuration loaded from the environment.
type Config struct {
	DatabaseURL              string
	HTTPAddr                 string
	MaxBodyBytes             int64
	MigrationsDir            string
	AllowDBFailureSimulation bool
}

// Load reads configuration from environment variables.
func Load() (Config, error) {
	dbURL := os.Getenv("DATABASE_URL")
	if dbURL == "" {
		return Config{}, fmt.Errorf("DATABASE_URL is required")
	}
	addr := os.Getenv("HTTP_ADDR")
	if addr == "" {
		addr = ":8080"
	}
	maxBody := int64(defaultMaxBodyBytes)
	if v := os.Getenv("MAX_BODY_BYTES"); v != "" {
		n, err := strconv.ParseInt(v, 10, 64)
		if err != nil || n <= 0 {
			return Config{}, fmt.Errorf("MAX_BODY_BYTES must be a positive integer")
		}
		maxBody = n
	}
	migDir := os.Getenv("MIGRATIONS_DIR")
	if migDir == "" {
		migDir = "migrations"
	}
	sim := os.Getenv("ALLOW_DB_FAILURE_SIMULATION") == "true" ||
		os.Getenv("ALLOW_DB_FAILURE_SIMULATION") == "1"
	return Config{
		DatabaseURL:              dbURL,
		HTTPAddr:                 addr,
		MaxBodyBytes:             maxBody,
		MigrationsDir:            migDir,
		AllowDBFailureSimulation: sim,
	}, nil
}
