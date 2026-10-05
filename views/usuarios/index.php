<?php
/**
 * Gestión de Usuarios del Sistema CCDB
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireRole('ADMINISTRADOR');

$pageTitle = 'Gestión de Usuarios';
$activeMenu = 'usuarios';
$db = Database::getConnection();

$error = '';
$success = '';

// Procesar Creación de Nuevo Usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $ci = trim($_POST['ci'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $id_rol = (int)($_POST['id_rol'] ?? 2);

    if (empty($usuario) || empty($password) || empty($nombres) || empty($apellidos) || empty($ci)) {
        $error = 'Por favor complete todos los campos obligatorios.';
    } else {
        // Verificar usuario o CI duplicado
        $stmtChk = $db->prepare("SELECT id_usuario FROM usuarios WHERE usuario = :u OR ci = :ci");
        $stmtChk->execute([':u' => $usuario, ':ci' => $ci]);
        if ($stmtChk->fetch()) {
            $error = 'El nombre de usuario o Cédula de Identidad ya se encuentra registrado.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtIns = $db->prepare("INSERT INTO usuarios (usuario, password, nombres, apellidos, ci, correo, telefono, id_rol, estado)
                                         VALUES (:u, :p, :nom, :ape, :ci, :cor, :tel, :rol, 1)");
                $stmtIns->execute([
                    ':u'   => $usuario,
                    ':p'   => $hash,
                    ':nom' => $nombres,
                    ':ape' => $apellidos,
                    ':ci'  => $ci,
                    ':cor' => $correo ?: null,
                    ':tel' => $telefono ?: null,
                    ':rol' => $id_rol
                ]);

                $newId = $db->lastInsertId();
                Auth::logAudit('CREAR_USUARIO', 'usuarios', $newId, "Usuario creado: $usuario ($nombres $apellidos)");
                $success = "Usuario '$usuario' creado exitosamente.";
            } catch (Exception $e) {
                $error = 'Error al registrar usuario: ' . $e->getMessage();
            }
        }
    }
}

// Listar usuarios existentes
$usuarios = $db->query("SELECT u.*, r.nombre AS rol_nombre 
                        FROM usuarios u 
                        INNER JOIN roles r ON u.id_rol = r.id_rol 
                        ORDER BY u.id_usuario DESC")->fetchAll();

$roles = $db->query("SELECT * FROM roles WHERE estado = 1")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
            <!-- Formulario Nuevo Usuario -->
            <div class="card">
                <h3 class="card-title" style="margin-bottom: 16px;">
                    <i class="fa-solid fa-user-plus" style="color: var(--primary-red); margin-right: 6px;"></i>
                    Nuevo Usuario
                </h3>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="form-group">
                        <label for="usuario">Nombre de Usuario *</label>
                        <input type="text" name="usuario" id="usuario" class="form-control" required placeholder="Ej. rquispe">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña *</label>
                        <input type="password" name="password" id="password" class="form-control" required placeholder="Mínimo 6 caracteres">
                    </div>

                    <div class="form-group">
                        <label for="nombres">Nombres *</label>
                        <input type="text" name="nombres" id="nombres" class="form-control" required placeholder="Ej. Rodrigo">
                    </div>

                    <div class="form-group">
                        <label for="apellidos">Apellidos *</label>
                        <input type="text" name="apellidos" id="apellidos" class="form-control" required placeholder="Ej. Quispe Rojas">
                    </div>

                    <div class="form-group">
                        <label for="ci">CI *</label>
                        <input type="text" name="ci" id="ci" class="form-control" required placeholder="Ej. 6543210">
                    </div>

                    <div class="form-group">
                        <label for="id_rol">Rol en el Sistema *</label>
                        <select name="id_rol" id="id_rol" class="form-control" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id_rol'] ?>"><?= htmlspecialchars($r['nombre']) ?> - <?= htmlspecialchars($r['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="correo">Correo Electrónico</label>
                        <input type="email" name="correo" id="correo" class="form-control" placeholder="usuario@ccdb.org.bo">
                    </div>

                    <button type="submit" class="btn btn-danger" style="width: 100%; justify-content: center; margin-top: 10px;">
                        <i class="fa-solid fa-floppy-disk"></i> Registrar Usuario
                    </button>
                </form>
            </div>

            <!-- Listado de Usuarios -->
            <div class="card">
                <div class="card-header-flex">
                    <h3 class="card-title">Usuarios del Sistema Registrados</h3>
                    <span class="badge badge-info"><?= count($usuarios) ?> usuarios</span>
                </div>

                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Nombre Completo</th>
                                <th>CI</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($u['usuario']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['nombres'] . ' ' . $u['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($u['ci']) ?></td>
                                    <td>
                                        <span class="badge <?= ($u['rol_nombre'] === 'ADMINISTRADOR') ? 'badge-danger' : 'badge-info' ?>">
                                            <?= htmlspecialchars($u['rol_nombre']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">Activo</span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= date('d/m/Y', strtotime($u['fecha_registro'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
