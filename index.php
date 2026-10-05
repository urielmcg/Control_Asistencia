<?php
/**
 * Dashboard Principal - Sistema Web CCDB
 * Vista con tarjetas de acción rápida estilo imagen_UI_UX.jpeg y métricas en tiempo real.
 */
require_once __DIR__ . '/config/auth.php';
Auth::requireLogin();

$pageTitle = 'Panel Principal';
$activeMenu = 'dashboard';
$db = Database::getConnection();

// Métricas del sistema
try {
    $totalPasantes = $db->query("SELECT COUNT(*) FROM pasantes WHERE estado = 'ACTIVO'")->fetchColumn();
    $totalProcesos = $db->query("SELECT COUNT(*) FROM procesos WHERE estado = 'EN_CURSO'")->fetchColumn();
    $totalAsistenciasHoy = $db->query("SELECT COUNT(*) FROM asistencias WHERE fecha = CURDATE()")->fetchColumn();
    $totalSanciones = $db->query("SELECT COUNT(*) FROM sanciones WHERE estado = 'ACTIVA'")->fetchColumn();
} catch (Exception $e) {
    $totalPasantes = 0;
    $totalProcesos = 0;
    $totalAsistenciasHoy = 0;
    $totalSanciones = 0;
}

// Últimos pasantes registrados
$stmtUltimos = $db->query("SELECT p.*, i.nombre AS institucion, c.nombre AS carrera 
                           FROM pasantes p
                           INNER JOIN instituciones i ON p.id_universidad = i.id_institucion
                           INNER JOIN carreras c ON p.id_carrera = c.id_carrera
                           ORDER BY p.id_pasante DESC LIMIT 5");
$ultimosPasantes = $stmtUltimos->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="content-body">
        <!-- Tarjetas de Acción Rápida (Inspiradas en imagen_UI_UX.jpeg) -->
        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--primary-blue); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">
            Módulos Principales
        </h2>
        <div class="action-grid">
            <a href="views/pasantes/index.php" class="action-card">
                <div class="action-icon-circle">
                    <i class="fa-solid fa-users"></i>
                </div>
                <h3>PASANTES</h3>
                <p>Registro, búsqueda, filtros y asignación académica</p>
            </a>

            <a href="views/asistencias/registrar_qr.php" class="action-card">
                <div class="action-icon-circle">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <h3>ASISTENCIA QR</h3>
                <p>Control rápido de entrada, salida y horas trabajadas</p>
            </a>

            <a href="views/modalidades/index.php" class="action-card">
                <div class="action-icon-circle">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <h3>MODALIDADES</h3>
                <p>Pasantía, Trabajo Dirigido y reportes de certificación</p>
            </a>
        </div>

        <!-- Estadísticas Operativas -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon" style="background:#e0f2fe; color:#0284c7;">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div class="stat-data">
                    <h4><?= (int)$totalPasantes ?></h4>
                    <span>Pasantes Activos</span>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon" style="background:#fef3c7; color:#d97706;">
                    <i class="fa-solid fa-diagram-project"></i>
                </div>
                <div class="stat-data">
                    <h4><?= (int)$totalProcesos ?></h4>
                    <span>Procesos en Curso</span>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon" style="background:#dcfce7; color:#16a34a;">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="stat-data">
                    <h4><?= (int)$totalAsistenciasHoy ?></h4>
                    <span>Asistencias Hoy</span>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon" style="background:#fee2e2; color:#dc2626;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="stat-data">
                    <h4><?= (int)$totalSanciones ?></h4>
                    <span>Sanciones Activas</span>
                </div>
            </div>
        </div>

        <!-- Tabla de Pasantes Recientes -->
        <div class="card">
            <div class="card-header-flex">
                <h3 class="card-title">
                    <i class="fa-solid fa-clock" style="margin-right: 8px; color: var(--primary-blue);"></i>
                    Pasantes Recientemente Registrados
                </h3>
                <a href="views/pasantes/nuevo.php" class="btn btn-danger btn-sm">
                    <i class="fa-solid fa-plus"></i> Nuevo Pasante
                </a>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>CI</th>
                            <th>Nombres y Apellidos</th>
                            <th>Institución / Universidad</th>
                            <th>Carrera</th>
                            <th>Semestre</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimosPasantes)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No hay pasantes registrados aún.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ultimosPasantes as $p): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['ci']) ?></strong></td>
                                    <td><?= htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($p['institucion']) ?></td>
                                    <td><?= htmlspecialchars($p['carrera']) ?></td>
                                    <td><?= htmlspecialchars($p['semestre'] ?? 'N/D') ?></td>
                                    <td>
                                        <?php if ($p['estado'] === 'ACTIVO'): ?>
                                            <span class="badge badge-success">Activo</span>
                                        <?php elseif ($p['estado'] === 'CONCLUIDO'): ?>
                                            <span class="badge badge-info">Concluido</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><?= htmlspecialchars($p['estado']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="views/pasantes/editar.php?id=<?= $p['id_pasante'] ?>" class="btn btn-outline btn-sm" title="Editar">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="views/asistencias/progreso_horas.php" class="btn btn-primary btn-sm" title="Ver Progreso">
                                            <i class="fa-solid fa-chart-pie"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</div>
