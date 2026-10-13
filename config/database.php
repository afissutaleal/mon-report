<?php
// config/database.php
// Database configuration supporting SQLite (default, zero-setup) and MySQL

define('DB_TYPE', getenv('DB_TYPE') ?: 'sqlite'); // 'sqlite' or 'mysql'

define('MYSQL_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('MYSQL_PORT', getenv('DB_PORT') ?: '3306');
define('MYSQL_NAME', getenv('DB_NAME') ?: 'monreport_db');
define('MYSQL_USER', getenv('DB_USER') ?: 'root');
define('MYSQL_PASS', (getenv('DB_PASS') !== false && getenv('DB_PASS') !== null) ? getenv('DB_PASS') : '');

define('SQLITE_FILE', getenv('SQLITE_PATH') ?: (__DIR__ . '/../database/monreport.sqlite'));

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_TYPE === 'sqlite') {
            $dbDir = dirname(SQLITE_FILE);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            $pdo = new PDO('sqlite:' . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON;');
        } else {
            $dsn = "mysql:host=" . MYSQL_HOST . ";port=" . MYSQL_PORT . ";dbname=" . MYSQL_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return $pdo;
    } catch (PDOException $e) {
        // If MySQL fails, fallback gracefully to SQLite
        if (DB_TYPE !== 'sqlite') {
            $dbDir = dirname(SQLITE_FILE);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            $pdo = new PDO('sqlite:' . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        }
        die("Koneksi Database Gagal: " . $e->getMessage());
    }
}
