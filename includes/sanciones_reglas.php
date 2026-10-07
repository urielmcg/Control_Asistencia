<?php
/**
 * Sanctions rule engine - Cuadro de sanciones por atraso y ausencia (CCDB)
 *
 * Late arrival (ATRASO): material is computed from late minutes and the
 * occurrence count for the process. Minutes only count within the first 10;
 * beyond 10 it becomes an excess case (book printing, +15 days).
 * Absence (FALTA): printed/bound folio, zero hour discount, due next Monday.
 */

/**
 * @return array{material: string, sorteo: string|null, exceso: bool}
 */
function reglaAtraso($minutos, $reincidencia) {
    $m = (int)$minutos;
    $r = max(1, (int)$reincidencia);

    if ($m > 10) {
        return [
            'material' => 'Impresión/empaste de libro, entrega a los 15 días.',
            'sorteo'   => null,
            'exceso'   => true,
        ];
    }

    if ($m >= 9) {
        $sorteos = [1 => 'MATERIALES', 2 => 'LIMPIEZA'];
        return [
            'material' => 'SORTEO "' . ($sorteos[$r] ?? 'VARIOS') . '" (ver lista oficial en recepción).',
            'sorteo'   => $sorteos[$r] ?? 'VARIOS',
            'exceso'   => false,
        ];
    }

    if ($r === 1) {
        if ($m <= 5) {
            $rollos = 4 * $m;
            $material = "$rollos rollos de papel higiénico ($m min x 4).";
        } else {
            $rollos = 4 * $m;
            $cart = 2 * $m;
            $material = "$rollos rollos de papel higiénico + $cart cartulinas de color ($m min).";
        }
    } elseif ($r === 2) {
        if ($m <= 5) {
            $rollos = 6 * $m;
            $material = "$rollos rollos de papel higiénico ($m min x 6).";
        } else {
            $rollos = 6 * $m;
            $marc = 2 * $m;
            $material = "$rollos rollos de papel higiénico + $marc marcadores negros de pizarra ($m min).";
        }
    } else {
        if ($m <= 5) {
            $scotch = 1 * $m;
            $material = "$scotch scotch grande ($m min x 1).";
        } else {
            $scotch = 1 * $m;
            $marc = 3 * $m;
            $material = "$scotch scotch grande + $marc marcadores negros de pizarra ($m min).";
        }
    }

    return ['material' => $material, 'sorteo' => null, 'exceso' => false];
}

/**
 * Due date: next Monday for late/absence, +15 days for excess.
 */
function fechaEntregaSancion(DateTime $fecha, $exceso) {
    if ($exceso) {
        $d = clone $fecha;
        return $d->modify('+15 days')->format('Y-m-d');
    }
    $d = clone $fecha;
    return $d->modify('next monday')->format('Y-m-d');
}

/**
 * Automatically registers an ATRASO sanction when entry is late.
 * Late = minutes past 09:00 (floor). On-time entries return null.
 * Skips when an ATRASO sanction already exists for the process and date.
 *
 * @return string|null Human-readable summary of the created sanction.
 */
function registrarSancionAtrasoAuto($db, $idProceso, $horaEntrada, $fecha) {
    $ref = strtotime('09:00:00');
    $ent = strtotime($horaEntrada);
    if ($ent === false || $ent <= $ref) {
        return null;
    }
    $minutos = (int)floor(($ent - $ref) / 60);
    if ($minutos < 1) {
        return null;
    }

    $stmtT = $db->prepare("SELECT id_tipo_sancion FROM tipos_sancion WHERE nombre = 'ATRASO' LIMIT 1");
    $stmtT->execute();
    $idTipo = $stmtT->fetchColumn();
    if (!$idTipo) {
        return null;
    }

    $stmtDup = $db->prepare("SELECT COUNT(*) FROM sanciones WHERE id_proceso = :id AND fecha = :fecha AND id_tipo_sancion = :tipo");
    $stmtDup->execute([':id' => $idProceso, ':fecha' => $fecha, ':tipo' => $idTipo]);
    if ((int)$stmtDup->fetchColumn() > 0) {
        return null;
    }

    $stmtReinc = $db->prepare("SELECT COUNT(*) FROM sanciones WHERE id_proceso = :id AND id_tipo_sancion = :tipo");
    $stmtReinc->execute([':id' => $idProceso, ':tipo' => $idTipo]);
    $reincidencia = (int)$stmtReinc->fetchColumn() + 1;

    $regla = reglaAtraso($minutos, $reincidencia);
    $stmtIns = $db->prepare("INSERT INTO sanciones (id_proceso, id_tipo_sancion, registrado_por, fecha, motivo, horas_descontadas, minutos, reincidencia, material, sorteo, fecha_entrega, estado)
                             VALUES (:proc, :tipo, NULL, :fecha, :motivo, 0, :min, :reinc, :mat, :sort, :ent, 'ACTIVA')");
    $stmtIns->execute([
        ':proc'  => $idProceso,
        ':tipo'  => $idTipo,
        ':fecha' => $fecha,
        ':motivo'=> "Atraso automático: entrada $horaEntrada ($minutos min tarde).",
        ':min'   => $minutos,
        ':reinc' => $reincidencia,
        ':mat'   => $regla['material'],
        ':sort'  => $regla['sorteo'],
        ':ent'   => fechaEntregaSancion(new DateTime($fecha), $regla['exceso']),
    ]);

    return "Atraso de $minutos min: " . $regla['material'];
}
