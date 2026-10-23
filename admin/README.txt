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

NOT INCLUDED - copy these yourself
  your overlay: index.php + index_*.php + style*.css      (from bug_buster_old)
  your pictures: contents of bug_buster_old/images and bug_buster_old/uploads
                 (match_1.json initially copies api_development/score_data.json)
  AI tab: ai_reports.php and match_record.json            (from your friend)

Match files are created in matches/ (match_1.json, match_2.json, ...).
active_match.json contains the match_id used by the admin and frontend.
Use the Match & players tab to create a match or switch to an existing match.
Undo snapshots are stored per match beside each match JSON.
Created automatically on first score tap: score.lock
Do NOT copy: config.php, test_form.html, scorer.html, old admin.php
