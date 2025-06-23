<?php
require_once '../includes/conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
  echo json_encode(['status' => false, 'message' => 'ID inválido']);
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id_asignatura = ?");
$stmt->execute([$id]);
$asignatura = $stmt->fetch(PDO::FETCH_ASSOC);

if ($asignatura) {
  echo json_encode($asignatura);
} else {
  echo json_encode(['status' => false, 'message' => 'Asignatura no encontrada']);
}
