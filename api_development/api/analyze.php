<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Use POST to request match analysis.']);
}
@set_time_limit(150);
$input = json_decode((string)file_get_contents('php://input'), true);
$allowed = ['player_insights', 'turning_points', 'chase_analysis', 'post_match_report'];
$reports = is_array($input) ? ($input['reports'] ?? null) : null;
if (!is_array($reports)) respond(400, ['error' => 'Send a reports array containing one or more report keys.']);
$reports = array_values(array_unique(array_filter($reports, static fn($key): bool => is_string($key) && in_array($key, $allowed, true))));
if (!$reports) respond(400, ['error' => 'Select at least one valid report.']);

$matchPath = dirname(__DIR__) . '/match_record.json';
$match = is_file($matchPath) ? json_decode((string)file_get_contents($matchPath), true) : null;
if (!is_array($match)) respond(500, ['error' => 'The match_record.json file is missing or invalid.']);

require_once __DIR__ . '/deepseek_client.php';
try {
    respond(200, deepseekAnalyzeMatch($match, $reports));
} catch (DeepSeekRequestException $error) {
    respond($error->httpStatus, ['error' => $error->getMessage()]);
} catch (Throwable $error) {
    respond(500, ['error' => 'Could not prepare the DeepSeek analysis request. Check the PHP error log.']);
}
