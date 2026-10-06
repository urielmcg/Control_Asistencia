<?php
/**
 * Listado de Tutores
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Tutores';
$activeMenu = 'tutores';
$db = Database::getConnection();

$tutores = $db->query("SELECT t.*,
                              (SELECT COUNT(*) FROM procesos pr WHERE pr.id_tutor = t.id_tutor AND pr.estado = 'EN_CURSO') AS postulantes
                       FROM tutores t
                       ORDER BY t.nombre ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Tutores de Trabajo Dirigido</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Responsables que acompañan solicitudes activas de Trabajo Dirigido.
                    </p>
                </div>
                <a href="nuevo.php" class="btn btn-danger">
                    <i class="fa-solid fa-plus"></i> Registrar Tutor
                </a>
            </div>

            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cargo</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Postulantes</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tutores)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    Aún no hay tutores registrados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tutores as $t): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($t['nombre']) ?></strong></td>
                                    <td><?= htmlspecialchars($t['cargo']) ?></td>
                                    <td><?= htmlspecialchars($t['correo']) ?></td>
                                    <td><?= htmlspecialchars($t['telefono']) ?></td>
                                    <td><span class="badge badge-info"><?= (int)$t['postulantes'] ?> activos</span></td>
                                    <td><span class="badge badge-success"><?= htmlspecialchars($t['estado']) ?></span></td>
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
