<?php
/**
 * Lista de Modalidades del Sistema
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();

$pageTitle = 'Modalidades de Graduación / Pasantía';
$activeMenu = 'modalidades';
$db = Database::getConnection();

// Listado de modalidades
$modalidades = $db->query("SELECT m.*, 
                           (SELECT COUNT(*) FROM procesos p WHERE p.id_modalidad = m.id_modalidad AND p.estado = 'EN_CURSO') AS total_activos
                           FROM modalidades m 
                           ORDER BY m.id_modalidad ASC")->fetchAll();

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

        <div class="card">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Modalidades Habilitadas en CCDB</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Modalidades técnicas y académicas admitidas para la certificación de estudiantes.
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="asignar.php" class="btn btn-danger">
                        <i class="fa-solid fa-plus-circle"></i> Asignar Modalidad a Pasante
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre de la Modalidad</th>
                            <th>Descripción</th>
                            <th>Horas Base Reglamentarias</th>
                            <th>Estudiantes Activos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modalidades as $mod): ?>
                            <tr>
                                <td>#<?= $mod['id_modalidad'] ?></td>
                                <td>
                                    <strong style="color: var(--primary-blue); font-size: 0.98rem;"><?= htmlspecialchars($mod['nombre']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($mod['descripcion'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size: 0.85rem;"><?= $mod['horas_requeridas_base'] ?> horas</span>
                                </td>
                                <td>
                                    <strong style="color: #16a34a;"><?= $mod['total_activos'] ?> en curso</strong>
                                </td>
                                <td>
                                    <span class="badge badge-success">Activo</span>
                                </td>
                                <td>
                                    <a href="asignar.php?id_modalidad=<?= $mod['id_modalidad'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fa-solid fa-user-plus"></i> Asignar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
