
<?php

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $db = getDatabaseConnection();

    $stmt = $db->query(
        "SELECT id, title, content, image_path, published_at
         FROM posts
         WHERE status = 'published'
           AND published_at IS NOT NULL
           AND published_at <= NOW()
         ORDER BY published_at DESC, id DESC
         LIMIT 20"
    );

    $posts = $stmt->fetchAll();

    echo json_encode(
        [
            'success' => true,
            'posts' => $posts
        ],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Nie udało się pobrać aktualności.'
    ]);
}
