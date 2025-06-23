<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id_estudiante = (int) ($_POST['id_estudiante'] ?? 0);
$asignaturas = $_POST['asignaturas'] ?? [];

if ($id_estudiante <= 0 || count($asignaturas) === 0 || count($asignaturas) > 6) {
  echo json_encode(['status' => false, 'message' => 'Selección inválida']);
  exit;
}

try {
  // Opcional: borrar asignaciones anteriores
  $pdo->prepare("DELETE FROM asignatura_estudiante WHERE id_estudiante = ?")->execute([$id_estudiante]);

  $stmt = $pdo->prepare("INSERT INTO asignatura_estudiante (id_estudiante, id_asignatura) VALUES (?, ?)");
  foreach ($asignaturas as $id_asig) {
    $stmt->execute([$id_estudiante, $id_asig]);
  }

  echo json_encode(['status' => true, 'message' => 'Asignaturas asignadas correctamente']);
} catch (PDOException $e) {
  echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
