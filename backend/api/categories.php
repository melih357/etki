<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$stmt = db()->query('SELECT id, slug, name, icon, description, default_xp FROM categories WHERE is_active = 1 ORDER BY sort_order, id');
jsonResponse(['ok' => true, 'categories' => $stmt->fetchAll()]);
