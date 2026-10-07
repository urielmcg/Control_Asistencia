<?php
/**
 * Reporte de Avance y Cumplimiento para Certificación
 * Vista apta para pantalla e impresión formal.
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Reporte de Avance y Certificación';
$activeMenu = 'reportes';
$db = Database::getConnection();

// Filtro por pasante específico
$id_pasante = isset($_GET['id_pasante']) ? (int)$_GET['id_pasante'] : 0;

$sql = "SELECT v.*, pas.telefono, pas.correo, pas.semestre
        FROM vista_horas_proceso v
        INNER JOIN pasantes pas ON v.id_pasante = pas.id_pasante
        WHERE 1=1 ";

if ($id_pasante > 0) {
    $sql .= " AND v.id_pasante = " . $id_pasante;
}

$sql .= " ORDER BY v.porcentaje_completado DESC";
$reportes = $db->query($sql)->fetchAll();

// Split hour tracks: pure interns vs Trabajo Dirigido
$gruposHoras = ['Pasantes' => [], 'Trabajo Dirigido' => []];
foreach ($reportes as $rep) {
    if (($rep['modalidad'] ?? '') === 'Trabajo Dirigido') {
        $gruposHoras['Trabajo Dirigido'][] = $rep;
    } else {
        $gruposHoras['Pasantes'][] = $rep;
    }
}

// Proyecto de Grado: presence in days (Mon-Sat), no hour goal
$pgSql = "SELECT pr.id_proceso, p.id_pasante, p.nombres, p.apellidos, p.ci, pr.fecha_inicio, pr.fecha_fin,
                 COUNT(DISTINCT CASE WHEN DAYOFWEEK(a.fecha) <> 1 AND a.hora_entrada IS NOT NULL THEN a.fecha END) AS dias_asistidos,
                 ROUND(COALESCE(SUM(CASE WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0 ELSE 0 END), 0), 2) AS horas_reg
          FROM procesos pr
          INNER JOIN pasantes p ON pr.id_pasante = p.id_pasante
          INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
          LEFT JOIN asistencias a ON a.id_proceso = pr.id_proceso
          WHERE pr.estado = 'EN_CURSO' AND m.nombre = 'Proyecto de Grado'";
$pgParams = [];
if ($id_pasante > 0) {
    $pgSql .= " AND p.id_pasante = " . $id_pasante;
}
$pgSql .= " GROUP BY pr.id_proceso ORDER BY p.apellidos";
$stmtPg = $db->prepare($pgSql);
$stmtPg->execute($pgParams);
$pgRows = [];
foreach ($stmtPg->fetchAll() as $g) {
    $metaMeses = 6;
    if (!empty($g['fecha_inicio']) && !empty($g['fecha_fin'])) {
        $dd1 = new DateTime($g['fecha_inicio']);
        $dd2 = new DateTime($g['fecha_fin']);
        if ($dd2 > $dd1) {
            $ddiff = $dd1->diff($dd2);
            $metaMeses = max(1, $ddiff->y * 12 + $ddiff->m);
        }
    }
    $g['meta_dias'] = $metaMeses * 26;
    $g['faltan'] = max(0, $g['meta_dias'] - (int)$g['dias_asistidos']);
    $pgRows[] = $g;
}

$listaPasantes = $db->query("SELECT id_pasante, nombres, apellidos, ci FROM pasantes ORDER BY apellidos ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Informe de Avance y Estado de Horas Reglamentarias</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Documento oficial de control de cumplimiento con miras a la emisión de certificados CCDB.
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fa-solid fa-print"></i> Imprimir Informe
                    </button>
                </div>
            </div>

            <!-- Selector de pasante para filtrar reporte -->
            <form method="GET" action="reporte_avance.php" style="display: flex; gap: 10px; align-items: center;" class="no-print">
                <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Filtrar por estudiante:</label>
                <select name="id_pasante" class="form-control" style="max-width: 320px;" onchange="this.form.submit()">
                    <option value="0">-- Todos los Estudiantes en Proceso --</option>
                    <?php foreach ($listaPasantes as $pas): ?>
                        <option value="<?= $pas['id_pasante'] ?>" <?= ($id_pasante === (int)$pas['id_pasante']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pas['apellidos'] . ' ' . $pas['nombres']) ?> (CI: <?= htmlspecialchars($pas['ci']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($id_pasante > 0): ?>
                    <a href="reporte_avance.php" class="btn btn-outline btn-sm">Ver Todos</a>
                <?php endif; ?>
            </form>
        </div>

        <?php foreach ($gruposHoras as $tituloGrupo => $grupo): ?>
            <h3 class="no-print" style="font-size: 1.05rem; font-weight: 800; color: var(--primary-blue); margin: 8px 0 16px 0;"><?= htmlspecialchars($tituloGrupo) ?></h3>
        <?php foreach ($grupo as $rep): ?>
            <?php 
                $porc = (float)$rep['porcentaje_completado'];
                $cumplido = $porc >= 100;
            ?>
            <div class="card" style="border-left: 6px solid <?= $cumplido ? '#10b981' : 'var(--primary-blue)' ?>; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <div>
                        <span class="badge <?= $cumplido ? 'badge-success' : 'badge-info' ?>" style="font-size: 0.8rem; margin-bottom: 6px;">
                            <?= $cumplido ? 'HABILITADO PARA CERTIFICACIÓN' : 'EN DESARROLLO' ?>
                        </span>
                        <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--primary-blue);">
                            <?= htmlspecialchars($rep['nombres'] . ' ' . $rep['apellidos']) ?>
                        </h2>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            CI: <strong><?= htmlspecialchars($rep['ci']) ?></strong> &bull; <?= htmlspecialchars($rep['carrera']) ?> &bull; <?= htmlspecialchars($rep['institucion']) ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.8rem; font-weight: 900; color: <?= $cumplido ? '#15803d' : 'var(--primary-blue)' ?>;">
                            <?= $porc ?>%
                        </div>
                        <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                            Cumplimiento
                        </span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
                    <div style="background: #f8fafc; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 0.72rem; color: #64748b; font-weight: 600;">MODALIDAD</div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-top: 4px;"><?= htmlspecialchars($rep['modalidad']) ?></div>
                    </div>

                    <div style="background: #f8fafc; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 0.72rem; color: #64748b; font-weight: 600;">HORAS REQUERIDAS</div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-top: 4px;"><?= $rep['horas_requeridas'] ?> hrs</div>
                    </div>

                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 0.72rem; color: #15803d; font-weight: 600;">HORAS ACUMULADAS</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #15803d; margin-top: 4px;"><?= $rep['horas_acumuladas'] ?> hrs</div>
                    </div>

                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px;">
                        <div style="font-size: 0.72rem; color: #b91c1c; font-weight: 600;">HORAS FALTANTES</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #dc2626; margin-top: 4px;"><?= $rep['horas_faltantes'] ?> hrs</div>
                    </div>
                </div>

                <!-- Barra de progreso -->
                <div class="progress-container" style="height: 16px; margin-bottom: 12px; background: #e2e8f0; border-radius: 999px;">
                    <div class="progress-bar <?= ($porc < 50) ? 'progress-bar-warning' : '' ?>" style="width: <?= min($porc, 100) ?>%; border-radius: 999px;"></div>
                </div>

                <?php if ((float)$rep['horas_descontadas'] > 0): ?>
                    <p style="font-size: 0.82rem; color: #dc2626; margin-top: 4px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Cuenta con sanciones activas que descontaron <strong><?= $rep['horas_descontadas'] ?> horas</strong> del tiempo acumulado.
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php endforeach; ?>

        <h3 class="no-print" style="font-size: 1.05rem; font-weight: 800; color: var(--primary-blue); margin: 8px 0 16px 0;">Proyecto de Grado — presencia por días</h3>
        <div class="card" style="margin-bottom: 24px;">
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>CI</th>
                            <th>Días asistidos</th>
                            <th>Meta (días)</th>
                            <th>Faltan</th>
                            <th>Horas registradas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pgRows)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">
                                    Sin procesos de Proyecto de Grado en curso.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pgRows as $g): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($g['nombres'] . ' ' . $g['apellidos']) ?></strong></td>
                                    <td><?= htmlspecialchars($g['ci']) ?></td>
                                    <td><?= (int)$g['dias_asistidos'] ?></td>
                                    <td><?= $g['meta_dias'] ?></td>
                                    <td><strong style="color: #b91c1c;"><?= $g['faltan'] ?> días</strong></td>
                                    <td><?= htmlspecialchars($g['horas_reg']) ?> hrs</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Versión tabular solo para impresión/PDF (invisible en pantalla) -->
        <div class="print-report">
            <div class="print-report-head">
                <h2>Informe de Avance y Estado de Horas Reglamentarias</h2>
                <p>Documento oficial de control de cumplimiento con miras a la emisión de certificados CCDB.</p>
                <p><strong>Fecha del reporte:</strong> <?= date('d/m/Y') ?></p>
            </div>
            <?php foreach ($gruposHoras as $tituloGrupo => $grupo): ?>
            <h3><?= htmlspecialchars($tituloGrupo) ?></h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Carrera / Institución</th>
                        <th>Modalidad</th>
                        <th>Tiempo Cumplido</th>
                        <th>Tiempo Faltante</th>
                        <th>Cumplimiento</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach ($grupo as $rep): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($rep['nombres'] . ' ' . $rep['apellidos']) ?></strong><br>
                                <small>CI: <?= htmlspecialchars($rep['ci']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($rep['carrera']) ?><br>
                                <small><?= htmlspecialchars($rep['institucion']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($rep['modalidad']) ?></td>
                            <td><?= $rep['horas_acumuladas'] ?> hrs</td>
                            <td><?= $rep['horas_faltantes'] ?> hrs</td>
                            <td><?= $rep['porcentaje_completado'] ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; ?>

            <h3 style="margin-top: 14px;">Proyecto de Grado — presencia por días</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>CI</th>
                        <th>Días asistidos</th>
                        <th>Meta (días)</th>
                        <th>Faltan</th>
                        <th>Horas registradas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pgRows as $g): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($g['nombres'] . ' ' . $g['apellidos']) ?></strong></td>
                            <td><?= htmlspecialchars($g['ci']) ?></td>
                            <td><?= (int)$g['dias_asistidos'] ?></td>
                            <td><?= $g['meta_dias'] ?></td>
                            <td><?= $g['faltan'] ?> días</td>
                            <td><?= htmlspecialchars($g['horas_reg']) ?> hrs</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="print-foot">Documento generado por el Sistema de Control y Seguimiento de Pasantes CCDB.</p>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>

<style>
@media print {
    .sidebar, .top-navbar, .no-print, .btn, .main-footer {
        display: none !important;
    }
    .main-wrapper {
        margin-left: 0 !important;
        width: 100% !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
        page-break-inside: avoid;
    }
}
</style>
