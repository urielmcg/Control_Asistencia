<?php
/**
 * Editar Solicitud de Modalidad de Titulación
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Editar Solicitud';
$activeMenu = 'modalidades';
$db = Database::getConnection();

$id_proceso = (int)($_GET['id'] ?? 0);
if ($id_proceso <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT pr.*, s.* FROM procesos pr
                      LEFT JOIN solicitudes_modalidad s ON s.id_proceso = pr.id_proceso
                      WHERE pr.id_proceso = :id LIMIT 1");
$stmt->execute([':id' => $id_proceso]);
$sol = $stmt->fetch();

if (!$sol) {
    $_SESSION['flash_error'] = 'La solicitud no existe.';
    header('Location: index.php');
    exit;
}

// Procesos linked to a pasante may have no solicitud row yet:
// prefill letter data from the pasante so the form is never empty
if (empty($sol['id_solicitud']) && !empty($sol['id_pasante'])) {
    $stmtPre = $db->prepare("SELECT p.nombres, p.apellidos, i.nombre AS institucion, c.nombre AS carrera
                             FROM pasantes p
                             LEFT JOIN instituciones i ON p.id_universidad = i.id_institucion
                             LEFT JOIN carreras c ON p.id_carrera = c.id_carrera
                             WHERE p.id_pasante = :id LIMIT 1");
    $stmtPre->execute([':id' => $sol['id_pasante']]);
    if ($pre = $stmtPre->fetch()) {
        $sol['postulante'] = trim(($pre['nombres'] ?? '') . ' ' . ($pre['apellidos'] ?? ''));
        $sol['universidad'] = $pre['institucion'] ?? '';
        $sol['carrera'] = $pre['carrera'] ?? '';
    }
    if (!empty($sol['fecha_inicio']) && !empty($sol['fecha_fin'])) {
        $dd1 = new DateTime($sol['fecha_inicio']);
        $dd2 = new DateTime($sol['fecha_fin']);
        if ($dd2 > $dd1) {
            $ddiff = $dd1->diff($dd2);
            $sol['meses_proyectados'] = max(1, $ddiff->y * 12 + $ddiff->m);
        }
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_modalidad = (int)($_POST['id_modalidad'] ?? 0);
    $horas_requeridas = trim($_POST['horas_requeridas'] ?? '');
    $id_turno = (int)($_POST['id_turno'] ?? 0);
    $id_tutor = (int)($_POST['id_tutor'] ?? 0);
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $meses = (int)($_POST['meses'] ?? 0);
    $estado = trim($_POST['estado'] ?? 'EN_CURSO');
    $postulante = trim($_POST['postulante'] ?? '');
    $universidad = trim($_POST['universidad'] ?? '');
    $carrera = trim($_POST['carrera'] ?? '');
    $motivacion = trim($_POST['motivacion'] ?? '');
    $compromiso = trim($_POST['compromiso'] ?? '');
    $idea = trim($_POST['idea_tema'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    $stmtMod = $db->prepare("SELECT nombre FROM modalidades WHERE id_modalidad = :id AND estado = 1");
    $stmtMod->execute([':id' => $id_modalidad]);
    $modNombre = $stmtMod->fetchColumn();
    $esPG = ($modNombre === 'Proyecto de Grado');

    if ($id_modalidad <= 0 || !$modNombre) {
        $error = 'Seleccione una modalidad válida.';
    } elseif ($postulante === '' || $universidad === '' || $carrera === '' || $fecha_inicio === '' || $meses <= 0
        || $motivacion === '' || $compromiso === '') {
        $error = 'Por favor complete todos los campos obligatorios (*).';
    } elseif (!$esPG && ($horas_requeridas === '' || (int)$horas_requeridas <= 0)) {
        $error = 'Indique las horas requeridas según facultad.';
    } elseif (!in_array($estado, ['EN_CURSO', 'FINALIZADO', 'CANCELADO'], true)) {
        $error = 'Estado inválido.';
    } else {
        try {
            $db->beginTransaction();
            $stmtUpd = $db->prepare("UPDATE procesos SET id_modalidad = :mod, id_tutor = :tutor, id_turno = :turno,
                                     fecha_inicio = :ini, fecha_fin = DATE_ADD(:ini2, INTERVAL :meses MONTH),
                                     horas_requeridas = :horas, estado = :estado, observacion = :obs
                                     WHERE id_proceso = :id");
            $stmtUpd->execute([
                ':mod'    => $id_modalidad,
                ':tutor'  => $id_tutor > 0 ? $id_tutor : null,
                ':turno'  => $id_turno > 0 ? $id_turno : null,
                ':ini'    => $fecha_inicio,
                ':ini2'   => $fecha_inicio,
                ':meses'  => $meses,
                ':horas'  => $esPG ? null : (int)$horas_requeridas,
                ':estado' => $estado,
                ':obs'    => $descripcion !== '' ? $descripcion : null,
                ':id'     => $id_proceso,
            ]);
            if (!empty($sol['id_solicitud'])) {
                $stmtS = $db->prepare("UPDATE solicitudes_modalidad SET postulante = :post, universidad = :uni, carrera = :car,
                                       meses_proyectados = :meses, motivacion = :mot, compromiso = :comp,
                                       idea_tema = :idea, descripcion = :desc WHERE id_proceso = :id");
                $stmtS->execute([
                    ':post'  => $postulante,
                    ':uni'   => $universidad,
                    ':car'   => $carrera,
                    ':meses' => $meses,
                    ':mot'   => $motivacion,
                    ':comp'  => $compromiso,
                    ':idea'  => $idea !== '' ? $idea : null,
                    ':desc'  => $descripcion !== '' ? $descripcion : null,
                    ':id'    => $id_proceso,
                ]);
            } else {
                $stmtS = $db->prepare("INSERT INTO solicitudes_modalidad (id_proceso, postulante, universidad, carrera, meses_proyectados, motivacion, compromiso, idea_tema, descripcion)
                                       VALUES (:id, :post, :uni, :car, :meses, :mot, :comp, :idea, :desc)");
                $stmtS->execute([
                    ':id'    => $id_proceso,
                    ':post'  => $postulante,
                    ':uni'   => $universidad,
                    ':car'   => $carrera,
                    ':meses' => $meses,
                    ':mot'   => $motivacion,
                    ':comp'  => $compromiso,
                    ':idea'  => $idea !== '' ? $idea : null,
                    ':desc'  => $descripcion !== '' ? $descripcion : null,
                ]);
            }
            Auth::logAudit('EDITAR_SOLICITUD', 'procesos', $id_proceso, "Solicitud actualizada: $modNombre - $postulante");
            $db->commit();
            $_SESSION['flash_success'] = 'Solicitud actualizada correctamente.';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al actualizar: ' . $e->getMessage();
        }
    }
    // Refresh displayed data after failed save
    $stmt->execute([':id' => $id_proceso]);
    $sol = $stmt->fetch();
}

$modalidades = $db->query("SELECT * FROM modalidades WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$turnos = $db->query("SELECT * FROM turnos WHERE estado = 1 ORDER BY hora_inicio ASC")->fetchAll();
$tutores = $db->query("SELECT id_tutor, nombre FROM tutores WHERE estado = 'ACTIVO' ORDER BY nombre ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card" style="max-width: 960px; margin: 0 auto;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Editar Solicitud de Modalidad</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        <?= htmlspecialchars($sol['postulante'] ?? 'Registro independiente') ?>
                    </p>
                </div>
                <a href="index.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> Volver a Modalidades
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <form action="editar_solicitud.php?id=<?= $id_proceso ?>" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_modalidad">Modalidad elegida *</label>
                        <select name="id_modalidad" id="id_modalidad" class="form-control" required onchange="toggleHoras(this)">
                            <?php foreach ($modalidades as $m): ?>
                                <option value="<?= $m['id_modalidad'] ?>" data-nombre="<?= htmlspecialchars($m['nombre']) ?>" <?= ((int)$sol['id_modalidad'] === (int)$m['id_modalidad']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="postulante">Nombre completo del postulante *</label>
                        <input type="text" name="postulante" id="postulante" class="form-control" required value="<?= htmlspecialchars($sol['postulante'] ?? '') ?>">
                    </div>
                    <div class="form-group" id="grupoHoras">
                        <label for="horas_requeridas">Horas requeridas *</label>
                        <input type="number" name="horas_requeridas" id="horas_requeridas" class="form-control" min="10" step="10" value="<?= htmlspecialchars($sol['horas_requeridas'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="universidad">Universidad o instituto *</label>
                        <input type="text" name="universidad" id="universidad" class="form-control" required value="<?= htmlspecialchars($sol['universidad'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="carrera">Carrera *</label>
                        <input type="text" name="carrera" id="carrera" class="form-control" required value="<?= htmlspecialchars($sol['carrera'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="id_turno">Turno de acompañamiento *</label>
                        <select name="id_turno" id="id_turno" class="form-control" required>
                            <option value="0">Seleccionar turno</option>
                            <?php foreach ($turnos as $t): ?>
                                <option value="<?= $t['id_turno'] ?>" <?= ((int)($sol['id_turno'] ?? 0) === (int)$t['id_turno']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nombre']) ?> · <?= htmlspecialchars(substr($t['hora_inicio'], 0, 5)) ?>–<?= htmlspecialchars(substr($t['hora_fin'], 0, 5)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_tutor">Tutor</label>
                        <select name="id_tutor" id="id_tutor" class="form-control">
                            <option value="0">-- Sin tutor --</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id_tutor'] ?>" <?= ((int)($sol['id_tutor'] ?? 0) === (int)$t['id_tutor']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de inicio *</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" required value="<?= htmlspecialchars($sol['fecha_inicio'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="meses">Tiempo proyectado (meses) *</label>
                        <input type="number" name="meses" id="meses" class="form-control" required min="1" max="24" value="<?= htmlspecialchars($sol['meses_proyectados'] ?? '6') ?>">
                    </div>
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select name="estado" id="estado" class="form-control">
                            <?php foreach (['EN_CURSO', 'FINALIZADO', 'CANCELADO'] as $e): ?>
                                <option value="<?= $e ?>" <?= ($sol['estado'] === $e) ? 'selected' : '' ?>><?= $e ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label for="motivacion">Motivación personal *</label>
                    <textarea name="motivacion" id="motivacion" rows="3" class="form-control" required><?= htmlspecialchars($sol['motivacion'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="compromiso">Compromiso que asume *</label>
                    <textarea name="compromiso" id="compromiso" rows="3" class="form-control" required><?= htmlspecialchars($sol['compromiso'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="idea_tema">Idea de tema tentativa (opcional)</label>
                    <input type="text" name="idea_tema" id="idea_tema" class="form-control" value="<?= htmlspecialchars($sol['idea_tema'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción / observaciones adicionales</label>
                    <textarea name="descripcion" id="descripcion" rows="3" class="form-control"><?= htmlspecialchars($sol['descripcion'] ?? $sol['observacion'] ?? '') ?></textarea>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="index.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>

<script>
function toggleHoras(sel) {
    const nombre = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].dataset.nombre : '';
    const grupo = document.getElementById('grupoHoras');
    const input = document.getElementById('horas_requeridas');
    const esPG = (nombre === 'Proyecto de Grado');
    grupo.style.opacity = esPG ? '0.5' : '1';
    input.required = !esPG;
    if (esPG) { input.value = ''; }
}
document.addEventListener('DOMContentLoaded', () => toggleHoras(document.getElementById('id_modalidad')));
</script>
