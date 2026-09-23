<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$action = $_GET['action'] ?? 'me';

if ($action === 'me') {
    jsonResponse(['ok' => true, 'user' => currentUser(), 'csrf_token' => csrfToken()]);
}

$data = requestData();

if ($action === 'register') {
    $username = cleanText((string) ($data['username'] ?? ''), 40);
    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    $birthYear = (int) ($data['birth_year'] ?? 0);

    if (mb_strlen($username) < 3) {
        jsonResponse(['ok' => false, 'message' => 'Kullanıcı adı en az 3 karakter olmalıdır.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'message' => 'Geçerli bir e-posta adresi girin.'], 422);
    }
    if (strlen($password) < 8) {
        jsonResponse(['ok' => false, 'message' => 'Şifre en az 8 karakter olmalıdır.'], 422);
    }
    if ($birthYear < 1900 || ((int) date('Y') - $birthYear) < 18) {
        jsonResponse(['ok' => false, 'message' => 'Etki prototipi yalnızca 18 yaş ve üzeri kullanıcılar içindir.'], 422);
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        jsonResponse(['ok' => false, 'message' => 'Bu e-posta veya kullanıcı adı zaten kullanılıyor.'], 409);
    }

    $stmt = db()->prepare('INSERT INTO users (username, email, password_hash, birth_year, role, status, xp, level, trust_score, energy) VALUES (?, ?, ?, ?, "user", "active", 0, 1, 50, 5)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $birthYear]);
    $_SESSION['user_id'] = (int) db()->lastInsertId();
    session_regenerate_id(true);

    jsonResponse(['ok' => true, 'message' => 'Hesabınız oluşturuldu.', 'user' => currentUser(), 'csrf_token' => csrfToken()], 201);
}

if ($action === 'login') {
    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        jsonResponse(['ok' => false, 'message' => 'E-posta veya şifre hatalı.'], 401);
    }
    if ($row['status'] !== 'active') {
        jsonResponse(['ok' => false, 'message' => 'Bu hesap şu anda kullanılamıyor.'], 403);
    }

    $_SESSION['user_id'] = (int) $row['id'];
    session_regenerate_id(true);
    jsonResponse(['ok' => true, 'message' => 'Giriş başarılı.', 'user' => currentUser(), 'csrf_token' => csrfToken()]);
}

if ($action === 'logout') {
    verifyCsrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    jsonResponse(['ok' => true, 'message' => 'Çıkış yapıldı.']);
}

jsonResponse(['ok' => false, 'message' => 'Geçersiz işlem.'], 404);
