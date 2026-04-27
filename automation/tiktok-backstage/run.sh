#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
LOOPS="${1:-1}"
CLI_USERNAME="${2:-}"

if ! [[ "${LOOPS}" =~ ^[0-9]+$ ]] || [[ "${LOOPS}" -lt 1 ]]; then
  echo "Usage: ./run.sh [loops] [username]  (loops must be a positive integer, defaults to 1)" >&2
  exit 1
fi

if [[ -f "${ENV_FILE}" ]]; then
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
fi

if [[ -z "${TT_BACKSTAGE_EMAIL:-}" || -z "${TT_BACKSTAGE_PASSWORD:-}" ]]; then
  echo "Set TT_BACKSTAGE_EMAIL and TT_BACKSTAGE_PASSWORD in .env or the shell environment." >&2
  exit 1
fi

if [[ -n "${CLI_USERNAME}" ]]; then
  export TT_CREATOR_USERNAME="${CLI_USERNAME}"
fi

cd "${ROOT_DIR}"

for (( i = 1; i <= LOOPS; i++ )); do
  echo "[run.sh] Loop ${i} of ${LOOPS} — starting scrape.js …"
  node scrape.js
  if [[ "${i}" -lt "${LOOPS}" ]]; then
    echo "[run.sh] Waiting 5 seconds before next run …"
    sleep 5
  fi
done

echo "[run.sh] All ${LOOPS} loop(s) complete."
