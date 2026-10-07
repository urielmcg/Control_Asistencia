<?php
/**
 * Lista de Modalidades del Sistema
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Modalidades de Graduación / Pasantía';
$activeMenu = 'modalidades';
$db = Database::getConnection();

// Listado de modalidades
$modalidades = $db->query("SELECT m.*, 
                           (SELECT COUNT(*) FROM procesos p WHERE p.id_modalidad = m.id_modalidad AND p.estado = 'EN_CURSO') AS total_activos
                           FROM modalidades m 
                           ORDER BY m.id_modalidad ASC")->fetchAll();

// Solicitudes por estado para la tabla inferior
$tab = ($_GET['tab'] ?? 'activas') === 'inactivas' ? 'inactivas' : 'activas';
$stmtSol = $db->prepare("SELECT pr.*, p.nombres, p.apellidos, p.ci, p.id_pasante,
                                i.nombre AS institucion, c.nombre AS carrera,
                                m.nombre AS modalidad,
                                t.nombre AS tutor,
                                tu.nombre AS turno_nombre, tu.hora_inicio AS turno_ini, tu.hora_fin AS turno_fin
                         FROM procesos pr
                         LEFT JOIN pasantes p ON pr.id_pasante = p.id_pasante
                         INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                         LEFT JOIN instituciones i ON pr.id_institucion = i.id_institucion
                         LEFT JOIN carreras c ON p.id_carrera = c.id_carrera
                         LEFT JOIN tutores t ON pr.id_tutor = t.id_tutor
                         LEFT JOIN turnos tu ON pr.id_turno = tu.id_turno
                         WHERE " . ($tab === 'activas' ? "pr.estado = 'EN_CURSO'" : "pr.estado <> 'EN_CURSO'") . "
                         ORDER BY pr.id_proceso DESC");
$stmtSol->execute();
$solicitudes = $stmtSol->fetchAll();

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

        <div class="card" style="margin-top: 24px;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Modalidades activas</h3>
                </div>
                <a href="asignar.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Nueva modalidad
                </a>
                <a href="turnos.php" class="btn btn-outline btn-sm" style="margin-left: 8px;">
                    <i class="fa-solid fa-clock"></i> Turnos
                </a>
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                <a href="index.php?tab=activas" class="btn btn-sm <?= $tab === 'activas' ? 'btn-primary' : 'btn-outline' ?>">
                    Modalidades activas
                </a>
                <a href="index.php?tab=inactivas" class="btn btn-sm <?= $tab === 'inactivas' ? 'btn-primary' : 'btn-outline' ?>">
                    Modalidades inactivas
                </a>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Modalidad</th>
                            <th>Postulante</th>
                            <th>Institución y carrera</th>
                            <th>Tutor asignado</th>
                            <th>Turno</th>
                            <th>Horas / Tiempo proyectado</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($solicitudes)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No hay solicitudes <?= $tab === 'activas' ? 'activas' : 'inactivas' ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($solicitudes as $sol):
                                $meses = null;
                                if (!empty($sol['fecha_inicio']) && !empty($sol['fecha_fin'])) {
                                    $d1 = new DateTime($sol['fecha_inicio']);
                                    $d2 = new DateTime($sol['fecha_fin']);
                                    if ($d2 >= $d1) {
                                        $diff = $d1->diff($d2);
                                        $meses = $diff->y * 12 + $diff->m;
                                    }
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sol['modalidad']) ?></strong><br>
                                        <span style="font-size: 0.78rem; color: var(--text-muted);">
                                            <?= $sol['modalidad'] === 'Proyecto de Grado' ? 'Horas no aplican' : htmlspecialchars($sol['horas_requeridas'] . ' horas requeridas') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($sol['id_pasante'])): ?>
                                            <?= htmlspecialchars(($sol['nombres'] ?? '') . ' ' . ($sol['apellidos'] ?? '')) ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">Registro independiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($sol['institucion'] ?? '-') ?><br>
                                        <span style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($sol['carrera'] ?? '') ?></span>
                                    </td>
                                    <td>
                                        <?= !empty($sol['tutor']) ? htmlspecialchars($sol['tutor']) : '<span style="color: var(--text-muted);">No asignado</span>' ?>
                                    </td>
                                    <td><?= !empty($sol['turno_nombre']) ? htmlspecialchars($sol['turno_nombre'] . ' · ' . substr($sol['turno_ini'], 0, 5) . '–' . substr($sol['turno_fin'], 0, 5)) : '—' ?></td>
                                    <td><?= $meses !== null ? htmlspecialchars($meses . ' meses') : '—' ?></td>
                                    <td>
                                        <?php if ($sol['estado'] === 'EN_CURSO'): ?>
                                            <span class="badge badge-success">ACTIVA</span>
                                        <?php else: ?>
                                            <span class="badge badge-info"><?= htmlspecialchars($sol['estado']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <?php if (!empty($sol['id_pasante'])): ?>
                                            <a href="../pasantes/editar.php?id=<?= $sol['id_pasante'] ?>" class="btn btn-outline btn-sm" title="Editar postulante">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="../asistencias/historial.php?id_pasante=<?= $sol['id_pasante'] ?>" class="btn btn-outline btn-sm" title="Ver asistencias">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                        <?php endif; ?>
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
