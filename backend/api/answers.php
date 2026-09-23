<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$missionId = (int) ($_GET['mission_id'] ?? requestData()['mission_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stmt = db()->prepare('SELECT a.id, a.answer, a.confidence, a.created_at, a.user_id, u.username
                           FROM answers a
                           JOIN users u ON u.id = a.user_id
                           JOIN questions q ON q.id = a.question_id
                           WHERE q.mission_id = ?
                           ORDER BY a.created_at DESC');
    $stmt->execute([$missionId]);
    jsonResponse(['ok' => true, 'answers' => $stmt->fetchAll()]);
}

$user = requireAuth();
verifyCsrf();
$data = requestData();
$answer = cleanText((string) ($data['answer'] ?? ''), 800);
$confidence = max(1, min(5, (int) ($data['confidence'] ?? 4)));
if (mb_strlen($answer) < 2) {
    jsonResponse(['ok' => false, 'message' => 'Cevabınızı yazın.'], 422);
}

$stmt = db()->prepare('SELECT q.id, m.creator_id, m.status FROM questions q JOIN missions m ON m.id = q.mission_id WHERE q.mission_id = ?');
$stmt->execute([$missionId]);
$question = $stmt->fetch();
if (!$question || !in_array($question['status'], ['open', 'accepted', 'in_progress'], true)) {
    jsonResponse(['ok' => false, 'message' => 'Bu soru artık cevap kabul etmiyor.'], 409);
}
if ((int) $question['creator_id'] === (int) $user['id']) {
    jsonResponse(['ok' => false, 'message' => 'Kendi sorunuza cevap veremezsiniz.'], 422);
}

$stmt = db()->prepare('INSERT INTO answers (question_id, user_id, answer, confidence) VALUES (?, ?, ?, ?)');
$stmt->execute([(int) $question['id'], (int) $user['id'], $answer, $confidence]);
addXp((int) $user['id'], 10, 'Kişisel soruya cevap', $missionId);

jsonResponse(['ok' => true, 'message' => 'Cevabınız paylaşıldı.'], 201);
