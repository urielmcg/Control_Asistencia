<?php
/**
 * Solicitud de Modalidad de Titulación (Proyecto de Grado o Trabajo Dirigido)
 * Standalone record with letter data, optional access account.
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Solicitud de Modalidad';
$activeMenu = 'modalidades';
$db = Database::getConnection();

$error = '';
$preModId = (int)($_GET['id_modalidad'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_modalidad = (int)($_POST['id_modalidad'] ?? 0);
    $postulante = trim($_POST['postulante'] ?? '');
    $ci = trim($_POST['ci'] ?? '');
    $horas_requeridas = trim($_POST['horas_requeridas'] ?? '');
    $universidad = trim($_POST['universidad'] ?? '');
    $carrera = trim($_POST['carrera'] ?? '');
    $id_turno = (int)($_POST['id_turno'] ?? 0);
    $meses = (int)($_POST['meses'] ?? 0);
    $motivacion = trim($_POST['motivacion'] ?? '');
    $compromiso = trim($_POST['compromiso'] ?? '');
    $idea = trim($_POST['idea_tema'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $usuario_acceso = trim($_POST['usuario_acceso'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmtMod = $db->prepare("SELECT nombre FROM modalidades WHERE id_modalidad = :id AND estado = 1");
    $stmtMod->execute([':id' => $id_modalidad]);
    $modNombre = $stmtMod->fetchColumn();
    $esPG = ($modNombre === 'Proyecto de Grado');

    if ($id_modalidad <= 0 || !$modNombre) {
        $error = 'Seleccione la modalidad (Proyecto de Grado o Trabajo Dirigido).';
    } elseif ($postulante === '' || $universidad === '' || $carrera === '' || $id_turno <= 0
        || $meses <= 0 || $motivacion === '' || $compromiso === '') {
        $error = 'Por favor complete todos los campos obligatorios (*).';
    } elseif (!$esPG && ($horas_requeridas === '' || (int)$horas_requeridas <= 0)) {
        $error = 'Indique las horas requeridas según facultad (ej. 280, 300, 400, 800, 1000).';
    } elseif (($usuario_acceso !== '' || $password !== '' || $ci !== '') && ($usuario_acceso === '' || strlen($password) < 8 || $ci === '')) {
        $error = 'Para crear el acceso complete usuario, contraseña (mínimo 8) y CI.';
    } else {
        try {
            $db->beginTransaction();

            $stmtTurno = $db->prepare("SELECT id_turno FROM turnos WHERE id_turno = :id AND estado = 1");
            $stmtTurno->execute([':id' => $id_turno]);
            if (!$stmtTurno->fetch()) {
                throw new Exception('Turno de acompañamiento inválido.');
            }

            // Optional access account for the applicant
            $id_usuario = null;
            if ($usuario_acceso !== '') {
                $stmtU = $db->prepare("SELECT id_usuario FROM usuarios WHERE usuario = :u OR ci = :ci");
                $stmtU->execute([':u' => $usuario_acceso, ':ci' => $ci]);
                if ($stmtU->fetch()) {
                    throw new Exception('El usuario de acceso o el CI ya existen.');
                }
                $stmtAcc = $db->prepare("INSERT INTO usuarios (id_rol, usuario, password, nombres, apellidos, ci, correo, estado)
                                         VALUES (3, :u, :p, :n, '', :ci, NULL, 1)");
                $stmtAcc->execute([
                    ':u'  => $usuario_acceso,
                    ':p'  => password_hash($password, PASSWORD_BCRYPT),
                    ':n'  => $postulante,
                    ':ci' => $ci,
                ]);
                $id_usuario = (int)$db->lastInsertId();
            }

            $stmt = $db->prepare("INSERT INTO procesos (id_pasante, id_institucion, id_modalidad, id_tutor, id_turno, fecha_inicio, fecha_fin, horas_requeridas, estado, observacion)
                                  VALUES (NULL, 4, :id_modalidad, NULL, :id_turno, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :meses MONTH), :horas, 'EN_CURSO', :observacion)");
            $stmt->execute([
                ':id_modalidad' => $id_modalidad,
                ':id_turno'     => $id_turno,
                ':meses'        => $meses,
                ':horas'        => $esPG ? null : (int)$horas_requeridas,
                ':observacion'  => $descripcion !== '' ? $descripcion : null,
            ]);
            $id_proceso = (int)$db->lastInsertId();

            $stmtSol = $db->prepare("INSERT INTO solicitudes_modalidad (id_proceso, id_usuario, postulante, ci, universidad, carrera, meses_proyectados, motivacion, compromiso, idea_tema, descripcion)
                                     VALUES (:proc, :idu, :post, :ci, :uni, :car, :meses, :mot, :comp, :idea, :desc)");
            $stmtSol->execute([
                ':proc'  => $id_proceso,
                ':idu'   => $id_usuario,
                ':post'  => $postulante,
                ':ci'    => $ci !== '' ? $ci : null,
                ':uni'   => $universidad,
                ':car'   => $carrera,
                ':meses' => $meses,
                ':mot'   => $motivacion,
                ':comp'  => $compromiso,
                ':idea'  => $idea !== '' ? $idea : null,
                ':desc'  => $descripcion !== '' ? $descripcion : null,
            ]);

            Auth::logAudit('SOLICITUD_MODALIDAD', 'solicitudes_modalidad', (int)$db->lastInsertId(), "Solicitud $modNombre: $postulante");
            $db->commit();

            $_SESSION['flash_success'] = 'Solicitud de modalidad registrada exitosamente.';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al registrar solicitud: ' . $e->getMessage();
        }
    }
}

$modalidades = $db->query("SELECT * FROM modalidades WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$turnos = $db->query("SELECT * FROM turnos WHERE estado = 1 ORDER BY hora_inicio ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <p style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Solicitud de modalidades</p>
        <div class="card" style="max-width: 960px; margin: 0 auto;">
            <div style="background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 8px; padding: 12px 16px; font-size: 0.85rem; color: #1e40af; margin-bottom: 20px;">
                Completa por teclado los datos que deben aparecer en la carta solicitada por la institución.
                El tipo solo puede ser Proyecto de Grado o Trabajo Dirigido. El tutor corresponde al
                acompañamiento de lunes a viernes con Tutor Interno y apoyo en Servicios y Actualización.
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="asignar.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_modalidad">Modalidad elegida *</label>
                        <select name="id_modalidad" id="id_modalidad" class="form-control" required onchange="toggleHoras(this)">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($modalidades as $m): ?>
                                <option value="<?= $m['id_modalidad'] ?>" data-nombre="<?= htmlspecialchars($m['nombre']) ?>" <?= ($preModId === (int)$m['id_modalidad']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="postulante">Nombre completo del postulante *</label>
                        <input type="text" name="postulante" id="postulante" class="form-control" required value="<?= htmlspecialchars($_POST['postulante'] ?? '') ?>">
                    </div>
                    <div class="form-group" id="grupoHoras">
                        <label for="horas_requeridas">Horas requeridas *</label>
                        <input type="number" name="horas_requeridas" id="horas_requeridas" class="form-control" min="10" step="10" list="horas_facultad" placeholder="280, 300, 400, 800, 1000" value="<?= htmlspecialchars($_POST['horas_requeridas'] ?? '') ?>">
                        <datalist id="horas_facultad">
                            <option value="280"></option>
                            <option value="300"></option>
                            <option value="400"></option>
                            <option value="800"></option>
                            <option value="1000"></option>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label for="ci">Documento / CI *</label>
                        <input type="text" name="ci" id="ci" class="form-control" required value="<?= htmlspecialchars($_POST['ci'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="universidad">Universidad o instituto *</label>
                        <input type="text" name="universidad" id="universidad" class="form-control" required value="<?= htmlspecialchars($_POST['universidad'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="carrera">Carrera *</label>
                        <input type="text" name="carrera" id="carrera" class="form-control" required value="<?= htmlspecialchars($_POST['carrera'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="id_turno">Turno de acompañamiento *</label>
                        <select name="id_turno" id="id_turno" class="form-control" required>
                            <option value="0">Seleccionar turno</option>
                            <?php foreach ($turnos as $t): ?>
                                <option value="<?= $t['id_turno'] ?>">
                                    <?= htmlspecialchars($t['nombre']) ?> · <?= htmlspecialchars(substr($t['hora_inicio'], 0, 5)) ?>–<?= htmlspecialchars(substr($t['hora_fin'], 0, 5)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="meses">Tiempo de presencia proyectado (meses) *</label>
                        <input type="number" name="meses" id="meses" class="form-control" required min="1" max="24" value="<?= htmlspecialchars($_POST['meses'] ?? '6') ?>">
                        <small style="color: var(--text-muted);">Calcula el tiempo previsto para concluir la modalidad según el periodo de titulación.</small>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label for="motivacion">Motivación personal *</label>
                    <textarea name="motivacion" id="motivacion" rows="3" class="form-control" required><?= htmlspecialchars($_POST['motivacion'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="compromiso">Compromiso que asume *</label>
                    <textarea name="compromiso" id="compromiso" rows="3" class="form-control" required><?= htmlspecialchars($_POST['compromiso'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="idea_tema">Idea de tema tentativa (opcional)</label>
                    <input type="text" name="idea_tema" id="idea_tema" class="form-control" value="<?= htmlspecialchars($_POST['idea_tema'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción / observaciones adicionales</label>
                    <textarea name="descripcion" id="descripcion" rows="3" class="form-control"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                </div>

                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 20px; margin-bottom: 16px;">
                    Acceso al sistema (opcional)
                </h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="usuario_acceso">Usuario de acceso</label>
                        <input type="text" name="usuario_acceso" id="usuario_acceso" class="form-control" value="<?= htmlspecialchars($_POST['usuario_acceso'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" minlength="8">
                        <small style="color: var(--text-muted);">Mínimo 8 caracteres. Usa el mismo CI de arriba.</small>
                    </div>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="index.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-check"></i> Registrar Solicitud
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
