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
export TT_BACKSTAGE_EMAIL='jamie@tiktokcreatornetwork.com'
export TT_BACKSTAGE_PASSWORD='admin1372Dextor!#&@'
node scrape.js
```

Or create a local `.env` from `.env.example` and run:

```bash
cd /path/to/socialflame.live/automation/tiktok-backstage
cp .env.example .env
printf "jamiewhittinghamofficial\n" > input.txt
./run.sh
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
