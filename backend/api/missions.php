<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $category = cleanText((string) ($_GET['category'] ?? ''), 40);
    $status = cleanText((string) ($_GET['status'] ?? ''), 40);

    $where = ['m.status NOT IN ("draft", "cancelled", "safety_closed")'];
    $params = [];
    if ($id > 0) {
        $where[] = 'm.id = ?';
        $params[] = $id;
    }
    if ($category !== '') {
        $where[] = 'c.slug = ?';
        $params[] = $category;
    }
    if ($status !== '') {
        $where[] = 'm.status = ?';
        $params[] = $status;
    }

    $sql = 'SELECT m.*, c.slug AS category_slug, c.name AS category_name, c.icon AS category_icon,
                   u.username AS creator_name,
                   CASE
                     WHEN m.latitude IS NOT NULL
                      AND m.longitude IS NOT NULL
                      AND (m.expires_at IS NULL OR m.expires_at > NOW())
                      AND m.status IN ("open", "accepted", "in_progress", "authority_routed", "authority_waiting", "solution_check")
                     THEN 1 ELSE 0
                   END AS is_map_active,
                   CASE WHEN m.expires_at IS NOT NULL AND m.expires_at <= NOW() THEN 1 ELSE 0 END AS is_expired,
                   (SELECT COUNT(*) FROM mission_assignments ma WHERE ma.mission_id = m.id AND ma.status IN ("accepted", "in_progress", "evidence_submitted")) AS active_assignments,
                   (SELECT COUNT(*) FROM answers a JOIN questions q ON q.id = a.question_id WHERE q.mission_id = m.id) AS answer_count,
                   (SELECT COUNT(*) FROM verifications v WHERE v.mission_id = m.id) AS verification_count
            FROM missions m
            JOIN categories c ON c.id = m.category_id
            JOIN users u ON u.id = m.creator_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY FIELD(m.status, "open", "accepted", "in_progress", "verification_pending", "solution_check", "completed", "partially_completed"), m.created_at DESC
            LIMIT 100';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $missions = $stmt->fetchAll();

    if ($id > 0) {
        if (!$missions) {
            jsonResponse(['ok' => false, 'message' => 'Görev bulunamadı.'], 404);
        }
        jsonResponse(['ok' => true, 'mission' => $missions[0]]);
    }

    jsonResponse(['ok' => true, 'missions' => $missions]);
}

if ($method === 'POST') {
    $user = requireAuth();
    verifyCsrf();
    $data = requestData();

    $categoryId = (int) ($data['category_id'] ?? 0);
    $title = cleanText((string) ($data['title'] ?? ''), 120);
    $description = cleanText((string) ($data['description'] ?? ''), 1500);
    $locationName = cleanText((string) ($data['location_name'] ?? ''), 150);
    $latitude = isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null;
    $longitude = isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null;
    $expiresTimestamp = !empty($data['expires_at']) ? strtotime((string) $data['expires_at']) : strtotime('+7 days');
    $expiresAt = date('Y-m-d H:i:s', $expiresTimestamp ?: strtotime('+7 days'));

    if ($categoryId < 1 || mb_strlen($title) < 5 || mb_strlen($description) < 10) {
        jsonResponse(['ok' => false, 'message' => 'Kategori, başlık ve açıklama alanlarını eksiksiz doldurun.'], 422);
    }

    $stmt = db()->prepare('SELECT id, slug, name, icon, default_xp FROM categories WHERE id = ? AND is_active = 1');
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    if (!$category) {
        jsonResponse(['ok' => false, 'message' => 'Geçersiz görev kategorisi.'], 422);
    }

    $locationOptional = $category['slug'] === 'animal';
    if (!$locationOptional && mb_strlen($locationName) < 2) {
        jsonResponse(['ok' => false, 'message' => 'Bu görev türü için konum veya genel bölge bilgisi gereklidir.'], 422);
    }
    if ($locationOptional && $locationName === '') {
        $locationName = 'Genel bölge paylaşılmadı';
    }

    if (($latitude === null) xor ($longitude === null)) {
        jsonResponse(['ok' => false, 'message' => 'Harita konumu eksik geldi. Konumu yeniden alın veya harita konumu olmadan yayınlayın.'], 422);
    }
    if ($latitude !== null && ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180)) {
        jsonResponse(['ok' => false, 'message' => 'Harita konumu geçerli aralıkta değil.'], 422);
    }
    if (!$locationOptional && ($latitude === null || $longitude === null)) {
        jsonResponse(['ok' => false, 'message' => 'Görevin haritada görünmesi için mevcut konumunuzu ekleyin veya açık adresi haritada bulun.'], 422);
    }
    if ($category['slug'] === 'animal' && $latitude !== null && $longitude !== null) {
        // Hayvan görevlerinde kesin nokta yerine yaklaşık 1 km'lik bölge saklanır.
        $latitude = round($latitude, 2);
        $longitude = round($longitude, 2);
    }

    $dangerWords = ['yangın', 'silah', 'kavga', 'gaz kokusu', 'kimyasal', 'trafik kazası', 'şüpheli paket', 'elektrik kablosu', 'tıbbi acil'];
    $haystack = mb_strtolower($title . ' ' . $description);
    foreach ($dangerWords as $word) {
        if (str_contains($haystack, $word)) {
            jsonResponse(['ok' => false, 'message' => 'Bu içerik oyun görevi olamayacak kadar riskli görünüyor. Lütfen uygun resmî veya acil kanalı kullanın.'], 422);
        }
    }

    $requiredEvidence = match ($category['slug']) {
        'question' => 'answer',
        'cleaning', 'animal' => 'before_after',
        default => 'photo',
    };
    $riskLevel = $category['slug'] === 'municipal' ? 'medium' : 'low';
    $xpReward = (int) $category['default_xp'];
    $publicPrecision = $category['slug'] === 'animal' ? 1000 : 250;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO missions (creator_id, category_id, title, description, location_name, latitude, longitude, public_location_precision, status, risk_level, required_evidence, xp_reward, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "open", ?, ?, ?, ?)');
        $stmt->execute([(int) $user['id'], $categoryId, $title, $description, $locationName, $latitude, $longitude, $publicPrecision, $riskLevel, $requiredEvidence, $xpReward, $expiresAt]);
        $missionId = (int) $pdo->lastInsertId();

        if ($category['slug'] === 'question') {
            $validity = max(15, min(240, (int) ($data['validity_minutes'] ?? 60)));
            $stmt = $pdo->prepare('INSERT INTO questions (mission_id, answer_type, validity_minutes) VALUES (?, "text", ?)');
            $stmt->execute([$missionId, $validity]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $stmt = db()->prepare('SELECT m.*, c.slug AS category_slug, c.name AS category_name, c.icon AS category_icon,
                                  u.username AS creator_name,
                                  CASE
                                    WHEN m.latitude IS NOT NULL
                                     AND m.longitude IS NOT NULL
                                     AND (m.expires_at IS NULL OR m.expires_at > NOW())
                                     AND m.status IN ("open", "accepted", "in_progress", "authority_routed", "authority_waiting", "solution_check")
                                    THEN 1 ELSE 0
                                  END AS is_map_active,
                                  CASE WHEN m.expires_at IS NOT NULL AND m.expires_at <= NOW() THEN 1 ELSE 0 END AS is_expired,
                                  0 AS active_assignments, 0 AS answer_count, 0 AS verification_count
                           FROM missions m
                           JOIN categories c ON c.id = m.category_id
                           JOIN users u ON u.id = m.creator_id
                           WHERE m.id = ? LIMIT 1');
    $stmt->execute([$missionId]);
    $mission = $stmt->fetch();

    jsonResponse([
        'ok' => true,
        'message' => 'Görev yayınlandı ve görevler listesine eklendi.',
        'mission_id' => $missionId,
        'mission' => $mission,
    ], 201);
}

jsonResponse(['ok' => false, 'message' => 'Desteklenmeyen istek yöntemi.'], 405);
