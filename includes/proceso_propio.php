<?php
/**
 * Alcance propio: procesos del usuario logueado.
 *
 * Un usuario limitado puede ser pasante puro (fila en pasantes) o solo
 * modalidad (solicitud sin pasante). En ambos casos sus procesos se
 * resuelven por CI (pasante o solicitud) o por el usuario de la solicitud.
 *
 * @return int[]|null null = staff (sin restricción); array vacío = sin procesos propios.
 */
function procesosPropios(PDO $db): ?array {
    if (!Auth::check() || Auth::isStaff()) {
        return null;
    }
    $u = Auth::user();
    $ci = trim($u['ci'] ?? '');
    $idu = (int)($u['id'] ?? 0);
    $stmt = $db->prepare("SELECT DISTINCT pr.id_proceso
                          FROM procesos pr
                          LEFT JOIN pasantes p ON pr.id_pasante = p.id_pasante
                          LEFT JOIN solicitudes_modalidad s ON s.id_proceso = pr.id_proceso
                          WHERE p.ci = :ci OR s.ci = :ci2 OR s.id_usuario = :idu");
    $stmt->execute([':ci' => $ci, ':ci2' => $ci, ':idu' => $idu]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
