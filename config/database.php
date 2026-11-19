
<?php

function getDatabaseConnection(): PDO
{
    $host = '127.0.0.1';
    $port = '5432';
    $dbname = 'timserwis_db';

    $username = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');

    if ($username === false || $password === false) {
        throw new RuntimeException('Brak konfiguracji bazy danych.');
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}
