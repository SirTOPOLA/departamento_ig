<?php
require_once '../includes/conexion.php';

$id = isset($_POST['id_asignatura']) ? (int)$_POST['id_asignatura'] : 0;

if ($id <= 0) {
  echo json_encode(['status' => false, 'message' => 'ID inválido']);
  exit;
}

$stmt = $pdo->prepare("DELETE FROM asignaturas WHERE id_asignatura = ?");
$ok = $stmt->execute([$id]);

echo json_encode([
  'status' => $ok,
  'message' => $ok ? 'Asignatura eliminada correctamente' : 'No se pudo eliminar'
]);
