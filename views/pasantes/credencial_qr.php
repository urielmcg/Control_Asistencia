<?php
/**
 * Credencial y Carnet QR del Pasante
 * Centro Cultural Don Bosco
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireStaff();

$pageTitle = 'Credencial QR de Pasante';
$activeMenu = 'pasantes';
$db = Database::getConnection();

$id_pasante = (int)($_GET['id'] ?? 0);
$pasante = null;
$procesoActivo = null;

if ($id_pasante > 0) {
    // Consultar datos completos del pasante
    $stmt = $db->prepare("SELECT p.*, i.nombre AS institucion, c.nombre AS carrera 
                          FROM pasantes p
                          LEFT JOIN instituciones i ON p.id_universidad = i.id_institucion
                          LEFT JOIN carreras c ON p.id_carrera = c.id_carrera
                          WHERE p.id_pasante = :id LIMIT 1");
    $stmt->execute([':id' => $id_pasante]);
    $pasante = $stmt->fetch();

    if ($pasante) {
        // Consultar proceso / modalidad activa
        $stmtProc = $db->prepare("SELECT pr.*, COALESCE(m.nombre, 'Pasantía') AS modalidad, m.descripcion AS modalidad_desc
                                  FROM procesos pr
                                  LEFT JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                                  WHERE pr.id_pasante = :id_pasante AND pr.estado = 'EN_CURSO'
                                  ORDER BY pr.id_proceso DESC LIMIT 1");
        $stmtProc->execute([':id_pasante' => $id_pasante]);
        $procesoActivo = $stmtProc->fetch();

        // Si no tiene 'EN_CURSO', buscar el último proceso registrado
        if (!$procesoActivo) {
            $stmtLast = $db->prepare("SELECT pr.*, COALESCE(m.nombre, 'Pasantía') AS modalidad, m.descripcion AS modalidad_desc
                                      FROM procesos pr
                                      LEFT JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                                      WHERE pr.id_pasante = :id_pasante
                                      ORDER BY pr.id_proceso DESC LIMIT 1");
            $stmtLast->execute([':id_pasante' => $id_pasante]);
            $procesoActivo = $stmtLast->fetch();
        }
    }
}

// Lista de pasantes para selector alternativo si no se encontró o no se pasó id
$pasantesList = $db->query("SELECT id_pasante, ci, nombres, apellidos, estado FROM pasantes ORDER BY apellidos ASC, nombres ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <?php if (!$pasante): ?>
            <!-- Alerta Amigable y Selección de Pasante -->
            <div class="card" style="max-width: 650px; margin: 40px auto; text-align: center; padding: 35px 25px;">
                <div style="width: 70px; height: 70px; margin: 0 auto 18px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-red); font-size: 2rem;">
                    <i class="fa-solid fa-id-card-clip"></i>
                </div>
                <h3 style="color: var(--primary-blue); font-weight: 800; margin-bottom: 10px;">Credencial no seleccionada</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 25px;">
                    <?= $id_pasante > 0 ? "No se encontró ningún pasante con el ID proporcionado ($id_pasante)." : "Por favor elija un pasante para generar y visualizar su credencial institucional con código QR." ?>
                </p>

                <form method="GET" action="credencial_qr.php" style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <select name="id" class="form-control" style="max-width: 360px;" required>
                        <option value="">-- Seleccionar Pasante --</option>
                        <?php foreach ($pasantesList as $p): ?>
                            <option value="<?= $p['id_pasante'] ?>">
                                <?= htmlspecialchars($p['apellidos'] . ' ' . $p['nombres'] . ' (CI: ' . $p['ci'] . ') - ' . $p['estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-qrcode"></i> Ver Carnet
                    </button>
                    <a href="index.php" class="btn btn-outline">
                        <i class="fa-solid fa-arrow-left"></i> Volver a la Lista
                    </a>
                </form>
            </div>
        <?php else: 
            $qrData = 'CCDB-CI-' . preg_replace('/[^0-9A-Za-z]/', '', $pasante['ci']);
            $iniciales = mb_strtoupper(mb_substr($pasante['nombres'], 0, 1) . mb_substr($pasante['apellidos'], 0, 1));
            $nombreCompleto = $pasante['nombres'] . ' ' . $pasante['apellidos'];
        ?>
            <!-- Barra de Acciones Superior (No imprimible) -->
            <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <a href="index.php" class="btn btn-outline">
                        <i class="fa-solid fa-arrow-left"></i> Volver a la Lista
                    </a>
                    <div>
                        <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--primary-blue); margin: 0;">
                            Credencial Oficial del Pasante
                        </h2>
                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                            <?= htmlspecialchars($nombreCompleto) ?> &bull; CI: <?= htmlspecialchars($pasante['ci']) ?>
                        </span>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="fa-solid fa-print"></i> Imprimir Carnet
                    </button>
                    <button type="button" class="btn btn-success" id="btnDescargarQR" onclick="descargarCodigoQR()">
                        <i class="fa-solid fa-download"></i> Descargar QR (PNG)
                    </button>
                    <a href="../asistencias/registrar_qr.php" class="btn btn-danger">
                        <i class="fa-solid fa-expand"></i> Probar en Asistencia QR
                    </a>
                </div>
            </div>

            <!-- Contenedor Principal de la Credencial -->
            <div class="credencial-wrapper" style="display: flex; justify-content: center; align-items: flex-start; gap: 30px; flex-wrap: wrap; margin-bottom: 35px;">
                
                <!-- CARNET FRENTE (Vertical 85.6mm x 54mm aprox / 340px x 530px) -->
                <div id="carnetPrintArea" class="carnet-card">
                    <!-- Decoración Superior de Cabecera -->
                    <div class="carnet-header">
                        <div class="carnet-header-bg"></div>
                        <div class="carnet-header-content">
                            <div class="carnet-logo-box">
                                <img src="<?= APP_ROOT ?>assets/img/ccdb/icono_ccdb_sin_fondo.png" alt="Logo Don Bosco" class="carnet-logo">
                            </div>
                            <div class="carnet-inst-text">
                                <h3>CENTRO CULTURAL DON BOSCO</h3>
                                <span>CREDENCIAL DE PASANTÍA</span>
                            </div>
                        </div>
                        <div class="carnet-stripe"></div>
                    </div>

                    <!-- Cuerpo de la Credencial -->
                    <div class="carnet-body">
                        <!-- Avatar / Foto -->
                        <div class="carnet-avatar-wrapper">
                            <div class="carnet-avatar">
                                <span><?= htmlspecialchars($iniciales) ?></span>
                            </div>
                            <div class="carnet-badge-status <?= $pasante['estado'] === 'ACTIVO' ? 'status-activo' : 'status-inactivo' ?>">
                                <i class="fa-solid <?= $pasante['estado'] === 'ACTIVO' ? 'fa-check' : 'fa-clock' ?>"></i>
                                <?= htmlspecialchars($pasante['estado']) ?>
                            </div>
                        </div>

                        <!-- Nombre del Pasante -->
                        <div class="carnet-fullname">
                            <?= htmlspecialchars($nombreCompleto) ?>
                        </div>
                        <div class="carnet-ci-tag">
                            <i class="fa-solid fa-id-card"></i> CI: <?= htmlspecialchars($pasante['ci']) ?>
                        </div>

                        <!-- Detalles Académicos -->
                        <div class="carnet-academic-info">
                            <div class="academic-row">
                                <span class="label"><i class="fa-solid fa-graduation-cap"></i> Carrera:</span>
                                <span class="val"><?= htmlspecialchars($pasante['carrera'] ?? 'No asignada') ?></span>
                            </div>
                            <div class="academic-row">
                                <span class="label"><i class="fa-solid fa-building-columns"></i> Institución:</span>
                                <span class="val"><?= htmlspecialchars($pasante['institucion'] ?? 'No registrada') ?></span>
                            </div>
                            <?php if ($procesoActivo): ?>
                                <div class="academic-row">
                                    <span class="label"><i class="fa-solid fa-briefcase"></i> Modalidad:</span>
                                    <span class="val" style="color: var(--primary-blue); font-weight: 700;"><?= htmlspecialchars($procesoActivo['modalidad']) ?></span>
                                </div>
                                <div class="academic-row">
                                    <span class="label"><i class="fa-solid fa-business-time"></i> Horas Requeridas:</span>
                                    <span class="val"><?= htmlspecialchars($procesoActivo['horas_requeridas']) ?> horas</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Contenedor QR -->
                        <div class="carnet-qr-container">
                            <div id="qrcodeCarnet" class="qr-box"></div>
                            <div class="qr-code-text">
                                <?= htmlspecialchars($qrData) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pie de la Credencial -->
                    <div class="carnet-footer">
                        <p>Válido para marcación de asistencias y control de acceso</p>
                        <small>CCDB &bull; Sistema Integrado de Gestión</small>
                    </div>
                </div>

                <!-- Panel Lateral de Información y Acciones Rápidas (No imprimible) -->
                <div class="card no-print" style="max-width: 380px; flex: 1; min-width: 300px;">
                    <h3 class="card-title" style="margin-bottom: 15px;">
                        <i class="fa-solid fa-circle-info" style="color: var(--primary-blue); margin-right: 6px;"></i>
                        Ficha del Pasante
                    </h3>

                    <div style="background: #f8fafc; border-radius: 10px; padding: 16px; margin-bottom: 18px; border: 1px solid var(--border-color);">
                        <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 4px;">CORREO ELECTRÓNICO</div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-dark); margin-bottom: 12px;">
                            <i class="fa-regular fa-envelope" style="color: #64748b;"></i> <?= htmlspecialchars($pasante['correo'] ?: 'No registrado') ?>
                        </div>

                        <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 4px;">TELÉFONO / CELULAR</div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-dark); margin-bottom: 12px;">
                            <i class="fa-solid fa-phone" style="color: #64748b;"></i> <?= htmlspecialchars($pasante['telefono'] ?: 'No registrado') ?>
                        </div>

                        <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 4px;">SEMESTRE / NIVEL</div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-dark);">
                            <i class="fa-solid fa-calendar-days" style="color: #64748b;"></i> <?= htmlspecialchars($pasante['semestre'] ?: 'No especificado') ?>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">
                            CÓDIGO DE ESCANEO DIRECTO:
                        </label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="copiarCodigoInput" class="form-control" value="<?= htmlspecialchars($qrData) ?>" readonly style="background: #f1f5f9; font-weight: 700; font-family: monospace; font-size: 0.95rem;">
                            <button type="button" class="btn btn-outline" onclick="copiarAlPortapapeles()" title="Copiar código">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>
                        <small style="color: #64748b; font-size: 0.78rem; margin-top: 4px; display: block;">
                            Tanto el código con prefijo (<code><?= htmlspecialchars($qrData) ?></code>) como el CI numérico (<code><?= htmlspecialchars($pasante['ci']) ?></code>) son válidos en el escáner.
                        </small>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="editar.php?id=<?= $pasante['id_pasante'] ?>" class="btn btn-outline" style="justify-content: center;">
                            <i class="fa-solid fa-user-pen"></i> Editar Información del Pasante
                        </a>
                        <a href="../asistencias/historial.php?id_pasante=<?= $pasante['id_pasante'] ?>" class="btn btn-outline" style="justify-content: center;">
                            <i class="fa-solid fa-clock-rotate-left"></i> Ver Historial de Asistencias
                        </a>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>

<!-- Biblioteca qrcode.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
/* Estilos Específicos para la Credencial Don Bosco */
.carnet-card {
    width: 350px;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(13, 59, 102, 0.15);
    overflow: hidden;
    border: 1px solid rgba(13, 59, 102, 0.12);
    display: flex;
    flex-direction: column;
    position: relative;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: var(--text-dark);
}

.carnet-header {
    background: linear-gradient(135deg, #0d3b66 0%, #1d4e89 100%);
    color: #ffffff;
    padding: 16px 14px 12px;
    position: relative;
    text-align: center;
}

.carnet-header-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.carnet-logo-box {
    width: 44px;
    height: 44px;
    background: #ffffff;
    border-radius: 10px;
    padding: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.carnet-logo {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.carnet-inst-text h3 {
    font-size: 0.86rem;
    font-weight: 900;
    margin: 0;
    letter-spacing: 0.5px;
    color: #ffffff;
    line-height: 1.2;
}

.carnet-inst-text span {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    color: #fca5a5;
    text-transform: uppercase;
    display: block;
    margin-top: 2px;
}

.carnet-stripe {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #e63946 0%, #f43f5e 50%, #0d3b66 100%);
}

.carnet-body {
    padding: 20px 20px 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    background: radial-gradient(circle at 50% 10%, rgba(241, 245, 249, 0.6) 0%, rgba(255, 255, 255, 1) 70%);
}

.carnet-avatar-wrapper {
    position: relative;
    margin-bottom: 12px;
}

.carnet-avatar {
    width: 82px;
    height: 82px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0d3b66, #2563eb);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.85rem;
    font-weight: 800;
    letter-spacing: 1px;
    border: 4px solid #ffffff;
    box-shadow: 0 4px 12px rgba(13, 59, 102, 0.2);
}

.carnet-badge-status {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.68rem;
    font-weight: 800;
    padding: 2px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    border: 2px solid #ffffff;
}

.status-activo {
    background: #16a34a;
    color: #ffffff;
}

.status-inactivo {
    background: #64748b;
    color: #ffffff;
}

.carnet-fullname {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--primary-blue);
    line-height: 1.25;
    margin-top: 6px;
    margin-bottom: 4px;
    max-width: 100%;
    word-break: break-word;
}

.carnet-ci-tag {
    display: inline-block;
    background: #e2e8f0;
    color: #1e293b;
    padding: 3px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    margin-bottom: 14px;
}

.carnet-academic-info {
    width: 100%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 16px;
    text-align: left;
}

.academic-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.78rem;
    padding: 3px 0;
    border-bottom: 1px dashed #e2e8f0;
}

.academic-row:last-child {
    border-bottom: none;
}

.academic-row .label {
    color: var(--text-muted);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.academic-row .label i {
    color: var(--primary-red);
    width: 14px;
    text-align: center;
}

.academic-row .val {
    font-weight: 700;
    color: var(--text-dark);
    text-align: right;
    max-width: 170px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.carnet-qr-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.qr-box {
    padding: 6px;
    background: #ffffff;
    display: inline-block;
}

.qr-box img {
    margin: 0 auto;
}

.qr-code-text {
    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--primary-blue);
    letter-spacing: 0.8px;
    margin-top: 6px;
}

.carnet-footer {
    background: #0d3b66;
    color: #ffffff;
    padding: 10px 14px;
    text-align: center;
    border-top: 2px solid #e63946;
}

.carnet-footer p {
    font-size: 0.68rem;
    font-weight: 600;
    margin: 0;
    color: #f1f5f9;
    letter-spacing: 0.2px;
}

.carnet-footer small {
    font-size: 0.62rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: block;
    margin-top: 2px;
}

/* ========================================================
   ESTILOS PARA IMPRESIÓN EXCLUSIVA DE LA TARJETA CARNET
======================================================== */
@media print {
    @page {
        size: auto;
        margin: 10mm;
    }

    body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
    }

    /* Ocultar elementos no relevantes */
    .sidebar,
    .navbar,
    .no-print,
    header,
    footer,
    .content-body > *:not(.credencial-wrapper) {
        display: none !important;
    }

    .main-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    .content-body {
        padding: 0 !important;
        margin: 0 !important;
    }

    .credencial-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
    }

    .carnet-card {
        box-shadow: none !important;
        border: 1.5px solid #0d3b66 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        margin: 20px auto !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($pasante): ?>
        // Generar Código QR nítido
        const qrContainer = document.getElementById('qrcodeCarnet');
        if (qrContainer) {
            new QRCode(qrContainer, {
                text: "<?= $qrData ?>",
                width: 128,
                height: 128,
                colorDark: "#0d3b66",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    <?php endif; ?>
});

// Descargar QR como Imagen PNG
function descargarCodigoQR() {
    const qrImg = document.querySelector('#qrcodeCarnet img');
    const qrCanvas = document.querySelector('#qrcodeCarnet canvas');

    let imageURI = null;
    if (qrCanvas) {
        imageURI = qrCanvas.toDataURL("image/png");
    } else if (qrImg && qrImg.src) {
        imageURI = qrImg.src;
    }

    if (!imageURI) {
        alert('No se pudo generar la imagen del código QR.');
        return;
    }

    const downloadLink = document.createElement('a');
    downloadLink.href = imageURI;
    downloadLink.download = 'QR_Pasante_<?= $pasante ? preg_replace('/[^0-9A-Za-z]/', '', $pasante['ci']) : 'CCDB' ?>.png';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Copiar código al portapapeles
function copiarAlPortapapeles() {
    const input = document.getElementById('copiarCodigoInput');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Código copiado al portapapeles: ' + input.value);
    }).catch(() => {
        document.execCommand('copy');
        alert('Código copiado: ' + input.value);
    });
}
</script>
