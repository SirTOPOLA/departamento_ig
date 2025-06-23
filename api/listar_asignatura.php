<?php
require '../includes/conexion.php';
$stmt = $pdo->query("SELECT id_asignatura, nombre FROM asignaturas ORDER BY nombre");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
