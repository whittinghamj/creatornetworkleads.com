#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUN_SCRIPT="${ROOT_DIR}/run-explore.sh"
ENV_FILE="${ROOT_DIR}/.env"

STATE_DIR="${ROOT_DIR}/.daemon"
PID_FILE="${STATE_DIR}/explore-daemon.pid"
STOP_FILE="${STATE_DIR}/explore-daemon.stop"
LOCK_FILE="${STATE_DIR}/explore-daemon.lock"
LOG_FILE="${STATE_DIR}/explore-daemon.log"
LAST_RUN_FILE="${STATE_DIR}/explore-daemon.last_run"
FAIL_STREAK_FILE="${STATE_DIR}/explore-daemon.fail_streak"
RUN_COUNT_PREFIX="${STATE_DIR}/explore-daemon.runs"

mkdir -p "${STATE_DIR}"

ts() {
  date '+%Y-%m-%d %H:%M:%S'
}

log() {
  printf '[%s] %s\n' "$(ts)" "$*" | tee -a "${LOG_FILE}"
}

load_env_file() {
  if [[ ! -f "${ENV_FILE}" ]]; then
    return
  fi

  while IFS= read -r line || [[ -n "${line}" ]]; do
    line="${line%$'\r'}"
    if [[ -z "${line}" || "${line}" =~ ^[[:space:]]*# ]]; then
      continue
    fi
    if [[ "${line}" != *=* ]]; then
      continue
    fi

    key="${line%%=*}"
    value="${line#*=}"

    key="${key#"${key%%[![:space:]]*}"}"
    key="${key%"${key##*[![:space:]]}"}"

    export "${key}=${value}"
  done < "${ENV_FILE}"
}

# Safety defaults (can be overridden by .env)
# These safeguards are intended to reduce load and respect platform limits.
DAEMON_LOOP_INTERVAL_SECONDS="${DAEMON_LOOP_INTERVAL_SECONDS:-1800}"
DAEMON_MIN_SECONDS_BETWEEN_RUNS="${DAEMON_MIN_SECONDS_BETWEEN_RUNS:-1200}"
DAEMON_MAX_RUNS_PER_DAY="${DAEMON_MAX_RUNS_PER_DAY:-24}"
DAEMON_JITTER_SECONDS="${DAEMON_JITTER_SECONDS:-300}"
DAEMON_FAILURE_BACKOFF_BASE_SECONDS="${DAEMON_FAILURE_BACKOFF_BASE_SECONDS:-1800}"
DAEMON_FAILURE_BACKOFF_MAX_SECONDS="${DAEMON_FAILURE_BACKOFF_MAX_SECONDS:-21600}"
DAEMON_QUIET_HOURS_START="${DAEMON_QUIET_HOURS_START:-0}"
DAEMON_QUIET_HOURS_END="${DAEMON_QUIET_HOURS_END:-6}"

load_env_file

if [[ ! -x "${RUN_SCRIPT}" ]]; then
  log "Missing or non-executable run script: ${RUN_SCRIPT}"
  exit 1
fi

if [[ -f "${PID_FILE}" ]]; then
  existing_pid="$(cat "${PID_FILE}" 2>/dev/null || true)"
  if [[ -n "${existing_pid}" ]] && kill -0 "${existing_pid}" 2>/dev/null; then
    log "Daemon already running with PID ${existing_pid}."
    exit 1
  fi
fi

exec 9>"${LOCK_FILE}"
if ! flock -n 9; then
  log "Could not acquire daemon lock (another instance may be running)."
  exit 1
fi

echo "$$" > "${PID_FILE}"
rm -f "${STOP_FILE}"

cleanup() {
  rm -f "${PID_FILE}"
}
trap cleanup EXIT
trap 'log "Received stop signal."; touch "${STOP_FILE}"' INT TERM

run_count_file_for_today() {
  local day
  day="$(date +%Y%m%d)"
  printf '%s.%s' "${RUN_COUNT_PREFIX}" "${day}"
}

get_today_run_count() {
  local file
  file="$(run_count_file_for_today)"
  if [[ -f "${file}" ]]; then
    cat "${file}"
  else
    echo 0
  fi
}

increment_today_run_count() {
  local file current
  file="$(run_count_file_for_today)"
  current="$(get_today_run_count)"
  echo $(( current + 1 )) > "${file}"
}

get_last_run_epoch() {
  if [[ -f "${LAST_RUN_FILE}" ]]; then
    cat "${LAST_RUN_FILE}"
  else
    echo 0
  fi
}

set_last_run_epoch_now() {
  date +%s > "${LAST_RUN_FILE}"
}

get_fail_streak() {
  if [[ -f "${FAIL_STREAK_FILE}" ]]; then
    cat "${FAIL_STREAK_FILE}"
  else
    echo 0
  fi
}

set_fail_streak() {
  echo "$1" > "${FAIL_STREAK_FILE}"
}

rand_jitter() {
  local max="${1:-0}"
  if (( max <= 0 )); then
    echo 0
    return
  fi

  if command -v shuf >/dev/null 2>&1; then
    shuf -i "0-${max}" -n 1
  else
    echo $(( RANDOM % (max + 1) ))
  fi
}

sleep_interruptible() {
  local seconds="${1:-0}"
  if (( seconds <= 0 )); then
    return 0
  fi

  local i
  for ((i=0; i<seconds; i++)); do
    if [[ -f "${STOP_FILE}" ]]; then
      return 1
    fi
    sleep 1
  done
  return 0
}

in_quiet_hours() {
  local hour
  hour="$(date +%H)"
  hour=$((10#${hour}))

  local start end
  start=$((10#${DAEMON_QUIET_HOURS_START}))
  end=$((10#${DAEMON_QUIET_HOURS_END}))

  # Quiet window wraps midnight when start > end.
  if (( start == end )); then
    return 1
  fi

  if (( start < end )); then
    (( hour >= start && hour < end ))
  else
    (( hour >= start || hour < end ))
  fi
}

seconds_until_quiet_window_end() {
  local now hour minute second end target_hour
  now="$(date +%s)"
  hour=$((10#$(date +%H)))
  minute=$((10#$(date +%M)))
  second=$((10#$(date +%S)))
  end=$((10#${DAEMON_QUIET_HOURS_END}))

  target_hour="${end}"
  if (( hour >= end )); then
    target_hour=$(( end + 24 ))
  fi

  echo $(( (target_hour - hour) * 3600 - minute * 60 - second ))
}

seconds_until_tomorrow() {
  local now tomorrow
  now="$(date +%s)"
  tomorrow="$(date -v+1d '+%Y-%m-%d 00:00:00' 2>/dev/null || date -d 'tomorrow 00:00:00' '+%Y-%m-%d %H:%M:%S')"
  echo $(( $(date -j -f '%Y-%m-%d %H:%M:%S' "${tomorrow}" +%s 2>/dev/null || date -d "${tomorrow}" +%s) - now ))
}

log "Explore daemon started (PID $$)."
log "Safeguards: min_interval=${DAEMON_MIN_SECONDS_BETWEEN_RUNS}s, max_runs_per_day=${DAEMON_MAX_RUNS_PER_DAY}, quiet_hours=${DAEMON_QUIET_HOURS_START}-${DAEMON_QUIET_HOURS_END}."

while true; do
  if [[ -f "${STOP_FILE}" ]]; then
    log "Stop file detected; daemon exiting."
    break
  fi

  if in_quiet_hours; then
    wait_secs="$(seconds_until_quiet_window_end)"
    wait_secs=$(( wait_secs + $(rand_jitter 120) ))
    log "Quiet hours active; sleeping ${wait_secs}s."
    sleep_interruptible "${wait_secs}" || break
    continue
  fi

  today_runs="$(get_today_run_count)"
  if (( today_runs >= DAEMON_MAX_RUNS_PER_DAY )); then
    wait_secs="$(seconds_until_tomorrow)"
    wait_secs=$(( wait_secs + $(rand_jitter 180) ))
    log "Reached daily run cap (${today_runs}/${DAEMON_MAX_RUNS_PER_DAY}); sleeping ${wait_secs}s."
    sleep_interruptible "${wait_secs}" || break
    continue
  fi

  now_epoch="$(date +%s)"
  last_run_epoch="$(get_last_run_epoch)"
  since_last=$(( now_epoch - last_run_epoch ))
  if (( last_run_epoch > 0 && since_last < DAEMON_MIN_SECONDS_BETWEEN_RUNS )); then
    wait_secs=$(( DAEMON_MIN_SECONDS_BETWEEN_RUNS - since_last + $(rand_jitter 60) ))
    log "Enforcing min interval; sleeping ${wait_secs}s."
    sleep_interruptible "${wait_secs}" || break
    continue
  fi

  log "Starting explore cycle..."
  if "${RUN_SCRIPT}" >> "${LOG_FILE}" 2>&1; then
    increment_today_run_count
    set_last_run_epoch_now
    set_fail_streak 0

    sleep_secs=$(( DAEMON_LOOP_INTERVAL_SECONDS + $(rand_jitter "${DAEMON_JITTER_SECONDS}") ))
    log "Explore cycle succeeded. Next run in ${sleep_secs}s."
    sleep_interruptible "${sleep_secs}" || break
  else
    fail_streak="$(get_fail_streak)"
    fail_streak=$(( fail_streak + 1 ))
    set_fail_streak "${fail_streak}"

    backoff=$(( DAEMON_FAILURE_BACKOFF_BASE_SECONDS * (2 ** (fail_streak - 1)) ))
    if (( backoff > DAEMON_FAILURE_BACKOFF_MAX_SECONDS )); then
      backoff="${DAEMON_FAILURE_BACKOFF_MAX_SECONDS}"
    fi
    backoff=$(( backoff + $(rand_jitter "${DAEMON_JITTER_SECONDS}") ))

    log "Explore cycle failed (streak=${fail_streak}). Backing off for ${backoff}s."
    sleep_interruptible "${backoff}" || break
  fi
done

log "Explore daemon stopped."
