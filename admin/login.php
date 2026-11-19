
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

startAdminSession();

if (isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['login_csrf'], $token)) {
        http_response_code(403);
        exit('Nieprawidlowy token formularza.');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (is_string($username) && is_string($password)) {
        $db = getDatabaseConnection();

        $stmt = $db->prepare(
            'SELECT id, username, password_hash
             FROM admins
             WHERE username = :username'
        );

        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            unset($_SESSION['login_csrf']);

            header('Location: /admin/dashboard.php');
            exit;
        }
    }

    $error = 'Nieprawidłowy login lub hasło.';
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie administratora - TimSerwis</title>
</head>
<body>

    <h1>Panel administratora TimSerwis</h1>

    <?php if ($error): ?>
        <p style="color: red;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label for="username">Login:</label>
        <input
            type="text"
            id="username"
            name="username"
            required
            autocomplete="username"
        >

        <br><br>

        <label for="password">Hasło:</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            autocomplete="current-password"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <br><br>

        <button type="submit">Zaloguj się</button>

    </form>

    <p><a href="/">Powrót do strony głównej</a></p>

</body>
</html>
