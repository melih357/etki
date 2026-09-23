<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$user = requireAuth();
verifyCsrf();
$data = requestData();
$action = cleanText((string) ($data['action'] ?? ''), 40);
$missionId = (int) ($data['mission_id'] ?? 0);

$stmt = db()->prepare('SELECT m.*, c.slug AS category_slug FROM missions m JOIN categories c ON c.id = m.category_id WHERE m.id = ?');
$stmt->execute([$missionId]);
$mission = $stmt->fetch();
if (!$mission) {
    jsonResponse(['ok' => false, 'message' => 'Görev bulunamadı.'], 404);
}

$pdo = db();
$isExpired = !empty($mission['expires_at']) && strtotime((string) $mission['expires_at']) <= time();

if (in_array($action, ['accept', 'start'], true) && $isExpired) {
    jsonResponse(['ok' => false, 'message' => 'Bu görevin süresi dolduğu için artık kabul edilemez veya başlatılamaz.'], 409);
}

if ($action === 'accept') {
    if ($mission['status'] !== 'open') {
        jsonResponse(['ok' => false, 'message' => 'Bu görev artık kabul edilemiyor.'], 409);
    }
    if ((int) $mission['creator_id'] === (int) $user['id']) {
        jsonResponse(['ok' => false, 'message' => 'Kendi oluşturduğunuz görevi kabul edemezsiniz.'], 422);
    }
    $stmt = $pdo->prepare('INSERT INTO mission_assignments (mission_id, user_id, accepted_at, status) VALUES (?, ?, NOW(), "accepted") ON DUPLICATE KEY UPDATE accepted_at = NOW(), status = "accepted"');
    $stmt->execute([$missionId, (int) $user['id']]);
    $pdo->prepare('UPDATE missions SET status = "accepted" WHERE id = ?')->execute([$missionId]);
    jsonResponse(['ok' => true, 'message' => 'Görevi kabul ettiniz. Güvenlik kurallarına uyarak ilerleyin.']);
}

if ($action === 'start') {
    $stmt = $pdo->prepare('UPDATE mission_assignments SET status = "in_progress" WHERE mission_id = ? AND user_id = ? AND status = "accepted"');
    $stmt->execute([$missionId, (int) $user['id']]);
    if ($stmt->rowCount() < 1) {
        jsonResponse(['ok' => false, 'message' => 'Önce görevi kabul etmelisiniz.'], 409);
    }
    $pdo->prepare('UPDATE missions SET status = "in_progress" WHERE id = ?')->execute([$missionId]);
    jsonResponse(['ok' => true, 'message' => 'Görev başlatıldı.']);
}

if ($action === 'complete') {
    $stmt = $pdo->prepare('SELECT id FROM mission_assignments WHERE mission_id = ? AND user_id = ? AND status IN ("accepted", "in_progress")');
    $stmt->execute([$missionId, (int) $user['id']]);
    if (!$stmt->fetch()) {
        jsonResponse(['ok' => false, 'message' => 'Bu görev size atanmış değil.'], 409);
    }

    $note = cleanText((string) ($data['note'] ?? ''), 1000);
    $filePath = null;
    if (!empty($_FILES['evidence']['name'])) {
        $filePath = saveUploadedImage($_FILES['evidence'], 'evidence');
    }
    if ($mission['required_evidence'] === 'photo' && !$filePath) {
        jsonResponse(['ok' => false, 'message' => 'Bu görev için fotoğraf kanıtı gereklidir.'], 422);
    }

    $pdo->beginTransaction();
    try {
        if ($filePath) {
            $stmt = $pdo->prepare('INSERT INTO mission_evidence (mission_id, user_id, evidence_type, file_path, captured_at, moderation_status, note) VALUES (?, ?, "photo", ?, NOW(), "pending", ?)');
            $stmt->execute([$missionId, (int) $user['id'], $filePath, $note]);
        }
        $stmt = $pdo->prepare('UPDATE mission_assignments SET status = "evidence_submitted", completed_at = NOW() WHERE mission_id = ? AND user_id = ?');
        $stmt->execute([$missionId, (int) $user['id']]);
        $pdo->prepare('UPDATE missions SET status = "verification_pending" WHERE id = ?')->execute([$missionId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    jsonResponse(['ok' => true, 'message' => 'Kanıt gönderildi. Topluluk doğrulaması bekleniyor.']);
}

if ($action === 'verify') {
    if ((int) $mission['creator_id'] === (int) $user['id']) {
        jsonResponse(['ok' => false, 'message' => 'Kendi görevinizi doğrulayamazsınız.'], 422);
    }
    $result = (string) ($data['result'] ?? '');
    if (!in_array($result, ['verified', 'partial', 'rejected', 'unreachable'], true)) {
        jsonResponse(['ok' => false, 'message' => 'Geçersiz doğrulama sonucu.'], 422);
    }
    $explanation = cleanText((string) ($data['explanation'] ?? ''), 500);

    $stmt = $pdo->prepare('INSERT INTO verifications (mission_id, verifier_id, result, explanation) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE result = VALUES(result), explanation = VALUES(explanation), created_at = NOW()');
    $stmt->execute([$missionId, (int) $user['id'], $result, $explanation]);

    if ($result === 'verified') {
        $pdo->prepare('UPDATE missions SET status = "completed", completed_at = NOW() WHERE id = ?')->execute([$missionId]);
        $stmt = $pdo->prepare('SELECT user_id FROM mission_assignments WHERE mission_id = ? AND status = "evidence_submitted" ORDER BY completed_at DESC LIMIT 1');
        $stmt->execute([$missionId]);
        $assigneeId = (int) $stmt->fetchColumn();
        if ($assigneeId > 0) {
            addXp($assigneeId, (int) $mission['xp_reward'], 'Doğrulanmış görev tamamlandı', $missionId);
        }
        addXp((int) $user['id'], 10, 'Görev doğrulaması', $missionId);
    } elseif ($result === 'partial') {
        $pdo->prepare('UPDATE missions SET status = "partially_completed" WHERE id = ?')->execute([$missionId]);
    } elseif ($result === 'rejected') {
        $pdo->prepare('UPDATE missions SET status = "open" WHERE id = ?')->execute([$missionId]);
    }

    jsonResponse(['ok' => true, 'message' => 'Doğrulama kaydedildi.']);
}

if ($action === 'thanks') {
    $targetUserId = (int) ($data['target_user_id'] ?? 0);
    $reason = cleanText((string) ($data['reason'] ?? 'Mahallemize katkı sağladı.'), 120);
    if ($targetUserId < 1 || $targetUserId === (int) $user['id']) {
        jsonResponse(['ok' => false, 'message' => 'Geçersiz teşekkür işlemi.'], 422);
    }
    $stmt = $pdo->prepare('INSERT IGNORE INTO thanks (mission_id, sender_id, receiver_id, reason) VALUES (?, ?, ?, ?)');
    $stmt->execute([$missionId, (int) $user['id'], $targetUserId, $reason]);
    if ($stmt->rowCount() > 0) {
        addXp($targetUserId, 10, 'Kullanıcıdan teşekkür aldı', $missionId);
    }
    jsonResponse(['ok' => true, 'message' => 'Teşekkürün iletildi.']);
}

jsonResponse(['ok' => false, 'message' => 'Geçersiz görev işlemi.'], 404);
