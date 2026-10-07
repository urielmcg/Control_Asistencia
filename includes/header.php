<?php
/**
 * Header común para todas las vistas autenticadas
 */
if (!defined('APP_ROOT')) {
    $root = Auth::getRootPath();
    define('APP_ROOT', $root);
}
$pageTitle = $pageTitle ?? 'Panel de Control';
$activeMenu = $activeMenu ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - CCDB Sistema Web</title>
    <!-- Google Fonts & Font Awesome CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos del Sistema -->
    <link rel="stylesheet" href="<?= APP_ROOT ?>assets/css/style.css">
    <link rel="icon" type="image/png" href="<?= APP_ROOT ?>assets/img/ccdb/icono_ccdb.png">
</head>
<body>
