<?php
/**
 * db.php — Singleton PDO para el Panel de Agentes
 */

require_once __DIR__ . '/config.php';

class DB
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                error_log('[DB] Conexión fallida: ' . $e->getMessage());
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(500);
                }
                echo json_encode([
                    'success' => false,
                    'error'   => 'Error de conexión a la base de datos.',
                ]);
                exit;
            }
        }

        return self::$instance;
    }

    // Evitar clonación e instanciación externa
    private function __construct() {}
    private function __clone()    {}
}
