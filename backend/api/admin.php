<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$admin = requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stats = [];
    $stats['users'] = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['open_missions'] = (int) db()->query('SELECT COUNT(*) FROM missions WHERE status IN ("open", "accepted", "in_progress", "verification_pending")')->fetchColumn();
    $stats['completed_missions'] = (int) db()->query('SELECT COUNT(*) FROM missions WHERE status = "completed"')->fetchColumn();
    $stats['pending_evidence'] = (int) db()->query('SELECT COUNT(*) FROM mission_evidence WHERE moderation_status = "pending"')->fetchColumn();
    $stats['groups'] = (int) db()->query('SELECT COUNT(*) FROM `groups` WHERE status = "active"')->fetchColumn();

    $users = db()->query('SELECT id, username, email, role, status, xp, level, trust_score, created_at FROM users ORDER BY created_at DESC LIMIT 20')->fetchAll();
    $missions = db()->query('SELECT m.id, m.title, m.status, m.risk_level, m.created_at, c.name AS category_name, u.username AS creator_name FROM missions m JOIN categories c ON c.id = m.category_id JOIN users u ON u.id = m.creator_id ORDER BY m.created_at DESC LIMIT 20')->fetchAll();
    jsonResponse(['ok' => true, 'stats' => $stats, 'users' => $users, 'missions' => $missions]);
}

verifyCsrf();
$data = requestData();
$action = (string) ($data['action'] ?? '');
if ($action === 'moderate_mission') {
    $missionId = (int) ($data['mission_id'] ?? 0);
    $status = (string) ($data['status'] ?? '');
    if (!in_array($status, ['open', 'completed', 'rejected', 'safety_closed', 'cancelled'], true)) {
        jsonResponse(['ok' => false, 'message' => 'Geçersiz görev durumu.'], 422);
    }
    $stmt = db()->prepare('UPDATE missions SET status = ? WHERE id = ?');
    $stmt->execute([$status, $missionId]);
    $stmt = db()->prepare('INSERT INTO audit_logs (actor_id, event_type, entity_type, entity_id, metadata) VALUES (?, "mission_moderated", "mission", ?, ?)');
    $stmt->execute([(int) $admin['id'], $missionId, json_encode(['status' => $status], JSON_UNESCAPED_UNICODE)]);
    jsonResponse(['ok' => true, 'message' => 'Görev durumu güncellendi.']);
}

jsonResponse(['ok' => false, 'message' => 'Geçersiz yönetici işlemi.'], 404);
