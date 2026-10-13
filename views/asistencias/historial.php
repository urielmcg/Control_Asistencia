<?php
/**
 * Historial de Asistencias con Filtros
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();

$pageTitle = 'Historial de Asistencias';
$activeMenu = 'asistencias_historial';
$db = Database::getConnection();

// Filtros
$id_pasante = isset($_GET['id_pasante']) ? (int)$_GET['id_pasante'] : 0;
$fecha_desde = trim($_GET['fecha_desde'] ?? '');
$fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
// Filtro por proceso (solicitudes sin pasante vinculado): solo staff
$id_proceso_f = (!Auth::isPasante()) ? (int)($_GET['id_proceso'] ?? 0) : 0;

// Staff ve todo; el resto solo ve sus procesos (pasante o modalidad sin pasante)
$esPropio = false;
$procesosPropios = null;
if (Auth::isPasante()) {
    require_once __DIR__ . '/../../includes/proceso_propio.php';
    $procesosPropios = procesosPropios($db);
    $id_pasante = 0;
    $id_proceso_f = 0;
    $esPropio = true;
}

$sql = "SELECT a.*, p.nombres, p.apellidos, p.ci, s.postulante AS sol_postulante, s.ci AS sol_ci, inst.nombre AS institucion, m.nombre AS modalidad,
               ROUND(TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0, 2) AS horas_calculadas
        FROM asistencias a
        INNER JOIN procesos pr ON a.id_proceso = pr.id_proceso
        LEFT JOIN pasantes p ON pr.id_pasante = p.id_pasante
        LEFT JOIN solicitudes_modalidad s ON s.id_proceso = pr.id_proceso
        INNER JOIN instituciones inst ON pr.id_institucion = inst.id_institucion
        LEFT JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
        WHERE 1=1 ";

$params = [];

// Track tabs: pure interns, Trabajo Dirigido, Proyecto de Grado
$tabH = $_GET['tab'] ?? 'pasantes';
if (!in_array($tabH, ['pasantes', 'td', 'pg'], true)) {
    $tabH = 'pasantes';
}
if ($id_proceso_f <= 0 && $procesosPropios === null) {
if ($tabH === 'td') {
    $sql .= " AND m.nombre = 'Trabajo Dirigido' ";
} elseif ($tabH === 'pg') {
    $sql .= " AND m.nombre = 'Proyecto de Grado' ";
} else {
    $sql .= " AND m.nombre IS NULL ";
}
}

if ($id_proceso_f > 0) {
    $sql .= " AND a.id_proceso = :id_proceso ";
    $params[':id_proceso'] = $id_proceso_f;
} elseif ($procesosPropios !== null) {
    if (empty($procesosPropios)) {
        $sql .= " AND 1 = 0 ";
    } else {
        $ph = [];
        foreach ($procesosPropios as $i => $idp) {
            $k = ':pp' . $i;
            $ph[] = $k;
            $params[$k] = $idp;
        }
        $sql .= " AND a.id_proceso IN (" . implode(',', $ph) . ") ";
    }
} elseif ($id_pasante > 0) {
    $sql .= " AND p.id_pasante = :id_pasante ";
    $params[':id_pasante'] = $id_pasante;
}

if (!empty($fecha_desde)) {
    $sql .= " AND a.fecha >= :fecha_desde ";
    $params[':fecha_desde'] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $sql .= " AND a.fecha <= :fecha_hasta ";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$sql .= " ORDER BY a.fecha DESC, a.hora_entrada DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$asistencias = $stmt->fetchAll();

// Contexto del proceso filtrado (para el banner)
$procesoCtx = null;
if ($id_proceso_f > 0) {
    $stmtCtx = $db->prepare("SELECT pr.id_proceso, pr.estado, COALESCE(m.nombre, 'Pasantía') AS modalidad,
                                    COALESCE(s.postulante, TRIM(CONCAT(COALESCE(p.nombres, ''), ' ', COALESCE(p.apellidos, '')))) AS quien
                             FROM procesos pr
                             LEFT JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                             LEFT JOIN solicitudes_modalidad s ON s.id_proceso = pr.id_proceso
                             LEFT JOIN pasantes p ON pr.id_pasante = p.id_pasante
                             WHERE pr.id_proceso = :id LIMIT 1");
    $stmtCtx->execute([':id' => $id_proceso_f]);
    $procesoCtx = $stmtCtx->fetch();
}

// Lista de pasantes para el selector de filtro
$listaPasantes = $db->query("SELECT id_pasante, nombres, apellidos, ci FROM pasantes ORDER BY apellidos ASC")->fetchAll();

// Resumen del alcance filtrado (un pasante, un proceso o vista propia)
$resumen = null;
if (!empty($asistencias) && ($id_pasante > 0 || $id_proceso_f > 0 || $esPropio)) {
    $diasSet = [];
    $horasTot = 0.0;
    $fhTot = 0;
    foreach ($asistencias as $a) {
        if (!empty($a['hora_entrada'])) {
            $diasSet[$a['fecha']] = true;
        }
        $horasTot += (float)($a['horas_calculadas'] ?? 0);
        if (!empty($a['fuera_horario'])) {
            $fhTot++;
        }
    }
    $resumen = ['dias' => count($diasSet), 'horas' => round($horasTot, 2), 'fh' => $fhTot, 'filas' => count($asistencias)];
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
                    <h3 class="card-title"><?= $esPropio ? 'Mis Asistencias' : 'Historial General de Asistencias' ?></h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Auditoría y revisión de horas de entrada, salida y tiempo efectivo.
                    </p>
                </div>
                <?php if (!$esPropio): ?>
                <a href="registrar_qr.php" class="btn btn-danger">
                    <i class="fa-solid fa-qrcode"></i> Ir a Marcado QR
                </a>
                <?php endif; ?>
            </div>

            <?php if (!$esPropio): ?>
            <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                <?php foreach (['pasantes' => 'Pasantes', 'td' => 'Trabajo Dirigido', 'pg' => 'Proyecto de Grado'] as $key => $label): ?>
                    <a href="historial.php?tab=<?= $key ?>" class="btn btn-sm <?= $tabH === $key ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Filtros -->
            <form method="GET" action="historial.php" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; background: #f8fafc; padding: 16px; border-radius: 8px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tabH) ?>">
                <?php if (!$esPropio): ?>
                <div style="flex: 1; min-width: 220px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">Pasante:</label>
                    <select name="id_pasante" class="form-control" style="margin-top: 4px;">
                        <option value="0">-- Todos los Pasantes --</option>
                        <?php foreach ($listaPasantes as $pas): ?>
                            <option value="<?= $pas['id_pasante'] ?>" <?= ($id_pasante == $pas['id_pasante']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pas['apellidos'] . ' ' . $pas['nombres']) ?> (CI: <?= htmlspecialchars($pas['ci']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="width: 170px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">Desde:</label>
                    <input type="date" name="fecha_desde" class="form-control" style="margin-top: 4px;" value="<?= htmlspecialchars($fecha_desde) ?>">
                </div>

                <div style="width: 170px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">Hasta:</label>
                    <input type="date" name="fecha_hasta" class="form-control" style="margin-top: 4px;" value="<?= htmlspecialchars($fecha_hasta) ?>">
                </div>

                <div style="display: flex; align-items: flex-end; gap: 8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-filter"></i> Filtrar
                    </button>
                    <?php if ($id_pasante > 0 || !empty($fecha_desde) || !empty($fecha_hasta)): ?>
                        <a href="historial.php?tab=<?= htmlspecialchars($tabH) ?>" class="btn btn-outline">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($resumen): ?>
            <div class="no-print" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; margin-bottom: 16px;">
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px;">
                    <div style="font-size: 0.72rem; color: #15803d; font-weight: 600;">DÍAS ASISTIDOS</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #15803d;"><?= $resumen['dias'] ?></div>
                </div>
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px;">
                    <div style="font-size: 0.72rem; color: #1d4ed8; font-weight: 600;">HORAS TOTALES</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #1d4ed8;"><?= $resumen['horas'] ?></div>
                </div>
                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px;">
                    <div style="font-size: 0.72rem; color: #b45309; font-weight: 600;">FUERA DE HORARIO</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #b45309;"><?= $resumen['fh'] ?></div>
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-print"></i> Imprimir Reporte
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="table-responsive">
                <?php if ($procesoCtx): ?>
                    <div class="alert alert-info" style="margin-bottom: 12px;">
                        Historial del proceso #<?= $procesoCtx['id_proceso'] ?> — <strong><?= htmlspecialchars($procesoCtx['quien'] ?: 'Sin nombre') ?></strong>
                        (<?= htmlspecialchars($procesoCtx['modalidad']) ?>) ·
                        <a href="historial.php?tab=<?= htmlspecialchars($tabH) ?>">Ver todos</a>
                    </div>
                <?php endif; ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>CI</th>
                            <th>Pasante</th>
                            <th>Modalidad</th>
                            <th>Hora Entrada</th>
                            <th>Hora Salida</th>
                            <th>Total Horas</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($asistencias)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 35px;">
                                    No se encontraron registros de asistencias para los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($asistencias as $as): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(date('d/m/Y', strtotime($as['fecha']))) ?></strong></td>
                                    <td><?= htmlspecialchars($as['ci'] ?? $as['sol_ci'] ?? '-') ?></td>
                                    <?php $nomH = trim(($as['nombres'] ?? '') . ' ' . ($as['apellidos'] ?? '')); if ($nomH === '') { $nomH = $as['sol_postulante'] ?? '-'; } ?>
                                    <td><strong><?= htmlspecialchars($nomH) ?></strong></td>
                                    <td><?= htmlspecialchars($as['modalidad'] ?? 'Pasantía') ?></td>
                                    <td><span class="badge badge-success"><?= htmlspecialchars($as['hora_entrada'] ?? '-') ?></span></td>
                                    <td>
                                        <?php if (!empty($as['hora_salida'])): ?>
                                            <span class="badge badge-info"><?= htmlspecialchars($as['hora_salida']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #d97706; font-size: 0.8rem;">Sin registrar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= !empty($as['horas_calculadas']) ? htmlspecialchars($as['horas_calculadas']) . ' hrs' : '-' ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary"><?= htmlspecialchars($as['estado']) ?></span>
                                        <?php if (!empty($as['fuera_horario'])): ?>
                                            <br><span class="badge badge-warning" style="margin-top: 4px;">FUERA DE HORARIO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($as['observacion'] ?? '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Versión tabular solo para impresión/PDF (invisible en pantalla) -->
        <div class="print-report">
            <div class="print-watermark" aria-hidden="true">
                <img src="<?= APP_ROOT ?>assets/img/ccdb/imagen_fondo_sin.png" alt="">
            </div>
            <div class="print-report-head">
                <h2>Historial de Asistencias</h2>
                <p>Auditoría de entradas, salidas y tiempo efectivo.</p>
                <p><strong>Fecha del reporte:</strong> <?= date('d/m/Y') ?><?php if (!empty($fecha_desde) || !empty($fecha_hasta)): ?> · Rango: <?= htmlspecialchars($fecha_desde ?: '...') ?> – <?= htmlspecialchars($fecha_hasta ?: '...') ?><?php endif; ?></p>
                <?php if ($resumen): ?>
                <p><strong>Resumen:</strong> <?= $resumen['dias'] ?> días asistidos · <?= $resumen['horas'] ?> hrs · <?= $resumen['fh'] ?> fuera de horario (<?= $resumen['filas'] ?> registros).</p>
                <?php endif; ?>
            </div>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Pasante</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Horas</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asistencias as $as): ?>
                    <?php $nomP = trim(($as['nombres'] ?? '') . ' ' . ($as['apellidos'] ?? '')); if ($nomP === '') { $nomP = $as['sol_postulante'] ?? '-'; } ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($as['fecha']))) ?></td>
                        <td><strong><?= htmlspecialchars($nomP) ?></strong><br><small>CI: <?= htmlspecialchars($as['ci'] ?? $as['sol_ci'] ?? '-') ?></small></td>
                        <td><?= htmlspecialchars($as['hora_entrada'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($as['hora_salida'] ?? 'Sin registrar') ?></td>
                        <td><?= !empty($as['horas_calculadas']) ? htmlspecialchars($as['horas_calculadas']) . ' hrs' : '-' ?></td>
                        <td><?= htmlspecialchars($as['estado']) ?><?= !empty($as['fuera_horario']) ? ' (FUERA DE HORARIO)' : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="print-foot">Documento generado por el Sistema de Control y Seguimiento de Pasantes CCDB.</p>
        </div>

<style>
@media print {
    .btn, .no-print {
        display: none !important;
    }
}
</style>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
