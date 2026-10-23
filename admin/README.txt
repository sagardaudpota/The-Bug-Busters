SCORE PANEL - SETUP
===================
1. Put this whole folder inside D:\COAL\www\ (WAMP web folder) and start WAMP (green icon).
2. Scorer panel : http://localhost/<folder-name>/scorer_panel.html
   Demo overlay : http://localhost/<folder-name>/overlay.html   (optional, replace with your own)

INCLUDED
  scorer_panel.html  control panel (scoring, match, branding, AI Analytics tabs)
  update_score.php   writes the score (rewritten: locking, safe undo, safe uploads, no key)
  get_score.php      reads the score (your original, unchanged)
  score_data.json    starter data copied from your PAK vs IND output
  overlay.html       demo overlay (optional)
  uploads/ images/   upload folders (uploads is PHP-protected)

NOT INCLUDED - copy these yourself
  your overlay: index.php + index_*.php + style*.css      (from bug_buster_old)
  your pictures: contents of bug_buster_old/images and bug_buster_old/uploads
                 (score_data.json points at uploads/1741681899_... logo and flag files)
  AI tab: ai_reports.php and match_record.json            (from your friend)

Created automatically on first score tap: score.lock, score_undo.json
Do NOT copy: config.php, test_form.html, scorer.html, old admin.php
