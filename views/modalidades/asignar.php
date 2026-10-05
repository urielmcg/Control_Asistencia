<?php
/**
 * Asignar Modalidad a Pasante creando un nuevo Proceso
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();

$pageTitle = 'Asignar Modalidad';
$activeMenu = 'modalidades';
$db = Database::getConnection();

$error = '';
$prePasanteId = (int)($_GET['id_pasante'] ?? 0);
$preModId = (int)($_GET['id_modalidad'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pasante = (int)($_POST['id_pasante'] ?? 0);
    $id_modalidad = (int)($_POST['id_modalidad'] ?? 0);
    $id_institucion = (int)($_POST['id_institucion'] ?? 4); // Default CCDB
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? date('Y-m-d'));
    $fecha_fin = !empty($_POST['fecha_fin']) ? trim($_POST['fecha_fin']) : null;
    $horas_requeridas = (int)($_POST['horas_requeridas'] ?? 1000);
    $observacion = trim($_POST['observacion'] ?? '');

    if ($id_pasante <= 0 || $id_modalidad <= 0 || $horas_requeridas <= 0) {
        $error = 'Por favor seleccione al pasante, la modalidad e ingrese las horas requeridas válidas.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO procesos (id_pasante, id_institucion, id_modalidad, fecha_inicio, fecha_fin, horas_requeridas, estado, observacion)
                                  VALUES (:id_pasante, :id_institucion, :id_modalidad, :fecha_inicio, :fecha_fin, :horas_requeridas, 'EN_CURSO', :observacion)");
            $stmt->execute([
                ':id_pasante'        => $id_pasante,
                ':id_institucion'    => $id_institucion,
                ':id_modalidad'      => $id_modalidad,
                ':fecha_inicio'      => $fecha_inicio,
                ':fecha_fin'         => $fecha_fin,
                ':horas_requeridas'  => $horas_requeridas,
                ':observacion'       => $observacion ?: null
            ]);

            $id_proceso = $db->lastInsertId();
            Auth::logAudit('ASIGNAR_MODALIDAD', 'procesos', $id_proceso, "Proceso asignado: Pasante $id_pasante, Modalidad $id_modalidad, Horas: $horas_requeridas");

            $_SESSION['flash_success'] = 'Modalidad asignada exitosamente al pasante.';
            header("Location: ../asistencias/progreso_horas.php");
            exit;
        } catch (Exception $e) {
            $error = 'Error al asignar modalidad: ' . $e->getMessage();
        }
    }
}

// Cargar listas
$pasantes = $db->query("SELECT id_pasante, nombres, apellidos, ci FROM pasantes WHERE estado = 'ACTIVO' ORDER BY apellidos ASC")->fetchAll();
$modalidades = $db->query("SELECT * FROM modalidades WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$instituciones = $db->query("SELECT id_institucion, nombre FROM instituciones WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Asignar Modalidad / Apertura de Proceso</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Vincula a un estudiante activo con su modalidad de titulación o pasantía y define la meta de horas.
                    </p>
                </div>
                <a href="index.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> Volver a Modalidades
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="asignar.php" method="POST">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="id_pasante">Seleccionar Pasante *</label>
                        <select name="id_pasante" id="id_pasante" class="form-control" required>
                            <option value="">-- Seleccionar Estudiante --</option>
                            <?php foreach ($pasantes as $p): ?>
                                <option value="<?= $p['id_pasante'] ?>" <?= ($prePasanteId === (int)$p['id_pasante']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['apellidos'] . ' ' . $p['nombres']) ?> (CI: <?= htmlspecialchars($p['ci']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_modalidad">Modalidad de Titulación / Práctica *</label>
                        <select name="id_modalidad" id="id_modalidad" class="form-control" required>
                            <option value="">-- Seleccionar Modalidad --</option>
                            <?php foreach ($modalidades as $m): ?>
                                <option value="<?= $m['id_modalidad'] ?>" <?= ($preModId === (int)$m['id_modalidad']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?> (Base <?= $m['horas_requeridas_base'] ?> hrs)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="horas_requeridas">Horas Requeridas *</label>
                        <input type="number" name="horas_requeridas" id="horas_requeridas" class="form-control" value="1000" min="10" step="10" required>
                    </div>

                    <div class="form-group">
                        <label for="id_institucion">Institución Receptora *</label>
                        <select name="id_institucion" id="id_institucion" class="form-control" required>
                            <?php foreach ($instituciones as $inst): ?>
                                <option value="<?= $inst['id_institucion'] ?>" <?= ($inst['id_institucion'] == 4) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de Inicio *</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin">Fecha de Fin Estimada</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="observacion">Observaciones o Términos del Convenio</label>
                        <textarea name="observacion" id="observacion" rows="3" class="form-control" placeholder="Detalles adicionales, proyecto asignado, tutor guía..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="index.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-check"></i> Asignar Proceso
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
