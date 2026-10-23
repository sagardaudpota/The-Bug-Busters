<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function publishRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    publishRespond(405, ['error' => 'Use POST to change AI report visibility.']);
}
$input = json_decode((string)file_get_contents('php://input'), true);
$matchId = is_array($input) ? (string)($input['match_id'] ?? '') : '';
$visible = is_array($input) && ($input['visible'] ?? null) === true;
if (!preg_match('/^match_[0-9]+$/', $matchId)) publishRespond(400, ['error' => 'Invalid match id.']);

$matchesDir = __DIR__ . '/matches';
$active = json_decode((string)@file_get_contents($matchesDir . '/active_match.json'), true);
if (!is_array($active) || ($active['match_id'] ?? '') !== $matchId) {
    publishRespond(409, ['error' => 'Select this match in the admin panel before changing its AI visibility.']);
}
$matchPath = $matchesDir . '/' . $matchId . '.json';
$lock = fopen(__DIR__ . '/score.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX)) publishRespond(500, ['error' => 'Could not lock the selected match.']);
$active = json_decode((string)@file_get_contents($matchesDir . '/active_match.json'), true);
if (!is_array($active) || ($active['match_id'] ?? '') !== $matchId) {
    flock($lock, LOCK_UN); fclose($lock);
    publishRespond(409, ['error' => 'The selected match changed. Refresh the admin panel and try again.']);
}
$match = json_decode((string)@file_get_contents($matchPath), true);
if (!is_array($match)) { flock($lock, LOCK_UN); fclose($lock); publishRespond(404, ['error' => 'Selected match not found.']); }
if ($visible && !is_array($match['ai_analysis'] ?? null)) {
    flock($lock, LOCK_UN); fclose($lock);
    publishRespond(409, ['error' => 'Generate an AI report before showing it on the frontend.']);
}
$match['ai_analysis_visible'] = $visible;
$match['ai_analysis_visibility_updated_at'] = gmdate('c');
$match['revision'] = (int)($match['revision'] ?? 0) + 1;
$match['updated_at'] = gmdate('c');
$tmpPath = $matchPath . '.tmp';
$saved = file_put_contents($tmpPath, json_encode($match, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false
    && rename($tmpPath, $matchPath);
flock($lock, LOCK_UN);
fclose($lock);
if (!$saved) publishRespond(500, ['error' => 'Could not update AI visibility.']);
publishRespond(200, ['match_id' => $matchId, 'visible' => $visible]);
