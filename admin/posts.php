
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

if (empty($_SESSION['post_csrf'])) {
    $_SESSION['post_csrf'] = bin2hex(random_bytes(32));
}

$db = getDatabaseConnection();

$stmt = $db->query(
    'SELECT id, title, status, created_at, published_at
     FROM posts
     ORDER BY created_at DESC, id DESC'
);

$posts = $stmt->fetchAll();

function escapeHtml(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktualności - TimSerwis</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<main class="admin-container">

    <header class="admin-header">
        <h1>Zarządzanie aktualnościami</h1>
        <a href="/admin/dashboard.php">Panel główny</a>
    </header>

    <section class="admin-actions">
        <a class="admin-button" href="/admin/create-post.php">
            + Dodaj aktualność
        </a>
    </section>

    <?php if (empty($posts)): ?>

        <p>Nie masz jeszcze żadnych aktualności.</p>

    <?php else: ?>

        <div class="posts-list">

            <?php foreach ($posts as $post): ?>

                <article class="post-card">

                    <div class="post-info">
                        <h2><?= escapeHtml($post['title']) ?></h2>

                        <p>
                            Status:
                            <strong>
                                <?= $post['status'] === 'published'
                                    ? 'Opublikowany'
                                    : 'Szkic' ?>
                            </strong>
                        </p>

                        <small>
                            Utworzono:
                            <?= escapeHtml($post['created_at']) ?>
                        </small>
                    </div>                    
                        <div class="post-actions">

                            <a href="/admin/edit-post.php?id=<?= (int)$post['id'] ?>">
                                Edytuj
                            </a>

                            <form
                                method="POST"
                                action="/admin/delete-post.php"
                                onsubmit="return confirm('Czy na pewno chcesz usunąć tę aktualność?');"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escapeHtml($_SESSION['post_csrf']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$post['id'] ?>"
                                >

                                <button type="submit" class="delete-button">
                                    Usuń
                                </button>

                            </form>
                        </div>


                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

</body>
</html>
