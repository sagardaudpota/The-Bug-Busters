<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
$dir = __DIR__ . '/matches';
$active = json_decode((string)@file_get_contents($dir . '/active_match.json'), true);
$id = is_array($active) ? (string)($active['match_id'] ?? '') : '';
if (!preg_match('/^match_[0-9]+$/', $id)) { http_response_code(404); echo json_encode(['error' => 'No active match']); exit; }
$data = json_decode((string)@file_get_contents($dir . '/' . $id . '.json'), true);
if (!is_array($data)) { http_response_code(404); echo json_encode(['error' => 'No active match']); exit; }
echo json_encode([
    'match_id' => $id,
    'ai_analysis' => is_array($data['ai_analysis'] ?? null) ? $data['ai_analysis'] : null,
    'ai_analysis_visible' => ($data['ai_analysis_visible'] ?? false) === true,
    'ai_analysis_generated_at' => $data['ai_analysis_generated_at'] ?? null,
]);
