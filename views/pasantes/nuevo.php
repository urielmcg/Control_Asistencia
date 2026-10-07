<?php
/**
 * Registro de Nuevo Pasante (formulario extendido con acceso, fechas y documentos)
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Nuevo Pasante';
$activeMenu = 'pasantes';
$db = Database::getConnection();

$error = '';

// Required documents: field name => [type, label, required]
$docFields = [
    'doc_carta'   => ['tipo' => 'carta_solicitud',   'label' => 'Carta de solicitud de modalidad',  'required' => true],
    'doc_ci'      => ['tipo' => 'fotocopia_ci',      'label' => 'Fotocopia de cédula vigente',      'required' => true],
    'doc_egreso'  => ['tipo' => 'certificado_egreso','label' => 'Certificado de egreso o equivalente','required' => true],
    'doc_cv'      => ['tipo' => 'cv',                'label' => 'Currículum vitae documentado',     'required' => true],
    'doc_croquis' => ['tipo' => 'croquis',           'label' => 'Croquis del domicilio actual',     'required' => true],
    'doc_idea'    => ['tipo' => 'idea_tema',         'label' => 'Idea de tema',                      'required' => false],
];
$allowedExt = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
$maxBytes = 8 * 1024 * 1024;

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
    $usuario_acceso = trim($_POST['usuario_acceso'] ?? '');
    $password = $_POST['password'] ?? '';
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $id_modalidad = (int)($_POST['id_modalidad'] ?? 0);
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    if (empty($ci) || empty($nombres) || $id_universidad <= 0 || $id_carrera <= 0
        || empty($usuario_acceso) || empty($password) || empty($correo) || empty($fecha_nacimiento)
        || empty($fecha_inicio) || empty($fecha_fin)) {
        $error = 'Por favor complete todos los campos obligatorios (*).';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no tiene un formato válido.';
    } elseif ($fecha_fin < $fecha_inicio) {
        $error = 'La fecha de fin no puede ser anterior a la de inicio.';
    } else {
        // Validate uploaded documents before touching the database
        $uploads = [];
        foreach ($docFields as $field => $meta) {
            $file = $_FILES[$field] ?? null;
            $hasFile = $file && $file['error'] !== UPLOAD_ERR_NO_FILE;
            if (!$hasFile) {
                if ($meta['required']) {
                    $error = 'Falta el documento: ' . $meta['label'] . '.';
                    break;
                }
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Error al subir ' . $meta['label'] . ' (código ' . $file['error'] . ').';
                break;
            }
            if ($file['size'] > $maxBytes) {
                $error = $meta['label'] . ' supera los 8 MB permitidos.';
                break;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!isset($allowedExt[$ext])) {
                $error = $meta['label'] . ' debe ser PDF, JPG o PNG.';
                break;
            }
            $mime = mime_content_type($file['tmp_name']);
            if ($mime !== $allowedExt[$ext]) {
                $error = $meta['label'] . ' tiene un contenido inválido.';
                break;
            }
            $uploads[$field] = $file;
        }

        if ($error === '') {
            try {
                $db->beginTransaction();

                $stmtCheck = $db->prepare("SELECT id_pasante FROM pasantes WHERE ci = :ci");
                $stmtCheck->execute([':ci' => $ci]);
                if ($stmtCheck->fetch()) {
                    throw new Exception("El número de CI $ci ya se encuentra registrado en el sistema.");
                }
                $stmtUser = $db->prepare("SELECT id_usuario FROM usuarios WHERE usuario = :u OR ci = :ci");
                $stmtUser->execute([':u' => $usuario_acceso, ':ci' => $ci]);
                if ($stmtUser->fetch()) {
                    throw new Exception('El usuario de acceso o el CI ya existen en usuarios.');
                }

                // Access account for the pasante
                $stmtAcc = $db->prepare("INSERT INTO usuarios (id_rol, usuario, password, nombres, apellidos, ci, correo, telefono, estado)
                                         VALUES (3, :u, :p, :n, :a, :ci, :correo, :tel, 1)");
                $stmtAcc->execute([
                    ':u'      => $usuario_acceso,
                    ':p'      => password_hash($password, PASSWORD_BCRYPT),
                    ':n'      => $nombres,
                    ':a'      => $apellidos,
                    ':ci'     => $ci,
                    ':correo' => $correo,
                    ':tel'    => $telefono ?: null,
                ]);
                $id_usuario = (int)$db->lastInsertId();

                $stmtInsert = $db->prepare("INSERT INTO pasantes (id_usuario, ci, nombres, apellidos, id_universidad, id_carrera, correo, telefono, semestre, fecha_nacimiento, direccion, estado)
                                            VALUES (:idu, :ci, :nombres, :apellidos, :id_universidad, :id_carrera, :correo, :telefono, :semestre, :fnac, :dir, 'ACTIVO')");
                $stmtInsert->execute([
                    ':idu'            => $id_usuario,
                    ':ci'             => $ci,
                    ':nombres'        => $nombres,
                    ':apellidos'      => $apellidos,
                    ':id_universidad' => $id_universidad,
                    ':id_carrera'     => $id_carrera,
                    ':correo'         => $correo,
                    ':telefono'       => $telefono ?: null,
                    ':semestre'       => $semestre ?: null,
                    ':fnac'           => $fecha_nacimiento,
                    ':dir'            => $direccion ?: null,
                ]);
                $id_pasante = (int)$db->lastInsertId();

                // Pure internship track (hours only, no graduation modality)
                $stmtProceso = $db->prepare("INSERT INTO procesos (id_pasante, id_institucion, id_modalidad, fecha_inicio, fecha_fin, horas_requeridas, estado, observacion)
                                             VALUES (:id_pasante, 4, NULL, :ini, :fin, 1000, 'EN_CURSO', 'Pasantía: cumplimiento de horas')");
                $stmtProceso->execute([
                    ':id_pasante'  => $id_pasante,
                    ':ini'         => $fecha_inicio,
                    ':fin'         => $fecha_fin,
                ]);

                // Store documents on disk and register them
                $baseDir = dirname(__DIR__, 2) . '/uploads/pasantes/' . $id_pasante;
                if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true)) {
                    throw new Exception('No se pudo crear la carpeta de documentos.');
                }
                $moved = [];
                $stmtDoc = $db->prepare("INSERT INTO documentos (id_pasante, tipo, ruta, nombre_original, mime, tamano)
                                         VALUES (:idp, :tipo, :ruta, :orig, :mime, :tam)");
                foreach ($docFields as $field => $meta) {
                    if (!isset($uploads[$field])) {
                        continue;
                    }
                    $file = $uploads[$field];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $safeName = $meta['tipo'] . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $baseDir . '/' . $safeName;
                    if (!move_uploaded_file($file['tmp_name'], $dest)) {
                        throw new Exception('No se pudo guardar ' . $meta['label'] . '.');
                    }
                    $moved[] = $dest;
                    $stmtDoc->execute([
                        ':idp'  => $id_pasante,
                        ':tipo' => $meta['tipo'],
                        ':ruta' => 'uploads/pasantes/' . $id_pasante . '/' . $safeName,
                        ':orig' => $file['name'],
                        ':mime' => $allowedExt[$ext],
                        ':tam'  => $file['size'],
                    ]);
                }

                Auth::logAudit('CREAR_PASANTE', 'pasantes', $id_pasante, "Pasante registrado: $nombres $apellidos (CI: $ci)");
                $db->commit();

                $_SESSION['flash_success'] = 'Pasante registrado exitosamente.';
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                foreach ($moved ?? [] as $path) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
                $error = 'Error al registrar pasante: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card" style="max-width: 960px; margin: 0 auto;">
            <div class="card-header-flex">
                <div>
                    <h3 class="card-title">Registrar Nuevo Pasante</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        Ingrese los datos del estudiante para incorporarlo al sistema CCDB.
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

            <form action="nuevo.php" method="POST" enctype="multipart/form-data" id="formNuevoPasante">
                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 16px;">
                    1. Información Personal
                </h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombres">Nombre completo *</label>
                        <input type="text" id="nombres" name="nombres" class="form-control" placeholder="Ej. Juan Carlos Pérez Quispe" required value="<?= htmlspecialchars($_POST['nombres'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="ci">Documento / CI *</label>
                        <input type="text" id="ci" name="ci" class="form-control" placeholder="Ej. 8492019" required value="<?= htmlspecialchars($_POST['ci'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="apellidos">Apellidos (registro interno)</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" placeholder="Ej. Pérez Quispe" value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="correo">Correo *</label>
                        <input type="email" id="correo" name="correo" class="form-control" placeholder="estudiante@incoslapaz.edu.bo" required value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" class="form-control" placeholder="Ej. 76543210" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="semestre">Semestre / Año Académico</label>
                        <input type="text" id="semestre" name="semestre" class="form-control" placeholder="Ej. 6to Semestre" value="<?= htmlspecialchars($_POST['semestre'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control" required value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <textarea id="direccion" name="direccion" class="form-control" rows="2"><?= htmlspecialchars($_POST['direccion'] ?? '') ?></textarea>
                    </div>
                </div>

                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 20px; margin-bottom: 16px;">
                    2. Acceso al sistema
                </h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="usuario_acceso">Usuario de acceso del pasante *</label>
                        <input type="text" id="usuario_acceso" name="usuario_acceso" class="form-control" required value="<?= htmlspecialchars($_POST['usuario_acceso'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña *</label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="8">
                        <small style="color: var(--text-muted);">Debe tener al menos 8 caracteres. Se guarda protegida.</small>
                    </div>
                </div>

                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 20px; margin-bottom: 16px;">
                    3. Procedencia Académica
                </h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_universidad">Universidad / Instituto de Origen *</label>
                        <select id="id_universidad" name="id_universidad" class="form-control" required onchange="cargarCarreras(this.value)">
                            <option value="">-- Seleccionar Institución --</option>
                            <?php foreach ($instituciones as $inst): ?>
                                <option value="<?= $inst['id_institucion'] ?>" <?= (isset($_POST['id_universidad']) && $_POST['id_universidad'] == $inst['id_institucion']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_carrera">Carrera *</label>
                        <select id="id_carrera" name="id_carrera" class="form-control" required>
                            <option value="">-- Seleccione primero una universidad --</option>
                        </select>
                    </div>
                </div>

                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 20px; margin-bottom: 16px;">
                    4. Periodo de pasantía (solo horas, sin modalidad)
                </h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="fecha_inicio">Inicio de pasantía *</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fin de pasantía *</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" required value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '') ?>">
                    </div>
                </div>

                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 20px; margin-bottom: 8px;">
                    Documentación requerida
                </h4>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                    Adjunta archivos PDF, JPG o PNG de hasta 8 MB cada uno.
                </p>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="doc_carta">1. Carta de solicitud de modalidad *</label>
                        <input type="file" id="doc_carta" name="doc_carta" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small style="color: var(--text-muted);">Dirigida al Director General e indicando los datos de postulación anteriores.</small>
                    </div>
                    <div class="form-group">
                        <label for="doc_ci">2. Fotocopia de cédula vigente *</label>
                        <input type="file" id="doc_ci" name="doc_ci" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small style="color: var(--text-muted);">Documento de identidad vigente.</small>
                    </div>
                    <div class="form-group">
                        <label for="doc_egreso">3. Certificado de egreso o equivalente *</label>
                        <input type="file" id="doc_egreso" name="doc_egreso" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small style="color: var(--text-muted);">Debe acreditar que puede realizar su modalidad de titulación.</small>
                    </div>
                    <div class="form-group">
                        <label for="doc_cv">4. Currículum vitae documentado *</label>
                        <input type="file" id="doc_cv" name="doc_cv" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small style="color: var(--text-muted);">Incluye los respaldos correspondientes.</small>
                    </div>
                    <div class="form-group">
                        <label for="doc_croquis">5. Croquis del domicilio actual *</label>
                        <input type="file" id="doc_croquis" name="doc_croquis" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small style="color: var(--text-muted);">Puede ser captura de mapa o dibujo a mano.</small>
                    </div>
                    <div class="form-group">
                        <label for="doc_idea">6. Idea de tema (opcional)</label>
                        <input type="file" id="doc_idea" name="doc_idea" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <small style="color: var(--text-muted);">Este documento es opcional.</small>
                    </div>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="index.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Pasante
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
    const uniVal = document.getElementById('id_universidad').value;
    if (uniVal) {
        cargarCarreras(uniVal, '<?= $_POST['id_carrera'] ?? '' ?>');
    }
});
</script>
