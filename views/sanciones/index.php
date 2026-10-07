<?php
/**
 * Gestión de Sanciones y Atrasos
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();
require_once __DIR__ . '/../../includes/sanciones_reglas.php';

$esStaff = Auth::isStaff();

$pageTitle = 'Sanciones y Descuentos';
$activeMenu = 'sanciones';
$db = Database::getConnection();

$error = '';
$success = '';

// Marcar sanción como cumplida (solo staff)
if (isset($_GET['cumplir'])) {
    if (!$esStaff) {
        header('Location: index.php');
        exit;
    }
    $idSan = (int)$_GET['cumplir'];
    $stmtC = $db->prepare("UPDATE sanciones SET estado = 'CUMPLIDA', fecha_cumplimiento = CURDATE() WHERE id_sancion = :id AND estado = 'ACTIVA'");
    $stmtC->execute([':id' => $idSan]);
    if ($stmtC->rowCount() > 0) {
        Auth::logAudit('SANCION_CUMPLIDA', 'sanciones', $idSan, 'Sanción marcada como cumplida');
        $_SESSION['flash_success'] = 'Sanción marcada como cumplida.';
    }
    header('Location: index.php');
    exit;
}

// Registrar nueva sanción (solo staff; todo lo demás es solo lectura propia)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    if (!$esStaff) {
        $error = 'No cuenta con los privilegios suficientes para realizar esta acción.';
    } else {
    $id_proceso = (int)($_POST['id_proceso'] ?? 0);
    $id_tipo_sancion = (int)($_POST['id_tipo_sancion'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
    $motivo = trim($_POST['motivo'] ?? '');
    $horas_descontadas = (float)($_POST['horas_descontadas'] ?? 0);
    $minutos = (int)($_POST['minutos'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $currentUser = Auth::user();

    if ($id_proceso <= 0 || $id_tipo_sancion <= 0 || $horas_descontadas < 0) {
        $error = 'Por favor complete todos los datos requeridos e ingrese un valor de descuento válido.';
    } else {
        try {
            $stmtTipo = $db->prepare("SELECT nombre FROM tipos_sancion WHERE id_tipo_sancion = :id");
            $stmtTipo->execute([':id' => $id_tipo_sancion]);
            $tipoNombre = $stmtTipo->fetchColumn();

            $material = null;
            $sorteo = null;
            $reincidencia = null;
            $fecha_entrega = null;

            if ($tipoNombre === 'ATRASO') {
                if ($minutos < 1) {
                    throw new Exception('Indique los minutos de atraso.');
                }
                $stmtReinc = $db->prepare("SELECT COUNT(*) FROM sanciones s
                                           INNER JOIN tipos_sancion t ON s.id_tipo_sancion = t.id_tipo_sancion
                                           WHERE s.id_proceso = :id AND t.nombre = 'ATRASO'");
                $stmtReinc->execute([':id' => $id_proceso]);
                $reincidencia = (int)$stmtReinc->fetchColumn() + 1;
                $regla = reglaAtraso($minutos, $reincidencia);
                $material = $regla['material'];
                $sorteo = $regla['sorteo'];
                $fecha_entrega = fechaEntregaSancion(new DateTime($fecha), $regla['exceso']);
            } elseif ($tipoNombre === 'FALTA') {
                $material = 'Impresión/empaste de folio digital (no repone horas; debe completar su tiempo).';
                $horas_descontadas = 0;
                $fecha_entrega = fechaEntregaSancion(new DateTime($fecha), false);
            }

            $stmt = $db->prepare("INSERT INTO sanciones (id_proceso, id_tipo_sancion, registrado_por, fecha, motivo, descripcion, horas_descontadas, minutos, reincidencia, material, sorteo, fecha_entrega, estado)
                                  VALUES (:id_proceso, :id_tipo_sancion, :reg_por, :fecha, :motivo, :descripcion, :horas, :min, :reinc, :mat, :sort, :ent, 'ACTIVA')");
            $stmt->execute([
                ':id_proceso'       => $id_proceso,
                ':id_tipo_sancion'  => $id_tipo_sancion,
                ':reg_por'          => $currentUser['id'],
                ':fecha'            => $fecha,
                ':motivo'           => $motivo,
                ':descripcion'       => $descripcion ?: null,
                ':horas'            => $horas_descontadas,
                ':min'              => $minutos > 0 ? $minutos : null,
                ':reinc'            => $reincidencia,
                ':mat'              => $material,
                ':sort'             => $sorteo,
                ':ent'              => $fecha_entrega
            ]);

            Auth::logAudit('REGISTRAR_SANCION', 'sanciones', $db->lastInsertId(), "Sanción $tipoNombre: Proceso $id_proceso" . ($material ? ", Material: $material" : ''));
            $success = 'Sanción registrada correctamente.' . ($material ? " Material: $material" : '') . ($fecha_entrega ? " Entregar hasta el $fecha_entrega." : '');
        } catch (Exception $e) {
            $error = 'Error al registrar sanción: ' . $e->getMessage();
        }
    }
    }
}

// Listar sanciones (tabs activas / cumplidas; no-staff solo ve las propias)
$filtroS = ($_GET['f'] ?? 'activas') === 'cumplidas' ? 'cumplidas' : 'activas';
$sqlSan = "SELECT s.*, ts.nombre AS tipo_sancion, p.nombres, p.apellidos, p.ci, u.usuario AS registrado_por_usuario, m.nombre AS modalidad
                         FROM sanciones s
                         INNER JOIN tipos_sancion ts ON s.id_tipo_sancion = ts.id_tipo_sancion
                         INNER JOIN procesos pr ON s.id_proceso = pr.id_proceso
                         LEFT JOIN pasantes p ON pr.id_pasante = p.id_pasante
                         LEFT JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                         LEFT JOIN usuarios u ON s.registrado_por = u.id_usuario
                         WHERE s.estado = :est ";
$paramsSan = [':est' => $filtroS === 'cumplidas' ? 'CUMPLIDA' : 'ACTIVA'];
if (!$esStaff) {
    $sqlSan .= " AND EXISTS (SELECT 1 FROM pasantes px WHERE px.id_pasante = pr.id_pasante AND px.ci = :ci) ";
    $paramsSan[':ci'] = trim(Auth::user()['ci'] ?? '');
}
$sqlSan .= " ORDER BY s.fecha DESC, s.id_sancion DESC";
$stmtSan = $db->prepare($sqlSan);
$stmtSan->execute($paramsSan);
$sanciones = $stmtSan->fetchAll();

// Procesos en curso para el selector (puros, TD y PG, vinculados o no)
$procesos = $db->query("SELECT pr.id_proceso, pr.horas_requeridas, p.nombres, p.apellidos, p.ci, COALESCE(m.nombre, 'Pasantía') AS modalidad
                        FROM procesos pr
                        LEFT JOIN pasantes p ON pr.id_pasante = p.id_pasante
                        LEFT JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
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

        <div style="margin-bottom: 20px;">
            <?php if ($esStaff): ?>
            <button type="button" class="btn btn-danger" onclick="document.getElementById('modalSancion').style.display='flex'">
                <i class="fa-solid fa-gavel"></i> Registrar Sanción
            </button>
            <?php endif; ?>
        </div>

        <?php if ($esStaff): ?>
        <!-- Modal Registrar Sanción -->
        <div id="modalSancion" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); z-index: 1000; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 40px 16px;" onclick="if (event.target === this) this.style.display='none'">
            <div class="card" style="max-width: 560px; width: 100%; margin: 0;">
                <div class="card-header-flex">
                    <h3 class="card-title">
                        <i class="fa-solid fa-triangle-exclamation" style="color: var(--primary-red); margin-right: 6px;"></i>
                        Registrar Sanción / Descuento
                    </h3>
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('modalSancion').style.display='none'">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="registrar">

                    <div class="form-group">
                        <label for="id_proceso">Pasante / Proceso en Curso *</label>
                        <select name="id_proceso" id="id_proceso" class="form-control" required>
                            <option value="">-- Seleccionar Pasante --</option>
                            <?php foreach ($procesos as $proc): ?>
                                <option value="<?= $proc['id_proceso'] ?>">
                                    <?= !empty($proc['id_proceso']) && !empty($proc['ci']) ? htmlspecialchars(($proc['apellidos'] ?? '') . ' ' . ($proc['nombres'] ?? '') . ' (CI: ' . $proc['ci'] . ') - ') : '' ?><?= htmlspecialchars($proc['modalidad']) ?> #<?= $proc['id_proceso'] ?>
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
                        <label for="minutos">Minutos de atraso (solo ATRASO)</label>
                        <input type="number" min="1" max="120" name="minutos" id="minutos" class="form-control" value="" placeholder="1-10 cómputo, más es exceso">
                        <small style="color: var(--text-muted); font-size: 0.78rem;">El material y la reincidencia se calculan solos.</small>
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
        </div>
        <?php endif; ?>

        <!-- Listado Histórico de Sanciones -->
        <div class="card">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title"><?= $esStaff ? 'Registro de Sanciones Aplicadas' : 'Mis Sanciones' ?></h3>
                </div>
                <span class="badge badge-danger"><?= count($sanciones) ?> registros</span>
            </div>

                <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                    <a href="index.php?f=activas" class="btn btn-sm <?= $filtroS === 'activas' ? 'btn-primary' : 'btn-outline' ?>">
                        Activas
                    </a>
                    <a href="index.php?f=cumplidas" class="btn btn-sm <?= $filtroS === 'cumplidas' ? 'btn-primary' : 'btn-outline' ?>">
                        Cumplidas
                    </a>
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
                                <th>Estado</th>
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
                                            <?php if (!empty($s['ci'])): ?>
                                                <div style="font-weight: 700; color: var(--primary-blue);"><?= htmlspecialchars(($s['nombres'] ?? '') . ' ' . ($s['apellidos'] ?? '')) ?></div>
                                                <small style="color: var(--text-muted);">CI: <?= htmlspecialchars($s['ci']) ?></small>
                                            <?php else: ?>
                                                <div style="font-weight: 700; color: var(--primary-blue);"><?= htmlspecialchars($s['modalidad'] ?? 'Modalidad') ?> #<?= $s['id_proceso'] ?></div>
                                                <small style="color: var(--text-muted);">Registro independiente</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning"><?= htmlspecialchars($s['tipo_sancion']) ?></span>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($s['motivo']) ?></div>
                                            <?php if (!empty($s['descripcion'])): ?>
                                                <small style="color: var(--text-muted);"><?= htmlspecialchars($s['descripcion']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($s['material'])): ?>
                                                <div style="font-size: 0.8rem; margin-top: 4px;"><strong>Material:</strong> <?= htmlspecialchars($s['material']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($s['sorteo'])): ?>
                                                <div style="margin-top: 4px;"><span class="badge badge-warning">SORTEO <?= htmlspecialchars($s['sorteo']) ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($s['fecha_entrega'])): ?>
                                                <small style="color: var(--text-muted);">Entregar hasta el <?= htmlspecialchars(date('d/m/Y', strtotime($s['fecha_entrega']))) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color: #dc2626; font-size: 0.95rem;">-<?= $s['horas_descontadas'] ?> hrs</strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary"><?= htmlspecialchars($s['registrado_por_usuario'] ?? 'Sistema') ?></span>
                                        </td>
                                        <td>
                                            <?php if ($s['estado'] === 'ACTIVA'): ?>
                                                <span class="badge badge-danger">ACTIVA</span><br>
                                                <?php if ($esStaff): ?>
                                                <a href="index.php?cumplir=<?= $s['id_sancion'] ?>" class="btn btn-outline btn-sm" style="margin-top: 6px;" title="Marcar cumplida" onclick="return confirm('¿Marcar esta sanción como cumplida?');">
                                                    <i class="fa-solid fa-check"></i> Cumplida
                                                </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge badge-success">CUMPLIDA</span>
                                                <?php if (!empty($s['fecha_cumplimiento'])): ?>
                                                    <br><small style="color: var(--text-muted);"><?= htmlspecialchars(date('d/m/Y', strtotime($s['fecha_cumplimiento']))) ?></small>
                                                <?php endif; ?>
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
