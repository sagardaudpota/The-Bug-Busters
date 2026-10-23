<?php
// update_score.php  (rewritten)
// Same POST actions as before: clear, undo, swap, update_details, update_striker,
// update_non_striker, update_bowler, set_state, change_inning, live_event.
// No key/login: anyone who can reach this file can score. Keep the scorer page URL private.

header('Content-Type: text/plain; charset=utf-8');

const MATCHES_DIR = __DIR__ . '/matches';
const ACTIVE_FILE = MATCHES_DIR . '/active_match.json';
const LOCK_FILE = __DIR__ . '/score.lock';
const UNDO_LIMIT = 60;

// ---------------------------------------------------------------- helpers
function respond($code, $msg) {
    http_response_code($code);
    echo $msg;
    exit;
}

function clean($v, $max = 60) {
    $v = trim(strip_tags((string)$v));
    return mb_substr($v, 0, $max);
}

function writeJson($file, $data) {
    // Atomic write: temp file then rename (lock is already held by the caller).
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, json_encode($data)) === false) return false;
    return rename($tmp, $file);
}

function readJson($file, $fallback) {
    if (!file_exists($file)) return $fallback;
    $d = json_decode(file_get_contents($file), true);
    return is_array($d) ? $d : $fallback;
}

function activeMatchId() {
    $active = readJson(ACTIVE_FILE, ['match_id' => 'match_1']);
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($active['match_id'] ?? 'match_1'));
    return $id !== '' ? $id : 'match_1';
}
function matchFile($id) { return MATCHES_DIR . '/' . $id . '.json'; }
function undoFile($id) { return MATCHES_DIR . '/' . $id . '_undo.json'; }
function markScoreSync(&$data) {
    $seq = (int)($data['event_seq'] ?? 0) + 1;
    $data['event_seq'] = $seq;
    $data['last_event'] = 'sync';
    $data['last_effects'] = [];
    $data['event_feed'] = [['seq' => $seq, 'event' => 'sync', 'effects' => []]];
}
function saveOrFail($data, $okMsg, $failMsg) {
    $data['revision'] = (int)($data['revision'] ?? 0) + 1;
    $data['updated_at'] = gmdate('c');
    if (writeJson(matchFile(activeMatchId()), $data)) respond(200, $okMsg);
    respond(500, $failMsg);
}

function oversToBalls($over) {
    $done = floor($over);
    return (int)($done * 6 + round(($over - $done) * 10));
}

function ballsToOvers($balls) {
    return round(intdiv($balls, 6) + ($balls % 6) / 10, 1);
}

function swapStrike(&$data) {
    [$data['striker'], $data['non_striker']] = [$data['non_striker'], $data['striker']];
    [$data['striker_runs'], $data['non_striker_runs']] = [$data['non_striker_runs'], $data['striker_runs']];
    [$data['striker_balls'], $data['non_striker_balls']] = [$data['non_striker_balls'], $data['striker_balls']];
}

// Only the scoring fields are snapshotted for undo.
function snapshot($data) {
    $keys = ['striker', 'non_striker', 'striker_runs', 'striker_balls',
             'non_striker_runs', 'non_striker_balls', 'team_runs',
             'team_wickets', 'over', 'ball_history', 'bowler_wicket_streak',
             'dismissals', 'fall_of_wickets', 'retirements', 'ball_by_ball', 'innings_number'];
    return array_intersect_key($data, array_flip($keys));
}

function pushUndo($data) {
    $file = undoFile(activeMatchId());
    $stack = readJson($file, []);
    $stack[] = snapshot($data);
    if (count($stack) > UNDO_LIMIT) $stack = array_slice($stack, -UNDO_LIMIT);
    writeJson($file, $stack);
}

function clearUndo() {
    writeJson(undoFile(activeMatchId()), []);
}

function parseEvent($raw) {
    if (preg_match('/^wicket:([12])(?:\+(\d))?$/', $raw, $m)) {
        return ['type' => 'wicket', 'target' => $m[1], 'extra' => isset($m[2]) ? (int)$m[2] : 0];
    }
    if (preg_match('/^[0-7]$/', $raw)) {
        return ['type' => 'run', 'runs' => (int)$raw];
    }
    if (preg_match('/^(nb|wd)(?:\+(\d))?$/', $raw, $m)) {
        return ['type' => $m[1], 'extra' => isset($m[2]) ? (int)$m[2] : 0];
    }
    if (preg_match('/^(bye|lbye)(?:\+(\d))?$/', $raw, $m)) {
        return ['type' => $m[1], 'runs' => isset($m[2]) ? (int)$m[2] : 0];
    }
    return null;
}

function handleUpload($field, $current) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return $current;
    $f = $_FILES[$field];
    if ($f['size'] > 2 * 1024 * 1024) return $current;                       // 2 MB max
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) return $current;
    if (@getimagesize($f['tmp_name']) === false) return $current;            // must be a real image

    $dir = __DIR__ . '/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    // Make sure PHP can never run from the uploads folder.
    if (!file_exists($dir . '.htaccess')) {
        file_put_contents($dir . '.htaccess', "php_flag engine off\nRemoveHandler .php .phtml\n");
    }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($f['tmp_name'], $dir . $name)) return 'uploads/' . $name;
    return $current;
}

// ---------------------------------------------------------------- defaults
$defaultData = [
    "striker"           => "Player1",
    "non_striker"       => "Player2",
    "striker_runs"      => 0,
    "striker_balls"     => 0,
    "non_striker_runs"  => 0,
    "non_striker_balls" => 0,
    "team"              => "TeamA",
    "opponent"          => "TeamB",
    "match_info"        => "Match info not set",
    "match_mode"        => "chasing",
    "team_runs"         => 0,
    "team_wickets"      => 0,
    "over"              => 0.0,
    "ball_history"      => [],
    "bowler"            => "Bowler1",
    "logo"              => "images/logo.png",
    "left_flag"         => "images/pakistan_flag.png",
    "right_flag"        => "images/indian_flag.png",
    "arrow_img"         => "images/arrow.png",
    "max_overs"         => 20,
    "target"            => 0,
    "active_design"     => "green",
    "bowler_wicket_streak" => 0,
    "innings_number"   => 1,
    "dismissals"       => [],
    "fall_of_wickets"  => [],
    "retirements"      => [],
    "ball_by_ball"     => []
];

// ---------------------------------------------------------------- request gate
if (!is_dir(MATCHES_DIR)) mkdir(MATCHES_DIR, 0755, true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, "POST only.");

// Lock: held until the script ends, so every read-modify-write is serialized.
$lock = fopen(LOCK_FILE, 'c');
if (!$lock || !flock($lock, LOCK_EX)) respond(500, "Could not lock score file.");

$activeId = activeMatchId();
$data = readJson(matchFile($activeId), $defaultData);
if (!array_key_exists('innings_number', $data)) {
    $data['innings_number'] = (($data['target'] ?? 0) > 0 && ($data['match_mode'] ?? '') === 'chasing') ? 2 : 1;
}
$data = array_merge($defaultData, $data);   // fill any missing keys
foreach (['dismissals', 'fall_of_wickets', 'retirements', 'ball_by_ball', 'event_feed'] as $listKey) {
    if (!is_array($data[$listKey])) $data[$listKey] = [];
}
if ((int)$data['innings_number'] < 1) $data['innings_number'] = 1;

// Match management: a new match starts from clean defaults; selecting an existing
// match only switches the active pointer and does not modify its score.
if (($_POST['list_matches'] ?? '') === '1') {
    $items = [];
    foreach (glob(MATCHES_DIR . '/match_*.json') ?: [] as $file) {
        if (preg_match('/^(match_[0-9]+)\.json$/', basename($file), $m)) {
            $d = readJson($file, []);
            $items[] = ['id' => $m[1], 'team' => $d['team'] ?? '', 'opponent' => $d['opponent'] ?? '', 'updated_at' => $d['updated_at'] ?? ''];
        }
    }
    header('Content-Type: application/json'); echo json_encode(['active' => $activeId, 'matches' => $items]); exit;
}
if (($_POST['create_match'] ?? '') === '1') {
    $ids = [];
    foreach (glob(MATCHES_DIR . '/match_*.json') ?: [] as $file) if (preg_match('/^match_([0-9]+)\.json$/', basename($file), $m)) $ids[] = (int)$m[1];
    $newId = 'match_' . (max($ids ?: [0]) + 1);
    $fresh = $defaultData; $fresh['revision'] = 1; $fresh['updated_at'] = gmdate('c');
    if (!writeJson(matchFile($newId), $fresh) || !writeJson(ACTIVE_FILE, ['match_id' => $newId])) respond(500, 'Could not create match.');
    writeJson(undoFile($newId), []); respond(200, 'Created and selected ' . $newId . '.');
}
if (isset($_POST['select_match'])) {
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_POST['select_match']);
    if (!preg_match('/^match_[0-9]+$/', $id) || !is_file(matchFile($id))) respond(404, 'Match not found.');
    if (!writeJson(ACTIVE_FILE, ['match_id' => $id])) respond(500, 'Could not select match.');
    respond(200, 'Selected ' . $id . '.');
}
// ---------------------------------------------------------------- clear
if (($_POST['clear'] ?? '') === '1') {
    $fresh = $defaultData;
    $fresh['active_design'] = $data['active_design'];   // keep the chosen theme
    markScoreSync($fresh);
    clearUndo();
    saveOrFail($fresh, "Score cleared.", "Failed to clear score.");
}

// ---------------------------------------------------------------- undo
if (($_POST['undo'] ?? '') === '1') {
    $undoPath = undoFile($activeId);
    $stack = readJson($undoPath, []);
    if (empty($stack)) respond(200, "Nothing to undo.");
    $prev = array_pop($stack);
    $data = array_merge($data, $prev);
    markScoreSync($data);
    writeJson($undoPath, $stack);
    saveOrFail($data, "Last ball undone.", "Failed to undo.");
}

// ---------------------------------------------------------------- swap batsmen
if (($_POST['swap'] ?? '') === '1') {
    swapStrike($data);
    saveOrFail($data, "Batsmen swapped.", "Failed to swap batsmen.");
}

// ---------------------------------------------------------------- match details
if (($_POST['update_details'] ?? '') === '1') {
    foreach (['team', 'opponent', 'match_info'] as $k) {
        if (isset($_POST[$k]) && clean($_POST[$k]) !== '') $data[$k] = clean($_POST[$k], 120);
    }
    if (isset($_POST['match_mode']) && clean($_POST['match_mode']) !== '') {
        $data['match_mode'] = preg_replace('/[^a-z_]/', '', strtolower($_POST['match_mode']));
    }
    if (isset($_POST['max_overs']) && is_numeric($_POST['max_overs']) && $_POST['max_overs'] > 0) {
        $data['max_overs'] = min(100, floatval($_POST['max_overs']));
    }
    if (isset($_POST['active_design']) && trim($_POST['active_design']) !== '') {
        $data['active_design'] = preg_replace('/[^a-z_]/', '', strtolower($_POST['active_design']));
    }
    $data['logo']       = handleUpload('logo_file', $data['logo']);
    $data['left_flag']  = handleUpload('left_flag_file', $data['left_flag']);
    $data['right_flag'] = handleUpload('right_flag_file', $data['right_flag']);
    $data['arrow_img']  = handleUpload('arrow_img_file', $data['arrow_img']);
    saveOrFail($data, "Match details updated.", "Failed to update match details.");
}

// ---------------------------------------------------------------- rename players
$renames = [
    // post flag          => [data key, current field, new field, label, strip " (out)"?]
    'update_striker'     => ['striker',     'current_striker',     'new_striker',     'Striker',     true],
    'update_non_striker' => ['non_striker', 'current_non_striker', 'new_non_striker', 'Non-striker', true],
    'update_bowler'      => ['bowler',      'current_bowler',      'new_bowler',      'Bowler',      false],
];
foreach ($renames as $flag => [$field, $curKey, $newKey, $label, $stripOut]) {
    if (($_POST[$flag] ?? '') !== '1') continue;
    $old = clean($_POST[$curKey] ?? '');
    $new = clean($_POST[$newKey] ?? '');
    $stored = $stripOut ? str_replace(" (out)", "", $data[$field]) : $data[$field];
    if ($new === '' || $stored !== $old) respond(409, "$label name did not match.");
    $data[$field] = $new;
    if ($field === 'bowler') $data['bowler_wicket_streak'] = 0;
    saveOrFail($data, "$label name updated.", "Failed to update " . strtolower($label) . " name.");
}

// ---------------------------------------------------------------- set custom state
    if (($_POST['set_state'] ?? '') === '1') {
    foreach (['team_runs', 'team_wickets', 'striker_runs', 'striker_balls',
              'non_striker_runs', 'non_striker_balls', 'target'] as $k) {
        if (isset($_POST[$k]) && trim($_POST[$k]) !== '' && is_numeric($_POST[$k])) {
            $data[$k] = max(0, intval($_POST[$k]));
        }
    }
    if (isset($_POST['team_wickets'])) $data['team_wickets'] = min(10, $data['team_wickets']);
    if (isset($_POST['over']) && is_numeric($_POST['over'])) {
        $data['over'] = ballsToOvers(oversToBalls(max(0, floatval($_POST['over']))));
    }
    if (isset($_POST['ball_history']) && trim($_POST['ball_history']) !== '') {
        $temp = [];
        foreach (explode(',', $_POST['ball_history']) as $item) {
            $item = clean($item, 10);
            if ($item !== '') $temp[] = ["display" => $item, "full" => $item];
        }
        if ($temp) $data['ball_history'] = $temp;
    }
    $data['bowler_wicket_streak'] = 0;
    markScoreSync($data);
    clearUndo();   // snapshots no longer match the state
    saveOrFail($data, "Match state updated.", "Failed to update match state.");
}

// ---------------------------------------------------------------- change inning
if (($_POST['change_inning'] ?? '') === '1') {
    $data['target'] = $data['team_runs'] + 1;
    [$data['team'], $data['opponent']] = [$data['opponent'], $data['team']];
    [$data['left_flag'], $data['right_flag']] = [$data['right_flag'], $data['left_flag']];
    $data['striker'] = "Striker";
    $data['non_striker'] = "Non-Striker";
    $data['striker_runs'] = $data['striker_balls'] = 0;
    $data['non_striker_runs'] = $data['non_striker_balls'] = 0;
    $data['team_runs'] = $data['team_wickets'] = 0;
    $data['over'] = 0.0;
    $data['ball_history'] = [];
    $data['match_mode'] = "chasing";
    $data['innings_number'] = max(1, (int)($data['innings_number'] ?? 1)) + 1;
    $data['bowler_wicket_streak'] = 0;
    markScoreSync($data);
    clearUndo();
    saveOrFail($data, "Inning changed.", "Failed to change inning.");
}

// Retire a batter out without consuming a delivery (for a declared retirement).
if (($_POST['retire_batter'] ?? '') === '1') {
    $slot = ($_POST['batter_slot'] ?? '') === '2' ? '2' : '1';
    if ((int)$data['team_wickets'] >= 10) respond(409, 'The innings is already all out.');
    $field = $slot === '1' ? 'striker' : 'non_striker';
    $runsField = $slot === '1' ? 'striker_runs' : 'non_striker_runs';
    $ballsField = $slot === '1' ? 'striker_balls' : 'non_striker_balls';
    $outName = clean($data[$field], 40);
    $incoming = clean($_POST['new_batter'] ?? '', 40);
    if ($incoming === '' && (int)$data['team_wickets'] < 9) respond(400, 'Enter the incoming batter name.');
    pushUndo($data);
    $ordinal = (int)$data['team_wickets'] + 1;
    $record = [
        'innings_number' => (int)$data['innings_number'], 'wicket_number' => $ordinal,
        'event_type' => 'retired_out', 'player' => $outName,
        'runs' => (int)$data[$runsField], 'balls' => (int)$data[$ballsField],
        'dismissal_type' => 'retired_out', 'bowler' => null, 'fielder' => null,
        'dismissed_by' => 'Retired out (not credited to bowler)',
        'over_at_fall' => $data['over'], 'legal_balls_at_fall' => oversToBalls($data['over']),
        'over_ball' => null, 'score_at_fall' => ['runs' => (int)$data['team_runs'], 'wickets' => $ordinal],
        'timestamp' => gmdate('c')
    ];
    $data['dismissals'][] = $record;
    $data['fall_of_wickets'][] = $record;
    $data['retirements'][] = $record;
    $data['team_wickets'] = $ordinal;
    if ($incoming !== '') { $data[$field] = $incoming; $data[$runsField] = 0; $data[$ballsField] = 0; }
    else $data[$field] = $outName . ' (out)';
    $data['bowler_wicket_streak'] = 0;
    $data['ball_by_ball'][] = [
        'sequence' => count($data['ball_by_ball']) + 1, 'innings_number' => (int)$data['innings_number'],
        'event_type' => 'retired_out', 'legal_delivery' => false, 'over_at_event' => $data['over'],
        'team_score' => (int)$data['team_runs'], 'team_wickets' => $ordinal, 'dismissal' => $record,
        'batters_after' => ['striker' => $data['striker'], 'non_striker' => $data['non_striker']],
        'timestamp' => gmdate('c')
    ];
    markScoreSync($data);
    saveOrFail($data, 'Batter retired out.', 'Failed to retire batter.');
}

// ---------------------------------------------------------------- live event
if (isset($_POST['live_event'])) {
    $raw = trim($_POST['live_event']);
    $ev = parseEvent($raw);
    if ($ev === null) respond(400, "Invalid event.");

    $beforeStrikerRuns = (int)$data['striker_runs'];
    $beforeStrikerName = (string)$data['striker'];
    $dismissalType = $_POST['dismissal_type'] ?? 'bowled';
    if (!in_array($dismissalType, ['bowled', 'caught', 'lbw', 'runout', 'stumped'], true)) $dismissalType = 'bowled';
    $reviewResult = $_POST['review_result'] ?? '';
    if (!in_array($reviewResult, ['out', 'notout'], true)) $reviewResult = '';
    if ($ev['type'] === 'wicket' && $data['team_wickets'] >= 10) respond(409, 'The innings is already all out.');
    if ($ev['type'] === 'wicket' && $ev['target'] === '2' && $dismissalType !== 'runout') respond(400, 'A non-striker can only be run out with these dismissal options.');
    if ($ev['type'] === 'wicket' && $reviewResult !== 'notout' && $dismissalType !== 'runout' && $ev['extra'] > 0) respond(400, 'Completed runs only apply to a run out.');
    $newBatter = clean($_POST['new_batter'] ?? '', 40);
    if ($ev['type'] === 'wicket' && $reviewResult !== 'notout' && $data['team_wickets'] < 9 && $newBatter === '') respond(400, 'Enter the incoming batter name.');
    $fielder = clean($_POST['fielder'] ?? '', 40);
    if ($ev['type'] === 'wicket' && $reviewResult !== 'notout' && in_array($dismissalType, ['caught', 'runout', 'stumped'], true) && $fielder === '') respond(400, 'Enter the fielder name.');
    $deliveryNo = oversToBalls($data['over']) + 1;
    $eventOverBall = (int)(floor(($deliveryNo - 1) / 6) + 1) . '.' . (int)(($deliveryNo - 1) % 6 + 1);
    $effects = [];
    $wasWicket = false;
    $rotateRuns = 0;

    pushUndo($data);   // snapshot BEFORE changing anything

    $legal = false;
    $display = $raw;

    switch ($ev['type']) {
        case 'wicket':
            // A review decision is entered in the wicket popup. An overturned
            // decision counts as a legal ball but does not add a wicket.
            if ($reviewResult === 'notout') {
                $data['team_runs'] += $ev['extra'];
                $data['striker_runs'] += $ev['extra'];
                $data['striker_balls'] += 1;
                $rotateRuns = $ev['extra'];
                $effects[] = ['type' => 'review', 'result' => 'notout'];
                $display = 'not out';
                $legal = true;
                break;
            }

            $wasWicket = true;
            $rotateRuns = $dismissalType === 'runout' ? $ev['extra'] : 0;
            if ($reviewResult === 'out') $effects[] = ['type' => 'review', 'result' => 'out'];
            if ($ev['target'] === '1') {
                $outRuns = (int)$data['striker_runs'];
                $outBalls = (int)$data['striker_balls'];
            } else {
                $outRuns = (int)$data['non_striker_runs'];
                $outBalls = (int)$data['non_striker_balls'];
            }
            $dismissedPlayer = $ev['target'] === '1' ? clean($data['striker'], 40) : clean($data['non_striker'], 40);
            $duck = '';
            if ($outRuns === 0) {
                if ($outBalls > 0) $duck = 'duck';
                elseif ($ev['target'] === '1' || $dismissalType !== 'runout') $duck = 'golden';
            }
            $dismissedBy = [
                'bowled' => 'b ' . clean($data['bowler'], 40),
                'caught' => 'c ' . $fielder . ' b ' . clean($data['bowler'], 40),
                'lbw' => 'lbw b ' . clean($data['bowler'], 40),
                'runout' => $fielder . ' run out',
                'stumped' => 'st ' . $fielder . ' b ' . clean($data['bowler'], 40)
            ][$dismissalType];
            $dismissalRecord = [
                'innings_number' => (int)$data['innings_number'], 'wicket_number' => (int)$data['team_wickets'] + 1,
                'event_type' => 'wicket', 'player' => $dismissedPlayer, 'runs' => $outRuns, 'balls' => $outBalls,
                'dismissal_type' => $dismissalType, 'bowler' => clean($data['bowler'], 40),
                'fielder' => $fielder !== '' ? $fielder : null, 'dismissed_by' => $dismissedBy,
                'over_at_fall' => $data['over'], 'legal_balls_at_fall' => oversToBalls($data['over']) + 1,
                'over_ball' => $eventOverBall, 'score_at_fall' => ['runs' => (int)$data['team_runs'] + $ev['extra'], 'wickets' => (int)$data['team_wickets'] + 1],
                'review_result' => $reviewResult !== '' ? $reviewResult : null, 'completed_runs' => $ev['extra'],
                'timestamp' => gmdate('c')
            ];
            $data['team_wickets'] = min(10, $data['team_wickets'] + 1);
            $data['dismissals'][] = $dismissalRecord;
            $data['fall_of_wickets'][] = $dismissalRecord;
            $data['team_runs'] += $ev['extra'];
            if ($ev['target'] === '1') {
                $data['striker'] .= " (out)";
                $data['striker_runs'] = 0;
                $data['striker_balls'] = 0;
            } else {
                // Non-striker is out: the striker still faced the ball and keeps the runs.
                $data['striker_runs'] += $ev['extra'];
                $data['striker_balls'] += 1;
                $data['non_striker'] .= " (out)";
                $data['non_striker_runs'] = 0;
                $data['non_striker_balls'] = 0;
            }
            $display = "W";
            $legal = true;
            $effects[] = ['type' => 'dismissal', 'kind' => $dismissalType, 'duck' => $duck];
            break;

        case 'run':
            $data['striker_runs'] += $ev['runs'];
            $data['team_runs'] += $ev['runs'];
            $data['striker_balls'] += 1;
            $rotateRuns = $ev['runs'];
            $legal = true;
            break;

        case 'nb':
        case 'wd':
            $data['team_runs'] += 1 + $ev['extra'];
            $rotateRuns = $ev['extra'];
            if ($ev['type'] === 'nb') $data['striker_runs'] += $ev['extra'];
            $display = $ev['type'];
            break;

        case 'bye':
        case 'lbye':
            $data['team_runs'] += $ev['runs'];
            $data['striker_balls'] += 1;
            $rotateRuns = $ev['runs'];
            $display = $ev['type'];
            $legal = true;
            break;
    }

    $milestoneName = str_replace(' (out)', '', (string)$data['striker']);
    $milestoneRuns = (int)$data['striker_runs'];
    $milestoneBalls = (int)$data['striker_balls'];

    if ($rotateRuns % 2 === 1) swapStrike($data);

    if ($legal) {
        $balls = oversToBalls($data['over']) + 1;
        $data['over'] = ballsToOvers($balls);

        if ($wasWicket && !in_array($dismissalType, ['runout'], true)) {
            $data['bowler_wicket_streak'] = (int)($data['bowler_wicket_streak'] ?? 0) + 1;
            if ($data['bowler_wicket_streak'] === 3) {
                $effects[] = ['type' => 'hat_trick', 'bowler' => $data['bowler']];
            }
        } elseif ($wasWicket || !in_array($ev['type'], ['wd', 'nb'], true)) {
            $data['bowler_wicket_streak'] = 0;
        }

        if ($balls % 6 === 0) swapStrike($data);
        if ($wasWicket && $newBatter !== '') {
            if (preg_match('/ \(out\)$/', $data['striker'])) {
                $data['striker'] = $newBatter;
                $data['striker_runs'] = $data['striker_balls'] = 0;
            } elseif (preg_match('/ \(out\)$/', $data['non_striker'])) {
                $data['non_striker'] = $newBatter;
                $data['non_striker_runs'] = $data['non_striker_balls'] = 0;
            }
        }

    }

    // No-ball runs can also take the striker across a batting milestone.
    if ($milestoneName === str_replace(' (out)', '', $beforeStrikerName)) {
        foreach ([50, 100, 150, 200] as $mark) {
            if ($beforeStrikerRuns < $mark && $milestoneRuns >= $mark) {
                $effects[] = ['type' => 'milestone', 'runs' => $mark, 'name' => $milestoneName, 'balls' => $milestoneBalls];
                break;
            }
        }
    }

    $data['ball_history'][] = ["display" => $display, "full" => $raw];
    $data['event_seq'] = (int)($data['event_seq'] ?? 0) + 1;
    $data['last_event'] = $raw;
    $data['last_effects'] = $effects;
    $data['ball_by_ball'][] = [
        'sequence' => count($data['ball_by_ball']) + 1, 'innings_number' => (int)$data['innings_number'],
        'event_type' => $ev['type'], 'event' => $raw, 'legal_delivery' => $legal,
        'over_ball' => $eventOverBall, 'over_after_event' => $data['over'],
        'team_score' => (int)$data['team_runs'], 'team_wickets' => (int)$data['team_wickets'],
        'striker' => ['name' => clean($data['striker'], 40), 'runs' => (int)$data['striker_runs'], 'balls' => (int)$data['striker_balls']],
        'non_striker' => ['name' => clean($data['non_striker'], 40), 'runs' => (int)$data['non_striker_runs'], 'balls' => (int)$data['non_striker_balls']],
        'bowler' => clean($data['bowler'], 40), 'dismissal' => $dismissalRecord ?? null,
        'effects' => $effects, 'timestamp' => gmdate('c')
    ];
    $feed = $data['event_feed'] ?? [];
    $feed[] = ['seq' => $data['event_seq'], 'event' => $raw, 'effects' => $effects];
    $data['event_feed'] = array_slice($feed, -50);
    // Over finished: clear the on-screen "this over" strip (undo still restores it).
    if ($legal && oversToBalls($data['over']) % 6 === 0) {
        $data['ball_history'] = [];
    }

    saveOrFail($data, "Live update processed.", "Failed to update score.");
}

respond(400, "No valid action.");
