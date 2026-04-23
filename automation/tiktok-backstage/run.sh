#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
CLI_USERNAME="${1:-}"

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
exec node scrape.js
