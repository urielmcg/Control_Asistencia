<?php
/**
 * Registrar Tutor y asignarlo a postulantes de Trabajo Dirigido
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();

$pageTitle = 'Registrar Tutor';
$activeMenu = 'tutores';
$db = Database::getConnection();

$error = '';

// Postulantes: active Trabajo Dirigido processes with their current tutor (if any)
$stmtPost = $db->query("SELECT pr.id_proceso, p.nombres, p.apellidos, p.ci,
                               i.nombre AS institucion, c.nombre AS carrera,
                               t.nombre AS tutor_actual
                        FROM procesos pr
                        INNER JOIN pasantes p ON pr.id_pasante = p.id_pasante
                        INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                        LEFT JOIN instituciones i ON pr.id_institucion = i.id_institucion
                        LEFT JOIN carreras c ON p.id_carrera = c.id_carrera
                        LEFT JOIN tutores t ON pr.id_tutor = t.id_tutor
                        WHERE pr.estado = 'EN_CURSO' AND m.nombre = 'Trabajo Dirigido'
                        ORDER BY p.apellidos, p.nombres");
$postulantes = $stmtPost->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $seleccionados = $_POST['postulantes'] ?? [];

    if ($nombre === '' || $cargo === '' || $correo === '' || $telefono === '') {
        $error = 'Por favor complete todos los campos obligatorios (*).';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no tiene un formato válido.';
    } elseif (!is_array($seleccionados) || count($seleccionados) === 0) {
        $error = 'Seleccione al menos un postulante de Trabajo Dirigido.';
    } else {
        try {
            $db->beginTransaction();

            $stmtIns = $db->prepare("INSERT INTO tutores (nombre, cargo, correo, telefono, estado)
                                     VALUES (:nombre, :cargo, :correo, :telefono, 'ACTIVO')");
            $stmtIns->execute([
                ':nombre'   => $nombre,
                ':cargo'    => $cargo,
                ':correo'   => $correo,
                ':telefono' => $telefono,
            ]);
            $id_tutor = (int)$db->lastInsertId();

            // Each application keeps a single tutor; assigning here reassigns if needed
            $stmtChk = $db->prepare("SELECT id_proceso FROM procesos pr
                                     INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                                     WHERE pr.id_proceso = :id AND pr.estado = 'EN_CURSO'
                                       AND m.nombre = 'Trabajo Dirigido' LIMIT 1");
            $stmtUpd = $db->prepare("UPDATE procesos SET id_tutor = :tutor WHERE id_proceso = :id");
            $asignados = 0;
            foreach ($seleccionados as $idProc) {
                $stmtChk->execute([':id' => (int)$idProc]);
                if ($stmtChk->fetch()) {
                    $stmtUpd->execute([':tutor' => $id_tutor, ':id' => (int)$idProc]);
                    $asignados++;
                }
            }

            if ($asignados === 0) {
                throw new Exception('Ninguna solicitud seleccionada es válida.');
            }

            Auth::logAudit('CREAR_TUTOR', 'tutores', $id_tutor, "Tutor registrado: $nombre ($asignados postulantes)");
            $db->commit();

            $_SESSION['flash_success'] = "Tutor registrado con $asignados postulante(s).";
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al registrar tutor: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <p style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Tutores</p>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--primary-blue); margin: 0 0 20px 0;">Registrar tutor</h2>

        <div class="card" style="max-width: 960px; margin: 0 auto;">
            <div style="background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 8px; padding: 12px 16px; font-size: 0.85rem; color: #1e40af; margin-bottom: 20px;">
                Los tutores se asignan exclusivamente a solicitudes activas de Trabajo Dirigido. Un tutor puede acompañar a varios postulantes; cada solicitud solo puede tener un tutor.
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <form action="nuevo.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre completo del tutor / responsable *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="cargo">Cargo o función *</label>
                        <input type="text" id="cargo" name="cargo" class="form-control" placeholder="Director, Tutor Institucional..." required value="<?= htmlspecialchars($_POST['cargo'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="correo">Correo electrónico institucional o de contacto *</label>
                        <input type="email" id="correo" name="correo" class="form-control" required value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Número de teléfono / celular *</label>
                        <input type="text" id="telefono" name="telefono" class="form-control" required value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                    </div>
                </div>

                <fieldset style="border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; margin-top: 20px;">
                    <legend style="font-size: 0.9rem; font-weight: 700; color: var(--primary-blue); padding: 0 8px;">Postulantes de Trabajo Dirigido a quienes tutorea *</legend>
                    <?php if (empty($postulantes)): ?>
                        <p style="color: var(--text-muted); font-size: 0.88rem;">No hay solicitudes activas de Trabajo Dirigido en este momento.</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px;">
                            <?php foreach ($postulantes as $pos): ?>
                                <label style="display: flex; gap: 10px; align-items: flex-start; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; cursor: pointer; font-size: 0.85rem;">
                                    <input type="checkbox" name="postulantes[]" value="<?= $pos['id_proceso'] ?>" style="margin-top: 3px;"
                                        <?= (!empty($_POST['postulantes']) && in_array($pos['id_proceso'], (array)$_POST['postulantes'])) ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= htmlspecialchars($pos['nombres'] . ' ' . $pos['apellidos']) ?></strong><br>
                                        <span style="color: var(--text-muted); font-size: 0.78rem;">
                                            <?= htmlspecialchars(strtolower(($pos['institucion'] ?? '') . ' - ' . ($pos['carrera'] ?? ''))) ?>
                                            <?php if (!empty($pos['tutor_actual'])): ?>
                                                &bull; Ya asignada a <?= htmlspecialchars(strtolower($pos['tutor_actual'])) ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </fieldset>

                <div style="margin-top: 24px; display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar tutor
                    </button>
                    <a href="index.php" class="btn btn-outline">Cancelar</a>
                </div>
            </form>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
