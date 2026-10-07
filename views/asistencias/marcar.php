<?php
/**
 * Mobile self check-in via daily QR - Centro Cultural Don Bosco
 *
 * Flow: pasante scans the Totem daily QR with their phone, logs in,
 * and marks ENTRADA / SALIDA. Identity always comes from the login
 * session (never from the QR); the QR only proves day and place.
 */
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/marcado.php';

$db = Database::getConnection();
$qrToken = trim($_GET['qr'] ?? '');
$result = null; // 'entrada' | 'salida' | 'done' | 'error'
$message = '';
$detail = null;

// 1. Validate the daily QR (must be today's ACTIVE code within working hours)
$qrRow = null;
$qrProblem = '';
if ($qrToken === '') {
    $qrProblem = 'no_qr';
} else {
    try {
        $stmtQr = $db->prepare("SELECT * FROM codigos_qr WHERE codigo = :codigo LIMIT 1");
        $stmtQr->execute([':codigo' => $qrToken]);
        $found = $stmtQr->fetch();
        if (!$found) {
            $qrProblem = 'unknown';
        } elseif ($found['fecha'] !== date('Y-m-d')) {
            $qrProblem = 'not_today';
        } elseif ($found['estado'] !== 'ACTIVO') {
            $qrProblem = 'expired';
        } else {
            $now = date('H:i:s');
            if ($now < $found['hora_inicio'] || $now > $found['hora_expiracion']) {
                $qrProblem = 'out_of_hours';
            } else {
                $qrRow = $found;
            }
        }
    } catch (Exception $e) {
        $qrProblem = 'db_error';
    }
}

// 2. Require login; remember the scanned QR so login returns here
if ($qrProblem === '' && !Auth::check()) {
    $_SESSION['pending_qr'] = $qrToken;
    $_SESSION['flash_error'] = 'Iniciá sesión para marcar tu asistencia.';
    header('Location: ../../login.php');
    exit;
}

// 3. Process the marking (POST from the confirm button, or direct GET when
// remember-me is active so a scan registers entry/exit with no extra taps)
$directMark = $qrProblem === '' && $_SERVER['REQUEST_METHOD'] === 'GET' && Auth::viaRemember() && !isset($_GET['marcado']);
if ($qrProblem === '' && ($_SERVER['REQUEST_METHOD'] === 'POST' || $directMark)) {
    try {
        $user = Auth::user();
        $ci = trim($user['ci'] ?? '');
        $cleanNumeric = preg_replace('/[^0-9]/', '', $ci);

        $stmtP = $db->prepare("SELECT p.* FROM pasantes p
                               WHERE p.ci = :ci
                                  OR (LENGTH(:clean1) >= 4 AND REPLACE(REPLACE(p.ci, '-', ''), ' ', '') = :clean2)
                               LIMIT 1");
        $stmtP->execute([':ci' => $ci, ':clean1' => $cleanNumeric, ':clean2' => $cleanNumeric]);
        $pasante = $stmtP->fetch();

        if (!$pasante) {
            $result = 'error';
            $message = 'Tu usuario no está vinculado a ningún pasante (CI: ' . $ci . '). Pedí en recepción que vinculen tu cuenta.';
        } elseif ($pasante['estado'] !== 'ACTIVO') {
            $result = 'error';
            $message = 'Tu registro de pasante no está ACTIVO (estado: ' . $pasante['estado'] . ').';
        } else {
            $stmtProc = $db->prepare("SELECT pr.*, COALESCE(m.nombre, 'Pasantía') AS modalidad FROM procesos pr
                                      LEFT JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                                      WHERE pr.id_pasante = :id AND pr.estado = 'EN_CURSO'
                                      ORDER BY pr.id_proceso DESC LIMIT 1");
            $stmtProc->execute([':id' => $pasante['id_pasante']]);
            $proceso = $stmtProc->fetch();

            if (!$proceso) {
                $result = 'error';
                $message = 'No tenés un proceso EN_CURSO asignado. Pedí en recepción que te asignen uno en Modalidades.';
            } else {
                $hoy = date('Y-m-d');
                $ahora = date('H:i:s');
                $stmtA = $db->prepare("SELECT * FROM asistencias WHERE id_proceso = :id AND fecha = :fecha LIMIT 1");
                $stmtA->execute([':id' => $proceso['id_proceso'], ':fecha' => $hoy]);
                $asis = $stmtA->fetch();

                if (!$asis) {
                    $vEntrada = ventanaEntrada(new DateTime());
                    if (!$vEntrada['ok']) {
                        $result = 'error';
                        $message = $vEntrada['error'];
                    } else {
                        $stmtIns = $db->prepare("INSERT INTO asistencias (id_proceso, id_qr, fecha, hora_entrada, estado, observacion)
                                                 VALUES (:proc, :qr, :fecha, :hora, 'PRESENTE', 'Auto-marcado móvil ENTRADA')");
                        $stmtIns->execute([':proc' => $proceso['id_proceso'], ':qr' => $qrRow['id_qr'], ':fecha' => $hoy, ':hora' => $ahora]);
                        header('Location: marcar.php?qr=' . urlencode($qrToken) . '&marcado=entrada');
                        exit;
                    }
                } elseif (empty($asis['hora_salida'])) {
                    $vSalida = ventanaMarcacion(new DateTime());
                    if (!$vSalida['ok']) {
                        $result = 'error';
                        $message = $vSalida['error'];
                    } else {
                        $horaSalida = $vSalida['hora_salida'];
                        $stmtUpd = $db->prepare("UPDATE asistencias SET hora_salida = :hora,
                                                 observacion = CONCAT(COALESCE(observacion, ''), ' | Auto-marcado móvil SALIDA')
                                                 WHERE id_asistencia = :id");
                        $stmtUpd->execute([':hora' => $horaSalida, ':id' => $asis['id_asistencia']]);
                        header('Location: marcar.php?qr=' . urlencode($qrToken) . '&marcado=salida');
                        exit;
                    }
                } else {
                    header('Location: marcar.php?qr=' . urlencode($qrToken) . '&marcado=done');
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        $result = 'error';
        $message = 'Error al registrar: ' . $e->getMessage();
    }
}

// 4. Read back today's receipt for display (GET after redirect, or current state)
if ($qrProblem === '' && $_SERVER['REQUEST_METHOD'] === 'GET' && Auth::check()) {
    $flag = $_GET['marcado'] ?? '';
    if (in_array($flag, ['entrada', 'salida', 'done'], true)) {
        $result = $flag;
    }
    try {
        $user = Auth::user();
        $stmtP = $db->prepare("SELECT id_pasante FROM pasantes WHERE ci = :ci LIMIT 1");
        $stmtP->execute([':ci' => trim($user['ci'] ?? '')]);
        if ($row = $stmtP->fetch()) {
            $stmtA = $db->prepare("SELECT a.*, m.nombre AS modalidad FROM asistencias a
                                   INNER JOIN procesos pr ON a.id_proceso = pr.id_proceso
                                   INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                                   WHERE pr.id_pasante = :id AND a.fecha = CURDATE() LIMIT 1");
            $stmtA->execute([':id' => $row['id_pasante']]);
            $detail = $stmtA->fetch() ?: null;
        }
    } catch (Exception $e) {
        // Display-only lookup; marking already succeeded
    }
}

$qrErrors = [
    'no_qr'       => 'Escaneá el QR del día expuesto en recepción para marcar tu asistencia.',
    'unknown'     => 'Ese código no existe. Escaneá el QR vigente del Tótem.',
    'not_today'   => 'Ese QR es de otro día. Escaneá el QR de hoy.',
    'expired'     => 'Ese QR fue regenerado y ya expiró. Escaneá el nuevo QR del Tótem.',
    'out_of_hours' => 'Fuera del horario de marcado (06:00 a 22:00).',
    'db_error'    => 'No se pudo validar el QR. Probá de nuevo.',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marcar asistencia - CCDB</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0d3b66; margin: 0; padding: 16px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .card { background: #fff; border-radius: 16px; padding: 28px 22px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 12px 32px rgba(0,0,0,.25); }
  .logo { font-weight: 900; color: #0d3b66; font-size: 1.05rem; margin-bottom: 2px; }
  .sub { color: #64748b; font-size: .82rem; margin-bottom: 18px; }
  .ok { background: #dcfce7; border-left: 4px solid #16a34a; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
  .ok h2 { color: #14532d; margin: 0 0 6px 0; font-size: 1.25rem; }
  .err { background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
  .err h2 { color: #7f1d1d; margin: 0 0 6px 0; font-size: 1.15rem; }
  .info { background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
  p { color: #334155; font-size: .92rem; }
  .big-btn { display: block; width: 100%; background: #c81d25; color: #fff; border: none; border-radius: 10px; padding: 16px; font-size: 1.05rem; font-weight: 800; cursor: pointer; }
  .meta { font-size: .8rem; color: #64748b; margin-top: 12px; }
  strong { color: #0d3b66; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">CENTRO CULTURAL DON BOSCO</div>
  <div class="sub">Marcación de asistencia &bull; <?= date('d/m/Y') ?></div>

  <?php if ($qrProblem !== ''): ?>
    <div class="err"><h2>No se puede marcar</h2><p><?= htmlspecialchars($qrErrors[$qrProblem] ?? 'QR inválido.') ?></p></div>
  <?php elseif ($result === 'error'): ?>
    <div class="err"><h2>No se pudo registrar</h2><p><?= htmlspecialchars($message) ?></p></div>
  <?php elseif ($result === 'entrada'): ?>
    <div class="ok"><h2>¡ENTRADA registrada!</h2>
      <p>Hora: <strong><?= htmlspecialchars($detail['hora_entrada'] ?? date('H:i:s')) ?></strong><br>Modalidad: <strong><?= htmlspecialchars($detail['modalidad'] ?? '') ?></strong></p>
      <p>Volvé a escanear el QR al salir para registrar tu SALIDA.</p></div>
  <?php elseif ($result === 'salida'): ?>
    <div class="ok"><h2>¡SALIDA registrada!</h2>
      <?php $h = (!empty($detail['hora_entrada']) && !empty($detail['hora_salida'])) ? round((strtotime($detail['hora_salida']) - strtotime($detail['hora_entrada'])) / 3600, 2) : null; ?>
      <p>Entrada: <strong><?= htmlspecialchars($detail['hora_entrada'] ?? '') ?></strong> &bull; Salida: <strong><?= htmlspecialchars($detail['hora_salida'] ?? '') ?></strong><br>
      <?php if ($h !== null): ?>Hoy trabajaste: <strong><?= htmlspecialchars((string)$h) ?> hrs</strong><?php endif; ?></p></div>
  <?php elseif ($result === 'done'): ?>
    <div class="info"><h2 style="color:#1e40af;margin:0 0 6px 0;">Jornada completa</h2>
      <p>Hoy ya registraste entrada (<?= htmlspecialchars($detail['hora_entrada'] ?? '') ?>) y salida (<?= htmlspecialchars($detail['hora_salida'] ?? '') ?>).</p></div>
  <?php else: ?>
    <p>Hola <strong><?= htmlspecialchars(Auth::user()['nombre'] ?? '') ?></strong>, estás por marcar tu asistencia de hoy.</p>
    <form method="POST" action="marcar.php?qr=<?= htmlspecialchars(urlencode($qrToken)) ?>">
      <button type="submit" class="big-btn">Marcar mi asistencia</button>
    </form>
    <div class="meta">El sistema detecta solo si es tu ENTRADA o tu SALIDA.</div>
  <?php endif; ?>

  <?php if ($qrProblem === '' && $result !== null && $result !== 'error' && $detail): ?>
    <div class="meta">CI: <?= htmlspecialchars(Auth::user()['ci'] ?? '') ?></div>
  <?php endif; ?>
</div>
</body>
</html>
