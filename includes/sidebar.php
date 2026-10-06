<?php
/**
 * Sidebar de navegación del Sistema Web CCDB
 * Staff sees the full operational menu; PASANTE only sees their own activity.
 */
$currentUser = Auth::user();
$isStaff = Auth::isStaff();
?>
<aside class="sidebar">
    <a href="<?= APP_ROOT ?>index.php" class="sidebar-brand">
        <img src="<?= APP_ROOT ?>assets/img/logo_donbosco.svg" alt="Logo Don Bosco CCDB">
        <div class="sidebar-brand-text">
            <h2>Don Bosco</h2>
            <span>CCDB Panel</span>
        </div>
    </a>

    <ul class="sidebar-menu">
        <div class="sidebar-heading">Principal</div>
        <li>
            <a href="<?= APP_ROOT ?>index.php" class="<?= ($activeMenu === 'dashboard') ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i>
                <span>Inicio</span>
            </a>
        </li>

        <div class="sidebar-heading">Gestión Operativa</div>
        <?php if ($isStaff): ?>
        <li>
            <a href="<?= APP_ROOT ?>views/pasantes/index.php" class="<?= ($activeMenu === 'pasantes') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Pasantes</span>
            </a>
        </li>
        <li>
            <a href="<?= APP_ROOT ?>views/modalidades/index.php" class="<?= ($activeMenu === 'modalidades') ? 'active' : '' ?>">
                <i class="fa-solid fa-folder-open"></i>
                <span>Modalidades</span>
            </a>
        </li>
        <li>
            <a href="<?= APP_ROOT ?>views/asistencias/registrar_qr.php" class="<?= ($activeMenu === 'asistencias_qr') ? 'active' : '' ?>">
                <i class="fa-solid fa-qrcode"></i>
                <span>Asistencia QR</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?= APP_ROOT ?>views/asistencias/historial.php" class="<?= ($activeMenu === 'asistencias_historial') ? 'active' : '' ?>">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span><?= $isStaff ? 'Historial Asistencias' : 'Mis Asistencias' ?></span>
            </a>
        </li>
        <li>
            <a href="<?= APP_ROOT ?>views/asistencias/progreso_horas.php" class="<?= ($activeMenu === 'progreso_horas') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span><?= $isStaff ? 'Progreso de Horas' : 'Mi Progreso' ?></span>
            </a>
        </li>

        <?php if ($isStaff): ?>
        <div class="sidebar-heading">Supervisión y Control</div>
        <li>
            <a href="<?= APP_ROOT ?>views/sanciones/index.php" class="<?= ($activeMenu === 'sanciones') ? 'active' : '' ?>">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Sanciones</span>
            </a>
        </li>
        <li>
            <a href="<?= APP_ROOT ?>views/modalidades/reporte_avance.php" class="<?= ($activeMenu === 'reportes') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-contract"></i>
                <span>Reportes</span>
            </a>
        </li>
        <li>
            <a href="<?= APP_ROOT ?>views/tutores/index.php" class="<?= ($activeMenu === 'tutores') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-tie"></i>
                <span>Tutores</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($currentUser && $currentUser['rol_name'] === 'ADMINISTRADOR'): ?>
        <div class="sidebar-heading">Administración</div>
        <li>
            <a href="<?= APP_ROOT ?>views/usuarios/index.php" class="<?= ($activeMenu === 'usuarios') ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i>
                <span>Gestión de Usuarios</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="<?= APP_ROOT ?>logout.php" class="btn-sidebar-logout" onclick="return confirm('¿Está seguro de cerrar sesión?');">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>
