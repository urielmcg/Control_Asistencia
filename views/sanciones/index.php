<?php
/**
 * Gestión de Sanciones y Atrasos
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Sanciones y Descuentos';
$activeMenu = 'sanciones';
$db = Database::getConnection();

$error = '';
$success = '';

// Registrar nueva sanción
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    $id_proceso = (int)($_POST['id_proceso'] ?? 0);
    $id_tipo_sancion = (int)($_POST['id_tipo_sancion'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
    $motivo = trim($_POST['motivo'] ?? '');
    $horas_descontadas = (float)($_POST['horas_descontadas'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $currentUser = Auth::user();

    if ($id_proceso <= 0 || $id_tipo_sancion <= 0 || $horas_descontadas < 0) {
        $error = 'Por favor complete todos los datos requeridos e ingrese un valor de descuento válido.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO sanciones (id_proceso, id_tipo_sancion, registrado_por, fecha, motivo, descripcion, horas_descontadas, estado)
                                  VALUES (:id_proceso, :id_tipo_sancion, :reg_por, :fecha, :motivo, :descripcion, :horas, 'ACTIVA')");
            $stmt->execute([
                ':id_proceso'       => $id_proceso,
                ':id_tipo_sancion'  => $id_tipo_sancion,
                ':reg_por'          => $currentUser['id'],
                ':fecha'            => $fecha,
                ':motivo'           => $motivo,
                ':descripcion'      => $descripcion ?: null,
                ':horas'            => $horas_descontadas
            ]);

            Auth::logAudit('REGISTRAR_SANCION', 'sanciones', $db->lastInsertId(), "Sanción aplicada: Proceso $id_proceso, Descuento: $horas_descontadas hrs");
            $success = "Sanción registrada correctamente. Se han descontado $horas_descontadas hrs del proceso.";
        } catch (Exception $e) {
            $error = 'Error al registrar sanción: ' . $e->getMessage();
        }
    }
}

// Listar sanciones
$sanciones = $db->query("SELECT s.*, ts.nombre AS tipo_sancion, p.nombres, p.apellidos, p.ci, u.usuario AS registrado_por_usuario, m.nombre AS modalidad
                         FROM sanciones s
                         INNER JOIN tipos_sancion ts ON s.id_tipo_sancion = ts.id_tipo_sancion
                         INNER JOIN procesos pr ON s.id_proceso = pr.id_proceso
                         INNER JOIN pasantes p ON pr.id_pasante = p.id_pasante
                         INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                         INNER JOIN usuarios u ON s.registrado_por = u.id_usuario
                         ORDER BY s.fecha DESC, s.id_sancion DESC")->fetchAll();

// Procesos en curso para el selector
$procesos = $db->query("SELECT pr.id_proceso, p.nombres, p.apellidos, p.ci, m.nombre AS modalidad
                        FROM procesos pr
                        INNER JOIN pasantes p ON pr.id_pasante = p.id_pasante
                        INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                        WHERE pr.estado = 'EN_CURSO'
                        ORDER BY p.apellidos ASC")->fetchAll();

$tiposSancion = $db->query("SELECT * FROM tipos_sancion WHERE estado = 1")->fetchAll();

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
            <!-- Formulario Aplicar Sanción -->
            <div class="card">
                <h3 class="card-title" style="margin-bottom: 16px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: var(--primary-red); margin-right: 6px;"></i>
                    Registrar Sanción / Descuento
                </h3>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="registrar">

                    <div class="form-group">
                        <label for="id_proceso">Pasante / Proceso en Curso *</label>
                        <select name="id_proceso" id="id_proceso" class="form-control" required>
                            <option value="">-- Seleccionar Pasante --</option>
                            <?php foreach ($procesos as $proc): ?>
                                <option value="<?= $proc['id_proceso'] ?>">
                                    <?= htmlspecialchars($proc['apellidos'] . ' ' . $proc['nombres']) ?> (CI: <?= htmlspecialchars($proc['ci']) ?>) - <?= htmlspecialchars($proc['modalidad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_tipo_sancion">Tipo de Falta / Sanción *</label>
                        <select name="id_tipo_sancion" id="id_tipo_sancion" class="form-control" required>
                            <?php foreach ($tiposSancion as $ts): ?>
                                <option value="<?= $ts['id_tipo_sancion'] ?>"><?= htmlspecialchars($ts['nombre']) ?> - <?= htmlspecialchars($ts['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha">Fecha del Suceso *</label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="horas_descontadas">Horas a Descontar *</label>
                        <input type="number" step="0.5" min="0" max="100" name="horas_descontadas" id="horas_descontadas" class="form-control" value="2.0" required>
                        <small style="color: var(--text-muted); font-size: 0.78rem;">Se restarán automáticamente del avance total de horas.</small>
                    </div>

                    <div class="form-group">
                        <label for="motivo">Motivo Resumido *</label>
                        <input type="text" name="motivo" id="motivo" class="form-control" placeholder="Ej. Atraso reiterado de más de 30 minutos" required>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Detalle de la Sanción</label>
                        <textarea name="descripcion" id="descripcion" rows="2" class="form-control" placeholder="Observaciones o informe del supervisor..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger" style="width: 100%; justify-content: center; margin-top: 10px;">
                        <i class="fa-solid fa-gavel"></i> Aplicar Sanción
                    </button>
                </form>
            </div>

            <!-- Listado Histórico de Sanciones -->
            <div class="card">
                <div class="card-header-flex">
                    <h3 class="card-title">Registro de Sanciones Aplicadas</h3>
                    <span class="badge badge-danger"><?= count($sanciones) ?> registros</span>
                </div>

                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Pasante</th>
                                <th>Tipo</th>
                                <th>Motivo</th>
                                <th>Descuento</th>
                                <th>Supervisor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sanciones)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                        No hay sanciones registradas hasta el momento.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sanciones as $s): ?>
                                    <tr>
                                        <td><strong><?= date('d/m/Y', strtotime($s['fecha'])) ?></strong></td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--primary-blue);"><?= htmlspecialchars($s['nombres'] . ' ' . $s['apellidos']) ?></div>
                                            <small style="color: var(--text-muted);">CI: <?= htmlspecialchars($s['ci']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning"><?= htmlspecialchars($s['tipo_sancion']) ?></span>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($s['motivo']) ?></div>
                                            <?php if (!empty($s['descripcion'])): ?>
                                                <small style="color: var(--text-muted);"><?= htmlspecialchars($s['descripcion']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color: #dc2626; font-size: 0.95rem;">-<?= $s['horas_descontadas'] ?> hrs</strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary"><?= htmlspecialchars($s['registrado_por_usuario']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
