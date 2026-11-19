
<?php

require_once __DIR__ . '/auth.php';

requireAdmin();

$username = htmlspecialchars(
    $_SESSION['admin_username'] ?? 'Administrator',
    ENT_QUOTES,
    'UTF-8'
);

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Panel administratora - TimSerwis</title>
</head>
<body>

    <h1>Panel administratora</h1>

    <p>Witaj, <?= $username ?>!</p>

    <p>Tutaj pojawi się zarządzanie aktualnościami.</p>

    <a href="/">Strona główna</a>

    <p>
        <a href="/admin/posts.php">Zarządzaj aktualnościami</a>
    </p>

    <a href="/admin/logout.php">Wyloguj się</a>
</body>
</html>
