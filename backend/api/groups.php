<?php
require_once dirname(__DIR__) . '/bootstrap.php';

function groupRoleRank(string $role): int
{
    return match ($role) {
        'owner' => 100,
        'administrator' => 80,
        'assistant' => 60,
        default => 10,
    };
}

function getGroupMember(int $groupId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT gm.*, u.username
                           FROM group_members gm
                           JOIN users u ON u.id = gm.user_id
                           WHERE gm.group_id = ? AND gm.user_id = ? LIMIT 1');
    $stmt->execute([$groupId, $userId]);
    $member = $stmt->fetch();
    return $member ?: null;
}

function requireActiveGroupMember(int $groupId, int $userId): array
{
    $member = getGroupMember($groupId, $userId);
    if (!$member || $member['status'] !== 'active') {
        jsonResponse(['ok' => false, 'message' => 'Bu ekibin aktif üyesi değilsiniz.'], 403);
    }
    return $member;
}

function canManageGroupMember(array $actor, array $target): bool
{
    if ((int) $actor['user_id'] === (int) $target['user_id']) {
        return false;
    }
    return groupRoleRank((string) $actor['role']) > groupRoleRank((string) $target['role']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $viewer = currentUser();
    $viewerId = (int) ($viewer['id'] ?? 0);
    $stmt = db()->prepare('SELECT g.*, u.username AS owner_name,
                          (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id AND gm2.status = "active") AS member_count,
                          gm.role AS my_role, gm.status AS my_status, gm.muted_until AS my_muted_until
                          FROM `groups` g
                          JOIN users u ON u.id = g.owner_id
                          LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
                          WHERE g.status = "active"
                          ORDER BY member_count DESC, g.created_at DESC');
    $stmt->execute([$viewerId]);
    jsonResponse(['ok' => true, 'groups' => $stmt->fetchAll()]);
}

$user = requireAuth();
verifyCsrf();
$data = requestData();
$action = (string) ($data['action'] ?? 'create');
$userId = (int) $user['id'];

if ($action === 'join') {
    $groupId = (int) ($data['group_id'] ?? 0);
    $stmt = db()->prepare('SELECT status FROM `groups` WHERE id = ? LIMIT 1');
    $stmt->execute([$groupId]);
    if ($stmt->fetchColumn() !== 'active') {
        jsonResponse(['ok' => false, 'message' => 'Bu ekip şu anda katılıma açık değil.'], 404);
    }

    $stmt = db()->prepare('INSERT INTO group_members (group_id, user_id, role, status, joined_at, muted_until)
                           VALUES (?, ?, "member", "active", NOW(), NULL)
                           ON DUPLICATE KEY UPDATE status = "active", joined_at = COALESCE(joined_at, NOW()), muted_until = NULL');
    $stmt->execute([$groupId, $userId]);
    jsonResponse(['ok' => true, 'message' => 'Ekibe katıldınız.', 'group_id' => $groupId]);
}

if ($action === 'leave') {
    $groupId = (int) ($data['group_id'] ?? 0);
    $member = requireActiveGroupMember($groupId, $userId);
    if ($member['role'] === 'owner') {
        jsonResponse(['ok' => false, 'message' => 'Kurucu ekipten ayrılamaz. Önce kuruculuğu devretmeniz gerekir.'], 422);
    }
    $stmt = db()->prepare('UPDATE group_members SET status = "removed", muted_until = NULL WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    jsonResponse(['ok' => true, 'message' => 'Ekipten ayrıldınız.']);
}

if (in_array($action, ['toggle_chat', 'set_role', 'mute_member', 'unmute_member', 'remove_member'], true)) {
    $groupId = (int) ($data['group_id'] ?? 0);
    $actor = requireActiveGroupMember($groupId, $userId);

    if ($action === 'toggle_chat') {
        if (!in_array($actor['role'], ['owner', 'administrator'], true)) {
            jsonResponse(['ok' => false, 'message' => 'Mesajlaşmayı yalnızca kurucu veya yönetici açıp kapatabilir.'], 403);
        }
        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $stmt = db()->prepare('UPDATE `groups` SET chat_enabled = ? WHERE id = ?');
        $stmt->execute([$enabled, $groupId]);
        jsonResponse(['ok' => true, 'message' => $enabled ? 'Grup mesajlaşması açıldı.' : 'Grup mesajlaşması yalnızca yöneticilere kapatıldı.']);
    }

    $targetUserId = (int) ($data['user_id'] ?? 0);
    $target = requireActiveGroupMember($groupId, $targetUserId);
    if (!canManageGroupMember($actor, $target)) {
        jsonResponse(['ok' => false, 'message' => 'Bu üyeyi yönetmek için yeterli yetkiniz yok.'], 403);
    }

    if ($action === 'set_role') {
        if (!in_array($actor['role'], ['owner', 'administrator'], true)) {
            jsonResponse(['ok' => false, 'message' => 'Rol atamak için yönetici yetkisi gerekir.'], 403);
        }
        $newRole = (string) ($data['role'] ?? 'member');
        $allowed = $actor['role'] === 'owner'
            ? ['administrator', 'assistant', 'member']
            : ['assistant', 'member'];
        if (!in_array($newRole, $allowed, true)) {
            jsonResponse(['ok' => false, 'message' => 'Bu rolü atama yetkiniz yok.'], 422);
        }
        $stmt = db()->prepare('UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$newRole, $groupId, $targetUserId]);
        jsonResponse(['ok' => true, 'message' => 'Üyenin rolü güncellendi.']);
    }

    if (!in_array($actor['role'], ['owner', 'administrator', 'assistant'], true)) {
        jsonResponse(['ok' => false, 'message' => 'Üyeleri yönetmek için yönetici yardımcısı veya daha yüksek yetki gerekir.'], 403);
    }

    if ($action === 'mute_member') {
        $minutes = (int) ($data['minutes'] ?? 60);
        $allowedMinutes = [10, 60, 480, 1440, 10080];
        if (!in_array($minutes, $allowedMinutes, true)) {
            $minutes = 60;
        }
        $mutedUntil = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $stmt = db()->prepare('UPDATE group_members SET muted_until = ? WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$mutedUntil, $groupId, $targetUserId]);
        jsonResponse(['ok' => true, 'message' => 'Üye geçici olarak susturuldu.']);
    }

    if ($action === 'unmute_member') {
        $stmt = db()->prepare('UPDATE group_members SET muted_until = NULL WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$groupId, $targetUserId]);
        jsonResponse(['ok' => true, 'message' => 'Üyenin susturması kaldırıldı.']);
    }

    if ($action === 'remove_member') {
        $stmt = db()->prepare('UPDATE group_members SET status = "removed", muted_until = NULL WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$groupId, $targetUserId]);
        jsonResponse(['ok' => true, 'message' => 'Üye ekipten çıkarıldı.']);
    }
}

if ((int) $user['level'] < 3 && $user['role'] !== 'admin') {
    jsonResponse(['ok' => false, 'message' => 'Ekip oluşturmak için en az 3. seviyeye ulaşmalısınız.'], 403);
}

$name = cleanText((string) ($data['name'] ?? ''), 80);
$type = cleanText((string) ($data['type'] ?? 'community'), 40);
$area = cleanText((string) ($data['area'] ?? ''), 120);
$description = cleanText((string) ($data['description'] ?? ''), 800);
if (mb_strlen($name) < 3 || mb_strlen($area) < 2) {
    jsonResponse(['ok' => false, 'message' => 'Ekip adı ve çalışma alanı gereklidir.'], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO `groups` (owner_id, name, type, area, description, level, status, chat_enabled) VALUES (?, ?, ?, ?, ?, 1, "active", 1)');
    $stmt->execute([$userId, $name, $type, $area, $description]);
    $groupId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO group_members (group_id, user_id, role, status, joined_at) VALUES (?, ?, "owner", "active", NOW())')->execute([$groupId, $userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

jsonResponse(['ok' => true, 'message' => 'Ekip oluşturuldu. Artık grup sohbetini yönetebilirsiniz.', 'group_id' => $groupId], 201);
