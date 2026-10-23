# ScoreCast

**ScoreCast** is a live cricket broadcast scoring suite built by **The Bug Busters**. It combines a landing page, a viewer-facing scoreboard overlay, a scorer control panel, match data and optional AI-generated match reports. The overlay is designed for use in OBS and other streaming/broadcast software as a browser source.

## Team

| Name | GitHub |
| --- | --- |
| Muhammad Zaid | [@ZAIDDIV](https://github.com/ZAIDDIV) |
| Sagar Habib | [@sagardaudpota](https://github.com/sagardaudpota) |
| Suleman Memon | [@sulemanbuilds](https://github.com/sulemanbuilds) |

## Contents

- [What ScoreCast does](#what-scorecast-does)
- [How the parts work together](#how-the-parts-work-together)
- [Repository guide](#repository-guide)
- [Requirements](#requirements)
- [Run locally with XAMPP](#run-locally-with-xampp)
- [Using the scorer and overlay](#using-the-scorer-and-overlay)
- [Match data and persistence](#match-data-and-persistence)
- [Optional AI match reports](#optional-ai-match-reports)
- [Development AI demo](#development-ai-demo)
- [Customization](#customization)
- [Troubleshooting](#troubleshooting)
- [Security notes](#security-notes)

## What ScoreCast does

### Landing page

The root [`index.html`](index.html) is the project home page. It introduces the broadcast suite and links to the scorer and viewer screens. [`assets/landing.css`](assets/landing.css) contains its visual design; [`assets/landing.js`](assets/landing.js) controls the loading sequence, animations, and interaction. The page loads GSAP from a CDN for animation effects.

### Viewer screen / broadcast overlay

[`frontend/index_blue.html`](frontend/index_blue.html) is the blue live scoreboard. Add it to OBS as a Browser Source (served from the PHP web server). The overlay presents the batting score, wickets, innings/phase, overs, batter and bowler details, recent balls, flags, ticker messages, and match venue. It can animate scoring events such as boundaries, wickets, milestones, and other match events. Published AI insights can also appear on the overlay.

The alternate [`frontend/index_blue_need.html`](frontend/index_blue_need.html) is a chase/required-runs presentation variant. Both use [`frontend/assets/style.css`](frontend/assets/style.css) and [`frontend/assets/script.js`](frontend/assets/script.js). Image assets (logo, flags, default player image) are in [`frontend/assets/images/`](frontend/assets/images/).

The overlay script exposes a browser-side `Score` helper API for demos and manual triggering, and supports `ticker` and `opp` URL parameters. Its live score state is normally obtained from the PHP endpoints and the active match record; a PHP server is required for that integration.

### Scorer control panel

[`admin/scorer_panel.html`](admin/scorer_panel.html) is the scorer interface. Its tabs and controls support ball-by-ball scoring, match and player setup, editing striker/non-striker/bowler and match details, undo and match state/innings controls, visual branding/theme controls, and AI Analytics. Scoring updates are sent to [`admin/update_score.php`](admin/update_score.php), and the active scoreboard data is read from [`admin/get_score.php`](admin/get_score.php).

The panel has no login or authorization layer currently. Anyone who can access the scorer page and its update endpoint can change the score. Keep it on a trusted network or add authentication and access restrictions before exposing it publicly.

### Optional AI reports

The admin AI Analytics tools can request player insights, turning points, chase analysis, and a post-match report from DeepSeek. Reports are stored in the selected match record and are hidden from viewers until explicitly published in the admin panel. The overlay checks the published state and displays the report when enabled.

## How the parts work together

1. A scorer opens the admin panel and selects or creates a match.
2. The panel submits score and match changes to PHP endpoints under `admin/`.
3. PHP validates and writes the active match JSON file. Updates use a file lock and atomic replacement to reduce partial writes and collisions.
4. The overlay polls the score endpoint and updates its display/animations from the latest match state.
5. If configured, AI reports are generated server-side. The admin chooses whether to publish them to the viewer overlay.

All parts should be served from the same PHP site origin for the provided relative endpoint paths to work.

## Repository guide

```text
.
├── index.html                         Project landing page
├── assets/
│   ├── landing.css                    Landing page styles
│   └── landing.js                     Landing page loader and interactions
├── frontend/
│   ├── index_blue.html                Blue live scoreboard overlay
│   ├── index_blue_need.html           Chase/required-runs overlay variant
│   └── assets/
│       ├── style.css                  Overlay styles and animations
│       ├── script.js                  Overlay rendering, polling, and events
│       └── images/                    Logo, team flags, default player image
├── admin/
│   ├── scorer_panel.html              Scorer control panel
│   ├── overlay.html                   Standalone/demo overlay page
│   ├── update_score.php               Score and match update actions
│   ├── get_score.php                  Reads the active match for the overlay
│   ├── score_data.json                Legacy/demo score data
│   ├── score_undo.json                Legacy/demo undo data
│   ├── score.lock                     Runtime file lock (created/used by PHP)
│   ├── matches/
│   │   ├── active_match.json          Selects the active match
│   │   ├── match_*.json               Individual match records
│   │   └── match_*_undo.json          Per-match undo snapshots
│   ├── ai_reports.php                 Generates selected AI reports
│   ├── ai_publish.php                 Publishes/hides a saved AI report
│   ├── get_ai_status.php              Returns saved report and visibility status
│   ├── deepseek_client.php            Server-side DeepSeek request helper
│   └── private/
│       ├── deepseek_config.example.php Example configuration template
│       └── deepseek_config.php        Local secret configuration (create yourself)
└── api_development/
    ├── website/index.html             Standalone AI report demo page
    ├── api/analyze.php                Demo API endpoint using match_record.json
    ├── api/deepseek_client.php        AI request helper for the demo API
    ├── match_record.json              Sample match input for the demo
    ├── players_data.json              Sample player data
    ├── score_data.json                Sample score data
    └── start_server.bat               Starts PHP's local development server
```

Other tracked files include [`admin/README.txt`](admin/README.txt), which has older deployment notes, and [`admin/score-panel.zip`](admin/score-panel.zip), a packaged copy of the score panel. The source files in `admin/` are the editable working version.

### Endpoint reference

| Endpoint | Purpose |
| --- | --- |
| `admin/get_score.php` | Returns the selected active match as JSON for the overlay. Hides AI report content unless it is published. |
| `admin/update_score.php` | Handles score taps and match/player/state update actions from the scorer panel. |
| `admin/ai_reports.php` | Accepts a POST request for one or more supported AI report types, analyzes the currently selected match, and saves the result. |
| `admin/ai_publish.php` | Accepts a POST request to show or hide the saved report for the active match. |
| `admin/get_ai_status.php` | Returns the saved report, generated time, and visibility state for the admin panel. |
| `api_development/api/analyze.php` | Standalone development/demo endpoint that analyzes `api_development/match_record.json`. |

## Requirements

- Windows with [XAMPP](https://www.apachefriends.org/) or another web server capable of running PHP.
- PHP with JSON support. Enable the PHP cURL extension and outbound HTTPS for DeepSeek AI reports.
- A modern browser. Internet access is needed for externally hosted fonts, GSAP, and (in the development AI demo) Bootstrap CDN assets.
- Writable access for PHP to the relevant JSON files and directories under `admin/matches/`, plus the lock file location.

The project does not have a Node/npm build step or Composer dependencies in the current repository.

## Run locally with XAMPP

1. Copy or clone this repository into your XAMPP web root, commonly `C:\xampp\htdocs\the-bug-busters` (adjust for your installation).
2. Start Apache from the XAMPP Control Panel.
3. Open the landing page at `http://localhost/the-bug-busters/`.
4. Open the scorer at `http://localhost/the-bug-busters/admin/scorer_panel.html`.
5. Open the broadcast overlay at `http://localhost/the-bug-busters/frontend/index_blue.html`.
6. In OBS, add a Browser Source using that overlay URL. Set the source dimensions to the intended broadcast layout; the overlay scales its 1920×1080 stage to fit the browser viewport.

Use the app through `http://localhost/...`, not by opening the HTML files directly with `file://`. Direct-file mode cannot call the PHP endpoints. For a hosted deployment, use HTTPS, preserve the directory structure, and ensure the PHP account can write match state files.

## Using the scorer and overlay

- Select or create a match in the match/player controls. The selected match is recorded in `admin/matches/active_match.json`.
- Enter teams, venue, players, and bowler details, then score each delivery using the scoring controls.
- Use the panel's undo and innings/state controls to correct or manage the match.
- Keep the overlay URL open in OBS or a browser. It refreshes from the active match data and renders the current score and event effects.
- The standalone [`admin/overlay.html`](admin/overlay.html) is a demo/legacy overlay page; the landing page's viewer link points to `frontend/index_blue.html`.

## Match data and persistence

Each match is represented by a JSON file in `admin/matches/`, generally named `match_1.json`, `match_2.json`, and so on. `active_match.json` identifies which match the admin and overlay currently use. Undo history is stored in a companion `match_*_undo.json` file. The application also maintains revision/event metadata in match records so the viewer can synchronize score changes and effects.

`admin/score_data.json` and `admin/score_undo.json` are older data files retained in the repository; the current multi-match flow uses the `admin/matches/` directory. `api_development/score_data.json`, `players_data.json`, and `match_record.json` are sample inputs for the separate development demo.

Back up the `admin/matches/` directory before replacing or cleaning match data. Do not edit a live match JSON file while a scorer is actively updating it.

## Optional AI match reports

1. Copy `admin/private/deepseek_config.example.php` to `admin/private/deepseek_config.php`.
2. Put your DeepSeek API key in the local config file. The real config filename is listed in `.gitignore`; do not commit it or put the key in HTML, JavaScript, or match JSON.
3. Confirm PHP cURL is enabled, outbound HTTPS is allowed, and the site is served over HTTPS in production.
4. Open the scorer panel, select the active match, and use the AI Analytics tab to request one or more report types.
5. Review the generated report. Use the panel's publish/show control to make it visible on the overlay; hide it again when needed.

The analysis endpoint sends match data from the server to DeepSeek. Check your provider's current data handling terms before using real player or match information. Treat any API key previously committed, shared, or embedded in a file as compromised and rotate it. Keep the example file to a placeholder value only.

## Development AI demo

`api_development/` contains an independent sample page and API flow. It uses `match_record.json` instead of the live admin match selection.

- Start it using `api_development/start_server.bat` on Windows with XAMPP installed at the path configured in the batch file. If your PHP executable is elsewhere, edit the `PHP_EXE` setting.
- The script starts PHP's built-in server at `http://127.0.0.1:8000` and opens `http://127.0.0.1:8000/website/`.
- Configure the API key for the demo in the appropriate server-side development configuration used by [`api_development/api/deepseek_client.php`](api_development/api/deepseek_client.php).
- Choose reports on the demo page and submit them to `api/analyze.php`.

This is a test/demo path separate from the live scorer-integrated AI reports in `admin/`.

## Customization

- Landing content and navigation: [`index.html`](index.html).
- Landing visual style and motion: [`assets/landing.css`](assets/landing.css), [`assets/landing.js`](assets/landing.js).
- Overlay layout and theme: [`frontend/index_blue.html`](frontend/index_blue.html), [`frontend/assets/style.css`](frontend/assets/style.css).
- Overlay scoring state, display rendering, polling, and event behavior: [`frontend/assets/script.js`](frontend/assets/script.js).
- Team branding and default imagery: replace the corresponding assets in [`frontend/assets/images/`](frontend/assets/images/).
- Scorer layout and controls: [`admin/scorer_panel.html`](admin/scorer_panel.html).
- Server-side score and report behavior: PHP files in [`admin/`](admin/).

The landing page currently links directly to the blue user screen and scorer page. Update those anchors in `index.html` if the paths change.

## Troubleshooting

- **Page opens but score does not update:** confirm Apache/PHP is running, use the site through localhost HTTP, and verify `admin/matches/active_match.json` points to an existing match JSON file.
- **PHP cannot save a score:** check that Apache's PHP process has write access to `admin/matches/` and can create/use `admin/score.lock`.
- **AI report request fails:** verify the server-side config exists, its key is valid, PHP cURL is enabled, outbound HTTPS is available, and consult the PHP error log for server-side details.
- **Overlay does not fit the OBS source:** set the Browser Source dimensions to the desired output and refresh the source.
- **Buttons or fonts look unstyled:** confirm external CDN access for GSAP/fonts and inspect the browser console for missing files or script errors.

## Security notes

The current scorer endpoints do not require a user account or login, so they should not be exposed to an untrusted public audience without adding authentication and suitable server access controls. Keep API credentials server-side, use HTTPS in production, and ensure match JSON files are not writable by unrelated system users. The bundled `admin/private/deepseek_config.example.php` should contain only a dummy placeholder key; rotate any actual key that has ever been placed in a tracked file.
