<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

// Buscar el único registro
$stmt = $pdo->query("SELECT * FROM departamento LIMIT 1");
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([]);
}
