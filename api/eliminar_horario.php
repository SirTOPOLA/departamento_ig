<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("DELETE FROM horarios WHERE id_horario = ?");
$stmt->execute([$id]);
echo json_encode(['status' => true, 'message' => 'Horario eliminado']);
