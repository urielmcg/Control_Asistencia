<?php
/**
 * Administración de Turnos (horarios asignables a procesos)
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Turnos';
$activeMenu = 'modalidades';
$db = Database::getConnection();

$error = '';
$editando = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $inicio = trim($_POST['hora_inicio'] ?? '');
    $fin = trim($_POST['hora_fin'] ?? '');

    if ($nombre === '' || $inicio === '' || $fin === '') {
        $error = 'Complete nombre, hora de inicio y hora de fin.';
    } elseif ($fin <= $inicio) {
        $error = 'La hora de fin debe ser posterior a la de inicio.';
    } else {
        try {
            if ($action === 'editar') {
                $id = (int)($_POST['id_turno'] ?? 0);
                $stmt = $db->prepare("UPDATE turnos SET nombre = :n, hora_inicio = :i, hora_fin = :f WHERE id_turno = :id");
                $stmt->execute([':n' => $nombre, ':i' => $inicio, ':f' => $fin, ':id' => $id]);
                Auth::logAudit('EDITAR_TURNO', 'turnos', $id, "Turno actualizado: $nombre ($inicio-$fin)");
            } else {
                $stmt = $db->prepare("INSERT INTO turnos (nombre, hora_inicio, hora_fin, estado) VALUES (:n, :i, :f, 1)");
                $stmt->execute([':n' => $nombre, ':i' => $inicio, ':f' => $fin]);
                Auth::logAudit('CREAR_TURNO', 'turnos', (int)$db->lastInsertId(), "Turno creado: $nombre ($inicio-$fin)");
            }
            $_SESSION['flash_success'] = 'Turno guardado exitosamente.';
            header('Location: turnos.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar turno: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $db->prepare("UPDATE turnos SET estado = 1 - estado WHERE id_turno = :id")->execute([':id' => $id]);
    header('Location: turnos.php');
    exit;
}

if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM turnos WHERE id_turno = :id");
    $stmt->execute([':id' => (int)$_GET['editar']]);
    $editando = $stmt->fetch() ?: null;
}

$turnos = $db->query("SELECT t.*,
                             (SELECT COUNT(*) FROM procesos pr WHERE pr.id_turno = t.id_turno AND pr.estado = 'EN_CURSO') AS en_curso
                      FROM turnos t ORDER BY t.hora_inicio ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card" style="max-width: 900px; margin: 0 auto 24px auto;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title"><?= $editando ? 'Editar Turno' : 'Nuevo Turno' ?></h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Los turnos se asignan a los procesos desde Asignar Modalidad.
                    </p>
                </div>
                <a href="index.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> Volver a Modalidades
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success"><span><?= htmlspecialchars($_SESSION['flash_success']) ?></span></div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <form action="turnos.php" method="POST">
                <input type="hidden" name="action" value="<?= $editando ? 'editar' : 'crear' ?>">
                <?php if ($editando): ?>
                    <input type="hidden" name="id_turno" value="<?= $editando['id_turno'] ?>">
                <?php endif; ?>
                <div class="form-grid" style="grid-template-columns: 2fr 1fr 1fr auto; align-items: end;">
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Mañana, Tarde, Noche..." required value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="hora_inicio">Inicio *</label>
                        <input type="time" id="hora_inicio" name="hora_inicio" class="form-control" required value="<?= htmlspecialchars(isset($editando) && $editando ? substr($editando['hora_inicio'], 0, 5) : '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="hora_fin">Fin *</label>
                        <input type="time" id="hora_fin" name="hora_fin" class="form-control" required value="<?= htmlspecialchars(isset($editando) && $editando ? substr($editando['hora_fin'], 0, 5) : '') ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-danger"><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
                        <?php if ($editando): ?>
                            <a href="turnos.php" class="btn btn-outline">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="card" style="max-width: 900px; margin: 0 auto;">
            <h3 class="card-title" style="margin-bottom: 16px;">Turnos definidos</h3>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Horario</th>
                            <th>Procesos en curso</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turnos as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars(substr($t['hora_inicio'], 0, 5)) ?> – <?= htmlspecialchars(substr($t['hora_fin'], 0, 5)) ?></td>
                                <td><?= (int)$t['en_curso'] ?></td>
                                <td>
                                    <?= ((int)$t['estado'] === 1) ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-info">Inactivo</span>' ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="turnos.php?editar=<?= $t['id_turno'] ?>" class="btn btn-outline btn-sm" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="turnos.php?toggle=<?= $t['id_turno'] ?>" class="btn btn-outline btn-sm" title="Activar/Desactivar" onclick="return confirm('¿Cambiar el estado de este turno?');">
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
