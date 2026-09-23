<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$user = requireAuth();
$missionId = (int) ($_GET['mission_id'] ?? requestData()['mission_id'] ?? 0);

$stmt = db()->prepare('SELECT creator_id FROM missions WHERE id = ?');
$stmt->execute([$missionId]);
$creatorId = (int) $stmt->fetchColumn();
$stmt = db()->prepare('SELECT COUNT(*) FROM mission_assignments WHERE mission_id = ? AND user_id = ?');
$stmt->execute([$missionId, (int) $user['id']]);
$isAssigned = (int) $stmt->fetchColumn() > 0;
if ($creatorId !== (int) $user['id'] && !$isAssigned && $user['role'] !== 'admin') {
    jsonResponse(['ok' => false, 'message' => 'Bu görev sohbetine erişiminiz yok.'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stmt = db()->prepare('SELECT mm.id, mm.body, mm.created_at, mm.sender_id, u.username
                           FROM mission_messages mm JOIN users u ON u.id = mm.sender_id
                           WHERE mm.mission_id = ? AND mm.status = "visible"
                           ORDER BY mm.created_at ASC LIMIT 100');
    $stmt->execute([$missionId]);
    jsonResponse(['ok' => true, 'messages' => $stmt->fetchAll()]);
}

verifyCsrf();
$data = requestData();
$body = cleanText((string) ($data['body'] ?? ''), 500);
if (mb_strlen($body) < 1) {
    jsonResponse(['ok' => false, 'message' => 'Mesaj boş olamaz.'], 422);
}

$blockedTerms = ['telefon numaram', 'whatsapp', 'instagram.com', 't.me/'];
$lower = mb_strtolower($body);
foreach ($blockedTerms as $term) {
    if (str_contains($lower, $term)) {
        jsonResponse(['ok' => false, 'message' => 'Güvenlik nedeniyle iletişim bilgisi veya harici bağlantı paylaşmayın.'], 422);
    }
}

$stmt = db()->prepare('INSERT INTO mission_messages (mission_id, sender_id, body, status) VALUES (?, ?, ?, "visible")');
$stmt->execute([$missionId, (int) $user['id'], $body]);
jsonResponse(['ok' => true, 'message' => 'Mesaj gönderildi.'], 201);
