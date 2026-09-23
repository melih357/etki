<?php
require_once dirname(__DIR__) . '/bootstrap.php';

requireAuth();

function fetchJsonUrl(string $url): array
{
    $headers = [
        'Accept: application/json',
        'Accept-Language: tr-TR,tr;q=0.9,en;q=0.5',
        'User-Agent: Etki-WAMP-Prototip/1.0',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($error !== '' ? $error : 'Adres servisine ulaşılamadı.');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new RuntimeException('Adres servisine ulaşılamadı. WAMP içinde cURL uzantısını etkinleştirin.');
        }
    }

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Adres servisinden geçersiz yanıt alındı.');
    }
    return $decoded;
}

$lat = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
$lon = isset($_GET['lon']) ? (float) $_GET['lon'] : null;
$q = cleanText((string) ($_GET['q'] ?? ''), 240);

if ($lat !== null && $lon !== null) {
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        jsonResponse(['ok' => false, 'message' => 'Geçersiz konum bilgisi.'], 422);
    }
    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=17&addressdetails=1&lat=' . rawurlencode((string) $lat) . '&lon=' . rawurlencode((string) $lon);
    $result = fetchJsonUrl($url);
    $label = cleanText((string) ($result['display_name'] ?? 'Mevcut konum çevresi'), 200);
    jsonResponse([
        'ok' => true,
        'result' => [
            'latitude' => (float) ($result['lat'] ?? $lat),
            'longitude' => (float) ($result['lon'] ?? $lon),
            'label' => $label !== '' ? $label : 'Mevcut konum çevresi',
        ],
    ]);
}

if (mb_strlen($q) < 4) {
    jsonResponse(['ok' => false, 'message' => 'Aramak için daha açık bir adres yazın.'], 422);
}

$url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&countrycodes=tr&q=' . rawurlencode($q);
$rows = fetchJsonUrl($url);
$results = [];
foreach ($rows as $row) {
    if (!isset($row['lat'], $row['lon'])) {
        continue;
    }
    $results[] = [
        'latitude' => (float) $row['lat'],
        'longitude' => (float) $row['lon'],
        'label' => cleanText((string) ($row['display_name'] ?? $q), 220),
        'type' => cleanText((string) ($row['type'] ?? ''), 50),
    ];
}

jsonResponse([
    'ok' => true,
    'results' => $results,
    'message' => $results ? 'Adres sonuçları bulundu.' : 'Bu adres için sonuç bulunamadı.',
]);
