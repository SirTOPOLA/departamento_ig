<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id_profesor = isset($_POST['id_profesor']) ? (int)$_POST['id_profesor'] : 0;
$asignaturas = isset($_POST['asignaturas']) ? $_POST['asignaturas'] : [];

if ($id_profesor <= 0) {
  echo json_encode(['status' => false, 'message' => 'ID de profesor inválido']);
  exit;
}

try {
  $pdo->beginTransaction();

  // Eliminar asignaciones actuales
  $pdo->prepare("DELETE FROM asignatura_profesor WHERE id_profesor = ?")->execute([$id_profesor]);

  // Insertar nuevas
  $stmt = $pdo->prepare("INSERT INTO asignatura_profesor (id_profesor, id_asignatura) VALUES (?, ?)");
  foreach ($asignaturas as $id_asig) {
    $stmt->execute([$id_profesor, $id_asig]);
  }

  $pdo->commit();
  echo json_encode(['status' => true, 'message' => 'Asignaturas guardadas correctamente']);
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
