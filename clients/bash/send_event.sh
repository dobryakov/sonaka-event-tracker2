#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${INGEST_BASE_URL:-http://app:8080}"
BASE_URL="${BASE_URL%/}"

rand_hex() {
	if command -v openssl >/dev/null 2>&1; then
		openssl rand -hex 4
	else
		date +%s
	fi
}

OBJECT_ID="obj-$(rand_hex)"
META_KEY="run"
META_VAL="$(rand_hex)"

BODY="$(printf '%s' "{
  \"system_name\": \"bash-client\",
  \"object_id\": \"${OBJECT_ID}\",
  \"event_name\": \"BashSend\",
  \"event_time\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\",
  \"metadata\": { \"${META_KEY}\": \"${META_VAL}\", \"n\": $RANDOM }
}")"

code="$(curl -sS -o /tmp/sonaka_resp.json -w '%{http_code}' \
	-X POST "${BASE_URL}/events" \
	-H 'Content-Type: application/json' \
	-d "${BODY}")"

if ! printf '%s' "${code}" | grep -Eq '^2[0-9][0-9]$'; then
	echo "HTTP error: ${code}" >&2
	cat /tmp/sonaka_resp.json >&2 || true
	exit 1
fi

printf 'OK %s\n' "$(cat /tmp/sonaka_resp.json)"
