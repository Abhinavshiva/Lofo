<?php
// LOFO - Lost and Found Management System
// Database Configuration & Connection (PDO)

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'lofo_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // First connect without dbname to ensure database exists
            $dsnNoDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
            $initPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

            // Now connect to the actual database
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("<div style='font-family:sans-serif;padding:20px;margin:20px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:8px;'>
                <h3>Database Connection Error</h3>
                <p>Could not connect to MySQL server at <strong>" . htmlspecialchars(DB_HOST . ":" . DB_PORT) . "</strong>.</p>
                <p><small>Error details: " . htmlspecialchars($e->getMessage()) . "</small></p>
                <p>Please ensure MySQL is running in your XAMPP Control Panel.</p>
            </div>");
        }
    }
    return $pdo;
}
