<?php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'ID inválido']);
    exit;
}

// Obtener el estado actual
$stmt = $pdo->prepare("SELECT visible FROM publicaciones WHERE id_publicacion = ?");
$stmt->execute([$id]);
$publicacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$publicacion) {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'Publicación no encontrada']);
    exit;
}

// Cambiar estado
$nuevoEstado = $publicacion['visible'] ? 0 : 1;
$stmt = $pdo->prepare("UPDATE publicaciones SET visible = ? WHERE id_publicacion = ?");
if ($stmt->execute([$nuevoEstado, $id])) {
    echo json_encode(['status' => true, 'message' => 'Estado actualizado correctamente', 'visible' => $nuevoEstado]);
} else {
    echo json_encode(['status' => false, 'message' => 'Error al actualizar estado']);
}
