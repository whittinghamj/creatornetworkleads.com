#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STATE_DIR="${ROOT_DIR}/.daemon"
PID_FILE="${STATE_DIR}/explore-daemon.pid"
STOP_FILE="${STATE_DIR}/explore-daemon.stop"
LOG_FILE="${STATE_DIR}/explore-daemon-cron.log"
DAEMON_SCRIPT="${ROOT_DIR}/run-explore-daemon.sh"

mkdir -p "${STATE_DIR}"

if [[ -f "${STOP_FILE}" ]]; then
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Stop file present; cron launcher will not start daemon." >> "${LOG_FILE}"
  exit 0
fi

if [[ -f "${PID_FILE}" ]]; then
  existing_pid="$(cat "${PID_FILE}" 2>/dev/null || true)"
  if [[ -n "${existing_pid}" ]] && kill -0 "${existing_pid}" 2>/dev/null; then
    exit 0
  fi
fi

if [[ ! -x "${DAEMON_SCRIPT}" ]]; then
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Missing executable daemon script: ${DAEMON_SCRIPT}" >> "${LOG_FILE}"
  exit 1
fi

nohup "${DAEMON_SCRIPT}" >> "${LOG_FILE}" 2>&1 &
