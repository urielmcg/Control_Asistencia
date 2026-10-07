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

// Consulta a la vista vista_horas_proceso (interns only see their own row)
if (Auth::isPasante()) {
    $stmt = $db->prepare("SELECT * FROM vista_horas_proceso WHERE ci = :ci ORDER BY horas_acumuladas DESC");
    $stmt->execute([':ci' => trim(Auth::user()['ci'] ?? '')]);
    $procesosHoras = $stmt->fetchAll();
    $esPropio = true;
} else {
    $sql = "SELECT * FROM vista_horas_proceso ORDER BY horas_acumuladas DESC";
    $stmt = $db->query($sql);
    $procesosHoras = $stmt->fetchAll();
    $esPropio = false;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title"><?= $esPropio ? 'Mi Progreso de Horas' : 'Avance y Cumplimiento de Horas de Pasantía' ?></h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Monitoreo hacia las 1,000 horas reglamentarias según convenio institucional.
                    </p>
                </div>
                <?php if (!$esPropio): ?>
                <a href="../modalidades/reporte_avance.php" class="btn btn-primary">
                    <i class="fa-solid fa-print"></i> Reporte Imprimible
                </a>
                <?php endif; ?>
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

        <?php
        // Proyecto de Grado: presence in days (Mon-Sat), not hours
        $pgSql = "SELECT pr.id_proceso, p.nombres, p.apellidos, p.ci, pr.fecha_inicio, pr.fecha_fin,
                         COUNT(DISTINCT CASE WHEN DAYOFWEEK(a.fecha) <> 1 AND a.hora_entrada IS NOT NULL THEN a.fecha END) AS dias_asistidos,
                         ROUND(COALESCE(SUM(CASE WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0 ELSE 0 END), 0), 2) AS horas_reg
                  FROM procesos pr
                  INNER JOIN pasantes p ON pr.id_pasante = p.id_pasante
                  INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                  LEFT JOIN asistencias a ON a.id_proceso = pr.id_proceso
                  WHERE pr.estado = 'EN_CURSO' AND m.nombre = 'Proyecto de Grado'";
        $pgParams = [];
        if ($esPropio) {
            $pgSql .= " AND p.ci = :ci";
            $pgParams[':ci'] = trim(Auth::user()['ci'] ?? '');
        }
        $pgSql .= " GROUP BY pr.id_proceso ORDER BY p.apellidos";
        $stmtPg = $db->prepare($pgSql);
        $stmtPg->execute($pgParams);
        $pgRows = $stmtPg->fetchAll();
        ?>

        <div class="card" style="margin-top: 24px;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Proyecto de Grado: presencia por días</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Sin meta de horas. Se cuentan días asistidos de lunes a sábado (meta: 26 días por mes proyectado).
                    </p>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                <?php if (empty($pgRows)): ?>
                    <p style="color: var(--text-muted); font-size: 0.88rem;">No hay procesos de Proyecto de Grado en curso.</p>
                <?php else: ?>
                    <?php foreach ($pgRows as $g):
                        $metaMeses = 6;
                        if (!empty($g['fecha_inicio']) && !empty($g['fecha_fin'])) {
                            $dd1 = new DateTime($g['fecha_inicio']);
                            $dd2 = new DateTime($g['fecha_fin']);
                            if ($dd2 > $dd1) {
                                $ddiff = $dd1->diff($dd2);
                                $metaMeses = max(1, $ddiff->y * 12 + $ddiff->m);
                            }
                        }
                        $metaDias = $metaMeses * 26;
                        $faltan = max(0, $metaDias - (int)$g['dias_asistidos']);
                    ?>
                        <div style="border: 1px solid var(--border-color); border-radius: 10px; padding: 16px; background: #f8fafc;">
                            <div style="font-weight: 800; color: var(--primary-blue);"><?= htmlspecialchars($g['nombres'] . ' ' . $g['apellidos']) ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">CI: <?= htmlspecialchars($g['ci']) ?></div>
                            <div style="margin-top: 10px; font-size: 0.9rem;">
                                Asistidos <strong><?= (int)$g['dias_asistidos'] ?></strong> de <?= $metaDias ?> días
                                &bull; <?= htmlspecialchars($g['horas_reg']) ?> hrs registradas
                            </div>
                            <div style="margin-top: 6px; font-size: 1.05rem; font-weight: 800; color: #b91c1c;">
                                Faltan <?= $faltan ?> días
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
