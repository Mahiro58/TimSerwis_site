
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

if (empty($_SESSION['post_csrf'])) {
    $_SESSION['post_csrf'] = bin2hex(random_bytes(32));
}

function escapeValue(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function uploadPostImage(array $file): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nie udało się przesłać zdjęcia.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Zdjęcie może mieć maksymalnie 5 MB.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))
        ->file($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType]) ||
        @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Nieprawidłowy format zdjęcia.');
    }

    $filename = bin2hex(random_bytes(16))
        . '.' . $allowedTypes[$mimeType];

    $directory = __DIR__ . '/../uploads/posts/';

    if (!is_dir($directory) &&
        !mkdir($directory, 0755, true) &&
        !is_dir($directory)) {
        throw new RuntimeException('Nie można utworzyć folderu zdjęć.');
    }

    if (!move_uploaded_file(
        $file['tmp_name'],
        $directory . $filename
    )) {
        throw new RuntimeException('Nie udało się zapisać zdjęcia.');
    }

    return '/uploads/posts/' . $filename;
}

function deletePostImage(?string $path): void
{
    if ($path === null ||
        !preg_match(
            '~^/uploads/posts/[a-f0-9]{32}\.(jpg|png|webp)$~',
            $path
        )) {
        return;
    }

    $file = __DIR__ . '/..' . $path;

    if (is_file($file)) {
        unlink($file);
    }
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Nieprawidłowe ID aktualności.');
}

$db = getDatabaseConnection();

$stmt = $db->prepare('SELECT * FROM posts WHERE id = :id');
$stmt->execute(['id' => $id]);

$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    exit('Nie znaleziono aktualności.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['post_csrf'], $token)) {
        http_response_code(403);
        exit('Nieprawidłowy token CSRF.');
    }

    $titleInput = $_POST['title'] ?? '';
    $contentInput = $_POST['content'] ?? '';
    $statusInput = $_POST['status'] ?? '';
    $removeImage = ($_POST['remove_image'] ?? '') === '1';

    $title = is_string($titleInput) ? trim($titleInput) : '';
    $content = is_string($contentInput) ? trim($contentInput) : '';
    $status = is_string($statusInput) ? $statusInput : '';

    $post['title'] = $title;
    $post['content'] = $content;
    $post['status'] = $status;

    if ($title === '' || $content === '') {
        $error = 'Tytuł i treść są wymagane.';
    } elseif (mb_strlen($title) > 200) {
        $error = 'Tytuł może mieć maksymalnie 200 znaków.';
    } elseif (!in_array($status, ['draft', 'published'], true)) {
        $error = 'Nieprawidłowy status.';
    } else {
        $oldImage = $post['image_path'];
        $newImage = null;

        try {
            if (isset($_FILES['image'])) {
                $newImage = uploadPostImage($_FILES['image']);
            }

            $imagePath = $newImage
                ?? ($removeImage ? null : $oldImage);

            $update = $db->prepare(
                'UPDATE posts
                 SET title = :title,
                     content = :content,
                     image_path = :image_path,
                     status = :status,
                     published_at = CASE
                         WHEN :publish_status = \'draft\' THEN NULL
                         ELSE COALESCE(published_at, NOW())
                     END,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $update->execute([
                'title' => $title,
                'content' => $content,
                'image_path' => $imagePath,
                'status' => $status,
                'publish_status' => $status,
                'id' => $id
            ]);

            if ($oldImage !== $imagePath) {
                deletePostImage($oldImage);
            }

            header('Location: /admin/posts.php');
            exit;

        } catch (RuntimeException | PDOException $e) {
            deletePostImage($newImage);
            error_log($e->getMessage());
            $error = 'Nie udało się zapisać zmian.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edytuj aktualność - TimSerwis</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<main class="admin-container">

    <header class="admin-header">
        <h1>Edytuj aktualność</h1>
        <a href="/admin/posts.php">Powrót do listy</a>
    </header>

    <?php if ($error): ?>
        <p class="form-error">
            <?= escapeValue($error) ?>
        </p>
    <?php endif; ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        class="post-form"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapeValue($_SESSION['post_csrf']) ?>"
        >

        <label for="title">Tytuł aktualności</label>
        <input
            type="text"
            id="title"
            name="title"
            maxlength="200"
            value="<?= escapeValue($post['title']) ?>"
            required
        >

        <label for="content">Treść aktualności</label>
        <textarea
            id="content"
            name="content"
            rows="10"
            required
        ><?= escapeValue($post['content']) ?></textarea>

        <?php if (!empty($post['image_path'])): ?>
            <label>Obecne zdjęcie</label>

            <img
                src="<?= escapeValue($post['image_path']) ?>"
                alt="Zdjęcie aktualności"
                class="edit-post-image"
            >

            <label class="checkbox-label">
                <input
                    type="checkbox"
                    name="remove_image"
                    value="1"
                >
                Usuń obecne zdjęcie
            </label>
        <?php endif; ?>

        <label for="image">Nowe zdjęcie (opcjonalne)</label>
        <input
            type="file"
            id="image"
            name="image"
            accept="image/jpeg,image/png,image/webp"
        >

        <small>JPG, PNG lub WEBP, maksymalnie 5 MB.</small>

        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="draft"
                <?= $post['status'] === 'draft' ? 'selected' : '' ?>>
                Szkic
            </option>
            <option value="published"
                <?= $post['status'] === 'published' ? 'selected' : '' ?>>
                Opublikowany
            </option>
        </select>

        <button type="submit" class="admin-button">
            Zapisz zmiany
        </button>

    </form>

</main>

</body>
</html>
