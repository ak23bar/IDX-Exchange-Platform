<?php
/**
 * Stores token in MySQL (`token` table) within the DB configured below.
 * Reads OAuth + token_type from /home/boxgra5/.idx_secrets.php (outside web root).
 */

declare(strict_types=1);


$db_host = 'localhost';
$db_name = '';
$db_user = '';
$db_pass = '';

// ---- Load secrets (OAuth + token_type only)
$secrets_file = '/home/boxgra5/.idx_secrets.php';
if (!is_file($secrets_file)) {
    http_response_code(500);
    exit('Config missing: .idx_secrets.php');
}
$cfg = require $secrets_file;

// ---- Detect CLI
$is_cli = (php_sapi_name() === 'cli');

// ---- Connect to database via PDO
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db_host, $db_name);
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    if ($is_cli) fwrite(STDERR, "DB connection failed: " . $e->getMessage() . PHP_EOL);
    http_response_code(500);
    exit('DB error');
}

// ---- Check if cached token still valid
$now    = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$buffer = $now->modify('+2 minutes');

$stmt = $pdo->prepare('SELECT access_token, expires_at FROM token WHERE token_type = :tt LIMIT 1');
$stmt->execute([':tt' => $cfg['token_type']]);
$row = $stmt->fetch();

if ($row) {
    $expiresAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['expires_at'], new DateTimeZone('UTC'));
    if ($expiresAt && $expiresAt > $buffer) {
        if ($is_cli) echo "[OK] Cached token valid until {$expiresAt->format('c')} UTC" . PHP_EOL;
        else echo 'OK';
        exit;
    }
}

// ---- Request a new token
$fields = http_build_query([
    'grant_type'    => 'client_credentials',
    'client_id'     => $cfg['trestle_client_id'],
    'client_secret' => $cfg['trestle_client_secret'],
], '', '&');

$ch = curl_init($cfg['token_url']);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $fields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);

$response = curl_exec($ch);
if ($response === false) {
    $err = curl_error($ch);
    curl_close($ch);
    if ($is_cli) fwrite(STDERR, "cURL error: $err" . PHP_EOL);
    http_response_code(502);
    exit('Token fetch error');
}
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    if ($is_cli) fwrite(STDERR, "Token endpoint HTTP $code: $response" . PHP_EOL);
    http_response_code(502);
    exit('Token endpoint error');
}

$data = json_decode($response, true);
if (!$data || empty($data['access_token']) || empty($data['expires_in'])) {
    if ($is_cli) fwrite(STDERR, "Unexpected token response: $response" . PHP_EOL);
    http_response_code(502);
    exit('Bad token payload');
}

$accessToken = $data['access_token'];
$expiresIn   = (int)$data['expires_in']; // seconds
$expiresAt   = $now->modify("+{$expiresIn} seconds")->format('Y-m-d H:i:s');

// ---- Upsert into token table
$pdo->beginTransaction();
try {
    $u = $pdo->prepare('UPDATE token SET access_token=:t, expires_at=:e WHERE token_type=:tt');
    $u->execute([':t' => $accessToken, ':e' => $expiresAt, ':tt' => $cfg['token_type']]);

    if ($u->rowCount() === 0) {
        $i = $pdo->prepare('INSERT INTO token (token_type, access_token, expires_at) VALUES (:tt, :t, :e)');
        $i->execute([':tt' => $cfg['token_type'], ':t' => $accessToken, ':e' => $expiresAt]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($is_cli) fwrite(STDERR, "DB write failed: " . $e->getMessage() . PHP_EOL);
    http_response_code(500);
    exit('DB write error');
}

// ---- Done
if ($is_cli) echo "[OK] New token cached; expires at {$expiresAt} UTC" . PHP_EOL;
else echo 'OK';
