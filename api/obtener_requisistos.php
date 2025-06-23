

<?php
require_once '../includes/conexion.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
  echo json_encode(['status' => false, 'message' => 'ID inválido']);
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM requisitos_matricula WHERE id_requisito = ?");
$stmt->execute([$id]);
$requisito = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($requisito ?: []);
