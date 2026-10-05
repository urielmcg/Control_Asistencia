<?php
/**
 * Consulta del Progreso de Horas por Proceso (1,000 Horas)
 * Conectado directamente a la vista 'vista_horas_proceso'.
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();

$pageTitle = 'Progreso de Horas';
$activeMenu = 'progreso_horas';
$db = Database::getConnection();

// Consulta a la vista vista_horas_proceso
$sql = "SELECT * FROM vista_horas_proceso ORDER BY horas_acumuladas DESC";
$stmt = $db->query($sql);
$procesosHoras = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Avance y Cumplimiento de Horas de Pasantía</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Monitoreo hacia las 1,000 horas reglamentarias según convenio institucional.
                    </p>
                </div>
                <a href="../modalidades/reporte_avance.php" class="btn btn-primary">
                    <i class="fa-solid fa-print"></i> Reporte Imprimible
                </a>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>CI</th>
                            <th>Pasante</th>
                            <th>Carrera / Universidad</th>
                            <th>Modalidad</th>
                            <th>Horas Acumuladas</th>
                            <th>Horas Faltantes</th>
                            <th style="width: 250px;">Progreso hacia Meta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($procesosHoras)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 35px;">
                                    No hay procesos registrados para mostrar progreso.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($procesosHoras as $p): ?>
                                <?php 
                                    $porcentaje = (float)$p['porcentaje_completado'];
                                    $horasAcum = (float)$p['horas_acumuladas'];
                                    $horasReq = (int)$p['horas_requeridas'];
                                    $horasFalt = (float)$p['horas_faltantes'];
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['ci']) ?></strong></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-blue);"><?= htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']) ?></div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($p['carrera']) ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($p['institucion']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($p['modalidad']) ?></td>
                                    <td>
                                        <span style="font-weight: 800; color: #15803d; font-size: 1rem;"><?= number_format($horasAcum, 2) ?> hrs</span>
                                        <?php if ((float)$p['horas_descontadas'] > 0): ?>
                                            <div style="font-size: 0.75rem; color: #dc2626;">(-<?= $p['horas_descontadas'] ?> hrs sanción)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: #b91c1c;"><?= number_format($horasFalt, 2) ?> hrs</span>
                                    </td>
                                    <td>
                                        <div style="display: flex; justify-content: space-between; font-size: 0.78rem; font-weight: 700; margin-bottom: 4px;">
                                            <span><?= $porcentaje ?>%</span>
                                            <span style="color: var(--text-muted);"><?= $horasAcum ?> / <?= $horasReq ?> hrs</span>
                                        </div>
                                        <div class="progress-container">
                                            <div class="progress-bar <?= ($porcentaje < 40) ? 'progress-bar-warning' : '' ?>" style="width: <?= min($porcentaje, 100) ?>%;"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($porcentaje >= 100): ?>
                                            <span class="badge badge-success">Meta Cumplida</span>
                                        <?php elseif ($p['estado_proceso'] === 'EN_CURSO'): ?>
                                            <span class="badge badge-info">En Curso</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><?= htmlspecialchars($p['estado_proceso']) ?></span>
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
