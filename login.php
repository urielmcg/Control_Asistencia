<?php
/**
 * Pantalla de Inicio de Sesión - Sistema Web CCDB
 * Inspirada en la estética institucional y requerimientos visuales.
 */
require_once __DIR__ . '/config/auth.php';

// Si ya está autenticado, redirigir al Dashboard
if (Auth::check()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        if (Auth::login($usuario, $password, $remember)) {
            // Coming from a scanned daily QR: go straight back to self check-in
            if (!empty($_SESSION['pending_qr'])) {
                $qr = $_SESSION['pending_qr'];
                unset($_SESSION['pending_qr']);
                header('Location: views/asistencias/marcar.php?qr=' . urlencode($qr));
                exit;
            }
            header("Location: index.php");
            exit;
        } else {
            $error = 'Credenciales incorrectas o usuario inactivo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Centro Cultural Don Bosco</title>
    <!-- Fonts & Font Awesome CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" type="image/png" href="assets/img/ccdb/icono_ccdb.png">
</head>
<body class="login-body">
    <div class="login-wrapper">
        <div class="login-card">
            <!-- Logotipo Don Bosco -->
            <img src="assets/img/ccdb/banner1_ccdb.png" alt="Logotipo Don Bosco CCDB" class="login-logo">
            
            <div class="login-header">
                <h1>SISTEMA WEB CCDB</h1>
                <p>Control de Pasantes y Modalidades de Titulación</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="usuario">Usuario del Sistema</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="usuario" name="usuario" placeholder="Ej. admin" required autofocus value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-right-to-bracket" style="margin-right: 8px;"></i>
                    Ingresar
                </button>

                <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:.85rem;color:#475569;cursor:pointer;">
                    <input type="checkbox" name="remember" value="1" checked>
                    Recuérdame en este celular (marca directa al escanear)
                </label>
            </form>
        </div>
    </div>
</body>
</html>
