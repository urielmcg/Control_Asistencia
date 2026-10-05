<?php
/**
 * Lista de Pasantes con Búsqueda y Filtros
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();

$pageTitle = 'Gestión de Pasantes';
$activeMenu = 'pasantes';
$db = Database::getConnection();

// Filtros y búsqueda
$q = trim($_GET['q'] ?? '');
$estadoFiltro = trim($_GET['estado'] ?? '');

$sql = "SELECT p.*, i.nombre AS institucion, c.nombre AS carrera,
               (SELECT pr.estado FROM procesos pr WHERE pr.id_pasante = p.id_pasante ORDER BY pr.id_proceso DESC LIMIT 1) AS estado_proceso,
               (SELECT m.nombre FROM procesos pr INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad WHERE pr.id_pasante = p.id_pasante ORDER BY pr.id_proceso DESC LIMIT 1) AS modalidad_actual
        FROM pasantes p
        INNER JOIN instituciones i ON p.id_universidad = i.id_institucion
        INNER JOIN carreras c ON p.id_carrera = c.id_carrera
        WHERE 1=1 ";

$params = [];

if (!empty($q)) {
    $sql .= " AND (p.ci LIKE :q OR p.nombres LIKE :q OR p.apellidos LIKE :q OR i.nombre LIKE :q) ";
    $params[':q'] = "%$q%";
}

if (!empty($estadoFiltro) && $estadoFiltro !== 'TODOS') {
    $sql .= " AND p.estado = :estado ";
    $params[':estado'] = $estadoFiltro;
}

$sql .= " ORDER BY p.id_pasante DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$pasantes = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Listado General de Pasantes</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Control y seguimiento de estudiantes registrados en convenios del CCDB.
                    </p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="credencial_qr.php" class="btn btn-primary" title="Generar e imprimir credenciales QR">
                        <i class="fa-solid fa-qrcode"></i> Ver Credenciales QR
                    </a>
                    <a href="nuevo.php" class="btn btn-danger">
                        <i class="fa-solid fa-user-plus"></i> Registrar Pasante
                    </a>
                </div>
            </div>

            <!-- Barra de Filtros y Búsqueda -->
            <form method="GET" action="index.php" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar por CI, Nombres, Apellidos o Universidad...">
                </div>
                <div style="width: 180px;">
                    <select name="estado" class="form-control" onchange="this.form.submit()">
                        <option value="TODOS" <?= ($estadoFiltro === '' || $estadoFiltro === 'TODOS') ? 'selected' : '' ?>>Todos los estados</option>
                        <option value="ACTIVO" <?= ($estadoFiltro === 'ACTIVO') ? 'selected' : '' ?>>Activos</option>
                        <option value="INACTIVO" <?= ($estadoFiltro === 'INACTIVO') ? 'selected' : '' ?>>Inactivos</option>
                        <option value="CONCLUIDO" <?= ($estadoFiltro === 'CONCLUIDO') ? 'selected' : '' ?>>Concluidos</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                </button>
                <?php if (!empty($q) || !empty($estadoFiltro)): ?>
                    <a href="index.php" class="btn btn-outline">Limpiar</a>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>CI</th>
                            <th>Pasante</th>
                            <th>Universidad / Instituto</th>
                            <th>Carrera</th>
                            <th>Modalidad Actual</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pasantes)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 35px;">
                                    No se encontraron pasantes con los criterios de búsqueda especificados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pasantes as $pas): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($pas['ci']) ?></strong></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-blue);"><?= htmlspecialchars($pas['nombres'] . ' ' . $pas['apellidos']) ?></div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($pas['semestre'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($pas['institucion']) ?></td>
                                    <td><?= htmlspecialchars($pas['carrera']) ?></td>
                                    <td>
                                        <?php if (!empty($pas['modalidad_actual'])): ?>
                                            <span class="badge badge-info"><?= htmlspecialchars($pas['modalidad_actual']) ?></span>
                                        <?php else: ?>
                                            <span style="font-size: 0.8rem; color: var(--text-muted);">Sin modalidad</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.82rem;"><i class="fa-solid fa-envelope" style="color:#94a3b8;"></i> <?= htmlspecialchars($pas['correo'] ?? '-') ?></div>
                                        <div style="font-size: 0.82rem;"><i class="fa-solid fa-phone" style="color:#94a3b8;"></i> <?= htmlspecialchars($pas['telefono'] ?? '-') ?></div>
                                    </td>
                                    <td>
                                        <?php if ($pas['estado'] === 'ACTIVO'): ?>
                                            <span class="badge badge-success">Activo</span>
                                        <?php elseif ($pas['estado'] === 'CONCLUIDO'): ?>
                                            <span class="badge badge-info">Concluido</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><?= htmlspecialchars($pas['estado']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <a href="credencial_qr.php?id=<?= $pas['id_pasante'] ?>" class="btn btn-outline btn-sm" title="Ver Credencial QR" style="color: var(--primary-blue); border-color: var(--primary-blue);">
                                                <i class="fa-solid fa-qrcode"></i>
                                            </a>
                                            <a href="editar.php?id=<?= $pas['id_pasante'] ?>" class="btn btn-outline btn-sm" title="Editar">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="../modalidades/asignar.php?id_pasante=<?= $pas['id_pasante'] ?>" class="btn btn-primary btn-sm" title="Asignar Modalidad">
                                                <i class="fa-solid fa-graduation-cap"></i>
                                            </a>
                                            <a href="../asistencias/historial.php?id_pasante=<?= $pas['id_pasante'] ?>" class="btn btn-outline btn-sm" title="Ver Asistencias">
                                                <i class="fa-solid fa-calendar-days"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
