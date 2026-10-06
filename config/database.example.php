<?php
/**
 * Database configuration (EXAMPLE) - Chiamata
 *
 * Copy this file to database.php and fill in your real credentials.
 * database.php is git-ignored so secrets stay out of the repository.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'chiamata');
define('DB_USER', 'chiamata');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Chiamata DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database non raggiungibile.');
        }
    }

    return $pdo;
}
