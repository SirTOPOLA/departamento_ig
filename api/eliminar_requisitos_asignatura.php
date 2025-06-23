<?php
require '../includes/conexion.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
if (!$id) {
  echo json_encode(['status' => false, 'message' => 'ID no válido']);
  exit;
}

$stmt = $pdo->prepare("DELETE FROM asignatura_requisitos WHERE id = :id");
$stmt->execute(['id' => $id]);
echo json_encode(['status' => true, 'message' => 'Requisito eliminado']);
