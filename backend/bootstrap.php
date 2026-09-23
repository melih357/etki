<?php
declare(strict_types=1);

function loadEnv(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('.env dosyası bulunamadı: ' . $path);
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        $values[$key] = $value;
        $_ENV[$key] = $value;
    }

    return $values;
}

$rootPath = dirname(__DIR__);
$env = loadEnv($rootPath . DIRECTORY_SEPARATOR . '.env');

date_default_timezone_set($env['APP_TIMEZONE'] ?? 'Europe/Istanbul');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name($env['SESSION_NAME'] ?? 'etki_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function envValue(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) envValue('DB_HOST', '127.0.0.1');
    $port = (string) envValue('DB_PORT', '3306');
    $name = (string) envValue('DB_DATABASE', 'etki_db');
    $user = (string) envValue('DB_USERNAME', 'root');
    $pass = (string) envValue('DB_PASSWORD', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '{}', true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (requestData()['csrf_token'] ?? '');
    if (!hash_equals(csrfToken(), (string) $token)) {
        jsonResponse(['ok' => false, 'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.'], 419);
    }
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, email, role, status, xp, level, trust_score, energy, avatar_path, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user) {
        jsonResponse(['ok' => false, 'message' => 'Bu işlem için giriş yapmalısınız.'], 401);
    }
    return $user;
}

function requireAdmin(): array
{
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        jsonResponse(['ok' => false, 'message' => 'Yönetici yetkisi gerekiyor.'], 403);
    }
    return $user;
}

function cleanText(string $text, int $max = 2000): string
{
    $text = trim(strip_tags($text));
    return mb_substr($text, 0, $max);
}

function calculateLevel(int $xp): int
{
    return max(1, (int) floor(sqrt(max(0, $xp) / 100)) + 1);
}

function addXp(int $userId, int $amount, string $reason, ?int $missionId = null): void
{
    if ($amount <= 0) {
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
        $stmt->execute([$amount, $userId]);

        $stmt = $pdo->prepare('SELECT xp FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $xp = (int) $stmt->fetchColumn();
        $level = calculateLevel($xp);

        $stmt = $pdo->prepare('UPDATE users SET level = ? WHERE id = ?');
        $stmt->execute([$level, $userId]);

        $stmt = $pdo->prepare('INSERT INTO xp_transactions (user_id, mission_id, amount, reason, status) VALUES (?, ?, ?, ?, "approved")');
        $stmt->execute([$userId, $missionId, $amount, $reason]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function saveUploadedImage(array $file, string $prefix = 'mission'): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fotoğraf yüklenemedi.');
    }

    $maxBytes = ((int) envValue('UPLOAD_MAX_MB', 8)) * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Dosya boyutu izin verilen sınırı aşıyor.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Yalnızca JPG, PNG veya WEBP görseller kabul edilir.');
    }

    $folder = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
    if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
        throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
    }

    $filename = $prefix . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    $target = $folder . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Dosya kaydedilemedi.');
    }

    return 'uploads/' . date('Y') . '/' . date('m') . '/' . $filename;
}

set_exception_handler(function (Throwable $e): void {
    $debug = filter_var(envValue('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    jsonResponse([
        'ok' => false,
        'message' => $debug ? $e->getMessage() : 'Beklenmeyen bir sunucu hatası oluştu.',
    ], 500);
});
