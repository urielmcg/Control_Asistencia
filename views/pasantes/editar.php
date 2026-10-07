<?php
/**
 * Edición de Pasante
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Editar Pasante';
$activeMenu = 'pasantes';
$db = Database::getConnection();

$id_pasante = (int)($_GET['id'] ?? 0);
if ($id_pasante <= 0) {
    header("Location: index.php");
    exit;
}

// Obtener datos del pasante
$stmt = $db->prepare("SELECT * FROM pasantes WHERE id_pasante = :id");
$stmt->execute([':id' => $id_pasante]);
$pasante = $stmt->fetch();

if (!$pasante) {
    $_SESSION['flash_error'] = 'El pasante solicitado no existe.';
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// Cargar Universidades e Instituciones
$instituciones = $db->query("SELECT id_institucion, nombre FROM instituciones WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ci = trim($_POST['ci'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $id_universidad = (int)($_POST['id_universidad'] ?? 0);
    $id_carrera = (int)($_POST['id_carrera'] ?? 0);
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $semestre = trim($_POST['semestre'] ?? '');
    $estado = trim($_POST['estado'] ?? 'ACTIVO');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    if (empty($ci) || empty($nombres) || $id_universidad <= 0 || $id_carrera <= 0) {
        $error = 'Por favor complete todos los campos obligatorios (*).';
    } else {
        // Verificar CI duplicado en otro ID
        $stmtCheck = $db->prepare("SELECT id_pasante FROM pasantes WHERE ci = :ci AND id_pasante != :id");
        $stmtCheck->execute([':ci' => $ci, ':id' => $id_pasante]);
        if ($stmtCheck->fetch()) {
            $error = "El número de CI $ci ya pertenece a otro pasante.";
        } else {
            try {
                $db->beginTransaction();
                $stmtUpdate = $db->prepare("UPDATE pasantes SET
                                                ci = :ci,
                                                nombres = :nombres,
                                                apellidos = :apellidos,
                                                id_universidad = :id_universidad,
                                                id_carrera = :id_carrera,
                                                correo = :correo,
                                                telefono = :telefono,
                                                semestre = :semestre,
                                                fecha_nacimiento = :fnac,
                                                direccion = :dir,
                                                estado = :estado
                                            WHERE id_pasante = :id");
                $stmtUpdate->execute([
                    ':ci'             => $ci,
                    ':nombres'        => $nombres,
                    ':apellidos'      => $apellidos,
                    ':id_universidad' => $id_universidad,
                    ':id_carrera'     => $id_carrera,
                    ':correo'         => $correo ?: null,
                    ':telefono'       => $telefono ?: null,
                    ':semestre'       => $semestre ?: null,
                    ':fnac'           => $fecha_nacimiento !== '' ? $fecha_nacimiento : null,
                    ':dir'            => $direccion !== '' ? $direccion : null,
                    ':estado'         => $estado,
                    ':id'             => $id_pasante
                ]);

                // Replace uploaded documents (same rules as registration)
                $docTypes = [
                    'doc_carta' => 'carta_solicitud', 'doc_ci' => 'fotocopia_ci',
                    'doc_egreso' => 'certificado_egreso', 'doc_cv' => 'cv',
                    'doc_croquis' => 'croquis', 'doc_idea' => 'idea_tema',
                ];
                $allowedExt = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
                $baseDir = dirname(__DIR__, 2) . '/uploads/pasantes/' . $id_pasante;
                foreach ($docTypes as $field => $tipo) {
                    $file = $_FILES[$field] ?? null;
                    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024) {
                        throw new Exception('Documento inválido o mayor a 8 MB: ' . $tipo);
                    }
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!isset($allowedExt[$ext]) || mime_content_type($file['tmp_name']) !== $allowedExt[$ext]) {
                        throw new Exception('Documento debe ser PDF, JPG o PNG: ' . $tipo);
                    }
                    if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true)) {
                        throw new Exception('No se pudo crear la carpeta de documentos.');
                    }
                    $safeName = $tipo . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (!move_uploaded_file($file['tmp_name'], $baseDir . '/' . $safeName)) {
                        throw new Exception('No se pudo guardar: ' . $tipo);
                    }
                    // Remove previous file of the same type
                    $stmtOld = $db->prepare("SELECT ruta FROM documentos WHERE id_pasante = :id AND tipo = :tipo");
                    $stmtOld->execute([':id' => $id_pasante, ':tipo' => $tipo]);
                    foreach ($stmtOld->fetchAll() as $old) {
                        $oldPath = dirname(__DIR__, 2) . '/' . $old['ruta'];
                        if (is_file($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    $db->prepare("DELETE FROM documentos WHERE id_pasante = :id AND tipo = :tipo")
                       ->execute([':id' => $id_pasante, ':tipo' => $tipo]);
                    $stmtDoc = $db->prepare("INSERT INTO documentos (id_pasante, tipo, ruta, nombre_original, mime, tamano)
                                             VALUES (:idp, :tipo, :ruta, :orig, :mime, :tam)");
                    $stmtDoc->execute([
                        ':idp'  => $id_pasante,
                        ':tipo' => $tipo,
                        ':ruta' => 'uploads/pasantes/' . $id_pasante . '/' . $safeName,
                        ':orig' => $file['name'],
                        ':mime' => $allowedExt[$ext],
                        ':tam'  => $file['size'],
                    ]);
                }

                Auth::logAudit('EDITAR_PASANTE', 'pasantes', $id_pasante, "Pasante actualizado: $nombres $apellidos");
                $db->commit();
                $_SESSION['flash_success'] = 'Datos del pasante actualizados correctamente.';
                header("Location: index.php");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error al actualizar: ' . $e->getMessage();
            }
        }
    }
}

// Documentos actuales para mostrar y reemplazar
$stmtDocs = $db->prepare("SELECT * FROM documentos WHERE id_pasante = :id ORDER BY tipo ASC");
$stmtDocs->execute([':id' => $id_pasante]);
$docsActuales = [];
foreach ($stmtDocs->fetchAll() as $d) {
    $docsActuales[$d['tipo']] = $d;
}
$docLabels = [
    'carta_solicitud' => '1. Carta de solicitud', 'fotocopia_ci' => '2. Fotocopia de cédula vigente',
    'certificado_egreso' => '3. Certificado de egreso o equivalente', 'cv' => '4. Currículum vitae documentado',
    'croquis' => '5. Croquis del domicilio actual', 'idea_tema' => '6. Idea de tema (opcional)',
];
$docFields = [
    'carta_solicitud' => 'doc_carta', 'fotocopia_ci' => 'doc_ci', 'certificado_egreso' => 'doc_egreso',
    'cv' => 'doc_cv', 'croquis' => 'doc_croquis', 'idea_tema' => 'doc_idea',
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card" style="max-width: 900px; margin: 0 auto;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Editar Pasante: <?= htmlspecialchars($pasante['nombres'] . ' ' . $pasante['apellidos']) ?></h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Actualización de datos personales, académicos y estado.
                    </p>
                </div>
                <a href="index.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> Volver a la Lista
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="editar.php?id=<?= $id_pasante ?>" method="POST" enctype="multipart/form-data">
                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 16px;">
                    1. Información Personal
                </h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="ci">Cédula de Identidad (CI) *</label>
                        <input type="text" id="ci" name="ci" class="form-control" required value="<?= htmlspecialchars($pasante['ci']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="nombres">Nombres *</label>
                        <input type="text" id="nombres" name="nombres" class="form-control" required value="<?= htmlspecialchars($pasante['nombres']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" value="<?= htmlspecialchars($pasante['apellidos']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="correo">Correo Electrónico</label>
                        <input type="email" id="correo" name="correo" class="form-control" value="<?= htmlspecialchars($pasante['correo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono / Celular</label>
                        <input type="text" id="telefono" name="telefono" class="form-control" value="<?= htmlspecialchars($pasante['telefono'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="semestre">Semestre / Nivel Académico</label>
                        <input type="text" id="semestre" name="semestre" class="form-control" value="<?= htmlspecialchars($pasante['semestre'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado del Pasante</label>
                        <select name="estado" id="estado" class="form-control">
                            <option value="ACTIVO" <?= ($pasante['estado'] === 'ACTIVO') ? 'selected' : '' ?>>Activo</option>
                            <option value="INACTIVO" <?= ($pasante['estado'] === 'INACTIVO') ? 'selected' : '' ?>>Inactivo</option>
                            <option value="CONCLUIDO" <?= ($pasante['estado'] === 'CONCLUIDO') ? 'selected' : '' ?>>Concluido</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control" value="<?= htmlspecialchars($pasante['fecha_nacimiento'] ?? '') ?>">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="direccion">Dirección</label>
                        <textarea id="direccion" name="direccion" class="form-control" rows="2"><?= htmlspecialchars($pasante['direccion'] ?? '') ?></textarea>
                    </div>
                </div>

                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 20px; margin-bottom: 16px;">
                    2. Procedencia Académica
                </h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_universidad">Universidad / Instituto de Origen *</label>
                        <select id="id_universidad" name="id_universidad" class="form-control" required onchange="cargarCarreras(this.value)">
                            <option value="">-- Seleccionar Institución --</option>
                            <?php foreach ($instituciones as $inst): ?>
                                <option value="<?= $inst['id_institucion'] ?>" <?= ($pasante['id_universidad'] == $inst['id_institucion']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_carrera">Carrera *</label>
                        <select id="id_carrera" name="id_carrera" class="form-control" required>
                            <!-- Se carga dinámicamente con JS -->
                        </select>
                    </div>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="index.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                    </button>
                </div>

                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 28px; margin-bottom: 8px;">
                    3. Documentación (reemplazar conserva solo el nuevo archivo)
                </h4>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                    PDF, JPG o PNG de hasta 8 MB. Si no eliges archivo, se conserva el actual.
                </p>
                <div class="form-grid">
                    <?php foreach ($docLabels as $tipo => $label): ?>
                        <div class="form-group">
                            <label><?= htmlspecialchars($label) ?></label>
                            <?php if (!empty($docsActuales[$tipo])): ?>
                                <div style="margin-bottom: 6px; font-size: 0.82rem;">
                                    <a href="<?= APP_ROOT . htmlspecialchars($docsActuales[$tipo]['ruta']) ?>" target="_blank">
                                        <i class="fa-solid fa-file"></i> <?= htmlspecialchars($docsActuales[$tipo]['nombre_original']) ?>
                                    </a>
                                    <span style="color: var(--text-muted);">(<?= round($docsActuales[$tipo]['tamano'] / 1024) ?> KB)</span>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom: 6px; font-size: 0.82rem; color: var(--text-muted);">Sin documento cargado.</div>
                            <?php endif; ?>
                            <input type="file" name="<?= $docFields[$tipo] ?>" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    <?php endforeach; ?>
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
function cargarCarreras(idUni, selectedId = null) {
    const selCarrera = document.getElementById('id_carrera');
    selCarrera.innerHTML = '<option value="">Cargando carreras...</option>';
    if (!idUni) {
        selCarrera.innerHTML = '<option value="">-- Seleccione primero una universidad --</option>';
        return;
    }
    fetch('<?= APP_ROOT ?>api/get_carreras.php?id_universidad=' + idUni)
        .then(response => response.json())
        .then(data => {
            selCarrera.innerHTML = '<option value="">-- Seleccionar Carrera --</option>';
            data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_carrera;
                opt.textContent = c.nombre;
                if (selectedId && c.id_carrera == selectedId) {
                    opt.selected = true;
                }
                selCarrera.appendChild(opt);
            });
        })
        .catch(err => {
            console.error(err);
            selCarrera.innerHTML = '<option value="">Error al cargar carreras</option>';
        });
}

document.addEventListener('DOMContentLoaded', () => {
    cargarCarreras('<?= $pasante['id_universidad'] ?>', '<?= $pasante['id_carrera'] ?>');
});
</script>
