#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STATE_DIR="${ROOT_DIR}/.daemon"
PID_FILE="${STATE_DIR}/explore-daemon.pid"
STOP_FILE="${STATE_DIR}/explore-daemon.stop"

mkdir -p "${STATE_DIR}"
touch "${STOP_FILE}"

echo "Stop signal written to ${STOP_FILE}."

if [[ -f "${PID_FILE}" ]]; then
  pid="$(cat "${PID_FILE}" 2>/dev/null || true)"
  if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
    echo "Daemon PID ${pid} is running and will stop after current sleep/cycle."
  else
    echo "No active daemon process found for PID file."
  fi
else
  echo "No PID file found."
fi
