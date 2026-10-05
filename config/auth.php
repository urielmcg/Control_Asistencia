<?php
/**
 * Helper de Autenticación, Auditoría y Control de Acceso - Sistema Web CCDB
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

class Auth {
    private const REMEMBER_COOKIE = 'ccdb_remember';
    private const REMEMBER_DAYS = 30;

    /**
     * Verifica si el usuario actual ha iniciado sesión.
     * Falls back to the persistent remember-me cookie when there is no session.
     */
    public static function check() {
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            return true;
        }
        return self::restoreFromCookie();
    }

    /**
     * Whether the current session is backed by an active remember-me token.
     * marcar.php uses this to register attendance immediately on scan.
     */
    public static function viaRemember() {
        return self::check() && !empty($_SESSION['via_remember']);
    }

    /**
     * Retorna los datos del usuario logueado o null
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        return [
            'id'       => $_SESSION['user_id'],
            'usuario'  => $_SESSION['user_name'] ?? '',
            'nombre'   => $_SESSION['user_fullname'] ?? '',
            'rol_id'   => $_SESSION['user_role_id'] ?? 0,
            'rol_name' => $_SESSION['user_role_name'] ?? 'INVITADO',
            'ci'       => $_SESSION['user_ci'] ?? '',
            'correo'   => $_SESSION['user_email'] ?? '',
        ];
    }

    /**
     * Exige que el usuario esté autenticado. Si no, redirige a login.
     * @param string $redirect Ruta relativa a la que redirigir si no está autenticado
     */
    public static function requireLogin($redirect = 'login.php') {
        if (!self::check()) {
            $_SESSION['flash_error'] = 'Debe iniciar sesión para acceder a este módulo.';
            // Determinar ruta adecuada hacia login.php
            $rootPath = self::getRootPath();
            header("Location: " . $rootPath . $redirect);
            exit;
        }
    }

    /**
     * Exige uno o varios roles específicos
     * @param array|string $roles
     */
    public static function requireRole($roles) {
        self::requireLogin();
        if (is_string($roles)) {
            $roles = [$roles];
        }

        $userRole = $_SESSION['user_role_name'] ?? '';
        if (!in_array($userRole, $roles)) {
            $rootPath = self::getRootPath();
            $_SESSION['flash_error'] = 'No cuenta con los privilegios suficientes para realizar esta acción.';
            header("Location: " . $rootPath . "index.php");
            exit;
        }
    }

    /**
     * Inicia sesión validando credenciales.
     * When $remember is true a persistent token is issued so the user
     * stays logged in on their phone and QR scans mark attendance directly.
     */
    public static function login($usuario, $password, $remember = false) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT u.*, r.nombre AS rol_nombre 
                              FROM usuarios u 
                              INNER JOIN roles r ON u.id_rol = r.id_rol 
                              WHERE u.usuario = :usuario AND u.estado = 1 
                              LIMIT 1");
        $stmt->execute([':usuario' => trim($usuario)]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Guardar variables de sesión
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['usuario'];
            $_SESSION['user_fullname'] = $user['nombres'] . ' ' . $user['apellidos'];
            $_SESSION['user_role_id'] = $user['id_rol'];
            $_SESSION['user_role_name'] = $user['rol_nombre'];
            $_SESSION['user_ci'] = $user['ci'];
            $_SESSION['user_email'] = $user['correo'];
            $_SESSION['via_remember'] = false;

            if ($remember) {
                self::issueRememberToken((int)$user['id_usuario']);
                $_SESSION['via_remember'] = true;
            } else {
                self::clearRememberToken((int)$user['id_usuario']);
            }

            // Registrar en auditoría
            self::logAudit('LOGIN', 'usuarios', $user['id_usuario'], 'Inicio de sesión exitoso');
            return true;
        }

        return false;
    }

    /**
     * Cierra la sesión
     */
    public static function logout() {
        if (self::check()) {
            self::clearRememberToken((int)$_SESSION['user_id']);
            self::logAudit('LOGOUT', 'usuarios', $_SESSION['user_id'], 'Cierre de sesión');
        }
        setcookie(self::REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Issues a new persistent login token for the user (only the hash is stored).
     */
    private static function issueRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE usuarios SET remember_token = :hash,
                              remember_expiry = DATE_ADD(NOW(), INTERVAL " . self::REMEMBER_DAYS . " DAY)
                              WHERE id_usuario = :id");
        $stmt->execute([':hash' => hash('sha256', $token), ':id' => $userId]);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::REMEMBER_COOKIE, $token, [
            'expires'  => time() + self::REMEMBER_DAYS * 86400,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::REMEMBER_COOKIE] = $token;
    }

    /**
     * Restores the session from a valid remember-me cookie (rotates the token).
     */
    private static function restoreFromCookie() {
        $token = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if (!is_string($token) || strlen($token) !== 64) {
            return false;
        }
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT u.*, r.nombre AS rol_nombre FROM usuarios u
                                  INNER JOIN roles r ON u.id_rol = r.id_rol
                                  WHERE u.remember_token = :hash AND u.remember_expiry > NOW() AND u.estado = 1
                                  LIMIT 1");
            $stmt->execute([':hash' => hash('sha256', $token)]);
            $user = $stmt->fetch();
            if (!$user) {
                return false;
            }
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['usuario'];
            $_SESSION['user_fullname'] = $user['nombres'] . ' ' . $user['apellidos'];
            $_SESSION['user_role_id'] = $user['id_rol'];
            $_SESSION['user_role_name'] = $user['rol_nombre'];
            $_SESSION['user_ci'] = $user['ci'];
            $_SESSION['user_email'] = $user['correo'];
            $_SESSION['via_remember'] = true;
            self::issueRememberToken((int)$user['id_usuario']);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Revokes the persistent login token for the user.
     */
    private static function clearRememberToken($userId) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE usuarios SET remember_token = NULL, remember_expiry = NULL WHERE id_usuario = :id");
            $stmt->execute([':id' => $userId]);
        } catch (Exception $e) {
            // Non-blocking: session login still succeeds
        }
    }

    /**
     * Registra eventos en la tabla de auditoría
     */
    public static function logAudit($accion, $tabla, $id_registro = null, $descripcion = null) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO auditoria (id_usuario, accion, tabla_afectada, id_registro, descripcion, ip) 
                                  VALUES (:id_usuario, :accion, :tabla, :id_registro, :descripcion, :ip)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $id_usuario = $_SESSION['user_id'] ?? null;
            $stmt->execute([
                ':id_usuario'  => $id_usuario,
                ':accion'      => $accion,
                ':tabla'       => $tabla,
                ':id_registro' => $id_registro,
                ':descripcion' => $descripcion,
                ':ip'          => $ip
            ]);
        } catch (Exception $e) {
            error_log("Error guardando auditoría: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el prefijo de ruta hacia la raíz del proyecto
     */
    public static function getRootPath() {
        $currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $parts = explode('/', trim($currentScript, '/'));
        // Si estamos dentro de views/pasantes, requerimos subir 2 niveles: ../../
        if (strpos($currentScript, '/views/') !== false) {
            $subpath = substr($currentScript, strpos($currentScript, '/views/') + 7);
            $slashes = substr_count($subpath, '/');
            return str_repeat('../', $slashes + 1);
        }
        return './';
    }
}
