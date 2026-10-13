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

// Display errors during setup
ini_set('display_errors', '1');
error_reporting(E_ALL);

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_TYPE === 'sqlite') {
            if (!in_array('sqlite', PDO::getAvailableDrivers())) {
                die("<div style='font-family:sans-serif;padding:25px;max-width:650px;margin:50px auto;border:1.5px solid #ef4444;border-radius:12px;background:#fef2f2;color:#991b1b;box-shadow:0 4px 12px rgba(0,0,0,0.08);'>
                    <h3 style='margin-top:0;color:#b91c1c;'>⚠️ Modul SQLite Tidak Aktif di Hosting Ini</h3>
                    <p style='color:#374151;line-height:1.6;'>Server hosting gratis ini menonaktifkan <code>pdo_sqlite</code> dan mewajibkan database <strong>MySQL</strong>.</p>
                    <hr style='border:0;border-top:1px solid #fecaca;margin:15px 0;'>
                    <p style='color:#1f2937;margin-bottom:8px;'><strong>Cara Mengatasinya (2 Menit):</strong></p>
                    <ol style='color:#4b5563;line-height:1.6;padding-left:20px;'>
                        <li>Buka cPanel $\\rightarrow$ menu <strong>MySQL Databases</strong> $\\rightarrow$ buat database baru.</li>
                        <li>Buka <strong>phpMyAdmin</strong> $\\rightarrow$ Import file <code>database/monreport_mysql.sql</code>.</li>
                        <li>Edit <code>config/database.php</code>: ubah <code>DB_TYPE</code> ke <code>'mysql'</code> dan isi host, user, password.</li>
                    </ol>
                </div>");
            }
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
