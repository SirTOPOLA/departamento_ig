<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id = $_POST['id_aula'] ?? null;
$nombre = trim($_POST['nombre'] ?? '');
$capacidad = (int) ($_POST['capacidad'] ?? 0);
$ubicacion = trim($_POST['ubicacion'] ?? '');

if ($nombre === '' || $capacidad <= 0) {
  echo json_encode(['status' => false, 'message' => 'Datos inválidos']);
  exit;
}

try {
  if ($id) {
    $stmt = $pdo->prepare("UPDATE aulas SET nombre = ?, capacidad = ?, ubicacion = ? WHERE id_aula = ?");
    $stmt->execute([$nombre, $capacidad, $ubicacion, $id]);
    echo json_encode(['status' => true, 'message' => 'Aula actualizada']);
  } else {
    $stmt = $pdo->prepare("INSERT INTO aulas (nombre, capacidad, ubicacion) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $capacidad, $ubicacion]);
    echo json_encode(['status' => true, 'message' => 'Aula registrada']);
  }
} catch (PDOException $e) {
  echo json_encode(['status' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
