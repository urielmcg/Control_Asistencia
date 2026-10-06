-- ============================================================
-- BASE DE DATOS: SISTEMA WEB CCDB
-- SISTEMA WEB DE CONTROL Y SEGUIMIENTO DE PASANTES
-- Y MODALIDADES DE TITULACIÓN
-- CENTRO CULTURAL DON BOSCO (CCDB)
-- Compatible con MySQL 8.0+ / MariaDB 10.4+ (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS sistema_pasantes_ccdb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sistema_pasantes_ccdb;

-- 1. TABLA: ROLES
CREATE TABLE IF NOT EXISTS roles (
    id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. TABLA: USUARIOS
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_rol INT UNSIGNED NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    ci VARCHAR(30) NOT NULL UNIQUE,
    correo VARCHAR(150) UNIQUE,
    telefono VARCHAR(30),
    estado TINYINT(1) NOT NULL DEFAULT 1,
    remember_token VARCHAR(255) NULL,
    remember_expiry DATETIME NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_roles
        FOREIGN KEY (id_rol)
        REFERENCES roles(id_rol)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. TABLA: TIPOS DE INSTITUCIÓN
CREATE TABLE IF NOT EXISTS tipos_institucion (
    id_tipo_institucion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 4. TABLA: INSTITUCIONES (Universidades / Institutos)
CREATE TABLE IF NOT EXISTS instituciones (
    id_institucion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_tipo_institucion INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    nit VARCHAR(30),
    direccion VARCHAR(255),
    telefono VARCHAR(30),
    correo VARCHAR(150),
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_instituciones_tipo
        FOREIGN KEY (id_tipo_institucion)
        REFERENCES tipos_institucion(id_tipo_institucion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. TABLA: CARRERAS
CREATE TABLE IF NOT EXISTS carreras (
    id_carrera INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_universidad INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_carrera_universidad
        UNIQUE (id_universidad, nombre),
    CONSTRAINT fk_carreras_universidad
        FOREIGN KEY (id_universidad)
        REFERENCES instituciones(id_institucion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 6. TABLA: PASANTES
CREATE TABLE IF NOT EXISTS pasantes (
    id_pasante INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NULL,
    id_universidad INT UNSIGNED NOT NULL,
    id_carrera INT UNSIGNED NOT NULL,
    ci VARCHAR(30) NOT NULL UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    correo VARCHAR(150),
    telefono VARCHAR(30),
    semestre VARCHAR(50),
    fecha_nacimiento DATE NULL,
    direccion TEXT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'ACTIVO',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pasantes_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_pasantes_universidad
        FOREIGN KEY (id_universidad)
        REFERENCES instituciones(id_institucion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_pasantes_carrera
        FOREIGN KEY (id_carrera)
        REFERENCES carreras(id_carrera)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 7. TABLA: MODALIDADES
CREATE TABLE IF NOT EXISTS modalidades (
    id_modalidad INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    horas_requeridas_base INT UNSIGNED NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 8. TABLA: TUTORES
CREATE TABLE IF NOT EXISTS tutores (
    id_tutor INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    cargo VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'ACTIVO',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 9. TABLA: DOCUMENTOS DEL PASANTE
CREATE TABLE IF NOT EXISTS documentos (
    id_documento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pasante INT UNSIGNED NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    tamano INT UNSIGNED NOT NULL,
    fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documentos_pasante
        FOREIGN KEY (id_pasante)
        REFERENCES pasantes(id_pasante)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. TABLA: PROCESOS (Asignación pasante - modalidad - horas)
CREATE TABLE IF NOT EXISTS procesos (
    id_proceso INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pasante INT UNSIGNED NOT NULL,
    id_institucion INT UNSIGNED NOT NULL,
    id_modalidad INT UNSIGNED NOT NULL,
    id_tutor INT UNSIGNED NULL,
    turno VARCHAR(20) NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    horas_requeridas INT UNSIGNED NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'EN_CURSO',
    observacion TEXT,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_procesos_pasante
        FOREIGN KEY (id_pasante)
        REFERENCES pasantes(id_pasante)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_procesos_institucion
        FOREIGN KEY (id_institucion)
        REFERENCES instituciones(id_institucion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_procesos_modalidad
        FOREIGN KEY (id_modalidad)
        REFERENCES modalidades(id_modalidad)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_procesos_tutor
        FOREIGN KEY (id_tutor)
        REFERENCES tutores(id_tutor)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 11. TABLA: CÓDIGOS QR
CREATE TABLE IF NOT EXISTS codigos_qr (
    id_qr INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(255) NOT NULL UNIQUE,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_expiracion TIME NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'ACTIVO',
    fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 12. TABLA: ASISTENCIAS
CREATE TABLE IF NOT EXISTS asistencias (
    id_asistencia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_proceso INT UNSIGNED NOT NULL,
    id_qr INT UNSIGNED NULL,
    fecha DATE NOT NULL,
    hora_entrada TIME NULL,
    hora_salida TIME NULL,
    horas_totales DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL AND hora_salida >= hora_entrada 
            THEN ROUND(TIME_TO_SEC(TIMEDIFF(hora_salida, hora_entrada)) / 3600.0, 2)
            ELSE 0.00
        END
    ) VIRTUAL,
    estado VARCHAR(30) NOT NULL DEFAULT 'PRESENTE',
    observacion TEXT,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_asistencias_proceso
        FOREIGN KEY (id_proceso)
        REFERENCES procesos(id_proceso)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_asistencias_qr
        FOREIGN KEY (id_qr)
        REFERENCES codigos_qr(id_qr)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT uq_asistencia_proceso_fecha
        UNIQUE (id_proceso, fecha)
) ENGINE=InnoDB;

-- 13. TABLA: TIPOS DE SANCIÓN
CREATE TABLE IF NOT EXISTS tipos_sancion (
    id_tipo_sancion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 14. TABLA: SANCIONES
CREATE TABLE IF NOT EXISTS sanciones (
    id_sancion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_proceso INT UNSIGNED NOT NULL,
    id_tipo_sancion INT UNSIGNED NOT NULL,
    registrado_por INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(255),
    descripcion TEXT,
    horas_descontadas DECIMAL(5,2) NOT NULL DEFAULT 0,
    estado VARCHAR(30) NOT NULL DEFAULT 'ACTIVA',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sanciones_proceso
        FOREIGN KEY (id_proceso)
        REFERENCES procesos(id_proceso)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_sanciones_tipo
        FOREIGN KEY (id_tipo_sancion)
        REFERENCES tipos_sancion(id_tipo_sancion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_sanciones_usuario
        FOREIGN KEY (registrado_por)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 15. TABLA: CERTIFICACIONES
CREATE TABLE IF NOT EXISTS certificaciones (
    id_certificacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_proceso INT UNSIGNED NOT NULL,
    emitido_por INT UNSIGNED NOT NULL,
    fecha_emision DATE NOT NULL,
    horas_cumplidas DECIMAL(8,2) NOT NULL,
    tipo_certificado VARCHAR(100) NOT NULL,
    numero_certificado VARCHAR(100) NOT NULL UNIQUE,
    observacion TEXT,
    estado VARCHAR(30) NOT NULL DEFAULT 'EMITIDO',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_certificaciones_proceso
        FOREIGN KEY (id_proceso)
        REFERENCES procesos(id_proceso)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_certificaciones_usuario
        FOREIGN KEY (emitido_por)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 16. TABLA: NOTIFICACIONES
CREATE TABLE IF NOT EXISTS notificaciones (
    id_notificacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pasante INT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notificaciones_pasante
        FOREIGN KEY (id_pasante)
        REFERENCES pasantes(id_pasante)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 17. TABLA: AUDITORÍA
CREATE TABLE IF NOT EXISTS auditoria (
    id_auditoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NULL,
    accion VARCHAR(50) NOT NULL,
    tabla_afectada VARCHAR(100) NOT NULL,
    id_registro BIGINT UNSIGNED NULL,
    descripcion TEXT,
    ip VARCHAR(45),
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- ÍNDICES DE RENDIMIENTO
-- ============================================================
CREATE INDEX idx_pasantes_universidad ON pasantes(id_universidad);
CREATE INDEX idx_pasantes_carrera ON pasantes(id_carrera);
CREATE INDEX idx_procesos_pasante ON procesos(id_pasante);
CREATE INDEX idx_procesos_institucion ON procesos(id_institucion);
CREATE INDEX idx_procesos_modalidad ON procesos(id_modalidad);
CREATE INDEX idx_procesos_estado ON procesos(estado);
CREATE INDEX idx_asistencias_fecha ON asistencias(fecha);
CREATE INDEX idx_asistencias_proceso_fecha ON asistencias(id_proceso, fecha);
CREATE INDEX idx_sanciones_fecha ON sanciones(fecha);
CREATE INDEX idx_auditoria_fecha ON auditoria(fecha);

-- ============================================================
-- VISTA: RESUMEN DE HORAS POR PROCESO (vista_horas_proceso)
-- ============================================================
CREATE OR REPLACE VIEW vista_horas_proceso AS
SELECT
    p.id_proceso,
    p.id_pasante,
    pas.nombres,
    pas.apellidos,
    pas.ci,
    inst.nombre AS institucion,
    car.nombre AS carrera,
    m.nombre AS modalidad,
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
INNER JOIN pasantes pas ON p.id_pasante = pas.id_pasante
INNER JOIN instituciones inst ON p.id_institucion = inst.id_institucion
INNER JOIN modalidades m ON p.id_modalidad = m.id_modalidad
INNER JOIN carreras car ON pas.id_carrera = car.id_carrera
LEFT JOIN asistencias a ON p.id_proceso = a.id_proceso
GROUP BY
    p.id_proceso,
    p.id_pasante,
    pas.nombres,
    pas.apellidos,
    pas.ci,
    inst.nombre,
    car.nombre,
    m.nombre,
    p.horas_requeridas,
    p.estado;

-- ============================================================
-- DATOS INICIALES SEMILLA (SEED DATA)
-- ============================================================

-- Roles del sistema
INSERT IGNORE INTO roles (id_rol, nombre, descripcion) VALUES
(1, 'ADMINISTRADOR', 'Administrador general del sistema con control total'),
(2, 'PERSONAL', 'Personal administrativo o tutor institucional'),
(3, 'PASANTE', 'Usuario correspondiente al pasante');

-- Usuario administrador inicial: admin / Admin123
INSERT IGNORE INTO usuarios (id_usuario, id_rol, usuario, password, nombres, apellidos, ci, correo, telefono, estado) VALUES
(1, 1, 'admin', '$2y$10$GVeomAnrKBMLmV.Ty8J1f.Kum1JudytnUBaqdmqdNckM6sPWmVi2y', 'Administrador', 'CCDB', '0000000', 'admin@ccdb.edu.bo', '70012345', 1);

-- Tipos de institución
INSERT IGNORE INTO tipos_institucion (id_tipo_institucion, nombre, descripcion) VALUES
(1, 'INSTITUTO_TECNICO', 'Instituto Técnico Superior'),
(2, 'UNIVERSIDAD', 'Universidad Pública o Privada'),
(3, 'CENTRO_CULTURAL', 'Centro Cultural y de Capacitación');

-- Instituciones de Educación Superior
INSERT IGNORE INTO instituciones (id_institucion, id_tipo_institucion, nombre, nit, direccion, telefono, correo) VALUES
(1, 1, 'INCOS La Paz', '102938475', 'Av. Montes #123, La Paz', '2281234', 'info@incoslapaz.edu.bo'),
(2, 2, 'Universidad Mayor de San Andrés (UMSA)', '102983741', 'Plaza del Bicentenario, Av. Villazón', '2441560', 'contacto@umsa.bo'),
(3, 2, 'Universidad Pública de El Alto (UPEA)', '108273645', 'Av. Sucre A s/n, Villa Esperanza, El Alto', '2844177', 'info@upea.bo'),
(4, 3, 'Centro Cultural Don Bosco (CCDB)', '100099887', 'Zona Don Bosco, Calle 1', '2223344', 'contacto@ccdb.org.bo');

-- Carreras
INSERT IGNORE INTO carreras (id_carrera, id_universidad, nombre) VALUES
(1, 1, 'Sistemas Informáticos'),
(2, 1, 'Secretariado Ejecutivo'),
(3, 1, 'Contaduría General'),
(4, 2, 'Informática'),
(5, 2, 'Ingeniería de Sistemas'),
(6, 3, 'Ingeniería de Sistemas');

-- Modalidades de Titulación: Proyecto de Grado y Trabajo Dirigido
INSERT IGNORE INTO modalidades (id_modalidad, nombre, descripcion, horas_requeridas_base) VALUES
(1, 'Proyecto de Grado', 'Modalidad de titulación mediante proyecto de grado institucional', 1000),
(2, 'Trabajo Dirigido', 'Modalidad de titulación mediante trabajo institucional dirigido', 1000);

-- Tipos de sanción
INSERT IGNORE INTO tipos_sancion (id_tipo_sancion, nombre, descripcion) VALUES
(1, 'ATRASO', 'Incumplimiento de horario de ingreso (descuento configurable)'),
(2, 'FALTA', 'Inasistencia no justificada a su jornada'),
(3, 'SALIDA_ANTICIPADA', 'Retiro sin autorización antes de concluir horario');

-- Pasante de prueba inicial
INSERT IGNORE INTO pasantes (id_pasante, id_usuario, id_universidad, id_carrera, ci, nombres, apellidos, correo, telefono, semestre, estado) VALUES
(1, NULL, 1, 1, '8329410', 'Carlos Andrés', 'Mamani Quispe', 'carlos.mamani@incoslapaz.edu.bo', '78945612', 'Sexto Semestre', 'ACTIVO');

-- Proceso asignado al pasante de prueba (Proyecto de Grado 1000 horas)
INSERT IGNORE INTO procesos (id_proceso, id_pasante, id_institucion, id_modalidad, fecha_inicio, fecha_fin, horas_requeridas, estado, observacion) VALUES
(1, 1, 4, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 1000, 'EN_CURSO', 'Asignación regular de 1000 horas para modalidad Proyecto de Grado en CCDB');

-- Asistencias demostrativas iniciales para el pasante de prueba
INSERT IGNORE INTO asistencias (id_asistencia, id_proceso, id_qr, fecha, hora_entrada, hora_salida, estado, observacion) VALUES
(1, 1, NULL, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', '13:00:00', 'PRESENTE', 'Jornada matutina completada - 5 hrs'),
(2, 1, NULL, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '14:00:00', 'PRESENTE', 'Jornada con tiempo extendido - 6 hrs');
