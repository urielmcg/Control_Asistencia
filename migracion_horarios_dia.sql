-- ============================================================
-- MIGRACIÓN: horarios por día + flag fuera de horario
-- Ejecutar UNA vez por base existente (local XAMPP y Aiven).
-- Las instalaciones nuevas ya lo traen vía database_setup.sql.
-- ============================================================

CREATE TABLE IF NOT EXISTS proceso_dias (
    id_proceso_dia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_proceso INT UNSIGNED NOT NULL,
    dia_semana TINYINT UNSIGNED NOT NULL,
    id_turno INT UNSIGNED NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_procesodias_proceso
        FOREIGN KEY (id_proceso)
        REFERENCES procesos(id_proceso)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_procesodias_turno
        FOREIGN KEY (id_turno)
        REFERENCES turnos(id_turno)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT uq_procesodias_proceso_dia
        UNIQUE (id_proceso, dia_semana),
    CONSTRAINT chk_procesodias_dia
        CHECK (dia_semana BETWEEN 1 AND 6)
) ENGINE=InnoDB;

ALTER TABLE asistencias ADD COLUMN fuera_horario TINYINT(1) NOT NULL DEFAULT 0;

CREATE INDEX idx_procesodias_proceso ON proceso_dias(id_proceso);
