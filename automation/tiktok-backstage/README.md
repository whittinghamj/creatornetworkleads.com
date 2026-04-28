# TikTok Backstage Scraper

This script logs into TikTok Live Backstage, waits for the overview dashboard at:

`https://live-backstage.tiktok.com/portal/overview`

It confirms login success by checking for:

- `Core metrics`
- `Last updated at`

It then extracts these dashboard metrics:

- `Diamonds`
- `Go LIVE rate`
- `Active creators`
- `New creators`
- `Valid go LIVE rate`
- `Valid active creators`

After that, it opens the `Invite` flow in the `Creators` panel, searches for up to 30 creator usernames at once, and extracts:

- `username`
- `profilePhotoUrl`
- `displayName`
- `status`
- `statusReason`
- `invitationType`
- `invitationTypeCode`

## Ubuntu 24 setup

```bash
sudo apt update
sudo apt install -y curl ca-certificates
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
cd /path/to/socialflame.live/automation/tiktok-backstage
npm install
npm run install:browsers
```

## Run

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
./run.sh 1
```

`run.sh` now reads login credentials from the database table `backstage_accounts` (active rows only), using DB connection settings from `.env`.

To run `node scrape.js` directly (without `run.sh`), you can still provide `TT_BACKSTAGE_EMAIL` and `TT_BACKSTAGE_PASSWORD` in your shell.

Or create a local `.env` from `.env.example` and run:

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
cp .env.example .env
printf "jamiewhittinghamofficial\n" > input.txt
./run.sh
```

For bulk checking with account rotation (random account each batch):

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
./run-bulk.sh 20
```

`run-bulk.sh` runs one `scrape.js` batch per loop (default `BATCH_SIZE=30`),
and picks a random login account each loop.

Primary account source is MySQL table `backstage_accounts`:

```sql
CREATE TABLE IF NOT EXISTS backstage_accounts (
	id int unsigned NOT NULL AUTO_INCREMENT,
	email varchar(255) NOT NULL,
	password varchar(255) NOT NULL,
	label varchar(100) DEFAULT NULL,
	is_active tinyint(1) NOT NULL DEFAULT 1,
	PRIMARY KEY (id),
	UNIQUE KEY uq_backstage_accounts_email (email)
);

INSERT INTO backstage_accounts (email, password, label, is_active)
VALUES
	('first@example.com', 'first-password', 'Account 1', 1),
	('second@example.com', 'second-password', 'Account 2', 1);
```

The script uses DB credentials from `.env` (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`) and can use a custom table name with `TT_BACKSTAGE_ACCOUNTS_TABLE`.

If DB accounts are unavailable, it falls back to `.env` login entries (legacy):

```bash
TT_BACKSTAGE_EMAIL_1=first@example.com
TT_BACKSTAGE_PASSWORD_1=first-password
TT_BACKSTAGE_EMAIL_2=second@example.com
TT_BACKSTAGE_PASSWORD_2=second-password
```

or a single semicolon-separated variable:

```bash
TT_BACKSTAGE_ACCOUNTS=first@example.com:first-password;second@example.com:second-password
```

Use `input.txt` for batch lookups:

```text
jamiewhittinghamofficial
bricbracbro
cutesyaugustx
```

The file supports up to 30 usernames, one per line. Blank lines and lines starting with `#` are ignored.

Pass a single creator username on the command line to override `input.txt`:

```bash
./run.sh jamiewhittinghamofficial
```

## Headed mode for debugging

```bash
HEADLESS=false node scrape.js
```

Use a different creator username directly:

```bash
TT_CREATOR_USERNAME=jamiewhittinghamofficial node scrape.js
```

## Output

Successful runs print JSON and also save it to:

`output/latest.json`

The invite results are returned as a JSON array under:

`creatorLookup.results`

## Explore usernames into MySQL

The Explore scraper opens `https://www.tiktok.com/explore`, extracts TikTok video URLs, converts them into bare usernames, prints them to the terminal, and inserts new usernames into the `creators` table.

Add database settings to `.env`:

```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=socialflame
DB_USER=root
DB_PASSWORD=secret
```

Then run:

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
./run-explore.sh
```

`run-explore.sh` now does two steps by default:

1. scrapes fresh TikTok LIVE usernames into `creators`
2. immediately runs `run.sh 1` to process unchecked usernames through Backstage so they become dashboard-ready leads

If you only want raw username collection without the follow-up processing step:

```bash
EXPLORE_PROCESS_UNCHECKED=false ./run-explore.sh
```

## Explore daemon mode (continuous loop with safeguards)

Use daemon mode to run explore cycles continuously until told to stop:

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
chmod +x run-explore-daemon.sh run-explore-daemon-stop.sh
./run-explore-daemon.sh
```

Stop it cleanly from another terminal:

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
./run-explore-daemon-stop.sh
```

Daemon state/log files:

- `.daemon/explore-daemon.log`
- `.daemon/explore-daemon.pid`
- `.daemon/explore-daemon.stop`

Safety and anti-overload controls (set in `.env`):

```bash
DAEMON_LOOP_INTERVAL_SECONDS=1800
DAEMON_MIN_SECONDS_BETWEEN_RUNS=1200
DAEMON_MAX_RUNS_PER_DAY=24
DAEMON_JITTER_SECONDS=300
DAEMON_FAILURE_BACKOFF_BASE_SECONDS=1800
DAEMON_FAILURE_BACKOFF_MAX_SECONDS=21600
DAEMON_QUIET_HOURS_START=0
DAEMON_QUIET_HOURS_END=6
```

These controls are designed to reduce request pressure (rate limiting, daily caps, quiet hours, and exponential backoff on failures) and should be used in line with TikTok's terms and policies.

## Ubuntu 24 cron setup (headless server)

Use cron to auto-start the daemon on reboot and keep it alive.

1) Make scripts executable:

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
chmod +x run-explore-daemon.sh run-explore-daemon-stop.sh run-explore-daemon-cron.sh
```

2) Install crontab entries:

```bash
crontab -e
```

Add these lines (replace the absolute path if needed):

```cron
@reboot /usr/bin/flock -n /path/to/socialflame.live/automation/tiktok-backstage/.daemon/explore-daemon.cron.lock /path/to/socialflame.live/automation/tiktok-backstage/run-explore-daemon-cron.sh
*/5 * * * * /usr/bin/flock -n /path/to/socialflame.live/automation/tiktok-backstage/.daemon/explore-daemon.cron.lock /path/to/socialflame.live/automation/tiktok-backstage/run-explore-daemon-cron.sh
```

What this does:

- `@reboot`: starts daemon after server boot
- every 5 minutes: health-check launcher restarts daemon only if not running
- `flock`: prevents duplicate starts from overlapping cron ticks

To stop and keep it stopped:

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
./run-explore-daemon-stop.sh
```

`run-explore-daemon-cron.sh` respects the stop-file (`.daemon/explore-daemon.stop`) and will not restart while it exists.

The script stores:

- `added`: current Unix timestamp
- `username`: scraped TikTok username without the leading `@`

It leaves the other columns at their table defaults and skips rows where `username` already exists.

Failed runs save:

- `output/failure.png`
- `output/failure.html`
- `output/latest-error.json`

## Notes

- If TikTok shows a CAPTCHA, email verification step, or another anti-bot challenge, the script will stop and save failure artifacts so you can inspect what happened.
- The metric parsing is text-based to survive minor dashboard markup changes.
