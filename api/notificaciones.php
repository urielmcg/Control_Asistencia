<?php
/**
 * API mínima de notificaciones: marcar como leídas y volver.
 */
require_once __DIR__ . '/../config/auth.php';
Auth::requireLogin();

if (($_POST['action'] ?? '') === 'leidas') {
    require_once __DIR__ . '/../includes/notificaciones.php';
    marcarLeidas(Database::getConnection());
}

$root = Auth::getRootPath();
$back = $_POST['return'] ?? '';
if (!is_string($back) || $back === '' || str_starts_with($back, 'http') || str_starts_with($back, '//')) {
    $back = $root . 'index.php';
} elseif (!str_starts_with($back, $root)) {
    $back = $root . 'index.php';
}
header('Location: ' . $back);
exit;
