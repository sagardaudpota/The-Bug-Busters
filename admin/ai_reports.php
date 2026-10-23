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

$matchesDir = __DIR__ . '/matches';
$active = json_decode((string)@file_get_contents($matchesDir . '/active_match.json'), true);
$matchId = is_array($active) ? (string)($active['match_id'] ?? '') : '';
if (!preg_match('/^match_[0-9]+$/', $matchId)) aiRespond(500, ['error' => 'The active match selection is missing or invalid.']);
$matchPath = $matchesDir . '/' . $matchId . '.json';
$match = is_file($matchPath) ? json_decode((string)file_get_contents($matchPath), true) : null;
if (!is_array($match)) aiRespond(404, ['error' => 'The selected match JSON is missing or invalid.']);
$match['match_id'] = $matchId;
$matchBytes = strlen((string)json_encode($match, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
if ($matchBytes > 1_500_000) aiRespond(413, ['error' => 'The selected match record is too large to analyze in one request.']);

require_once __DIR__ . '/deepseek_client.php';
try {
    aiRespond(200, deepseekAnalyzeMatch($match, $reports));
} catch (DeepSeekRequestException $error) {
    aiRespond($error->httpStatus, ['error' => $error->getMessage()]);
} catch (Throwable $error) {
    aiRespond(500, ['error' => 'Could not prepare the DeepSeek analysis request. Check the PHP error log.']);
}
