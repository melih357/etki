<?php
require_once dirname(__DIR__) . '/bootstrap.php';

function groupChatRoleRank(string $role): int
{
    return match ($role) {
        'owner' => 100,
        'administrator' => 80,
        'assistant' => 60,
        default => 10,
    };
}

function loadGroupChatContext(int $groupId, int $userId): array
{
    $stmt = db()->prepare('SELECT g.id, g.name, g.type, g.area, g.description, g.level, g.chat_enabled, g.owner_id,
                          gm.role AS my_role, gm.status AS my_status, gm.muted_until AS my_muted_until
                          FROM `groups` g
                          JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
                          WHERE g.id = ? AND g.status = "active" LIMIT 1');
    $stmt->execute([$userId, $groupId]);
    $context = $stmt->fetch();
    if (!$context || $context['my_status'] !== 'active') {
        jsonResponse(['ok' => false, 'message' => 'Bu grup sohbetine erişiminiz yok.'], 403);
    }
    return $context;
}

function isCurrentlyMuted(?string $until): bool
{
    return $until !== null && strtotime($until) > time();
}

function groupChatPermissions(array $context): array
{
    $role = (string) $context['my_role'];
    $isManager = in_array($role, ['owner', 'administrator', 'assistant'], true);
    $isMuted = isCurrentlyMuted($context['my_muted_until'] ?? null);
    return [
        'can_manage_chat' => in_array($role, ['owner', 'administrator'], true),
        'can_manage_members' => $isManager,
        'can_assign_roles' => in_array($role, ['owner', 'administrator'], true),
        'can_delete_any_message' => $isManager,
        'is_muted' => $isMuted,
        'can_send' => !$isMuted && ((int) $context['chat_enabled'] === 1 || $isManager),
    ];
}

$user = requireAuth();
$userId = (int) $user['id'];
$data = requestData();
$groupId = (int) ($_GET['group_id'] ?? $data['group_id'] ?? 0);
if ($groupId < 1) {
    jsonResponse(['ok' => false, 'message' => 'Geçerli bir ekip seçilmedi.'], 422);
}

$context = loadGroupChatContext($groupId, $userId);
$permissions = groupChatPermissions($context);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = db()->prepare('SELECT gm.user_id, gm.role, gm.status, gm.joined_at, gm.muted_until, u.username,
                          CASE WHEN gm.muted_until IS NOT NULL AND gm.muted_until > NOW() THEN 1 ELSE 0 END AS is_muted
                          FROM group_members gm
                          JOIN users u ON u.id = gm.user_id
                          WHERE gm.group_id = ? AND gm.status = "active"
                          ORDER BY FIELD(gm.role, "owner", "administrator", "assistant", "leader", "moderator", "event_manager", "safety_officer", "verifier", "member"), u.username');
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT x.* FROM (
                            SELECT m.id, m.sender_id, m.body, m.image_path, m.message_type, m.created_at,
                                   u.username, gm.role AS sender_role
                            FROM group_messages m
                            JOIN users u ON u.id = m.sender_id
                            LEFT JOIN group_members gm ON gm.group_id = m.group_id AND gm.user_id = m.sender_id
                            WHERE m.group_id = ? AND m.status = "visible"
                            ORDER BY m.id DESC LIMIT 100
                          ) x ORDER BY x.id ASC');
    $stmt->execute([$groupId]);
    $messages = $stmt->fetchAll();

    jsonResponse([
        'ok' => true,
        'group' => [
            'id' => (int) $context['id'],
            'name' => $context['name'],
            'type' => $context['type'],
            'area' => $context['area'],
            'description' => $context['description'],
            'level' => (int) $context['level'],
            'chat_enabled' => (bool) $context['chat_enabled'],
            'owner_id' => (int) $context['owner_id'],
        ],
        'membership' => [
            'role' => $context['my_role'],
            'muted_until' => $context['my_muted_until'],
        ],
        'permissions' => $permissions,
        'members' => $members,
        'messages' => $messages,
    ]);
}

verifyCsrf();
$action = (string) ($data['action'] ?? 'send');

if ($action === 'delete_message') {
    $messageId = (int) ($data['message_id'] ?? 0);
    $stmt = db()->prepare('SELECT sender_id FROM group_messages WHERE id = ? AND group_id = ? AND status = "visible" LIMIT 1');
    $stmt->execute([$messageId, $groupId]);
    $senderId = (int) $stmt->fetchColumn();
    if ($senderId < 1) {
        jsonResponse(['ok' => false, 'message' => 'Mesaj bulunamadı.'], 404);
    }
    if ($senderId !== $userId && !$permissions['can_delete_any_message']) {
        jsonResponse(['ok' => false, 'message' => 'Bu mesajı silme yetkiniz yok.'], 403);
    }
    $stmt = db()->prepare('UPDATE group_messages SET status = "deleted", deleted_by = ?, deleted_at = NOW() WHERE id = ? AND group_id = ?');
    $stmt->execute([$userId, $messageId, $groupId]);
    jsonResponse(['ok' => true, 'message' => 'Mesaj silindi.']);
}

if (!$permissions['can_send']) {
    if ($permissions['is_muted']) {
        jsonResponse(['ok' => false, 'message' => 'Bu grupta geçici olarak susturuldunuz.'], 403);
    }
    jsonResponse(['ok' => false, 'message' => 'Grup mesajlaşması üyeler için kapalı. Yalnızca yöneticiler mesaj gönderebilir.'], 403);
}

$body = cleanText((string) ($data['body'] ?? ''), 1000);
$imagePath = null;
if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $imagePath = saveUploadedImage($_FILES['image'], 'group');
}
if ($body === '' && $imagePath === null) {
    jsonResponse(['ok' => false, 'message' => 'Mesaj veya fotoğraf eklemelisiniz.'], 422);
}

$type = $body !== '' && $imagePath !== null ? 'mixed' : ($imagePath !== null ? 'image' : 'text');
$stmt = db()->prepare('INSERT INTO group_messages (group_id, sender_id, body, image_path, message_type, status) VALUES (?, ?, ?, ?, ?, "visible")');
$stmt->execute([$groupId, $userId, $body !== '' ? $body : null, $imagePath, $type]);
jsonResponse(['ok' => true, 'message' => 'Mesaj gönderildi.', 'message_id' => (int) db()->lastInsertId()], 201);
