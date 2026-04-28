#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
PROCESS_SCRIPT="${ROOT_DIR}/run.sh"

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

pick_node() {
  local candidate

  for candidate in "${NODE_BIN:-}" "$(command -v node || true)" /opt/homebrew/bin/node /usr/local/bin/node; do
    if [[ -z "${candidate}" || ! -x "${candidate}" ]]; then
      continue
    fi

    local major
    major="$("${candidate}" -p 'process.versions.node.split(".")[0]' 2>/dev/null || true)"
    if [[ "${major}" =~ ^[0-9]+$ ]] && (( major >= 18 )); then
      printf '%s\n' "${candidate}"
      return 0
    fi
  done

  return 1
}

NODE_CMD="$(pick_node || true)"

if [[ -z "${NODE_CMD}" ]]; then
  echo "Node.js 18+ is required to run the explore scraper." >&2
  exit 1
fi

cd "${ROOT_DIR}"
"${NODE_CMD}" explore-usernames.js

if [[ "${EXPLORE_PROCESS_UNCHECKED:-true}" == "true" ]]; then
  if [[ ! -x "${PROCESS_SCRIPT}" ]]; then
    echo "Found new explore usernames, but ${PROCESS_SCRIPT} is not executable." >&2
    exit 1
  fi

  echo "[run-explore.sh] Processing newly added usernames through scrape.js ..."
  exec "${PROCESS_SCRIPT}" 1
fi
