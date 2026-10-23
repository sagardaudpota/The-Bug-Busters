SCORE PANEL - SETUP
===================
1. Put this whole folder inside D:\COAL\www\ (WAMP web folder) and start WAMP (green icon).
2. Scorer panel : http://localhost/<folder-name>/scorer_panel.html
   Demo overlay : http://localhost/<folder-name>/overlay.html   (optional, replace with your own)

INCLUDED
  scorer_panel.html  control panel (scoring, match, branding, AI Analytics tabs)
  update_score.php   writes the score (rewritten: locking, safe undo, safe uploads, no key)
  get_score.php      reads the currently selected match
  matches/           one JSON file per match plus active_match.json
  overlay.html       demo overlay (optional)
  uploads/ images/   upload folders (uploads is PHP-protected)

OPTIONAL - copy these yourself
  your overlay: index.php + index_*.php + style*.css      (from bug_buster_old)
  your pictures: contents of bug_buster_old/images and bug_buster_old/uploads
  Existing match JSON files stay in admin/matches/.

AI files
  ai_reports.php             reads the active match and returns DeepSeek reports
  deepseek_client.php        sends the selected match JSON to DeepSeek
  private/deepseek_config.php stores the server-side API key

DEEPSEEK AI SETUP (HTTPS / SHARED HOSTING)
  1. Add a newly rotated key to private/deepseek_config.php (replace the placeholder value).
     The private directory blocks direct web requests, and this key file is git-ignored.
     Never put the key in scorer_panel.html, match JSON, or a browser request.
  2. Ensure PHP cURL is enabled and the hosting account allows outbound HTTPS requests.
  3. Open scorer_panel.html through your HTTPS website URL, select a match, then use AI Analytics.
     The browser calls admin/ai_reports.php on the same HTTPS host; PHP loads active_match.json,
     reads that match's JSON, and sends it to DeepSeek. The key stays on the server.

Match files are created in matches/ (match_1.json, match_2.json, ...).
active_match.json contains the match_id used by the admin and frontend.
Use the Match & players tab to create a match or switch to an existing match.
Undo snapshots are stored per match beside each match JSON.
Created automatically on first score tap: score.lock
Do NOT copy: config.php, test_form.html, scorer.html, old admin.php
