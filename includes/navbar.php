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
        <?php
        $notifCount = 0;
        $notifList = [];
        try {
            require_once __DIR__ . '/notificaciones.php';
            $notifCount = contarNoLeidas(Database::getConnection());
            if ($notifCount > 0) {
                $notifList = ultimasNoLeidas(Database::getConnection(), 8);
            }
        } catch (Throwable $e) {
            // Las notificaciones jamás rompen la barra
        }
        ?>
        <details class="notif-bell">
            <summary title="Notificaciones">
                <i class="fa-solid fa-bell"></i><?php if ($notifCount > 0): ?><span class="notif-count"><?= $notifCount ?></span><?php endif; ?>
            </summary>
            <div class="notif-drop">
                <?php if (empty($notifList)): ?>
                    <p class="notif-empty">Sin notificaciones nuevas.</p>
                <?php else: ?>
                    <?php foreach ($notifList as $n): ?>
                        <div class="notif-item">
                            <strong><?= htmlspecialchars($n['titulo']) ?></strong>
                            <span><?= htmlspecialchars($n['mensaje']) ?></span>
                            <small><?= htmlspecialchars($n['fecha_envio']) ?><?= !empty($n['quien']) ? ' · ' . htmlspecialchars($n['quien']) : '' ?></small>
                        </div>
                    <?php endforeach; ?>
                    <form method="POST" action="<?= APP_ROOT ?>api/notificaciones.php">
                        <input type="hidden" name="action" value="leidas">
                        <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="width: 100%; justify-content: center;">Marcar como leídas</button>
                    </form>
                <?php endif; ?>
            </div>
        </details>
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

<style>
.notif-bell { position: relative; }
.notif-bell summary { list-style: none; cursor: pointer; font-size: 1.1rem; color: var(--primary-blue); padding: 8px; border-radius: 8px; display: inline-flex; align-items: center; }
.notif-bell summary::-webkit-details-marker { display: none; }
.notif-bell summary:hover { background: #f1f5f9; }
.notif-count { background: #dc2626; color: #fff; font-size: 0.68rem; font-weight: 800; border-radius: 999px; padding: 1px 6px; margin-left: 4px; }
.notif-drop { position: absolute; right: 0; top: calc(100% + 8px); width: 320px; max-height: 380px; overflow-y: auto; background: #fff; border: 1px solid var(--border-color); border-radius: 10px; box-shadow: 0 12px 28px rgba(0,0,0,.15); padding: 10px; z-index: 1200; }
.notif-item { border-bottom: 1px solid #f1f5f9; padding: 8px 4px; font-size: 0.82rem; }
.notif-item strong { display: block; color: var(--primary-blue); }
.notif-item small { color: var(--text-muted); }
.notif-empty { font-size: 0.85rem; color: var(--text-muted); padding: 10px 4px; margin: 0; }
</style>
