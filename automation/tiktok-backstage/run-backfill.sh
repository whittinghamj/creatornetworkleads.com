#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
BATCH_SIZE="${BATCH_SIZE:-30}"
PAUSE_SECONDS="${PAUSE_SECONDS:-2}"
MAX_BATCH_RETRIES="${MAX_BATCH_RETRIES:-3}"
RETRY_DELAY_SECONDS="${RETRY_DELAY_SECONDS:-10}"

load_env() {
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
}

db_count_remaining() {
  node <<'NODE'
const fs = require('fs');
const mysql = require('mysql2/promise');

function loadEnv(filePath) {
  const text = fs.readFileSync(filePath, 'utf8');
  for (const raw of text.split(/\r?\n/)) {
    if (!raw || /^\s*#/.test(raw) || !raw.includes('=')) {
      continue;
    }
    const idx = raw.indexOf('=');
    const key = raw.slice(0, idx).trim();
    const value = raw.slice(idx + 1);
    process.env[key] = value;
  }
}

(async () => {
  loadEnv('.env');
  const db = await mysql.createConnection({
    host: process.env.DB_HOST,
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
  });

  const [rows] = await db.execute(`
    SELECT COUNT(*) AS remaining
    FROM creators
    WHERE backstage_checked = 'no'
      AND username IS NOT NULL
      AND username != ''
  `);

  await db.end();
  console.log(String(rows[0].remaining || 0));
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
NODE
}

db_mark_all_unchecked() {
  node <<'NODE'
const fs = require('fs');
const mysql = require('mysql2/promise');

function loadEnv(filePath) {
  const text = fs.readFileSync(filePath, 'utf8');
  for (const raw of text.split(/\r?\n/)) {
    if (!raw || /^\s*#/.test(raw) || !raw.includes('=')) {
      continue;
    }
    const idx = raw.indexOf('=');
    const key = raw.slice(0, idx).trim();
    const value = raw.slice(idx + 1);
    process.env[key] = value;
  }
}

(async () => {
  loadEnv('.env');
  const db = await mysql.createConnection({
    host: process.env.DB_HOST,
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
  });

  const [result] = await db.execute(`
    UPDATE creators
    SET backstage_checked = 'no'
    WHERE username IS NOT NULL
      AND username != ''
  `);

  await db.end();
  console.log(String(result.affectedRows || 0));
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
NODE
}

cd "${ROOT_DIR}"
load_env

if [[ -z "${TT_BACKSTAGE_EMAIL:-}" || -z "${TT_BACKSTAGE_PASSWORD:-}" ]]; then
  echo "Set TT_BACKSTAGE_EMAIL and TT_BACKSTAGE_PASSWORD in .env or shell environment." >&2
  exit 1
fi

export BATCH_SIZE

echo "Backfill starting with batch size ${BATCH_SIZE}."
reset_count="$(db_mark_all_unchecked)"
echo "Marked ${reset_count} creator records as unchecked for revalidation."

batch_no=1
while true; do
  remaining="$(db_count_remaining)"
  if [[ "${remaining}" -eq 0 ]]; then
    echo "Backfill complete. No unchecked creators remain."
    break
  fi

  echo "Batch ${batch_no}: ${remaining} creators remaining before run."

  attempt=1
  batch_succeeded=0
  while [[ "${attempt}" -le "${MAX_BATCH_RETRIES}" ]]; do
    if ./run.sh; then
      batch_succeeded=1
      break
    fi

    echo "Batch ${batch_no} attempt ${attempt}/${MAX_BATCH_RETRIES} failed." >&2

    if [[ "${attempt}" -lt "${MAX_BATCH_RETRIES}" ]]; then
      echo "Retrying batch ${batch_no} in ${RETRY_DELAY_SECONDS}s..." >&2
      sleep "${RETRY_DELAY_SECONDS}"
    fi

    attempt="$((attempt + 1))"
  done

  if [[ "${batch_succeeded}" -ne 1 ]]; then
    echo "Batch ${batch_no} failed after ${MAX_BATCH_RETRIES} attempts. Stopping backfill." >&2
    exit 1
  fi

  remaining_after="$(db_count_remaining)"
  echo "Batch ${batch_no} finished. ${remaining_after} creators remaining."

  batch_no="$((batch_no + 1))"

  if [[ "${PAUSE_SECONDS}" -gt 0 ]]; then
    sleep "${PAUSE_SECONDS}"
  fi
done
