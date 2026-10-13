-- ============================================================
-- MIGRACIÓN 02: vista_horas_proceso incluye solicitudes sin pasante
-- Ejecutar UNA vez por base existente (local XAMPP y Aiven).
-- CREATE OR REPLACE es idempotente: re-ejecutable sin riesgo.
-- ============================================================

CREATE OR REPLACE VIEW vista_horas_proceso AS
SELECT
    p.id_proceso,
    p.id_pasante,
    pas.nombres,
    pas.apellidos,
    pas.ci,
    s.postulante AS sol_postulante,
    s.ci AS sol_ci,
    inst.nombre AS institucion,
    COALESCE(car.nombre, s.carrera) AS carrera,
    COALESCE(m.nombre, 'Pasantía') AS modalidad,
    p.horas_requeridas,
    p.estado AS estado_proceso,
    COALESCE(
        SUM(
            CASE
                WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL
                THEN TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0
                ELSE 0
            END
        ), 0
    ) AS horas_brutas,
    COALESCE(
        (SELECT SUM(s.horas_descontadas) 
         FROM sanciones s 
         WHERE s.id_proceso = p.id_proceso AND s.estado = 'ACTIVA'), 0
    ) AS horas_descontadas,
    ROUND(
        GREATEST(
            COALESCE(
                SUM(
                    CASE
                        WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL
                        THEN TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0
                        ELSE 0
                    END
                ), 0
            ) - COALESCE(
                (SELECT SUM(s.horas_descontadas) 
                 FROM sanciones s 
                 WHERE s.id_proceso = p.id_proceso AND s.estado = 'ACTIVA'), 0
            ), 0
        ), 2
    ) AS horas_acumuladas,
    ROUND(
        GREATEST(
            p.horas_requeridas - GREATEST(
                COALESCE(
                    SUM(
                        CASE
                            WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL
                            THEN TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0
                            ELSE 0
                        END
                    ), 0
                ) - COALESCE(
                    (SELECT SUM(s.horas_descontadas) 
                     FROM sanciones s 
                     WHERE s.id_proceso = p.id_proceso AND s.estado = 'ACTIVA'), 0
                ), 0
            ), 0
        ), 2
    ) AS horas_faltantes,
    ROUND(
        LEAST(
            (GREATEST(
                COALESCE(
                    SUM(
                        CASE
                            WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL
                            THEN TIME_TO_SEC(TIMEDIFF(a.hora_salida, a.hora_entrada)) / 3600.0
                            ELSE 0
                        END
                    ), 0
                ) - COALESCE(
                    (SELECT SUM(s.horas_descontadas) 
                     FROM sanciones s 
                     WHERE s.id_proceso = p.id_proceso AND s.estado = 'ACTIVA'), 0
                ), 0
            ) / p.horas_requeridas) * 100, 100.0
        ), 2
    ) AS porcentaje_completado
FROM procesos p
LEFT JOIN pasantes pas ON p.id_pasante = pas.id_pasante
INNER JOIN instituciones inst ON p.id_institucion = inst.id_institucion
LEFT JOIN modalidades m ON p.id_modalidad = m.id_modalidad
LEFT JOIN carreras car ON pas.id_carrera = car.id_carrera
LEFT JOIN solicitudes_modalidad s ON s.id_proceso = p.id_proceso
LEFT JOIN asistencias a ON p.id_proceso = a.id_proceso
WHERE p.horas_requeridas IS NOT NULL AND p.horas_requeridas > 0
  AND (m.nombre IS NULL OR m.nombre <> 'Proyecto de Grado')
GROUP BY
    p.id_proceso,
    p.id_pasante,
    pas.nombres,
    pas.apellidos,
    pas.ci,
    inst.nombre,
    car.nombre,
    s.postulante,
    s.ci,
    s.carrera,
    m.nombre,
    p.horas_requeridas,
    p.estado;
