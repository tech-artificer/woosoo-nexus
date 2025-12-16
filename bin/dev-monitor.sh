#!/usr/bin/env bash
set -euo pipefail

# Dev server monitor script
# - starts `npm run dev` in background
# - waits up to 120s for the dev server URL to respond
# - tails the log and attempts up to 3 restarts on exit

VITE_PORT="${VITE_DEV_PORT:-3000}"
VITE_URL="${VITE_DEV_SERVER_URL:-http://localhost:${VITE_PORT}}"
LOG_FILE="vite.log"
MAX_RESTARTS=3
RESTART_COUNT=0

start_server() {
  echo "Starting dev server (npm run dev) at ${VITE_URL}..."
  npm run dev > "${LOG_FILE}" 2>&1 &
  DEV_PID=$!
  echo "Dev server PID: ${DEV_PID}"
}

wait_for_server() {
  echo "Waiting for ${VITE_URL} to respond (timeout 120s)..."
  if command -v npx >/dev/null 2>&1; then
    npx wait-on "${VITE_URL}" --timeout 120000
    return $?
  fi

  local elapsed=0
  while [ ${elapsed} -lt 120 ]; do
    if curl -sSf "${VITE_URL}" >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
    elapsed=$((elapsed + 2))
  done
  return 1
}

restart_with_backoff() {
  RESTART_COUNT=$((RESTART_COUNT + 1))
  if [ "${RESTART_COUNT}" -gt "${MAX_RESTARTS}" ]; then
    echo "Exceeded max restarts (${MAX_RESTARTS}). Exiting."
    return 1
  fi
  local backoff=$((RESTART_COUNT * 3))
  echo "Restart attempt ${RESTART_COUNT} in ${backoff} seconds..."
  sleep "${backoff}"
  start_server
  if wait_for_server; then
    return 0
  fi
  return 1
}

main() {
  start_server
  if wait_for_server; then
    echo "Dev server is up. Tailing ${LOG_FILE}"
    tail -F "${LOG_FILE}" &
    TAIL_PID=$!
    # Wait for the dev server process; if it exits, try restarts
    wait "${DEV_PID}" || true
    echo "Dev server process exited."
    kill "${TAIL_PID}" 2>/dev/null || true

    while [ "${RESTART_COUNT}" -lt "${MAX_RESTARTS}" ]; do
      if restart_with_backoff; then
        echo "Restart success; resuming log tail."
        tail -F "${LOG_FILE}" &
        TAIL_PID=$!
        wait "${DEV_PID}" || true
        kill "${TAIL_PID}" 2>/dev/null || true
      else
        echo "Restart failed."
      fi
    done

    echo "All restart attempts exhausted. Showing last 200 lines of ${LOG_FILE}:"
    tail -n 200 "${LOG_FILE}" || true
    exit 1
  else
    echo "Dev server did not respond within timeout. Showing ${LOG_FILE}:"
    tail -n 200 "${LOG_FILE}" || true
    exit 1
  fi
}

main
