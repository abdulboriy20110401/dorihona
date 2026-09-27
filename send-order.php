<?php
/**
 * send-order.php
 * ---------------------------------------------------------------
 * This file receives the order data from the site (script.js) and
 * forwards it to your Telegram bot, WITHOUT ever exposing your bot
 * token to the browser. This file must live on a PHP-capable web
 * server (regular shared hosting works fine) — it will NOT run
 * under VS Code Live Server, which only serves static files.
 * ---------------------------------------------------------------
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // tighten this to your domain once live

// 1) FILL THESE TWO VALUES IN — see the chat explanation for how to get them.
$BOT_TOKEN = '8947242440:AAHwEetJbCzJYrGKYGiB9FENahxX7ET5R0c';
$CHAT_ID   = '1714087221';

// ---------------------------------------------------------------

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_data']);
    exit;
}

function fmt_price($n) {
    return number_format((float)$n, 0, '', ' ') . " so'm";
}

$name    = trim($data['name'] ?? '');
$phone   = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');
$items   = is_array($data['items'] ?? null) ? $data['items'] : [];
$total   = $data['total'] ?? 0;

if ($name === '' || $phone === '' || $address === '' || empty($items)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}

$itemLines = '';
$i = 1;
foreach ($items as $item) {
    $itemName = htmlspecialchars((string)($item['name'] ?? ''));
    $qty      = (int)($item['qty'] ?? 1);
    $price    = (float)($item['price'] ?? 0);
    $itemLines .= "{$i}. {$itemName} x{$qty} — " . fmt_price($price * $qty) . "\n";
    $i++;
}

$safeName    = htmlspecialchars($name);
$safePhone   = htmlspecialchars($phone);
$safeAddress = htmlspecialchars($address);

$text  = "🛒 <b>Yangi buyurtma!</b>\n\n";
$text .= "👤 Mijoz: {$safeName}\n";
$text .= "📞 Telefon: {$safePhone}\n";
$text .= "📍 Manzil: {$safeAddress}\n\n";
$text .= "<b>Mahsulotlar:</b>\n{$itemLines}\n";
$text .= "💰 <b>Jami: " . fmt_price($total) . "</b>";

$telegramUrl = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
$payload = json_encode([
    'chat_id'    => $CHAT_ID,
    'text'       => $text,
    'parse_mode' => 'HTML',
]);

$ch = curl_init($telegramUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'telegram_failed',
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'telegram_response' => $response,
    ]);
}