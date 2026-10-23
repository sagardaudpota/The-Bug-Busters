<?php
header('Content-Type: application/json; charset=utf-8');
$dir = __DIR__ . '/matches';
$active = json_decode(@file_get_contents($dir . '/active_match.json'), true) ?: ['match_id' => 'match_1'];
$id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($active['match_id'] ?? 'match_1'));
if (!preg_match('/^match_[0-9]+$/', $id)) $id = 'match_1';
$file = $dir . '/' . $id . '.json';
$data = json_decode(@file_get_contents($file), true);
if (!is_array($data)) { http_response_code(404); echo json_encode(['error' => 'No active match']); exit; }
if (isset($data['ball_history']) && is_array($data['ball_history'])) {
    $data['ball_history'] = array_map(function ($entry) {
        return is_array($entry) && isset($entry['display']) ? $entry['display'] : $entry;
    }, $data['ball_history']);
}
$data['match_id'] = $id;
echo json_encode($data);
