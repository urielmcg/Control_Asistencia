<?php
/**
 * Registro de Nuevo Pasante
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();

$pageTitle = 'Nuevo Pasante';
$activeMenu = 'pasantes';
$db = Database::getConnection();

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
    $crear_proceso = isset($_POST['crear_proceso']) ? true : false;
    $id_modalidad = (int)($_POST['id_modalidad'] ?? 1);
    $horas_requeridas = (int)($_POST['horas_requeridas'] ?? 1000);

    if (empty($ci) || empty($nombres) || empty($apellidos) || $id_universidad <= 0 || $id_carrera <= 0) {
        $error = 'Por favor complete todos los campos obligatorios (*).';
    } else {
        // Verificar si CI ya existe
        $stmtCheck = $db->prepare("SELECT id_pasante FROM pasantes WHERE ci = :ci");
        $stmtCheck->execute([':ci' => $ci]);
        if ($stmtCheck->fetch()) {
            $error = "El número de CI $ci ya se encuentra registrado en el sistema.";
        } else {
            try {
                $db->beginTransaction();

                $stmtInsert = $db->prepare("INSERT INTO pasantes (ci, nombres, apellidos, id_universidad, id_carrera, correo, telefono, semestre, estado)
                                            VALUES (:ci, :nombres, :apellidos, :id_universidad, :id_carrera, :correo, :telefono, :semestre, 'ACTIVO')");
                $stmtInsert->execute([
                    ':ci'             => $ci,
                    ':nombres'        => $nombres,
                    ':apellidos'      => $apellidos,
                    ':id_universidad' => $id_universidad,
                    ':id_carrera'     => $id_carrera,
                    ':correo'         => $correo ?: null,
                    ':telefono'       => $telefono ?: null,
                    ':semestre'       => $semestre ?: null
                ]);

                $id_pasante = $db->lastInsertId();

                // Si se marcó asignar proceso de modalidad inmediatamente
                if ($crear_proceso) {
                    $stmtProceso = $db->prepare("INSERT INTO procesos (id_pasante, id_institucion, id_modalidad, fecha_inicio, horas_requeridas, estado, observacion)
                                                 VALUES (:id_pasante, :id_institucion, :id_modalidad, CURDATE(), :horas_requeridas, 'EN_CURSO', 'Asignación inicial al registrar pasante')");
                    $stmtProceso->execute([
                        ':id_pasante'        => $id_pasante,
                        ':id_institucion'    => 4, // CCDB
                        ':id_modalidad'      => $id_modalidad,
                        ':horas_requeridas'  => $horas_requeridas
                    ]);
                }

                Auth::logAudit('CREAR_PASANTE', 'pasantes', $id_pasante, "Pasante registrado: $nombres $apellidos (CI: $ci)");
                $db->commit();

                $_SESSION['flash_success'] = "Pasante registrado exitosamente.";
                header("Location: index.php");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error al registrar pasante: ' . $e->getMessage();
            }
        }
    }
}

// Cargar modalidades para el formulario rápido
$modalidades = $db->query("SELECT id_modalidad, nombre, horas_requeridas_base FROM modalidades WHERE estado = 1")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="card" style="max-width: 900px; margin: 0 auto;">
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

            <form action="nuevo.php" method="POST" id="formNuevoPasante">
                <h4 style="font-size: 0.95rem; color: var(--primary-blue); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 16px;">
                    1. Información Personal
                </h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="ci">Cédula de Identidad (CI) *</label>
                        <input type="text" id="ci" name="ci" class="form-control" placeholder="Ej. 8492019" required value="<?= htmlspecialchars($_POST['ci'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="nombres">Nombres *</label>
                        <input type="text" id="nombres" name="nombres" class="form-control" placeholder="Ej. Juan Carlos" required value="<?= htmlspecialchars($_POST['nombres'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="apellidos">Apellidos *</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" placeholder="Ej. Pérez Quispe" required value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="correo">Correo Electrónico</label>
                        <input type="email" id="correo" name="correo" class="form-control" placeholder="estudiante@incoslapaz.edu.bo" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono / Celular</label>
                        <input type="text" id="telefono" name="telefono" class="form-control" placeholder="Ej. 76543210" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="semestre">Semestre / Año Académico</label>
                        <input type="text" id="semestre" name="semestre" class="form-control" placeholder="Ej. 6to Semestre / Tercer Año" value="<?= htmlspecialchars($_POST['semestre'] ?? '') ?>">
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
                    3. Asignación Inicial de Modalidad (Opcional)
                </h4>
                <div style="margin-bottom: 15px;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                        <input type="checkbox" name="crear_proceso" id="crear_proceso" value="1" checked onchange="toggleProceso(this.checked)">
                        Asignar e iniciar proceso de modalidad inmediatamente
                    </label>
                </div>

                <div id="seccionProceso" class="form-grid" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <div class="form-group">
                        <label for="id_modalidad">Modalidad de Titulación / Práctica</label>
                        <select name="id_modalidad" id="id_modalidad" class="form-control">
                            <?php foreach ($modalidades as $m): ?>
                                <option value="<?= $m['id_modalidad'] ?>"><?= htmlspecialchars($m['nombre']) ?> (Base <?= $m['horas_requeridas_base'] ?> hrs)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="horas_requeridas">Horas Requeridas en Convenio</label>
                        <input type="number" name="horas_requeridas" id="horas_requeridas" class="form-control" value="1000" min="10" step="10">
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

function toggleProceso(checked) {
    document.getElementById('seccionProceso').style.display = checked ? 'grid' : 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const uniVal = document.getElementById('id_universidad').value;
    if (uniVal) {
        cargarCarreras(uniVal, '<?= $_POST['id_carrera'] ?? '' ?>');
    }
});
</script>
