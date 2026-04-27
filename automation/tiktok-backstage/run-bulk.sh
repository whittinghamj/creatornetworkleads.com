#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
ROOT_ENV_FILE="${ROOT_DIR}/../../.env"
LOOPS="${1:-1}"
CLI_USERNAME="${2:-}"
RETRY_DELAY_SECONDS="${RETRY_DELAY_SECONDS:-60}"
MAX_CONSECUTIVE_FAILURES="${MAX_CONSECUTIVE_FAILURES:-3}"
BATCH_SIZE="${BATCH_SIZE:-30}"
ACCOUNTS_TABLE="${TT_BACKSTAGE_ACCOUNTS_TABLE:-backstage_accounts}"

declare -a ACCOUNT_EMAILS=()
declare -a ACCOUNT_PASSWORDS=()
declare -a ACCOUNT_IDS=()

load_env() {
  local source_file=""
  if [[ -f "${ROOT_ENV_FILE}" ]]; then
    source_file="${ROOT_ENV_FILE}"
  elif [[ -f "${ENV_FILE}" ]]; then
    source_file="${ENV_FILE}"
  fi

  if [[ -n "${source_file}" ]]; then
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
    done < "${source_file}"
  fi
}

append_account() {
  local account_id="$1"
  local email="$2"
  local password="$3"

  if [[ -z "${email}" || -z "${password}" ]]; then
    return
  fi

  ACCOUNT_EMAILS+=("${email}")
  ACCOUNT_PASSWORDS+=("${password}")
  ACCOUNT_IDS+=("${account_id}")
}

mark_account_timestamp() {
  local account_id="$1"
  local column_name="$2"

  if [[ -z "${account_id}" ]]; then
    return
  fi

  if ! [[ "${account_id}" =~ ^[0-9]+$ ]]; then
    return
  fi

  if [[ -z "${DB_HOST:-}" || -z "${DB_USER:-}" || -z "${DB_NAME:-}" ]]; then
    return
  fi

  case "${column_name}" in
    last_used_at|last_success_at|last_failure_at)
      ;;
    *)
      return
      ;;
  esac

  ACCOUNT_ID="${account_id}" TRACK_COLUMN="${column_name}" ACCOUNTS_TABLE="${ACCOUNTS_TABLE}" node <<'NODE' >/dev/null 2>&1 || true
const mysql = require('mysql2/promise');

function clean(v) {
  return String(v ?? '').trim();
}

(async () => {
  const table = clean(process.env.ACCOUNTS_TABLE || 'backstage_accounts').replace(/[^a-zA-Z0-9_]/g, '');
  const col = clean(process.env.TRACK_COLUMN || '');
  const accountId = Number.parseInt(clean(process.env.ACCOUNT_ID), 10);

  const allowedCols = new Set(['last_used_at', 'last_success_at', 'last_failure_at']);
  if (!table || !allowedCols.has(col) || !Number.isInteger(accountId) || accountId <= 0) {
    return;
  }

  const db = await mysql.createConnection({
    host: process.env.DB_HOST,
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
  });

  try {
    await db.query(`UPDATE ${table} SET ${col} = NOW() WHERE id = ?`, [accountId]);
  } finally {
    await db.end();
  }
})().catch(() => {
  // Ignore logging failures so scraping can continue.
});
NODE
}

load_accounts() {
  local db_loaded=0

  if [[ -n "${DB_HOST:-}" && -n "${DB_USER:-}" && -n "${DB_NAME:-}" ]]; then
    # Pull active backstage logins from DB first.
    local db_rows
    db_rows="$({
      ACCOUNTS_TABLE="${ACCOUNTS_TABLE}" node <<'NODE'
const mysql = require('mysql2/promise');

function clean(v) {
  return String(v ?? '').trim();
}

(async () => {
  const table = clean(process.env.ACCOUNTS_TABLE || 'backstage_accounts').replace(/[^a-zA-Z0-9_]/g, '');
  if (!table) {
    process.exit(0);
  }

  const db = await mysql.createConnection({
    host: process.env.DB_HOST,
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
  });

  try {
    const [colRows] = await db.query(`SHOW COLUMNS FROM ${table} LIKE 'is_active'`);
    const hasIsActive = Array.isArray(colRows) && colRows.length > 0;

    const whereParts = [
      "email IS NOT NULL",
      "email != ''",
      "password IS NOT NULL",
      "password != ''",
    ];

    if (hasIsActive) {
      whereParts.push(
        "LOWER(TRIM(CAST(COALESCE(is_active, '1') AS CHAR))) IN ('1', 'true', 'yes', 'active')"
      );
    }

    const sql = `SELECT id, email, password FROM ${table} WHERE ${whereParts.join(' AND ')}`;
    const [rows] = await db.query(sql);

    for (const row of rows) {
      const id = Number.parseInt(clean(row.id), 10);
      const email = clean(row.email);
      const password = clean(row.password);
      if (!Number.isInteger(id) || id <= 0 || !email || !password) {
        continue;
      }
      console.log(`${id}\t${email}\t${password}`);
    }
  } catch (error) {
    console.error(`Failed loading backstage accounts from table '${table}': ${error.message}`);
    process.exit(1);
  } finally {
    await db.end();
  }
})().catch((error) => {
  console.error(error?.message || String(error));
  process.exit(1);
});
NODE
    })"

    if [[ -n "${db_rows}" ]]; then
      while IFS=$'\t' read -r account_id email password; do
        append_account "${account_id}" "${email}" "${password}"
      done <<< "${db_rows}"

      if [[ "${#ACCOUNT_EMAILS[@]}" -gt 0 ]]; then
        db_loaded=1
      fi
    fi
  fi

  if [[ "${db_loaded}" -eq 1 ]]; then
    return
  fi

  if [[ -n "${TT_BACKSTAGE_ACCOUNTS:-}" ]]; then
    IFS=';' read -r -a pairs <<< "${TT_BACKSTAGE_ACCOUNTS}"
    for pair in "${pairs[@]}"; do
      pair="${pair#"${pair%%[![:space:]]*}"}"
      pair="${pair%"${pair##*[![:space:]]}"}"

      [[ -z "${pair}" ]] && continue
      [[ "${pair}" != *:* ]] && continue

      email="${pair%%:*}"
      password="${pair#*:}"

      append_account "" "${email}" "${password}"
    done
  fi

  local idx=1
  while :; do
    local email_var="TT_BACKSTAGE_EMAIL_${idx}"
    local pass_var="TT_BACKSTAGE_PASSWORD_${idx}"
    local email="${!email_var:-}"
    local password="${!pass_var:-}"

    if [[ -z "${email}" || -z "${password}" ]]; then
      break
    fi

    append_account "" "${email}" "${password}"
    idx=$((idx + 1))
  done

  if [[ "${#ACCOUNT_EMAILS[@]}" -eq 0 ]]; then
    append_account "" "${TT_BACKSTAGE_EMAIL:-}" "${TT_BACKSTAGE_PASSWORD:-}"
  fi

  if [[ "${#ACCOUNT_EMAILS[@]}" -eq 0 ]]; then
    echo "No active backstage accounts found in DB table '${ACCOUNTS_TABLE}', and no fallback .env accounts were provided." >&2
    exit 1
  fi
}

pick_random_index() {
  local previous_index="$1"
  local count="${#ACCOUNT_EMAILS[@]}"
  local next_index

  if [[ "${count}" -le 1 ]]; then
    echo 0
    return
  fi

  while :; do
    next_index=$((RANDOM % count))
    if [[ "${next_index}" -ne "${previous_index}" ]]; then
      echo "${next_index}"
      return
    fi
  done
}

if ! [[ "${LOOPS}" =~ ^[0-9]+$ ]] || [[ "${LOOPS}" -lt 1 ]]; then
  echo "Usage: ./run-bulk.sh [loops] [username]  (loops must be a positive integer, defaults to 1)" >&2
  exit 1
fi

load_env
load_accounts

if ! [[ "${BATCH_SIZE}" =~ ^[0-9]+$ ]] || [[ "${BATCH_SIZE}" -lt 1 ]]; then
  echo "BATCH_SIZE must be a positive integer. Current value: ${BATCH_SIZE}" >&2
  exit 1
fi

if [[ -n "${CLI_USERNAME}" ]]; then
  export TT_CREATOR_USERNAME="${CLI_USERNAME}"
fi

export BATCH_SIZE

cd "${ROOT_DIR}"

consecutive_failures=0
i=1
last_account_index=-1

echo "[run-bulk.sh] Loaded ${#ACCOUNT_EMAILS[@]} login account(s). Batch size: ${BATCH_SIZE}."

while [[ "${i}" -le "${LOOPS}" ]]; do
  account_index="$(pick_random_index "${last_account_index}")"
  last_account_index="${account_index}"

  export TT_BACKSTAGE_EMAIL="${ACCOUNT_EMAILS[account_index]}"
  export TT_BACKSTAGE_PASSWORD="${ACCOUNT_PASSWORDS[account_index]}"
  account_id="${ACCOUNT_IDS[account_index]}"

  mark_account_timestamp "${account_id}" "last_used_at"

  echo "[run-bulk.sh] Loop ${i} of ${LOOPS} — starting scrape.js with account $((account_index + 1))/${#ACCOUNT_EMAILS[@]} …"

  if node scrape.js; then
    mark_account_timestamp "${account_id}" "last_success_at"
    consecutive_failures=0

    if [[ "${i}" -lt "${LOOPS}" ]]; then
      echo "[run-bulk.sh] Waiting 5 seconds before next batch/login rotation …"
      sleep 5
    fi

    i=$((i + 1))
    continue
  fi

  consecutive_failures=$((consecutive_failures + 1))
  mark_account_timestamp "${account_id}" "last_failure_at"
  echo "[run-bulk.sh] scrape.js failed (${consecutive_failures}/${MAX_CONSECUTIVE_FAILURES} consecutive failures)." >&2

  if [[ "${consecutive_failures}" -ge "${MAX_CONSECUTIVE_FAILURES}" ]]; then
    echo "[run-bulk.sh] Reached ${MAX_CONSECUTIVE_FAILURES} consecutive failures. Stopping." >&2
    exit 1
  fi

  echo "[run-bulk.sh] Waiting ${RETRY_DELAY_SECONDS} seconds before retrying loop ${i} …" >&2
  sleep "${RETRY_DELAY_SECONDS}"
done

echo "[run-bulk.sh] All ${LOOPS} loop(s) complete."