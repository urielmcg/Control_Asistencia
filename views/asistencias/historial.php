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

// Interns only ever see their own records, regardless of URL parameters
$esPropio = false;
if (Auth::isPasante()) {
    $stmtOwn = $db->prepare("SELECT id_pasante FROM pasantes WHERE ci = :ci LIMIT 1");
    $stmtOwn->execute([':ci' => trim(Auth::user()['ci'] ?? '')]);
    $own = $stmtOwn->fetch();
    $id_pasante = $own ? (int)$own['id_pasante'] : -1;
    $esPropio = true;
}

$sql = "SELECT a.*, p.nombres, p.apellidos, p.ci, inst.nombre AS institucion, m.nombre AS modalidad,
               ROUND(TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0, 2) AS horas_calculadas
        FROM asistencias a
        INNER JOIN procesos pr ON a.id_proceso = pr.id_proceso
        INNER JOIN pasantes p ON pr.id_pasante = p.id_pasante
        INNER JOIN instituciones inst ON pr.id_institucion = inst.id_institucion
        LEFT JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
        WHERE 1=1 ";

$params = [];

// Track tabs: pure interns, Trabajo Dirigido, Proyecto de Grado
$tabH = $_GET['tab'] ?? 'pasantes';
if (!in_array($tabH, ['pasantes', 'td', 'pg'], true)) {
    $tabH = 'pasantes';
}
if ($tabH === 'td') {
    $sql .= " AND m.nombre = 'Trabajo Dirigido' ";
} elseif ($tabH === 'pg') {
    $sql .= " AND m.nombre = 'Proyecto de Grado' ";
} else {
    $sql .= " AND m.nombre IS NULL ";
}

if ($id_pasante > 0) {
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

// Lista de pasantes para el selector de filtro
$listaPasantes = $db->query("SELECT id_pasante, nombres, apellidos, ci FROM pasantes ORDER BY apellidos ASC")->fetchAll();

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

            <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                <?php foreach (['pasantes' => 'Pasantes', 'td' => 'Trabajo Dirigido', 'pg' => 'Proyecto de Grado'] as $key => $label): ?>
                    <a href="historial.php?tab=<?= $key ?>" class="btn btn-sm <?= $tabH === $key ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>

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

            <div class="table-responsive">
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
                                    <td><?= htmlspecialchars($as['ci']) ?></td>
                                    <td><strong><?= htmlspecialchars($as['nombres'] . ' ' . $as['apellidos']) ?></strong></td>
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
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
