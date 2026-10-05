<?php
/**
 * Registro Rápido de Asistencia por Escaneo QR o Ingreso de CI
 * Centro Cultural Don Bosco
 */
require_once __DIR__ . '/../../config/auth.php';
Auth::requireLogin();

$pageTitle = 'Registro de Asistencia QR';
$activeMenu = 'asistencias_qr';
$db = Database::getConnection();

// Asegurar existencia de la tabla codigos_qr si no estuviera creada aún
try {
    $db->exec("CREATE TABLE IF NOT EXISTS codigos_qr (
        id_qr INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(255) NOT NULL UNIQUE,
        fecha DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_expiracion TIME NOT NULL,
        estado VARCHAR(30) NOT NULL DEFAULT 'ACTIVO',
        fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Exception $e) {
    // Ya existe o sin permisos DDL
}

$hoy = date('Y-m-d');
$ahora = date('H:i:s');

// Gestión del QR del Día para el Tótem CCDB
$qrDelDia = null;
try {
    // Buscar si ya existe un QR activo para hoy
    $stmtQr = $db->prepare("SELECT * FROM codigos_qr WHERE fecha = :fecha AND estado = 'ACTIVO' ORDER BY id_qr DESC LIMIT 1");
    $stmtQr->execute([':fecha' => $hoy]);
    $qrDelDia = $stmtQr->fetch();

    // Si se solicitó regenerar o no existe para hoy
    if (isset($_GET['generar_qr_dia']) && $_GET['generar_qr_dia'] == '1') {
        $tokenUnico = strtoupper(bin2hex(random_bytes(4)));
        $nuevoCodigo = 'CCDB-ASIS-' . date('Ymd') . '-' . $tokenUnico;

        // Desactivar anteriores de hoy
        $db->prepare("UPDATE codigos_qr SET estado = 'EXPIRADO' WHERE fecha = :fecha")->execute([':fecha' => $hoy]);

        $stmtInsQr = $db->prepare("INSERT INTO codigos_qr (codigo, fecha, hora_inicio, hora_expiracion, estado) 
                                   VALUES (:codigo, :fecha, '06:00:00', '22:00:00', 'ACTIVO')");
        $stmtInsQr->execute([
            ':codigo' => $nuevoCodigo,
            ':fecha'  => $hoy
        ]);

        header("Location: registrar_qr.php?tab=totem&msg=qr_generado");
        exit;
    }

    // Si aún no hay ninguno hoy, crearlo automáticamente
    if (!$qrDelDia) {
        $tokenUnico = strtoupper(bin2hex(random_bytes(4)));
        $nuevoCodigo = 'CCDB-ASIS-' . date('Ymd') . '-' . $tokenUnico;
        $stmtInsQr = $db->prepare("INSERT INTO codigos_qr (codigo, fecha, hora_inicio, hora_expiracion, estado) 
                                   VALUES (:codigo, :fecha, '06:00:00', '22:00:00', 'ACTIVO')");
        $stmtInsQr->execute([
            ':codigo' => $nuevoCodigo,
            ':fecha'  => $hoy
        ]);
        $stmtQr->execute([':fecha' => $hoy]);
        $qrDelDia = $stmtQr->fetch();
    }
} catch (Exception $e) {
    // Loguear error de QR silenciosamente
}

// Public self check-in URL encoded in the Totem QR (scanned from the pasante's phone)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBase = preg_replace('#/views/asistencias/registrar_qr\.php$#', '', $scriptPath);
$qrTotemUrl = $scheme . '://' . $host . $appBase . '/views/asistencias/marcar.php?qr=' . urlencode($qrDelDia['codigo'] ?? ('CCDB-ASIS-' . date('Ymd')));

$mensaje = '';
$tipoMensaje = '';
$datosMarcado = null;

if (isset($_GET['msg']) && $_GET['msg'] === 'qr_generado') {
    $mensaje = 'Se ha generado un nuevo Código QR institucional para el Tótem del día de hoy.';
    $tipoMensaje = 'info';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigoRaw = trim($_POST['codigo_ci'] ?? '');

    if (empty($codigoRaw)) {
        $mensaje = 'Por favor ingrese o escanee el CI / Código QR del pasante.';
        $tipoMensaje = 'danger';
    } else {
        try {
            // Limpieza y extracción inteligente del CI:
            // Soportar:
            // 1. "CCDB-CI-8329410" -> "8329410"
            // 2. "CCDB-CI-8329410-LP" -> "8329410" o "8329410-LP"
            // 3. URLs como "http://...?ci=8329410"
            // 4. CI plano con o sin extensión ("8329410", "8329410-LP", "E-8329410")
            $ciCandidato = $codigoRaw;

            // Quitar prefijo estándar CCDB-CI- si viene presente
            if (stripos($ciCandidato, 'CCDB-CI-') === 0) {
                $ciCandidato = substr($ciCandidato, 8);
            }

            // Si vino una URL con parámetro id o ci
            if (filter_var($ciCandidato, FILTER_VALIDATE_URL)) {
                $parsedUrl = parse_url($ciCandidato);
                if (isset($parsedUrl['query'])) {
                    parse_str($parsedUrl['query'], $queryParams);
                    if (!empty($queryParams['ci'])) {
                        $ciCandidato = $queryParams['ci'];
                    } elseif (!empty($queryParams['id'])) {
                        // Buscar pasante por id si la url era a la credencial
                        $idParam = (int)$queryParams['id'];
                        $stmtPasId = $db->prepare("SELECT ci FROM pasantes WHERE id_pasante = :id LIMIT 1");
                        $stmtPasId->execute([':id' => $idParam]);
                        $pasRow = $stmtPasId->fetch();
                        if ($pasRow) {
                            $ciCandidato = $pasRow['ci'];
                        }
                    }
                }
            }

            $ciCandidato = trim($ciCandidato);
            $cleanNumeric = preg_replace('/[^0-9]/', '', $ciCandidato);

            // Buscar pasante con varias coincidencias seguras:
            // 1) ci idéntico al raw o ciCandidato
            // 2) ci limpio numérico
            // 3) LIKE con el número
            $stmt = $db->prepare("SELECT p.*, i.nombre AS institucion, c.nombre AS carrera 
                                  FROM pasantes p
                                  LEFT JOIN instituciones i ON p.id_universidad = i.id_institucion
                                  LEFT JOIN carreras c ON p.id_carrera = c.id_carrera
                                  WHERE p.ci = :ci 
                                     OR p.ci = :ci_cand
                                     OR (LENGTH(:ci_clean) >= 4 AND REPLACE(REPLACE(p.ci, '-', ''), ' ', '') = :ci_clean)
                                  LIMIT 1");
            $stmt->execute([
                ':ci'       => $codigoRaw,
                ':ci_cand'  => $ciCandidato,
                ':ci_clean' => $cleanNumeric
            ]);
            $pasante = $stmt->fetch();

            if (!$pasante) {
                $mensaje = "No se encontró ningún pasante con el CI o Código escaneado: '$codigoRaw'.";
                $tipoMensaje = 'danger';
            } elseif ($pasante['estado'] !== 'ACTIVO') {
                $mensaje = "El pasante " . $pasante['nombres'] . " " . $pasante['apellidos'] . " no se encuentra en estado ACTIVO (Estado actual: " . $pasante['estado'] . ").";
                $tipoMensaje = 'danger';
            } else {
                // Buscar proceso activo
                $stmtProc = $db->prepare("SELECT pr.*, m.nombre AS modalidad 
                                          FROM procesos pr 
                                          INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad 
                                          WHERE pr.id_pasante = :id_pasante AND pr.estado = 'EN_CURSO' 
                                          ORDER BY pr.id_proceso DESC LIMIT 1");
                $stmtProc->execute([':id_pasante' => $pasante['id_pasante']]);
                $proceso = $stmtProc->fetch();

                if (!$proceso) {
                    $mensaje = "El pasante " . $pasante['nombres'] . " " . $pasante['apellidos'] . " no tiene un proceso de pasantía activo (EN_CURSO). Asigne uno primero en 'Modalidades'.";
                    $tipoMensaje = 'danger';
                } else {
                    $hoy = date('Y-m-d');
                    $ahora = date('H:i:s');
                    $idQrActual = $qrDelDia ? $qrDelDia['id_qr'] : null;

                    // Verificar si ya existe asistencia para hoy
                    $stmtAsis = $db->prepare("SELECT * FROM asistencias WHERE id_proceso = :id_proceso AND fecha = :fecha LIMIT 1");
                    $stmtAsis->execute([':id_proceso' => $proceso['id_proceso'], ':fecha' => $hoy]);
                    $asistenciaHoy = $stmtAsis->fetch();

                    if (!$asistenciaHoy) {
                        // REGISTRAR ENTRADA
                        $stmtIns = $db->prepare("INSERT INTO asistencias (id_proceso, id_qr, fecha, hora_entrada, estado, observacion) 
                                                 VALUES (:id_proceso, :id_qr, :fecha, :hora_entrada, 'PRESENTE', 'Marcado biométrico/QR Entrada')");
                        $stmtIns->execute([
                            ':id_proceso'     => $proceso['id_proceso'],
                            ':id_qr'          => $idQrActual,
                            ':fecha'          => $hoy,
                            ':hora_entrada'   => $ahora
                        ]);

                        $mensaje = "¡ENTRADA REGISTRADA! " . $pasante['nombres'] . " " . $pasante['apellidos'] . " a las $ahora.";
                        $tipoMensaje = 'success';
                        $datosMarcado = [
                            'tipo' => 'ENTRADA',
                            'hora' => $ahora,
                            'pasante' => $pasante['nombres'] . ' ' . $pasante['apellidos'],
                            'ci' => $pasante['ci'],
                            'modalidad' => $proceso['modalidad']
                        ];
                    } else if (empty($asistenciaHoy['hora_salida'])) {
                        // REGISTRAR SALIDA
                        $stmtUpd = $db->prepare("UPDATE asistencias SET hora_salida = :hora_salida, observacion = CONCAT(COALESCE(observacion,''), ' | Marcado QR Salida') 
                                                 WHERE id_asistencia = :id_asistencia");
                        $stmtUpd->execute([
                            ':hora_salida'   => $ahora,
                            ':id_asistencia' => $asistenciaHoy['id_asistencia']
                        ]);

                        // Calcular horas cumplidas hoy
                        $diffSegundos = strtotime($ahora) - strtotime($asistenciaHoy['hora_entrada']);
                        $horasHoy = round($diffSegundos / 3600.0, 2);

                        $mensaje = "¡SALIDA REGISTRADA! " . $pasante['nombres'] . " " . $pasante['apellidos'] . " a las $ahora. Total de hoy: $horasHoy hrs.";
                        $tipoMensaje = 'success';
                        $datosMarcado = [
                            'tipo' => 'SALIDA',
                            'hora' => $ahora,
                            'hora_entrada' => $asistenciaHoy['hora_entrada'],
                            'horas_trabajadas' => $horasHoy,
                            'pasante' => $pasante['nombres'] . ' ' . $pasante['apellidos'],
                            'ci' => $pasante['ci'],
                            'modalidad' => $proceso['modalidad']
                        ];
                    } else {
                        // Ya completó ambas marcaciones
                        $mensaje = "El pasante " . $pasante['nombres'] . " ya completó su Entrada (" . $asistenciaHoy['hora_entrada'] . ") y Salida (" . $asistenciaHoy['hora_salida'] . ") de hoy.";
                        $tipoMensaje = 'info';
                    }
                }
            }
        } catch (Exception $e) {
            $mensaje = 'Error al registrar asistencia: ' . $e->getMessage();
            $tipoMensaje = 'danger';
        }
    }
}

// Obtener asistencias registradas el día de hoy
$asistenciasHoyLista = $db->query("SELECT a.*, p.id_pasante, p.nombres, p.apellidos, p.ci, m.nombre AS modalidad,
                                          ROUND(TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0, 2) AS horas_calculadas
                                   FROM asistencias a
                                   INNER JOIN procesos pr ON a.id_proceso = pr.id_proceso
                                   INNER JOIN pasantes p ON pr.id_pasante = p.id_pasante
                                   INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                                   WHERE a.fecha = CURDATE()
                                   ORDER BY a.id_asistencia DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

    <main class="content-body">
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipoMensaje ?>" id="alertaMarcacion">
                <i class="fa-solid fa-<?= $tipoMensaje === 'success' ? 'circle-check' : ($tipoMensaje === 'info' ? 'circle-info' : 'triangle-exclamation') ?>"></i>
                <span><?= htmlspecialchars($mensaje) ?></span>
            </div>
        <?php endif; ?>

        <!-- Pestañas de Navegación del Módulo QR -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px; flex-wrap: wrap;">
            <button type="button" class="btn tab-btn active" id="tabBtnScanner" onclick="cambiarTab('scanner')">
                <i class="fa-solid fa-camera"></i> Escáner por Cámara &amp; Teclado
            </button>
            <button type="button" class="btn btn-outline tab-btn" id="tabBtnTotem" onclick="cambiarTab('totem')">
                <i class="fa-solid fa-tv"></i> Tótem CCDB - QR del Día
            </button>
            <a href="../pasantes/credencial_qr.php" class="btn btn-outline" style="margin-left: auto;">
                <i class="fa-solid fa-id-card-clip"></i> Carnets &amp; Credenciales QR
            </a>
        </div>

        <!-- SECCIÓN 1: ESCÁNER POR CÁMARA Y MARCADO -->
        <div id="seccionScanner">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                <!-- Panel de Marcado y Cámara HTML5 -->
                <div class="card" style="text-align: center;">
                    <div class="action-icon-circle" style="margin: 0 auto 14px auto; width: 68px; height: 68px; background: #e0f2fe; color: var(--primary-blue);">
                        <i class="fa-solid fa-qrcode" style="font-size: 2rem;"></i>
                    </div>
                    <h3 style="color: var(--primary-blue); font-weight: 800; margin-bottom: 6px;">
                        Módulo de Marcado QR / CI
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 16px;">
                        Escanee el carnet con la cámara de su dispositivo, use un lector USB de código de barras o escriba el CI.
                    </p>

                    <!-- Botón para Iniciar / Detener Cámara -->
                    <div style="margin-bottom: 16px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-primary" id="btnToggleCamera" onclick="toggleCamaraQR()">
                            <i class="fa-solid fa-camera" id="iconCamBtn"></i> <span id="textCamBtn">Activar Cámara / Escáner QR</span>
                        </button>
                        <select id="selectCamara" class="form-control" style="max-width: 220px; display: none;" onchange="cambiarCamaraSeleccionada()">
                            <option value="">Seleccionar cámara...</option>
                        </select>
                    </div>

                    <!-- Contenedor del Visor de la Cámara -->
                    <div id="cameraPreviewWrapper" style="display: none; margin: 0 auto 16px auto; max-width: 360px; border-radius: 14px; overflow: hidden; border: 2px solid var(--primary-blue); background: #000000; position: relative;">
                        <div id="reader" style="width: 100%;"></div>
                        <div id="cameraScanLine" style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #22c55e; box-shadow: 0 0 8px #22c55e; animation: scanAnimation 2s infinite ease-in-out;"></div>
                    </div>

                    <!-- Formulario de Entrada Manual / Lector Pistola -->
                    <form method="POST" action="registrar_qr.php" id="formQR">
                        <div class="form-group" style="max-width: 380px; margin: 0 auto 16px auto;">
                            <div class="input-with-icon">
                                <i class="fa-solid fa-id-card"></i>
                                <input type="text" name="codigo_ci" id="codigo_ci" class="form-control" placeholder="Escanear QR o ingresar CI..." autofocus required style="text-align: center; font-size: 1.15rem; font-weight: 700; letter-spacing: 1px;">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-danger" style="padding: 12px 28px; font-size: 1rem;">
                            <i class="fa-solid fa-fingerprint"></i> Registrar Marcación
                        </button>
                    </form>

                    <div style="margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--border-color); display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="simularPasantePrueba('8329410')">
                            <i class="fa-solid fa-vial"></i> Simular CI (8329410)
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="simularPasantePrueba('CCDB-CI-8329410')">
                            <i class="fa-solid fa-qrcode"></i> Simular QR Carnet
                        </button>
                    </div>
                </div>

                <!-- Resumen de Marcación en Vivo y Reloj -->
                <div class="card">
                    <h3 class="card-title" style="margin-bottom: 16px;">
                        <i class="fa-solid fa-clock" style="color: var(--primary-red); margin-right: 6px;"></i>
                        Reloj Institucional &amp; Estado
                    </h3>

                    <div style="background: #f8fafc; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 20px; border: 1px solid var(--border-color);">
                        <div id="liveClock" style="font-size: 2.3rem; font-weight: 900; color: var(--primary-blue); font-variant-numeric: tabular-nums;">
                            <?= date('H:i:s') ?>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-top: 4px;">
                            <?= date('d/m/Y') ?> &bull; Centro Cultural Don Bosco
                        </div>
                    </div>

                    <?php if ($datosMarcado): ?>
                        <div style="background: #dcfce7; border-left: 4px solid #16a34a; padding: 18px; border-radius: 10px; animation: pulseSuccess 0.5s ease;">
                            <div style="font-size: 0.82rem; color: #15803d; font-weight: 800; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-circle-check"></i> ÚLTIMA MARCACIÓN PROCESADA
                            </div>
                            <div style="font-size: 1.15rem; font-weight: 800; color: #14532d; margin: 6px 0 2px 0;">
                                <?= htmlspecialchars($datosMarcado['pasante']) ?>
                            </div>
                            <div style="font-size: 0.86rem; color: #166534;">
                                CI: <strong><?= htmlspecialchars($datosMarcado['ci']) ?></strong> &bull; Modalidad: <strong><?= htmlspecialchars($datosMarcado['modalidad']) ?></strong>
                            </div>
                            <div style="font-size: 0.92rem; color: #166534; margin-top: 8px; font-weight: 700;">
                                <?= $datosMarcado['tipo'] === 'ENTRADA' ? '<i class="fa-solid fa-arrow-right-to-bracket"></i>' : '<i class="fa-solid fa-arrow-right-from-bracket"></i>' ?>
                                Marcación: <?= $datosMarcado['tipo'] ?> a las <?= $datosMarcado['hora'] ?>
                                <?php if (isset($datosMarcado['horas_trabajadas'])): ?>
                                    &bull; Horas hoy: <?= $datosMarcado['horas_trabajadas'] ?> hrs
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 25px 20px; color: var(--text-muted); font-size: 0.88rem; background: #fafafa; border-radius: 10px; border: 1px dashed var(--border-color);">
                            <i class="fa-solid fa-expand" style="font-size: 2.2rem; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                            Listo para registrar asistencia.<br>Presente la credencial QR frente a la cámara o use el teclado.
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 20px; padding: 12px 14px; background: #eff6ff; border-radius: 8px; font-size: 0.8rem; color: #1e40af; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-lightbulb" style="font-size: 1rem;"></i>
                        <span>El sistema detecta automáticamente si corresponde a Entrada o Salida según los registros del día.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 2: TÓTEM CCDB - QR DEL DÍA -->
        <div id="seccionTotem" style="display: none; margin-bottom: 24px;">
            <div class="card" style="text-align: center; padding: 30px 20px;">
                <div style="max-width: 600px; margin: 0 auto;">
                    <div class="action-icon-circle" style="margin: 0 auto 14px auto; width: 70px; height: 70px; background: #fef2f2; color: var(--primary-red);">
                        <i class="fa-solid fa-tv" style="font-size: 2rem;"></i>
                    </div>
                    <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--primary-blue); margin-bottom: 6px;">
                        Tótem Institucional - Código QR del Día
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px;">
                        Proyecte esta pantalla en el tótem o recepción del Centro Cultural Don Bosco para que los pasantes registren su ingreso/salida.
                    </p>

                    <!-- QR Dinámico del Día -->
                    <div style="background: #ffffff; padding: 25px; border-radius: 16px; border: 2px solid var(--primary-blue); display: inline-block; box-shadow: 0 10px 25px rgba(13, 59, 102, 0.1); margin-bottom: 20px;">
                        <div id="qrcodeTotem" style="padding: 10px; display: inline-block;"></div>
                        <div style="font-family: monospace; font-size: 1.05rem; font-weight: 800; color: var(--primary-blue); letter-spacing: 1px; margin-top: 10px;">
                            <?= htmlspecialchars($qrDelDia['codigo'] ?? 'CCDB-ASIS-' . date('Ymd')) ?>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
                            Fecha: <?= date('d/m/Y') ?> &bull; Horario: <?= htmlspecialchars($qrDelDia['hora_inicio'] ?? '06:00') ?> - <?= htmlspecialchars($qrDelDia['hora_expiracion'] ?? '22:00') ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #1e40af; margin-top: 6px; font-weight: 600;">
                            El pasante escanea este QR con su celular e inicia sesión para marcar su entrada/salida.
                        </div>
                    </div>

                    <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                        <a href="registrar_qr.php?generar_qr_dia=1" class="btn btn-primary" onclick="return confirm('¿Desea generar un nuevo código QR del día? El anterior quedará expirado.');">
                            <i class="fa-solid fa-arrows-rotate"></i> Regenerar Código del Día
                        </a>
                        <button type="button" class="btn btn-outline" onclick="imprimirTotemQR()">
                            <i class="fa-solid fa-print"></i> Imprimir para Recepción
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asistencias de la Jornada -->
        <div class="card">
            <div class="card-header-flex">
                <h3 class="card-title">
                    <i class="fa-solid fa-list-check" style="margin-right: 8px; color: var(--primary-blue);"></i>
                    Marcaciones del Día de Hoy (<?= date('d/m/Y') ?>)
                </h3>
                <span class="badge badge-info"><?= count($asistenciasHoyLista) ?> registros hoy</span>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>CI</th>
                            <th>Pasante</th>
                            <th>Modalidad</th>
                            <th>Hora Entrada</th>
                            <th>Hora Salida</th>
                            <th>Horas Hoy</th>
                            <th>Estado</th>
                            <th>Credencial</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($asistenciasHoyLista)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    Aún no hay marcaciones registradas en el día de hoy.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($asistenciasHoyLista as $as): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($as['ci']) ?></strong></td>
                                    <td><?= htmlspecialchars($as['nombres'] . ' ' . $as['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($as['modalidad']) ?></td>
                                    <td><span class="badge badge-success"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($as['hora_entrada'] ?? '--:--') ?></span></td>
                                    <td>
                                        <?php if (!empty($as['hora_salida'])): ?>
                                            <span class="badge badge-info"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($as['hora_salida']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #d97706; font-size: 0.82rem; font-weight: 600;"><i class="fa-solid fa-hourglass-half"></i> En jornada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= !empty($as['horas_calculadas']) ? htmlspecialchars($as['horas_calculadas']) . ' hrs' : '-' ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-success"><?= htmlspecialchars($as['estado']) ?></span>
                                    </td>
                                    <td>
                                        <a href="../pasantes/credencial_qr.php?id=<?= $as['id_pasante'] ?? '' ?>" class="btn btn-outline btn-sm" title="Ver Credencial" style="padding: 4px 8px;">
                                            <i class="fa-solid fa-qrcode"></i> Carnet
                                        </a>
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

<!-- Biblioteca html5-qrcode para escaneo por cámara -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<!-- Biblioteca qrcode.js para el Tótem CCDB -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
@keyframes scanAnimation {
    0% { top: 5%; }
    50% { top: 90%; }
    100% { top: 5%; }
}

@keyframes pulseSuccess {
    0% { transform: scale(0.98); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

.tab-btn.active {
    background: var(--primary-blue);
    color: #ffffff;
    border-color: var(--primary-blue);
}
</style>

<script>
// Reloj digital en vivo
setInterval(() => {
    const d = new Date();
    const liveClockEl = document.getElementById('liveClock');
    if (liveClockEl) {
        liveClockEl.textContent = d.toTimeString().split(' ')[0];
    }
}, 1000);

// Cambiar entre pestañas Escáner y Tótem
function cambiarTab(tab) {
    const btnScanner = document.getElementById('tabBtnScanner');
    const btnTotem = document.getElementById('tabBtnTotem');
    const secScanner = document.getElementById('seccionScanner');
    const secTotem = document.getElementById('seccionTotem');

    if (tab === 'totem') {
        btnScanner.classList.remove('active');
        btnScanner.classList.add('btn-outline');
        btnTotem.classList.add('active');
        btnTotem.classList.remove('btn-outline');
        secScanner.style.display = 'none';
        secTotem.style.display = 'block';

        // Detener cámara si estaba activa
        detenerCamaraQR();
    } else {
        btnTotem.classList.remove('active');
        btnTotem.classList.add('btn-outline');
        btnScanner.classList.add('active');
        btnScanner.classList.remove('btn-outline');
        secTotem.style.display = 'none';
        secScanner.style.display = 'block';
    }
}

// Comprobar si vino con parámetro ?tab=totem
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('tab') === 'totem') {
    cambiarTab('totem');
}

// Generar QR para el Tótem
document.addEventListener('DOMContentLoaded', function() {
    const qrTotemContainer = document.getElementById('qrcodeTotem');
    if (qrTotemContainer) {
        new QRCode(qrTotemContainer, {
            text: "<?= htmlspecialchars($qrTotemUrl, ENT_QUOTES) ?>",
            width: 220,
            height: 220,
            colorDark: "#0d3b66",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }
});

// Imprimir vista del QR del Tótem
function imprimirTotemQR() {
    window.print();
}

// Simular escaneo de prueba
function simularPasantePrueba(ci) {
    document.getElementById('codigo_ci').value = ci;
    document.getElementById('formQR').submit();
}

// ========================================================
// ESCANEO POR CÁMARA CON html5-qrcode
// ========================================================
let html5QrCode = null;
let camaraActiva = false;

// Reproducir sonido beep al detectar QR
function reproducirBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.value = 880; // La (A5)
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.2);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.2);
    } catch (e) {
        console.log('AudioContext not allowed or not supported:', e);
    }
}

function onScanSuccess(decodedText, decodedResult) {
    console.log(`Código escaneado: ${decodedText}`);
    reproducirBeep();
    
    // Detener la cámara temporalmente para evitar lecturas duplicadas
    detenerCamaraQR();

    const inputCI = document.getElementById('codigo_ci');
    if (inputCI) {
        inputCI.value = decodedText;
        // Enviar automáticamente el formulario de asistencia
        document.getElementById('formQR').submit();
    }
}

function onScanFailure(error) {
    // Escaneo continuo en búsqueda de QR (ignorar errores de frame vacío)
}

function toggleCamaraQR() {
    if (camaraActiva) {
        detenerCamaraQR();
    } else {
        iniciarCamaraQR();
    }
}

function iniciarCamaraQR() {
    const previewWrapper = document.getElementById('cameraPreviewWrapper');
    const btnToggle = document.getElementById('btnToggleCamera');
    const textCamBtn = document.getElementById('textCamBtn');
    const selectCam = document.getElementById('selectCamara');

    previewWrapper.style.display = 'block';
    textCamBtn.textContent = 'Detener Cámara';
    btnToggle.classList.remove('btn-primary');
    btnToggle.classList.add('btn-danger');

    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
    }

    // Listar cámaras disponibles si no están listadas
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            selectCam.style.display = 'inline-block';
            selectCam.innerHTML = '';
            devices.forEach((device, index) => {
                const opt = document.createElement('option');
                opt.value = device.id;
                opt.text = device.label || `Cámara ${index + 1}`;
                selectCam.appendChild(opt);
            });

            // Usar cámara trasera por defecto si existe (facingMode: environment)
            const cameraId = devices[0].id;
            const config = { fps: 10, qrbox: { width: 240, height: 240 } };

            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanFailure
            ).catch(err => {
                // Fallback a primera cámara ID
                html5QrCode.start(cameraId, config, onScanSuccess, onScanFailure).catch(e => {
                    alert('No se pudo acceder a la cámara: ' + e);
                    detenerCamaraQR();
                });
            });

            camaraActiva = true;
        } else {
            alert('No se detectaron cámaras en este dispositivo.');
            detenerCamaraQR();
        }
    }).catch(err => {
        alert('Permiso de cámara denegado o error: ' + err);
        detenerCamaraQR();
    });
}

function cambiarCamaraSeleccionada() {
    const selectCam = document.getElementById('selectCamara');
    const cameraId = selectCam.value;
    if (!cameraId || !html5QrCode) return;

    html5QrCode.stop().then(() => {
        const config = { fps: 10, qrbox: { width: 240, height: 240 } };
        html5QrCode.start(cameraId, config, onScanSuccess, onScanFailure);
    });
}

function detenerCamaraQR() {
    if (html5QrCode && camaraActiva) {
        html5QrCode.stop().then(() => {
            const previewWrapper = document.getElementById('cameraPreviewWrapper');
            const btnToggle = document.getElementById('btnToggleCamera');
            const textCamBtn = document.getElementById('textCamBtn');
            const selectCam = document.getElementById('selectCamara');

            previewWrapper.style.display = 'none';
            textCamBtn.textContent = 'Activar Cámara / Escáner QR';
            btnToggle.classList.remove('btn-danger');
            btnToggle.classList.add('btn-primary');
            selectCam.style.display = 'none';
            camaraActiva = false;
        }).catch(err => {
            console.error('Error al detener cámara:', err);
            camaraActiva = false;
        });
    }
}
</script>
