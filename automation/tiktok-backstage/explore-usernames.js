import { chromium } from "playwright";
import mysql from "mysql2/promise";

const LIVE_URL = "https://www.tiktok.com/live";
const HEADLESS = process.env.HEADLESS !== "false";
const TIMEOUT_MS = Number(process.env.TIMEOUT_MS || 90000);
const DB_PORT = Number(process.env.DB_PORT || 3306);
const REFRESH_ROUNDS = Number(process.env.REFRESH_ROUNDS || 100);
const REFRESH_WAIT_MS = Number(process.env.REFRESH_WAIT_MS || 5000);

const LIVE_HREF_REGEX = /^\/@([^/]+)\/live(?:\/)?(?:\?.*)?$/i;

function buildLiveUrl(attempt, phase = "open") {
  const url = new URL(LIVE_URL);
  url.searchParams.set("fresh", `${Date.now()}-${attempt}-${phase}`);
  url.searchParams.set("a", String(Math.floor(Math.random() * 1001)));
  return url.toString();
}

function getDatabaseConfig() {
  const config = {
    host: process.env.DB_HOST,
    port: DB_PORT,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
  };

  const missing = Object.entries(config)
    .filter(([, value]) => value === undefined || value === null || value === "")
    .map(([key]) => key.toUpperCase());

  if (missing.length) {
    throw new Error(
      `Missing database configuration: ${missing.join(", ")}.`
    );
  }

  return config;
}

function extractUsernameFromLiveHref(rawHref) {
  if (!rawHref) {
    return null;
  }

  const match = rawHref.trim().match(LIVE_HREF_REGEX);
  return match?.[1] || null;
}

async function dismissOverlays(page) {
  const buttons = [
    page.getByRole("button", { name: /accept/i }).first(),
    page.getByRole("button", { name: /agree/i }).first(),
    page.getByRole("button", { name: /allow all/i }).first(),
    page.getByRole("button", { name: /decline/i }).first(),
    page.getByRole("button", { name: /not now/i }).first(),
  ];

  for (const button of buttons) {
    try {
      if (await button.isVisible({ timeout: 1200 })) {
        await button.click({ timeout: 2000 });
      }
    } catch {
      // Overlay not present.
    }
  }
}

async function collectSuggestedLiveUsernames(page) {
  return page.evaluate(() => {
    const usernames = new Set();
    const section = document.querySelector('[data-e2e="live-side-nav-channel"]');
    if (!section) {
      return [];
    }

    for (const item of section.querySelectorAll('[data-e2e="live-side-nav-item"] a[href]')) {
      const href = item.getAttribute("href");
      if (!href) {
        continue;
      }

      const username = href
        .trim()
        .replace(/^\/@/i, "")
        .replace(/\/live(?:\/)?(?:\?.*)?$/i, "")
        .trim();

      if (username && !username.includes("/")) {
        usernames.add(username);
      }
    }

    return [...usernames];
  });
}

async function clearPageState(page, context) {
  await context.clearCookies().catch(() => {});

  const cdp = await context.newCDPSession(page).catch(() => null);
  if (cdp) {
    await cdp.send("Network.enable").catch(() => {});
    await cdp.send("Network.setCacheDisabled", { cacheDisabled: true }).catch(() => {});
    await cdp.send("Network.clearBrowserCache").catch(() => {});
    await cdp.send("Network.clearBrowserCookies").catch(() => {});
  }

  await page.evaluate(async () => {
    try {
      localStorage.clear();
      sessionStorage.clear();
    } catch {
      // Ignore storage access issues.
    }

    try {
      const databases = await indexedDB.databases();
      for (const database of databases) {
        if (database.name) {
          indexedDB.deleteDatabase(database.name);
        }
      }
    } catch {
      // Ignore IndexedDB issues.
    }

    try {
      const registrations = await navigator.serviceWorker.getRegistrations();
      for (const registration of registrations) {
        await registration.unregister();
      }
    } catch {
      // Ignore service worker issues.
    }

    try {
      if ("caches" in window) {
        const cacheNames = await caches.keys();
        await Promise.all(cacheNames.map((name) => caches.delete(name)));
      }
    } catch {
      // Ignore Cache Storage issues.
    }
  }).catch(() => {});
}

async function insertCreators(usernames) {
  const connection = await mysql.createConnection(getDatabaseConfig());

  try {
    const unixTimestamp = Math.floor(Date.now() / 1000).toString();
    let insertedCount = 0;

    for (const username of usernames) {
      const [result] = await connection.execute(
        `
          INSERT INTO creators (added, username)
          SELECT ?, ?
          FROM DUAL
          WHERE NOT EXISTS (
            SELECT 1
            FROM creators
            WHERE username = ?
          )
        `,
        [unixTimestamp, username, username]
      );

      insertedCount += result.affectedRows || 0;
    }

    return insertedCount;
  } finally {
    await connection.end();
  }
}

async function loadExistingUsernames() {
  const connection = await mysql.createConnection(getDatabaseConfig());

  try {
    const [rows] = await connection.execute(
      "SELECT username FROM creators WHERE username IS NOT NULL AND username != ''"
    );

    return new Set(
      rows
        .map((row) => String(row.username || "").trim())
        .filter(Boolean)
    );
  } finally {
    await connection.end();
  }
}

async function openLivePage(page, context, attempt) {
  await clearPageState(page, context);
  await page.goto(buildLiveUrl(attempt, "open"), {
    waitUntil: "domcontentloaded",
    timeout: TIMEOUT_MS,
  });

  await dismissOverlays(page);
  await page.waitForTimeout(3000);
}

async function forceRefreshLivePage(page, context, attempt) {
  await clearPageState(page, context);

  await page.reload({
    waitUntil: "domcontentloaded",
    timeout: TIMEOUT_MS,
  }).catch(() => {});

  await page.keyboard.press("F5").catch(() => {});
  await page.goto(buildLiveUrl(attempt, "reload"), {
    waitUntil: "domcontentloaded",
    timeout: TIMEOUT_MS,
  });

  await dismissOverlays(page);
  await page.waitForTimeout(3000);
}

async function main() {
  const browser = await chromium.launch({
    headless: HEADLESS,
  });

  try {
    const existingUsernames = await loadExistingUsernames();
    const usernames = [];
    const seenInRun = new Set();
    
    for (let round = 0; round < REFRESH_ROUNDS; round += 1) {
      const context = await browser.newContext({
        userAgent:
          "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
        viewport: {
          width: 1280 + (round % 3) * 80,
          height: 2000 + (round % 4) * 120,
        },
        locale: "en-GB",
      });
      const page = await context.newPage();

      await openLivePage(page, context, round);
      if (round > 0) {
        await forceRefreshLivePage(page, context, round);
      }

      const rawUsernames = await collectSuggestedLiveUsernames(page);
      const uniquePageUsernames = [...new Set(rawUsernames.filter(Boolean))];
      let duplicateDbCount = 0;
      let duplicateRunCount = 0;
      let newRoundCount = 0;

      for (const username of uniquePageUsernames) {
        if (existingUsernames.has(username) || seenInRun.has(username)) {
          if (existingUsernames.has(username)) {
            duplicateDbCount += 1;
          } else {
            duplicateRunCount += 1;
          }
          continue;
        }

        seenInRun.add(username);
        usernames.push(username);
        newRoundCount += 1;
      }

      console.error(
        [
          `Round ${round + 1}/${REFRESH_ROUNDS}`,
          `live_candidates=${uniquePageUsernames.length}`,
          `new=${newRoundCount}`,
          `in_db=${duplicateDbCount}`,
          `seen_this_run=${duplicateRunCount}`,
        ].join(" | ")
      );

      await context.close();

      if (round < REFRESH_ROUNDS - 1) {
        await new Promise((resolve) => setTimeout(resolve, REFRESH_WAIT_MS));
      }
    }

    if (!usernames.length) {
      console.error(
        "No new TikTok LIVE usernames were found after repeated fresh reloads."
      );
      process.exitCode = 1;
      return;
    }

    for (const username of usernames) {
      console.log(username);
    }

    const insertedCount = await insertCreators(usernames);
    console.error(
      `Stored ${insertedCount} new creator${insertedCount === 1 ? "" : "s"} in MySQL.`
    );
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
