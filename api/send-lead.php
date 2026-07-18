<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration']);
    exit;
}
require $configFile;

if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID') || TELEGRAM_BOT_TOKEN === '' || TELEGRAM_CHAT_ID === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

function ds_clean($value, $maxLen) {
    if (!is_string($value)) return '';
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLen) : substr($value, 0, $maxLen);
}

$name = ds_clean($data['name'] ?? '', 100);
$phone = ds_clean($data['phone'] ?? '', 40);
$category = ds_clean($data['category'] ?? '', 100);
$comment = ds_clean($data['comment'] ?? '', 800);

if ($name === '' || $phone === '' || $category === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

function ds_escape_html($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$lines = [
    "<b>Нова заявка з сайту DS Motors</b>",
    "",
    "<b>Ім'я:</b> " . ds_escape_html($name),
    "<b>Телефон:</b> " . ds_escape_html($phone),
    "<b>Техніка:</b> " . ds_escape_html($category),
];
if ($comment !== '') {
    $lines[] = "<b>Коментар:</b> " . ds_escape_html($comment);
}
$text = implode("\n", $lines);

$url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
$payload = json_encode([
    'chat_id' => TELEGRAM_CHAT_ID,
    'text' => $text,
    'parse_mode' => 'HTML',
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('DS Motors lead form: Telegram request failed: ' . $curlError);
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
    exit;
}

$tgData = json_decode($response, true);
if (!isset($tgData['ok']) || $tgData['ok'] !== true) {
    error_log('DS Motors lead form: Telegram API error: ' . $response);
    http_response_code(502);
    echo json_encode(['error' => 'Failed to deliver message']);
    exit;
}

echo json_encode(['ok' => true]);
