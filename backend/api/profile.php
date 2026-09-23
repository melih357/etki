<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$user = requireAuth();
$uid = (int) $user['id'];

$stmt = db()->prepare('SELECT COUNT(*) FROM mission_assignments WHERE user_id = ? AND status = "evidence_submitted"');
$stmt->execute([$uid]);
$submitted = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM xp_transactions WHERE user_id = ? AND reason LIKE "%Doğrulanmış görev%"');
$stmt->execute([$uid]);
$completed = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM thanks WHERE receiver_id = ?');
$stmt->execute([$uid]);
$thanks = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT b.name, b.icon, ub.earned_at FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = ? ORDER BY ub.earned_at DESC');
$stmt->execute([$uid]);
$badges = $stmt->fetchAll();

$stmt = db()->prepare('SELECT xt.amount, xt.reason, xt.created_at, m.title AS mission_title FROM xp_transactions xt LEFT JOIN missions m ON m.id = xt.mission_id WHERE xt.user_id = ? ORDER BY xt.created_at DESC LIMIT 20');
$stmt->execute([$uid]);
$history = $stmt->fetchAll();

jsonResponse([
    'ok' => true,
    'user' => $user,
    'stats' => ['submitted' => $submitted, 'completed' => $completed, 'thanks' => $thanks],
    'badges' => $badges,
    'xp_history' => $history,
]);
