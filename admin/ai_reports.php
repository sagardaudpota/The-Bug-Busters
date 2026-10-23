<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function aiRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    aiRespond(405, ['error' => 'Use POST to request match analysis.']);
}
@set_time_limit(150);

$input = json_decode((string)file_get_contents('php://input'), true);
$allowed = ['player_insights', 'turning_points', 'chase_analysis', 'post_match_report'];
$reports = is_array($input) ? ($input['reports'] ?? null) : null;
if (!is_array($reports)) aiRespond(400, ['error' => 'Send a reports array containing one or more report keys.']);
$reports = array_values(array_unique(array_filter($reports, static fn($key): bool => is_string($key) && in_array($key, $allowed, true))));
if (!$reports) aiRespond(400, ['error' => 'Select at least one valid report.']);
$requestedMatchId = is_array($input) ? (string)($input['match_id'] ?? '') : '';

$matchesDir = __DIR__ . '/matches';
$active = json_decode((string)@file_get_contents($matchesDir . '/active_match.json'), true);
$matchId = is_array($active) ? (string)($active['match_id'] ?? '') : '';
if (!preg_match('/^match_[0-9]+$/', $matchId)) aiRespond(500, ['error' => 'The active match selection is missing or invalid.']);
if ($requestedMatchId !== '' && $requestedMatchId !== $matchId) aiRespond(409, ['error' => 'The selected match changed. Refresh the admin panel and try again.']);
$matchPath = $matchesDir . '/' . $matchId . '.json';
$match = is_file($matchPath) ? json_decode((string)file_get_contents($matchPath), true) : null;
if (!is_array($match)) aiRespond(404, ['error' => 'The selected match JSON is missing or invalid.']);
$match['match_id'] = $matchId;
$matchBytes = strlen((string)json_encode($match, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
if ($matchBytes > 1_500_000) aiRespond(413, ['error' => 'The selected match record is too large to analyze in one request.']);

require_once __DIR__ . '/deepseek_client.php';
try {
    $analysis = deepseekAnalyzeMatch($match, $reports);
    $lock = fopen(__DIR__ . '/score.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) aiRespond(500, ['error' => 'Could not lock the selected match to save the analysis.']);
    $latest = json_decode((string)@file_get_contents($matchPath), true);
    if (!is_array($latest)) { flock($lock, LOCK_UN); fclose($lock); aiRespond(500, ['error' => 'The selected match JSON became unavailable.']); }
    $latest['ai_analysis'] = $analysis;
    $latest['ai_analysis_visible'] = false;
    $latest['ai_analysis_generated_at'] = gmdate('c');
    $latest['revision'] = (int)($latest['revision'] ?? 0) + 1;
    $latest['updated_at'] = gmdate('c');
    $tmpPath = $matchPath . '.tmp';
    $saved = file_put_contents($tmpPath, json_encode($latest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false
        && rename($tmpPath, $matchPath);
    flock($lock, LOCK_UN);
    fclose($lock);
    if (!$saved) aiRespond(500, ['error' => 'Could not save the generated report to the selected match.']);
    $analysis['ai_analysis_visible'] = false;
    aiRespond(200, $analysis);
} catch (DeepSeekRequestException $error) {
    aiRespond($error->httpStatus, ['error' => $error->getMessage()]);
} catch (Throwable $error) {
    aiRespond(500, ['error' => 'Could not prepare the DeepSeek analysis request. Check the PHP error log.']);
}
