<?php
require_once '../includes/conexion.php';

$stmt = $pdo->query("SELECT * FROM requisitos_matricula ORDER BY tipo, titulo");
$requisitos = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($requisitos);
