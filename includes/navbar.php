<?php
/**
 * Barra superior de navegación (Navbar)
 */
$currentUser = Auth::user();
?>
<header class="top-navbar">
    <div class="nav-left">
        <h3><?= htmlspecialchars($pageTitle ?? 'Panel de Control') ?></h3>
    </div>

    <div class="nav-right">
        <div class="user-badge">
            <div class="user-avatar">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div class="user-info">
                <div class="user-name">Bienvenido, <?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?></div>
                <div class="user-role"><?= htmlspecialchars($currentUser['rol_name'] ?? 'PERSONAL') ?></div>
            </div>
        </div>

        <a href="<?= APP_ROOT ?>logout.php" class="btn-logout-nav" title="Cerrar sesión" onclick="return confirm('¿Desea cerrar la sesión actual?');">
            <i class="fa-solid fa-power-off"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</header>
