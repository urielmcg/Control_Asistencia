-- ============================================================
-- MIGRACIÓN 03: notificaciones por proceso (incluye modalidad sin pasante)
-- Ejecutar UNA vez por base existente (local XAMPP y Aiven).
-- Las instalaciones nuevas ya lo traen vía database_setup.sql.
-- ============================================================

ALTER TABLE notificaciones MODIFY COLUMN id_pasante INT UNSIGNED NULL;

ALTER TABLE notificaciones ADD COLUMN id_proceso INT UNSIGNED NULL AFTER id_pasante;

ALTER TABLE notificaciones ADD CONSTRAINT fk_notificaciones_proceso
    FOREIGN KEY (id_proceso)
    REFERENCES procesos(id_proceso)
    ON UPDATE CASCADE
    ON DELETE CASCADE;

CREATE INDEX idx_notificaciones_proceso ON notificaciones(id_proceso);
