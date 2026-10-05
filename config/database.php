<?php
/**
 * Configuración de Conexión a Base de Datos - Sistema Web CCDB
 * Utiliza PDO con manejo de excepciones y codificación UTF-8.
 */

class Database {
    private static $host = 'localhost';
    private static $db_name = 'sistema_pasantes_ccdb';
    private static $username = 'root';
    private static $password = '';
    private static $charset = 'utf8mb4';
    private static $instance = null;

    // Patrón Singleton: prevenir instanciación directa
    private function __construct() {}
    private function __clone() {}

    /**
     * Reads an environment variable with a local default.
     * On hosting (e.g. Render) set DB_HOST, DB_NAME, DB_USER, DB_PASS.
     * Locally (XAMPP) nothing must be set: it falls back to root/''.
     */
    private static function env($key, $default) {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }

    /**
     * Obtiene la conexión PDO singleton
     * @return PDO
     */
    public static function getConnection() {
        if (self::$instance === null) {
            $host = self::env('DB_HOST', self::$host);
            $dbName = self::env('DB_NAME', self::$db_name);
            $user = self::env('DB_USER', self::$username);
            $pass = self::env('DB_PASS', self::$password);
            $dsn = "mysql:host=" . $host . ";dbname=" . $dbName . ";charset=" . self::$charset;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Registrar y mostrar error amigable
                error_log("Error de conexión a la base de datos: " . $e->getMessage());
                die("<div style='font-family:sans-serif;padding:30px;background:#ffebee;border-left:5px solid #d32f2f;margin:20px;border-radius:4px;'>
                        <h2 style='color:#c62828;margin-top:0;'>Error de Conexión al Sistema CCDB</h2>
                        <p>No se pudo conectar a la base de datos <strong>" . htmlspecialchars($dbName) . "</strong> (" . htmlspecialchars($host) . ").</p>
                        <p>Verifique que MySQL esté iniciado en XAMPP y que haya importado el archivo <code>database_setup.sql</code>.</p>
                        <small style='color:#777;'>" . htmlspecialchars($e->getMessage()) . "</small>
                    </div>");
            }
        }

        return self::$instance;
    }
}
