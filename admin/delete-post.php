
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Niedozwolona metoda.');
}

$token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['post_csrf']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['post_csrf'], $token)
) {
    http_response_code(403);
    exit('Nieprawidłowy token CSRF.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Nieprawidłowe ID aktualności.');
}

try {
    $db = getDatabaseConnection();

    $db->beginTransaction();

    $stmt = $db->prepare(
        'SELECT image_path FROM posts WHERE id = :id FOR UPDATE'
    );
    $stmt->execute(['id' => $id]);

    $post = $stmt->fetch();

    if (!$post) {
        $db->rollBack();
        http_response_code(404);
        exit('Nie znaleziono aktualności.');
    }

    $stmt = $db->prepare(
        'DELETE FROM posts WHERE id = :id'
    );
    $stmt->execute(['id' => $id]);

    $db->commit();

    // Usuwamy również zdjęcie przypisane do posta.
    $imagePath = $post['image_path'];

    if (
        is_string($imagePath) &&
        preg_match(
            '~^/uploads/posts/[a-f0-9]{32}\.(jpg|png|webp)$~',
            $imagePath
        )
    ) {
        $file = __DIR__ . '/..' . $imagePath;

        if (is_file($file) && !unlink($file)) {
            error_log('Nie udało się usunąć zdjęcia: ' . $file);
        }
    }

    header('Location: /admin/posts.php?deleted=1');
    exit;

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);
    exit('Nie udało się usunąć aktualności.');
}
