<?php
/**
 * Notificaciones de cumplimiento - Centro Cultural Don Bosco
 *
 * Al registrar la SALIDA se evalúa si el proceso completó su meta:
 * - Pasantía / Trabajo Dirigido: horas acumuladas >= requeridas (vista).
 * - Proyecto de Grado: días asistidos >= meta (meses * 26).
 * Cumplido => una notificación COMPLETADO (dedup por proceso no leída),
 * visible para el interesado y para el staff en la campanita.
 */
require_once __DIR__ . '/proceso_propio.php';

function nombreProceso(PDO $db, int $idProceso): array {
    $stmt = $db->prepare("SELECT p.id_pasante, p.nombres, p.apellidos, p.ci, s.postulante AS sol_postulante, s.ci AS sol_ci
                          FROM procesos pr
                          LEFT JOIN pasantes p ON pr.id_pasante = p.id_pasante
                          LEFT JOIN solicitudes_modalidad s ON s.id_proceso = pr.id_proceso
                          WHERE pr.id_proceso = :id LIMIT 1");
    $stmt->execute([':id' => $idProceso]);
    $r = $stmt->fetch() ?: [];
    $nom = trim(($r['nombres'] ?? '') . ' ' . ($r['apellidos'] ?? ''));
    if ($nom === '') {
        $nom = $r['sol_postulante'] ?? 'Proceso #' . $idProceso;
    }
    return ['nombre' => $nom, 'id_pasante' => isset($r['id_pasante']) && $r['id_pasante'] !== null ? (int)$r['id_pasante'] : null];
}

/**
 * Evalúa y crea la notificación de completado si corresponde.
 * @return string|null Mensaje creado, o null si no correspondía.
 */
function notificarCompletado(PDO $db, int $idProceso): ?string {
    $stmtD = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE id_proceso = :id AND tipo = 'COMPLETADO' AND leida = 0");
    $stmtD->execute([':id' => $idProceso]);
    if ((int)$stmtD->fetchColumn() > 0) {
        return null;
    }

    $msg = null;
    // 1) Meta por horas (Pasantía / Trabajo Dirigido)
    $stmtV = $db->prepare("SELECT modalidad, horas_acumuladas, horas_requeridas, porcentaje_completado
                           FROM vista_horas_proceso WHERE id_proceso = :id LIMIT 1");
    $stmtV->execute([':id' => $idProceso]);
    if ($v = $stmtV->fetch()) {
        if ($v['horas_requeridas'] !== null && (float)$v['porcentaje_completado'] >= 100) {
            $who = nombreProceso($db, $idProceso);
            $msg = "¡Tiempo requerido completado! " . $who['nombre'] . " acumuló " . $v['horas_acumuladas']
                . " de " . $v['horas_requeridas'] . " hrs (" . $v['modalidad'] . "). Ya puede tramitar certificación.";
        }
    }

    // 2) Meta por días (Proyecto de Grado)
    if ($msg === null) {
        $stmtP = $db->prepare("SELECT pr.id_proceso, pr.fecha_inicio, pr.fecha_fin
                               FROM procesos pr
                               INNER JOIN modalidades m ON pr.id_modalidad = m.id_modalidad
                               WHERE pr.id_proceso = :id AND pr.estado = 'EN_CURSO' AND m.nombre = 'Proyecto de Grado' LIMIT 1");
        $stmtP->execute([':id' => $idProceso]);
        if ($pg = $stmtP->fetch()) {
            $metaMeses = 6;
            if (!empty($pg['fecha_inicio']) && !empty($pg['fecha_fin'])) {
                $dd1 = new DateTime($pg['fecha_inicio']);
                $dd2 = new DateTime($pg['fecha_fin']);
                if ($dd2 > $dd1) {
                    $ddiff = $dd1->diff($dd2);
                    $metaMeses = max(1, $ddiff->y * 12 + $ddiff->m);
                }
            }
            $metaDias = $metaMeses * 26;
            $stmtDias = $db->prepare("SELECT COUNT(DISTINCT CASE WHEN DAYOFWEEK(fecha) <> 1 AND hora_entrada IS NOT NULL THEN fecha END) FROM asistencias WHERE id_proceso = :id");
            $stmtDias->execute([':id' => $idProceso]);
            $dias = (int)$stmtDias->fetchColumn();
            if ($dias >= $metaDias) {
                $who = nombreProceso($db, $idProceso);
                $msg = "¡Meta de días cumplida! " . $who['nombre'] . " asistió $dias de $metaDias días (Proyecto de Grado). Ya puede tramitar certificación.";
            }
        }
    }

    if ($msg === null) {
        return null;
    }
    $who = $who ?? nombreProceso($db, $idProceso);
    $stmtI = $db->prepare("INSERT INTO notificaciones (id_pasante, id_proceso, titulo, mensaje, tipo)
                           VALUES (:pas, :proc, 'Tiempo completado', :msg, 'COMPLETADO')");
    $stmtI->execute([':pas' => $who['id_pasante'], ':proc' => $idProceso, ':msg' => $msg]);
    return $msg;
}

/** Alcance de lectura: null = staff (todo), array = procesos propios. */
function alcanceNotificaciones(PDO $db): ?array {
    if (!Auth::check() || Auth::isStaff()) {
        return null;
    }
    return procesosPropios($db) ?? [];
}

function contarNoLeidas(PDO $db): int {
    $alc = alcanceNotificaciones($db);
    if ($alc === null) {
        return (int)$db->query("SELECT COUNT(*) FROM notificaciones WHERE leida = 0")->fetchColumn();
    }
    if (empty($alc)) {
        return 0;
    }
    $ph = [];
    $params = [];
    foreach ($alc as $i => $idp) {
        $k = ':np' . $i;
        $ph[] = $k;
        $params[$k] = $idp;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE leida = 0 AND id_proceso IN (" . implode(',', $ph) . ")");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function ultimasNoLeidas(PDO $db, int $lim = 8): array {
    $alc = alcanceNotificaciones($db);
    $where = "n.leida = 0";
    $params = [];
    if ($alc !== null) {
        if (empty($alc)) {
            return [];
        }
        $ph = [];
        foreach ($alc as $i => $idp) {
            $k = ':nu' . $i;
            $ph[] = $k;
            $params[$k] = $idp;
        }
        $where .= " AND n.id_proceso IN (" . implode(',', $ph) . ")";
    }
    $stmt = $db->prepare("SELECT n.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.nombres, ''), ' ', COALESCE(p.apellidos, ''))), ''), s.postulante) AS quien
                          FROM notificaciones n
                          LEFT JOIN pasantes p ON p.id_pasante = n.id_pasante
                          LEFT JOIN procesos pr ON pr.id_proceso = n.id_proceso
                          LEFT JOIN solicitudes_modalidad s ON s.id_proceso = n.id_proceso
                          WHERE $where ORDER BY n.id_notificacion DESC LIMIT " . max(1, $lim));
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function marcarLeidas(PDO $db): int {
    $alc = alcanceNotificaciones($db);
    if ($alc === null) {
        return $db->exec("UPDATE notificaciones SET leida = 1 WHERE leida = 0");
    }
    if (empty($alc)) {
        return 0;
    }
    $ph = [];
    $params = [];
    foreach ($alc as $i => $idp) {
        $k = ':nm' . $i;
        $ph[] = $k;
        $params[$k] = $idp;
    }
    $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE leida = 0 AND id_proceso IN (" . implode(',', $ph) . ")");
    $stmt->execute($params);
    return $stmt->rowCount();
}
