<?php
/**
 * Shared attendance-window rules - Centro Cultural Don Bosco
 *
 * Working days are Monday to Saturday, mark window 09:00-12:00:
 * - ENTRADA is only accepted between 09:00:00 and 12:00:00.
 * - SALIDA after 12:00 is capped and recorded as 12:00:00.
 * - Sundays are blocked (no working day).
 */

/**
 * @return array{ok: bool, hora_salida: string, tope_aplicado: bool, error: string}
 */
function ventanaMarcacion(DateTime $ahora) {
    $dow = (int)$ahora->format('N'); // 1 = Monday ... 7 = Sunday
    if ($dow === 7) {
        return ['ok' => false, 'hora_salida' => $ahora->format('H:i:s'), 'tope_aplicado' => false,
                'error' => 'Hoy domingo no hay jornada. El horario es de lunes a sábado, 09:00 a 12:00.'];
    }
    $hora = $ahora->format('H:i:s');
    if ($hora < '09:00:00' || $hora > '12:00:00') {
        if ($hora < '09:00:00') {
            return ['ok' => false, 'hora_salida' => $hora, 'tope_aplicado' => false,
                    'error' => 'Aún no es hora de marcar. La jornada inicia a las 09:00.'];
        }
        // After closing: exits are capped at noon
        return ['ok' => true, 'hora_salida' => '12:00:00', 'tope_aplicado' => true, 'error' => ''];
    }
    return ['ok' => true, 'hora_salida' => $hora, 'tope_aplicado' => false, 'error' => ''];
}

/**
 * Entry is only valid inside the 09:00-12:00 window, Monday to Saturday.
 * @return array{ok: bool, error: string}
 */
function ventanaEntrada(DateTime $ahora) {
    $r = ventanaMarcacion($ahora);
    if (!$r['ok']) {
        return ['ok' => false, 'error' => $r['error']];
    }
    if ($r['tope_aplicado']) {
        return ['ok' => false, 'error' => 'Fuera de horario. La jornada de marcación es de 09:00 a 12:00.'];
    }
    return ['ok' => true, 'error' => ''];
}
