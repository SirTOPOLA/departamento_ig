<?php
require_once '../includes/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
  echo json_encode(['status' => false, 'message' => 'ID inválido']);
  exit;
}

$stmt = $pdo->prepare("SELECT visible FROM requisitos_matricula WHERE id_requisito = ?");
$stmt->execute([$id]);
$r = $stmt->fetch();

if (!$r) {
  echo json_encode(['status' => false, 'message' => 'Requisito no encontrado']);
  exit;
}

$nuevo = $r['visible'] ? 0 : 1;
$stmt = $pdo->prepare("UPDATE requisitos_matricula SET visible = ? WHERE id_requisito = ?");
$stmt->execute([$nuevo, $id]);

echo json_encode(['status' => true, 'message' => 'Visibilidad actualizada', 'visible' => $nuevo]);
