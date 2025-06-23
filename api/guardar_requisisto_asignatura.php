<?php
require '../includes/conexion.php';
header('Content-Type: application/json');

$id = $_POST['id_asignatura'] ?? null;
$requisitos = $_POST['requisitos'] ?? [];

if (!$id || !is_array($requisitos)) {
  echo json_encode(['status' => false, 'message' => 'Datos incompletos']);
  exit;
}

$insertados = 0;
foreach ($requisitos as $req) {
  if ($req == $id) continue;
  try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO asignatura_requisitos (asignatura_id, requisito_id) VALUES (:id, :req)");
    $stmt->execute(['id' => $id, 'req' => $req]);
    $insertados++;
  } catch (Exception $e) {}
}

echo json_encode(['status' => true, 'message' => "$insertados requisito(s) guardado(s)."]);
