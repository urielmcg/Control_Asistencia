<?php
/**
 * Shared attendance-window rules - Centro Cultural Don Bosco
 *
 * Working days are Monday to Saturday, mark window 09:00-12:00 with a
 * 30-minute margin on both ends:
 * - ENTRADA accepted from 08:30; before 09:00 it is recorded as 09:00:00.
 * - SALIDA accepted until 12:30; after 12:00 it is capped at 12:00:00.
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
    if ($hora < '08:30:00' || $hora > '12:30:00') {
        return ['ok' => false, 'hora_salida' => $hora, 'tope_aplicado' => false,
                'error' => 'Fuera del horario permitido (08:30 a 12:30).'];
    }
    if ($hora > '12:00:00') {
        // After closing: exits are capped at noon
        return ['ok' => true, 'hora_salida' => '12:00:00', 'tope_aplicado' => true, 'error' => ''];
    }
    return ['ok' => true, 'hora_salida' => $hora, 'tope_aplicado' => false, 'error' => ''];
}

/**
 * Entry is valid Monday to Saturday from 08:30 to 12:00.
 * Early arrivals (08:30-09:00) are recorded at 09:00:00 with no sanction.
 * @return array{ok: bool, hora_entrada: string, ajustada: bool, error: string}
 */
function ventanaEntrada(DateTime $ahora) {
    $dow = (int)$ahora->format('N'); // 1 = Monday ... 7 = Sunday
    if ($dow === 7) {
        return ['ok' => false, 'hora_entrada' => $ahora->format('H:i:s'), 'ajustada' => false,
                'error' => 'Hoy domingo no hay jornada. El horario es de lunes a sábado, 09:00 a 12:00.'];
    }
    $hora = $ahora->format('H:i:s');
    if ($hora < '08:30:00' || $hora > '12:00:00') {
        return ['ok' => false, 'hora_entrada' => $hora, 'ajustada' => false,
                'error' => 'Fuera del horario permitido (08:30 a 12:00).'];
    }
    if ($hora < '09:00:00') {
        return ['ok' => true, 'hora_entrada' => '09:00:00', 'ajustada' => true, 'error' => ''];
    }
    return ['ok' => true, 'hora_entrada' => $hora, 'ajustada' => false, 'error' => ''];
}
