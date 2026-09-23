<?php
require_once dirname(__DIR__) . '/bootstrap.php';

function directPair(int $a, int $b): array
{
    return $a < $b ? [$a, $b] : [$b, $a];
}

function loadDirectContext(int $groupId, int $userId, int $peerId): array
{
    if ($peerId < 1 || $peerId === $userId) {
        jsonResponse(['ok' => false, 'message' => 'Geçerli bir ekip üyesi seçilmedi.'], 422);
    }

    $stmt = db()->prepare('SELECT g.id AS group_id, g.name AS group_name, u.id AS peer_id, u.username AS peer_name,
                                  mine.status AS my_status, peer.status AS peer_status
                           FROM `groups` g
                           JOIN group_members mine ON mine.group_id = g.id AND mine.user_id = ?
                           JOIN group_members peer ON peer.group_id = g.id AND peer.user_id = ?
                           JOIN users u ON u.id = peer.user_id
                           WHERE g.id = ? AND g.status = "active" LIMIT 1');
    $stmt->execute([$userId, $peerId, $groupId]);
    $context = $stmt->fetch();
    if (!$context || $context['my_status'] !== 'active' || $context['peer_status'] !== 'active') {
        jsonResponse(['ok' => false, 'message' => 'Birebir sohbet için iki kullanıcının da aynı ekipte aktif olması gerekir.'], 403);
    }
    return $context;
}

function findConversationId(int $groupId, int $userId, int $peerId): int
{
    [$one, $two] = directPair($userId, $peerId);
    $stmt = db()->prepare('SELECT id FROM direct_conversations WHERE group_id = ? AND user_one_id = ? AND user_two_id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$groupId, $one, $two]);
    return (int) $stmt->fetchColumn();
}

function ensureConversationId(int $groupId, int $userId, int $peerId): int
{
    [$one, $two] = directPair($userId, $peerId);
    $stmt = db()->prepare('INSERT INTO direct_conversations (group_id, user_one_id, user_two_id, created_by, status)
                           VALUES (?, ?, ?, ?, "active")
                           ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), status = "active", updated_at = NOW()');
    $stmt->execute([$groupId, $one, $two, $userId]);
    $id = (int) db()->lastInsertId();
    return $id > 0 ? $id : findConversationId($groupId, $userId, $peerId);
}

function loadDirectMessages(int $conversationId): array
{
    if ($conversationId < 1) {
        return [];
    }
    $stmt = db()->prepare('SELECT x.* FROM (
                            SELECT dm.id, dm.sender_id, dm.body, dm.image_path, dm.message_type, dm.created_at, u.username
                            FROM direct_messages dm
                            JOIN users u ON u.id = dm.sender_id
                            WHERE dm.conversation_id = ? AND dm.status = "visible"
                            ORDER BY dm.id DESC LIMIT 100
                          ) x ORDER BY x.id ASC');
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll();
}

function loadDirectConversationList(int $userId): array
{
    $stmt = db()->prepare('SELECT dc.id AS conversation_id, dc.group_id, g.name AS group_name,
                                  IF(dc.user_one_id = ?, dc.user_two_id, dc.user_one_id) AS peer_id,
                                  peer_user.username AS peer_name,
                                  lm.body AS last_body, lm.image_path AS last_image_path,
                                  lm.message_type AS last_message_type, lm.created_at AS last_message_at,
                                  CASE WHEN g.status = "active" AND mine.status = "active" AND peer_member.status = "active" THEN 1 ELSE 0 END AS can_message
                           FROM direct_conversations dc
                           JOIN `groups` g ON g.id = dc.group_id
                           JOIN users peer_user ON peer_user.id = IF(dc.user_one_id = ?, dc.user_two_id, dc.user_one_id)
                           LEFT JOIN group_members mine ON mine.group_id = dc.group_id AND mine.user_id = ?
                           LEFT JOIN group_members peer_member ON peer_member.group_id = dc.group_id
                                AND peer_member.user_id = IF(dc.user_one_id = ?, dc.user_two_id, dc.user_one_id)
                           LEFT JOIN direct_messages lm ON lm.id = (
                               SELECT dm2.id FROM direct_messages dm2
                               WHERE dm2.conversation_id = dc.id AND dm2.status = "visible"
                               ORDER BY dm2.id DESC LIMIT 1
                           )
                           WHERE dc.status = "active" AND (dc.user_one_id = ? OR dc.user_two_id = ?)
                           ORDER BY COALESCE(lm.created_at, dc.updated_at) DESC, dc.id DESC');
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
    $rows = $stmt->fetchAll();

    return array_map(static function (array $row): array {
        $body = trim((string) ($row['last_body'] ?? ''));
        if ($body !== '') {
            $preview = mb_strlen($body) > 90 ? mb_substr($body, 0, 90) . '…' : $body;
        } elseif (!empty($row['last_image_path'])) {
            $preview = '📷 Fotoğraf';
        } else {
            $preview = 'Henüz görünür mesaj yok.';
        }

        return [
            'conversation_id' => (int) $row['conversation_id'],
            'group_id' => (int) $row['group_id'],
            'group_name' => (string) $row['group_name'],
            'peer_id' => (int) $row['peer_id'],
            'peer_name' => (string) $row['peer_name'],
            'last_preview' => $preview,
            'last_message_at' => $row['last_message_at'],
            'can_message' => (bool) $row['can_message'],
        ];
    }, $rows);
}

$user = requireAuth();
$userId = (int) $user['id'];
$data = requestData();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestedAction = cleanText((string) ($_GET['action'] ?? $data['action'] ?? ''), 40);

if ($method === 'GET' && $requestedAction === 'list') {
    jsonResponse([
        'ok' => true,
        'conversations' => loadDirectConversationList($userId),
    ]);
}

$groupId = (int) ($_GET['group_id'] ?? $data['group_id'] ?? 0);
$peerId = (int) ($_GET['user_id'] ?? $data['user_id'] ?? 0);
if ($groupId < 1) {
    jsonResponse(['ok' => false, 'message' => 'Geçerli bir ekip seçilmedi.'], 422);
}

$context = loadDirectContext($groupId, $userId, $peerId);

if ($method === 'GET') {
    $conversationId = findConversationId($groupId, $userId, $peerId);
    jsonResponse([
        'ok' => true,
        'conversation_id' => $conversationId ?: null,
        'group' => ['id' => (int) $context['group_id'], 'name' => $context['group_name']],
        'peer' => ['id' => (int) $context['peer_id'], 'username' => $context['peer_name']],
        'messages' => loadDirectMessages($conversationId),
    ]);
}

verifyCsrf();
$action = $requestedAction !== '' ? $requestedAction : 'send';

if ($action === 'delete_message') {
    $conversationId = findConversationId($groupId, $userId, $peerId);
    $messageId = (int) ($data['message_id'] ?? 0);
    if ($conversationId < 1 || $messageId < 1) {
        jsonResponse(['ok' => false, 'message' => 'Mesaj bulunamadı.'], 404);
    }
    $stmt = db()->prepare('UPDATE direct_messages SET status = "deleted", deleted_at = NOW()
                           WHERE id = ? AND conversation_id = ? AND sender_id = ? AND status = "visible"');
    $stmt->execute([$messageId, $conversationId, $userId]);
    if ($stmt->rowCount() < 1) {
        jsonResponse(['ok' => false, 'message' => 'Yalnızca kendi mesajınızı silebilirsiniz.'], 403);
    }
    jsonResponse(['ok' => true, 'message' => 'Mesaj silindi.']);
}

$body = cleanText((string) ($data['body'] ?? ''), 1000);
$imagePath = null;
if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $imagePath = saveUploadedImage($_FILES['image'], 'direct');
}
if ($body === '' && $imagePath === null) {
    jsonResponse(['ok' => false, 'message' => 'Mesaj veya fotoğraf eklemelisiniz.'], 422);
}

$conversationId = ensureConversationId($groupId, $userId, $peerId);
$type = $body !== '' && $imagePath !== null ? 'mixed' : ($imagePath !== null ? 'image' : 'text');
$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO direct_messages (conversation_id, sender_id, body, image_path, message_type, status)
                           VALUES (?, ?, ?, ?, ?, "visible")');
    $stmt->execute([$conversationId, $userId, $body !== '' ? $body : null, $imagePath, $type]);
    $messageId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE direct_conversations SET updated_at = NOW() WHERE id = ?')->execute([$conversationId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

jsonResponse([
    'ok' => true,
    'message' => 'Mesaj gönderildi.',
    'conversation_id' => $conversationId,
    'message_id' => $messageId,
], 201);
