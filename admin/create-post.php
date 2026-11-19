
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

startAdminSession();

// Token CSRF
if (empty($_SESSION['post_csrf'])) {
    $_SESSION['post_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$title = '';
$content = '';
$status = 'draft';

// Funkcja zabezpieczająca tekst wyświetlany w HTML
function escapeValue(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Funkcja przesyłania zdjęcia
function uploadPostImage(array $file): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'Nie udało się przesłać zdjęcia.'
        );
    }

    // Maksymalny rozmiar: 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException(
            'Zdjęcie może mieć maksymalnie 5 MB.'
        );
    }

    // Dozwolone formaty
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    // Sprawdzenie rzeczywistego typu pliku
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException(
            'Dozwolone formaty: JPG, PNG, WEBP.'
        );
    }

    // Sprawdzenie poprawności obrazu
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException(
            'Przesłany plik nie jest poprawnym zdjęciem.'
        );
    }

    // Losowa nazwa pliku
    $extension = $allowedTypes[$mimeType];

    $filename = bin2hex(random_bytes(16))
        . '.'
        . $extension;

    // Folder docelowy
    $uploadDirectory = __DIR__ . '/../uploads/posts/';

    if (!is_dir($uploadDirectory)) {
        if (
            !mkdir($uploadDirectory, 0755, true)
            && !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                'Nie można utworzyć folderu zdjęć.'
            );
        }
    }

    $destination = $uploadDirectory . $filename;

    // Przeniesienie przesłanego pliku
    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {
        throw new RuntimeException(
            'Nie udało się zapisać zdjęcia.'
        );
    }

    // Ścieżka zapisywana w PostgreSQL
    return '/uploads/posts/' . $filename;
}

// Obsługa formularza
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sprawdzenie CSRF
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['post_csrf'], $token)
    ) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza.');
    }

    // Pobranie danych
    $titleInput = $_POST['title'] ?? '';
    $contentInput = $_POST['content'] ?? '';
    $statusInput = $_POST['status'] ?? 'draft';

    $title = is_string($titleInput)
        ? trim($titleInput)
        : '';

    $content = is_string($contentInput)
        ? trim($contentInput)
        : '';

    $status = is_string($statusInput)
        ? $statusInput
        : '';

    // Walidacja
    if ($title === '' || $content === '') {

        $error = 'Tytuł i treść są wymagane.';

    } elseif (mb_strlen($title) > 200) {

        $error = 'Tytuł może mieć maksymalnie 200 znaków.';

    } elseif (!in_array(
        $status,
        ['draft', 'published'],
        true
    )) {

        $error = 'Nieprawidłowy status aktualności.';

    } else {

        $imagePath = null;

        try {

            // Przesłanie zdjęcia
            if (isset($_FILES['image'])) {
                $imagePath = uploadPostImage(
                    $_FILES['image']
                );
            }

            // Połączenie z PostgreSQL
            $db = getDatabaseConnection();

            // Zapis aktualności
            $stmt = $db->prepare(
                'INSERT INTO posts
                    (
                        admin_id,
                        title,
                        content,
                        image_path,
                        status,
                        published_at
                    )
                 VALUES
                    (
                        :admin_id,
                        :title,
                        :content,
                        :image_path,
                        :status,
                        CASE
                            WHEN :publish_status = \'published\'
                            THEN NOW()
                            ELSE NULL
                        END
                    )'
            );

            $stmt->execute([
                'admin_id' => $_SESSION['admin_id'],
                'title' => $title,
                'content' => $content,
                'image_path' => $imagePath,
                'status' => $status,
                'publish_status' => $status
            ]);

            // Przekierowanie po zapisaniu
            header('Location: /admin/posts.php');
            exit;

        } catch (RuntimeException | PDOException $e) {

            // Usunięcie zdjęcia, jeśli zapis posta się nie udał
            if ($imagePath !== null) {

                $storedFile = __DIR__ . '/..' . $imagePath;

                if (is_file($storedFile)) {
                    unlink($storedFile);
                }
            }

            error_log($e->getMessage());

            $error = 'Nie udało się zapisać aktualności lub zdjęcia.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dodaj aktualność - TimSerwis</title>

    <link
        rel="stylesheet"
        href="/admin/admin.css"
    >
</head>

<body>

<main class="admin-container">

    <header class="admin-header">

        <h1>Dodaj aktualność</h1>

        <a href="/admin/posts.php">
            Powrót do listy
        </a>

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

        <!-- Token CSRF -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapeValue($_SESSION['post_csrf']) ?>"
        >

        <!-- Tytuł -->
        <label for="title">
            Tytuł aktualności
        </label>

        <input
            type="text"
            id="title"
            name="title"
            maxlength="200"
            value="<?= escapeValue($title) ?>"
            required
        >

        <!-- Treść -->
        <label for="content">
            Treść aktualności
        </label>

        <textarea
            id="content"
            name="content"
            rows="10"
            required
        ><?= escapeValue($content) ?></textarea>

        <!-- Zdjęcie -->
        <label for="image">
            Zdjęcie aktualności (opcjonalne)
        </label>

        <input
            type="file"
            id="image"
            name="image"
            accept="image/jpeg,image/png,image/webp"
        >

        <small>
            Dozwolone formaty: JPG, PNG, WEBP.
            Maksymalny rozmiar: 5 MB.
        </small>

        <!-- Status -->
        <label for="status">
            Status aktualności
        </label>

        <select
            id="status"
            name="status"
        >

            <option
                value="draft"
                <?= $status === 'draft' ? 'selected' : '' ?>
            >
                Zapisz jako szkic
            </option>

            <option
                value="published"
                <?= $status === 'published' ? 'selected' : '' ?>
            >
                Opublikuj
            </option>

        </select>

        <!-- Przycisk zapisu -->
        <button
            type="submit"
            class="admin-button"
        >
            Zapisz aktualność
        </button>

    </form>

</main>

</body>
</html>
