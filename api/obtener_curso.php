<?php
require_once '../includes/conexion.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  echo json_encode(['error' => 'ID inválido']);
  exit;
}

$id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id_curso = :id");
$stmt->execute(['id' => $id]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if ($curso) {
  echo json_encode($curso);
} else {
  echo json_encode(['error' => 'Curso no encontrado']);
}
