<?php
/**
 * Shared attendance-window rules - Centro Cultural Don Bosco
 *
 * El horario sale de proceso_dias (turno por día, lun-sáb con turnos
 * administrables), con fallback a procesos.id_turno y luego a la ventana
 * histórica Mañana 09:00-12:00. Margen de cortesía: 30 min antes del inicio
 * y 30 min después del fin.
 *
 * Nunca se bloquea por horario: fuera de programa se registra igual con
 * fuera_horario=true para que el historial lo marque.
 */

const MARCADO_MARGEN_MIN = 30;

/**
 * Turno programado del proceso para el día ISO ($dowN: 1=Lun ... 7=Dom).
 * null = día libre (incluido domingo).
 */
function turnoProgramado(PDO $db, int $idProceso, int $dowN): ?array {
    if ($dowN < 1 || $dowN > 6) {
        return null;
    }
    try {
        $stmt = $db->prepare("SELECT t.nombre, t.hora_inicio, t.hora_fin
                              FROM proceso_dias pd
                              INNER JOIN turnos t ON t.id_turno = pd.id_turno
                              WHERE pd.id_proceso = :id AND pd.dia_semana = :dia LIMIT 1");
        $stmt->execute([':id' => $idProceso, ':dia' => $dowN]);
        if ($row = $stmt->fetch()) {
            return $row;
        }
    } catch (Exception $e) {
        // Sin tabla proceso_dias (base sin migrar): se sigue con fallbacks
    }
    $stmt2 = $db->prepare("SELECT t.nombre, t.hora_inicio, t.hora_fin
                           FROM procesos pr
                           INNER JOIN turnos t ON t.id_turno = pr.id_turno
                           WHERE pr.id_proceso = :id LIMIT 1");
    $stmt2->execute([':id' => $idProceso]);
    if ($row = $stmt2->fetch()) {
        return $row;
    }
    return ['nombre' => 'Mañana', 'hora_inicio' => '09:00:00', 'hora_fin' => '12:00:00'];
}

function margenVentana(string $hora, int $minutos, string $sentido): string {
    $ts = strtotime($hora);
    $ts = ($sentido === 'antes') ? $ts - $minutos * 60 : $ts + $minutos * 60;
    return date('H:i:s', $ts);
}

/**
 * @return array{ok: bool, hora_entrada: string, ajustada: bool, fuera_horario: bool, turno: ?array, error: string}
 */
function ventanaEntrada(PDO $db, int $idProceso, DateTime $ahora) {
    $dow = (int)$ahora->format('N');
    $hora = $ahora->format('H:i:s');
    $t = turnoProgramado($db, $idProceso, $dow);
    if ($t === null) {
        return ['ok' => true, 'hora_entrada' => $hora, 'ajustada' => false, 'fuera_horario' => true, 'turno' => null, 'error' => ''];
    }
    $ini = substr($t['hora_inicio'], 0, 8);
    $fin = substr($t['hora_fin'], 0, 8);
    if ($hora < margenVentana($ini, MARCADO_MARGEN_MIN, 'antes') || $hora > margenVentana($fin, MARCADO_MARGEN_MIN, 'despues')) {
        return ['ok' => true, 'hora_entrada' => $hora, 'ajustada' => false, 'fuera_horario' => true, 'turno' => $t, 'error' => ''];
    }
    if ($hora < $ini) {
        return ['ok' => true, 'hora_entrada' => $ini, 'ajustada' => true, 'fuera_horario' => false, 'turno' => $t, 'error' => ''];
    }
    return ['ok' => true, 'hora_entrada' => $hora, 'ajustada' => false, 'fuera_horario' => false, 'turno' => $t, 'error' => ''];
}

/**
 * @return array{ok: bool, hora_salida: string, tope_aplicado: bool, fuera_horario: bool, turno: ?array, error: string}
 */
function ventanaMarcacion(PDO $db, int $idProceso, DateTime $ahora) {
    $dow = (int)$ahora->format('N');
    $hora = $ahora->format('H:i:s');
    $t = turnoProgramado($db, $idProceso, $dow);
    if ($t === null) {
        return ['ok' => true, 'hora_salida' => $hora, 'tope_aplicado' => false, 'fuera_horario' => true, 'turno' => null, 'error' => ''];
    }
    $ini = substr($t['hora_inicio'], 0, 8);
    $fin = substr($t['hora_fin'], 0, 8);
    if ($hora < margenVentana($ini, MARCADO_MARGEN_MIN, 'antes') || $hora > margenVentana($fin, MARCADO_MARGEN_MIN, 'despues')) {
        return ['ok' => true, 'hora_salida' => $hora, 'tope_aplicado' => false, 'fuera_horario' => true, 'turno' => $t, 'error' => ''];
    }
    if ($hora > $fin) {
        return ['ok' => true, 'hora_salida' => $fin, 'tope_aplicado' => true, 'fuera_horario' => false, 'turno' => $t, 'error' => ''];
    }
    return ['ok' => true, 'hora_salida' => $hora, 'tope_aplicado' => false, 'fuera_horario' => false, 'turno' => $t, 'error' => ''];
}
