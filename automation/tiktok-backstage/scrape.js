import fs from "node:fs/promises";
import path from "node:path";
import mysql from "mysql2/promise";
import { chromium } from "playwright";

const LOGIN_URL = "https://live-backstage.tiktok.com/login/";
const SUCCESS_URL = "https://live-backstage.tiktok.com/portal/overview";
const OUTPUT_DIR = path.resolve("output");
const HEADLESS = process.env.HEADLESS !== "false";
const TIMEOUT_MS = Number(process.env.TIMEOUT_MS || 90000);
const DB_PORT = Number(process.env.DB_PORT || 3306);
const DEBUG_INVITE = process.env.DEBUG_INVITE === "true";

const EMAIL = process.env.TT_BACKSTAGE_EMAIL;
const PASSWORD = process.env.TT_BACKSTAGE_PASSWORD;

if (!EMAIL || !PASSWORD) {
  console.error(
    "Missing credentials. Set TT_BACKSTAGE_EMAIL and TT_BACKSTAGE_PASSWORD."
  );
  process.exit(1);
}

const metricLabels = [
  "Diamonds",
  "Go LIVE rate",
  "Active creators",
  "New creators",
  "Valid go LIVE rate",
  "Valid active creators",
];

async function ensureOutputDir() {
  await fs.mkdir(OUTPUT_DIR, { recursive: true });
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
    throw new Error(`Missing database configuration: ${missing.join(", ")}.`);
  }

  return config;
}

function normalizeUsername(value) {
  return value.trim().replace(/^@+/, "");
}

async function resolveUsernames() {
  if (process.env.TT_CREATOR_USERNAME?.trim()) {
    return [
      {
        id: null,
        username: normalizeUsername(process.env.TT_CREATOR_USERNAME),
      },
    ];
  }

  const connection = await mysql.createConnection(getDatabaseConfig());

  try {
    const [rows] = await connection.execute(
      `
        SELECT id, username
        FROM creators
        WHERE backstage_checked = 'no'
          AND username IS NOT NULL
          AND username != ''
        ORDER BY id ASC
        LIMIT 30
      `
    );

    const creators = rows
      .map((row) => ({
        id: Number(row.id),
        username: normalizeUsername(String(row.username || "")),
      }))
      .filter((row) => row.username);

    if (!creators.length) {
      throw new Error("No unchecked creators found in the database.");
    }

    return creators;
  } finally {
    await connection.end();
  }
}

function limitDbString(value, maxLength = 32) {
  if (value === null || value === undefined) {
    return null;
  }

  const normalized = String(value).trim();
  if (!normalized) {
    return null;
  }

  return normalized.slice(0, maxLength);
}

function normalizeInvitationTypeForDb(value) {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  if (typeof value === "number" && Number.isInteger(value)) {
    return value;
  }

  const match = String(value).match(/-?\d+/);
  return match ? Number.parseInt(match[0], 10) : null;
}

function pickInvitationTypeCode(creator) {
  return normalizeInvitationTypeForDb(
    creator.invitationTypeCode ?? creator.invitationType
  );
}

async function updateCreatorsInDatabase(creators) {
  const rowsToUpdate = creators.filter((creator) => creator.id !== null);
  if (!rowsToUpdate.length) {
    return;
  }

  const connection = await mysql.createConnection(getDatabaseConfig());

  try {
    for (const creator of rowsToUpdate) {
      await connection.execute(
        `
          UPDATE creators
          SET display_name = ?,
              backstage_status = ?,
              backstage_checked = 'yes',
              invitation_type = ?
          WHERE id = ?
        `,
        [
          limitDbString(creator.displayName),
          limitDbString(creator.status),
          pickInvitationTypeCode(creator),
          creator.id,
        ]
      );
    }
  } finally {
    await connection.end();
  }
}

function isInviteDebugUrl(url) {
  return (
    url.includes("/invite/") ||
    url.includes("/union_invite/") ||
    url.includes("/invite_anchor/") ||
    url.includes("/check_profile/") ||
    url.includes("/batch_check_anchor/") ||
    url.includes("/invitation_type_rule/") ||
    url.includes("/anchor/") ||
    url.includes("/host/") ||
    url.includes("/creator/")
  );
}

function cleanLines(text) {
  return text
    .split("\n")
    .map((line) => line.replace(/\s+/g, " ").trim())
    .filter(Boolean);
}

function findTimestamp(lines) {
  const updatedIndex = lines.findIndex((line) =>
    /^Last updated at$/i.test(line)
  );

  if (updatedIndex === -1) {
    return null;
  }

  return lines
    .slice(updatedIndex + 1, updatedIndex + 5)
    .find((line) => /\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}:\d{2}/.test(line)) || null;
}

function looksLikeMetricValue(line) {
  return /[0-9]/.test(line);
}

function extractMetric(lines, label) {
  const index = lines.findIndex((line) => line.toLowerCase() === label.toLowerCase());
  if (index === -1) {
    return null;
  }

  const window = lines.slice(index + 1, index + 8);
  const current = window.find(looksLikeMetricValue) || null;

  let yesterday = null;
  let ratioPercent = null;
  const ratioIndex = window.findIndex((line) => /^Ratio vs\. yesterday$/i.test(line));

  if (ratioIndex !== -1) {
    const ratioWindow = window.slice(ratioIndex + 1);
    const numericLines = ratioWindow.filter(looksLikeMetricValue);
    yesterday = numericLines[0] || null;
    ratioPercent = numericLines[1] || null;
  }

  return {
    label,
    current,
    yesterday,
    ratioPercent,
  };
}

function extractMetricsFromText(bodyText) {
  const lines = cleanLines(bodyText);
  const metrics = {};

  for (const label of metricLabels) {
    metrics[label] = extractMetric(lines, label);
  }

  return {
    coreMetricsVisible: lines.some((line) => /^Core metrics$/i.test(line)),
    lastUpdatedAt: findTimestamp(lines),
    metrics,
    rawLines: lines,
  };
}

async function dismissOverlays(page) {
  const buttons = [
    page.getByRole("button", { name: /accept/i }),
    page.getByRole("button", { name: /agree/i }),
    page.getByRole("button", { name: /allow all/i }),
    page.getByRole("button", { name: /decline optional cookies/i }),
    page.getByRole("button", { name: /got it/i }),
  ];

  for (const button of buttons) {
    try {
      if (await button.first().isVisible({ timeout: 1500 })) {
        await button.first().click({ timeout: 1500 });
      }
    } catch {
      // Overlay was not present or became detached.
    }
  }

  await page
    .addStyleTag({
      content: `
        tiktok-cookie-banner,
        #tiktok-cookie-banner-config,
        .semi-sidesheet-mask + tiktok-cookie-banner {
          display: none !important;
          visibility: hidden !important;
          pointer-events: none !important;
        }
      `,
    })
    .catch(() => {});

  await page
    .evaluate(() => {
      for (const selector of ["tiktok-cookie-banner", "#tiktok-cookie-banner-config"]) {
        const nodes = document.querySelectorAll(selector);
        for (const node of nodes) {
          node.remove();
        }
      }
    })
    .catch(() => {});
}

async function fillLoginForm(page) {
  const emailField = page
    .locator(
      [
        'input[type="email"]',
        'input[name*="email" i]',
        'input[placeholder*="email" i]',
        'input[id*="email" i]',
      ].join(", ")
    )
    .first();

  const passwordField = page
    .locator(
      [
        'input[type="password"]',
        'input[name*="password" i]',
        'input[placeholder*="password" i]',
        'input[id*="password" i]',
      ].join(", ")
    )
    .first();

  await emailField.waitFor({ state: "visible", timeout: TIMEOUT_MS });
  await emailField.fill(EMAIL);
  await passwordField.fill(PASSWORD);
}

async function submitLogin(page) {
  const submitCandidates = [
    page.getByRole("button", { name: /log in/i }).first(),
    page.getByRole("button", { name: /login/i }).first(),
    page.getByRole("button", { name: /sign in/i }).first(),
    page.locator('button[type="submit"]').first(),
    page.locator('input[type="submit"]').first(),
  ];

  for (const candidate of submitCandidates) {
    try {
      if (await candidate.isVisible({ timeout: 1500 })) {
        await candidate.click({ timeout: 3000 });
        return;
      }
    } catch {
      // Try the next submit control.
    }
  }

  await page.keyboard.press("Enter");
}

async function waitForDashboard(page) {
  await page.waitForLoadState("domcontentloaded");

  const dashboardReady = page
    .locator("body")
    .filter({ hasText: /Core metrics/i })
    .first();

  await Promise.race([
    page.waitForURL("**/portal/overview**", { timeout: TIMEOUT_MS }),
    dashboardReady.waitFor({ state: "visible", timeout: TIMEOUT_MS }),
  ]);

  await page.waitForLoadState("networkidle", { timeout: 15000 }).catch(() => {});
}

async function saveFailureArtifacts(page, name) {
  await ensureOutputDir();
  await page.screenshot({
    path: path.join(OUTPUT_DIR, `${name}.png`),
    fullPage: true,
  });
  await fs.writeFile(
    path.join(OUTPUT_DIR, `${name}.html`),
    await page.content(),
    "utf8"
  );
}

async function writeDebugLog(entries) {
  if (!DEBUG_INVITE) {
    return;
  }

  await ensureOutputDir();
  await fs.writeFile(
    path.join(OUTPUT_DIR, "network-debug.json"),
    `${JSON.stringify(entries, null, 2)}\n`,
    "utf8"
  );
}

async function saveDebugSnapshot(page, name, modal = null) {
  if (!DEBUG_INVITE) {
    return;
  }

  await ensureOutputDir();
  await page.screenshot({
    path: path.join(OUTPUT_DIR, `${name}.png`),
    fullPage: true,
  });
  await fs.writeFile(
    path.join(OUTPUT_DIR, `${name}.html`),
    await page.content(),
    "utf8"
  );

  if (modal) {
    try {
      await fs.writeFile(
        path.join(OUTPUT_DIR, `${name}-modal.html`),
        await modal.evaluate((node) => node.outerHTML),
        "utf8"
      );
    } catch {
      // Modal may have been detached during rerender.
    }
  }
}

async function waitForInviteButtonReady(page) {
  const button = page.locator('button[data-id="add-host-btn"]').first();
  await button.waitFor({ state: "visible", timeout: TIMEOUT_MS });
  const creatorsCard = button.locator(
    'xpath=ancestor::div[contains(@class,"workbench-card")][1]'
  );

  await page.waitForFunction(() => {
    const element = document.querySelector('button[data-id="add-host-btn"]');
    if (!element) {
      return false;
    }

    const disabled =
      element.hasAttribute("disabled") ||
      element.getAttribute("aria-disabled") === "true";

    return !disabled;
  }, { timeout: TIMEOUT_MS });

  await page.waitForFunction(() => {
    const element = document.querySelector('button[data-id="add-host-btn"]');
    const card = element?.closest('.workbench-card');
    if (!card) {
      return false;
    }

    return !card.querySelector('.semi-skeleton');
  }, { timeout: TIMEOUT_MS });

  await saveDebugSnapshot(page, "invite-button-ready", creatorsCard);

  return button;
}

async function inviteUiAppeared(page) {
  const modalTitle = page
    .locator('.semi-sidesheet-inner-wrap:visible .semi-sidesheet-title h5')
    .filter({ hasText: /Invite creators/i })
    .first();

  if (await modalTitle.count()) {
    return true;
  }

  const accessPopover = page
    .locator(".semi-popover-wrapper:visible")
    .filter({ hasText: /You don.t have access to this feature/i })
    .first();

  return (await accessPopover.count()) > 0;
}

async function clickInviteButton(page) {
  await dismissOverlays(page);
  const readyButton = await waitForInviteButtonReady(page);
  await dismissOverlays(page);
  await readyButton.scrollIntoViewIfNeeded().catch(() => {});

  const clickStrategies = [
    async () => {
      await readyButton.click({ timeout: 5000 });
    },
    async () => {
      await readyButton.evaluate((node) => node.click());
    },
    async () => {
      await readyButton.focus();
      await readyButton.press("Enter");
    },
    async () => {
      const box = await readyButton.boundingBox();
      if (!box) {
        throw new Error("Invite button has no bounding box.");
      }
      await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
    },
  ];

  for (const [index, strategy] of clickStrategies.entries()) {
    try {
      await dismissOverlays(page);
      await strategy();
      await page.waitForTimeout(1500);
      await saveDebugSnapshot(page, `after-invite-click-${index + 1}`);

      if (await inviteUiAppeared(page)) {
        return;
      }
    } catch {
      // Try the next activation method.
    }
  }

  throw new Error("Invite modal did not open after clicking the Invite button.");
}

function getVisibleInviteModals(page) {
  return page.locator(
    '.semi-sidesheet[data-id="invite-host"] .semi-sidesheet-inner-wrap:visible'
  );
}

function getActiveInviteModal(page) {
  return getVisibleInviteModals(page).last();
}

async function waitForInviteModal(page) {
  const modal = getActiveInviteModal(page);
  try {
    await modal.waitFor({ state: "visible", timeout: 10000 });
  } catch {
    const accessDenied = page
      .locator(".semi-popover-wrapper:visible")
      .filter({ hasText: /You don.t have access to this feature/i })
      .first();

    if (await accessDenied.count()) {
      const accessDeniedText = (await accessDenied.innerText()).trim();
      throw new Error(`Invite access denied: ${accessDeniedText}`);
    }

    throw new Error("Invite modal did not open after clicking the Invite button.");
  }

  await modal
    .locator(".semi-sidesheet-title h5")
    .filter({ hasText: /Invite creators/i })
    .first()
    .waitFor({ state: "visible", timeout: TIMEOUT_MS });
  await saveDebugSnapshot(page, "invite-modal-open", modal);
  return modal;
}

async function populateInviteTextarea(page, usernames) {
  const modal = getActiveInviteModal(page);
  const textarea = modal.locator('textarea[data-testid="inviteHostTextArea"]').last();
  const value = usernames.join("\n");

  await textarea.waitFor({ state: "visible", timeout: TIMEOUT_MS });
  await textarea.scrollIntoViewIfNeeded().catch(() => {});
  await textarea.click({ timeout: 5000 });
  await page.keyboard.press("Meta+A").catch(() => {});
  await page.keyboard.press("Control+A").catch(() => {});
  await page.keyboard.press("Backspace").catch(() => {});
  await page.keyboard.type(value, { delay: 35 });
  await textarea.evaluate((node, value) => {
    const nativeSetter = Object.getOwnPropertyDescriptor(
      HTMLTextAreaElement.prototype,
      "value"
    )?.set;

    nativeSetter?.call(node, value);
    node.dispatchEvent(new Event("input", { bubbles: true }));
    node.dispatchEvent(new Event("change", { bubbles: true }));
    node.dispatchEvent(new KeyboardEvent("keyup", { bubbles: true, key: "m" }));
    node.blur();
  }, value);

  await page.waitForTimeout(250);
  return modal;
}

async function toastContains(page, pattern) {
  const toast = page.locator(".semi-toast-wrapper .semi-toast-content-text").last();
  try {
    await toast.waitFor({ state: "visible", timeout: 1500 });
  } catch {
    return false;
  }

  const text = (await toast.innerText()).trim();
  return pattern.test(text);
}

async function waitForInviteSearchSignal(page) {
  const response = await page
    .waitForResponse((candidate) => isInviteDebugUrl(candidate.url()), {
      timeout: 8000,
    })
    .catch(() => null);

  const modal = getActiveInviteModal(page);
  const row = modal.locator("tr:visible").first();
  const stepTwo = modal
    .locator(".semi-steps-item-process .semi-steps-item-title-text")
    .filter({ hasText: /Select creators to invite/i })
    .first();

  if (response) {
    return {
      type: "response",
      response,
    };
  }

  if (await row.count()) {
    return { type: "row" };
  }

  if (await stepTwo.count()) {
    return { type: "step" };
  }

  return null;
}

async function submitCreatorSearch(page, usernames) {
  let lastResponse = null;

  for (let attempt = 0; attempt < 3; attempt += 1) {
    const modal = await populateInviteTextarea(page, usernames);
    const nextButton = modal.locator('button[data-id="invite-host-next"]').last();

    await nextButton.waitFor({ state: "visible", timeout: TIMEOUT_MS });
    await dismissOverlays(page);
    await nextButton.scrollIntoViewIfNeeded().catch(() => {});
    await saveDebugSnapshot(page, `before-next-click-${attempt + 1}`, modal);

    await nextButton.click({ timeout: 5000, force: true });
    const signal = await waitForInviteSearchSignal(page);

    await page.waitForTimeout(1000);
    await saveDebugSnapshot(page, `post-next-${attempt + 1}-1s`);

    if (signal?.type === "response") {
      lastResponse = signal.response;
    }

    if (!(await toastContains(page, /To continue, enter a creator name/i))) {
      await page.waitForTimeout(3000);
      await saveDebugSnapshot(page, `post-next-${attempt + 1}-4s`);
      break;
    }
  }

  if (!lastResponse) {
    return null;
  }

  try {
    return {
      url: lastResponse.url(),
      payload: await lastResponse.json().catch(() => null),
    };
  } catch {
    return null;
  }
}

async function waitForInviteResults(page, expectedCount) {
  const rows = getActiveInviteModal(page).locator("tbody tr:visible");
  await rows.first().waitFor({ state: "visible", timeout: TIMEOUT_MS });

  const start = Date.now();
  while (Date.now() - start < TIMEOUT_MS) {
    if ((await rows.count()) >= expectedCount) {
      return rows;
    }

    await page.waitForTimeout(250);
  }

  throw new Error(
    `Invite results table did not return ${expectedCount} rows within ${TIMEOUT_MS}ms.`
  );
}

function pickCreatorFromApiPayload(payload, username) {
  if (!payload || typeof payload !== "object") {
    return null;
  }

  const normalizedUsername = username.toLowerCase();
  const queue = [payload];

  while (queue.length) {
    const current = queue.shift();

    if (Array.isArray(current)) {
      queue.push(...current);
      continue;
    }

    if (!current || typeof current !== "object") {
      continue;
    }

    const values = Object.values(current);
    queue.push(...values);

    const candidateNames = [
      current.unique_id,
      current.username,
      current.anchor_unique_id,
      current.display_id,
      current.user_name,
    ]
      .filter(Boolean)
      .map((value) => String(value).toLowerCase());

    if (candidateNames.includes(normalizedUsername)) {
      return current;
    }
  }

  return null;
}

function normalizeApiCreatorResult(raw, username) {
  if (!raw) {
    return null;
  }

  const invitationTypeCode =
    raw.invitation_type ??
    raw.can_use_invitation_type ??
    raw.InvitationType ??
    raw.CanUseInvitationType ??
    null;

  return {
    username:
      raw.unique_id ||
      raw.username ||
      raw.anchor_unique_id ||
      raw.display_id ||
      username,
    profilePhotoUrl:
      raw.avatar_url ||
      raw.avatar ||
      raw.avatar_thumb?.url_list?.[0] ||
      raw.user_avatar ||
      null,
    displayName:
      raw.nick_name ||
      raw.nickname ||
      raw.name ||
      raw.display_name ||
      null,
    status:
      raw.status_desc ||
      raw.anchor_status_desc ||
      raw.status ||
      raw.anchor_status ||
      null,
    statusReason:
      raw.reason ||
      raw.status_reason ||
      raw.ineligible_reason ||
      raw.sub_status_desc ||
      null,
    invitationTypeCode,
    invitationType:
      raw.invitation_type_desc ||
      raw.can_use_invitation_type_desc ||
      invitationTypeCode ||
      null,
  };
}

async function extractCreatorResult(row) {
  const usernameNode = row
    .locator('[data-e2e-tag="common_anchorInfo_username"], [data-testid="anchorDetail"]')
    .first();

  const username = (await usernameNode.innerText())
    .replace(/^@/, "")
    .split("\n")[0]
    .trim();

  let profilePhotoUrl = null;
  const image = row.locator("img").first();
  if (await image.count()) {
    profilePhotoUrl = (await image.getAttribute("src")) || null;
  }

  let displayName = null;
  const displayNameNode = row.locator('[data-testid="anchorShortId"] p').first();
  if (await displayNameNode.count()) {
    displayName = (await displayNameNode.innerText()).trim() || null;
  }

  const statusCell = row.locator('td[aria-colindex="2"]').first();
  const invitationTypeCell = row.locator('td[aria-colindex="3"]').first();

  const status = (
    await statusCell.locator(".semi-tag-content").first().innerText()
  ).trim();

  let statusReason = null;
  const extraStatus = statusCell.locator(".liveplatform-status-tag_extra-bottom").first();
  if (await extraStatus.count()) {
    statusReason = (await extraStatus.innerText()).trim() || null;
  }

  const invitationType = (await invitationTypeCell.innerText()).trim() || null;

  return {
    username,
    profilePhotoUrl,
    displayName,
    status,
    statusReason,
    invitationTypeCode: normalizeInvitationTypeForDb(invitationType),
    invitationType,
  };
}

async function extractAllCreatorResults(rows, requestedUsernames) {
  const rowCount = await rows.count();
  const extractedResults = [];

  for (let index = 0; index < rowCount; index += 1) {
    extractedResults.push(await extractCreatorResult(rows.nth(index)));
  }

  const resultsByUsername = new Map(
    extractedResults.map((entry) => [entry.username.toLowerCase(), entry])
  );

  return requestedUsernames.map((username) => {
    return (
      resultsByUsername.get(username.toLowerCase()) || {
        username,
        profilePhotoUrl: null,
        displayName: null,
        status: "Missing",
        statusReason: "No matching row returned by TikTok",
        invitationTypeCode: null,
        invitationType: null,
      }
    );
  });
}

async function main() {
  const creatorsToCheck = await resolveUsernames();
  const creatorUsernames = creatorsToCheck.map((creator) => creator.username);
  const browser = await chromium.launch({
    headless: HEADLESS,
    args: ["--disable-blink-features=AutomationControlled"],
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 1100 },
  });
  const page = await context.newPage();
  const debugEntries = [];

  if (DEBUG_INVITE) {
    page.on("requestfinished", async (request) => {
      if (!["xhr", "fetch"].includes(request.resourceType())) {
        return;
      }

      debugEntries.push({
        type: "requestfinished",
        url: request.url(),
        method: request.method(),
        resourceType: request.resourceType(),
        postData: request.postData() || null,
        capturedAt: new Date().toISOString(),
      });
    });

    page.on("response", async (response) => {
      const request = response.request();
      if (
        !isInviteDebugUrl(response.url()) &&
        !["xhr", "fetch"].includes(request.resourceType())
      ) {
        return;
      }

      let body = null;
      try {
        body = await response.text();
      } catch {
        body = null;
      }

      debugEntries.push({
        type: "response",
        url: response.url(),
        status: response.status(),
        resourceType: request.resourceType(),
        bodySnippet: body ? body.slice(0, 4000) : null,
        capturedAt: new Date().toISOString(),
      });
    });
  }

  try {
    await page.goto(LOGIN_URL, {
      waitUntil: "domcontentloaded",
      timeout: TIMEOUT_MS,
    });

    await dismissOverlays(page);
    await fillLoginForm(page);
    await submitLogin(page);
    await waitForDashboard(page);

    const currentUrl = page.url();
    const bodyText = await page.locator("body").innerText();
    const extracted = extractMetricsFromText(bodyText);

    if (
      !currentUrl.startsWith(SUCCESS_URL) ||
      !extracted.coreMetricsVisible ||
      !extracted.lastUpdatedAt
    ) {
      throw new Error(
        `Login did not reach the expected dashboard state. URL: ${currentUrl}`
      );
    }

    await clickInviteButton(page);
    await waitForInviteModal(page);
    const apiResult = await submitCreatorSearch(page, creatorUsernames);
    const resultRows = await waitForInviteResults(page, creatorUsernames.length);

    let creatorResults = await extractAllCreatorResults(resultRows, creatorUsernames);
    if (apiResult?.payload && creatorUsernames.length === 1 && !creatorResults[0]) {
      const rawApiCreator = pickCreatorFromApiPayload(
        apiResult.payload,
        creatorUsernames[0]
      );
      const normalized = normalizeApiCreatorResult(rawApiCreator, creatorUsernames[0]);
      creatorResults = normalized ? [normalized] : [];
    }

    const creatorResultsWithIds = creatorResults.map((result, index) => ({
      ...result,
      id: creatorsToCheck[index]?.id ?? null,
    }));

    await updateCreatorsInDatabase(creatorResultsWithIds);

    const result = {
      success: true,
      url: currentUrl,
      dashboardConfirmed: true,
      coreMetricsVisible: extracted.coreMetricsVisible,
      lastUpdatedAt: extracted.lastUpdatedAt,
      metrics: extracted.metrics,
      creatorLookup: {
        searchedUsernames: creatorUsernames,
        apiResponseUrl: apiResult?.url || null,
        results: creatorResultsWithIds,
      },
      scrapedAt: new Date().toISOString(),
    };

    await ensureOutputDir();
    await fs.writeFile(
      path.join(OUTPUT_DIR, "latest.json"),
      `${JSON.stringify(result, null, 2)}\n`,
      "utf8"
    );
    await writeDebugLog(debugEntries);

    console.log(JSON.stringify(result, null, 2));
  } catch (error) {
    await saveFailureArtifacts(page, "failure");
    await writeDebugLog(debugEntries);

    const failure = {
      success: false,
      url: page.url(),
      message: error instanceof Error ? error.message : String(error),
      scrapedAt: new Date().toISOString(),
    };

    await ensureOutputDir();
    await fs.writeFile(
      path.join(OUTPUT_DIR, "latest-error.json"),
      `${JSON.stringify(failure, null, 2)}\n`,
      "utf8"
    );

    console.error(JSON.stringify(failure, null, 2));
    process.exitCode = 1;
  } finally {
    await context.close();
    await browser.close();
  }
}

main();
