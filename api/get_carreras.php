<?php
/**
 * API: Obtener Carreras de una Universidad en formato JSON
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_universidad = isset($_GET['id_universidad']) ? (int)$_GET['id_universidad'] : 0;

if ($id_universidad <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT id_carrera, nombre FROM carreras WHERE id_universidad = :id_uni AND estado = 1 ORDER BY nombre ASC");
    $stmt->execute([':id_uni' => $id_universidad]);
    $carreras = $stmt->fetchAll();
    echo json_encode($carreras);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
