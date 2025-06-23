<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id_profesor = isset($_GET['id_profesor']) ? (int)$_GET['id_profesor'] : 0;

$sql = "SELECT a.id_asignatura, a.nombre, 
               IF(ap.id IS NULL, 0, 1) AS asignada
        FROM asignaturas a
        LEFT JOIN asignatura_profesor ap ON a.id_asignatura = ap.id_asignatura AND ap.id_profesor = :id_profesor
        ORDER BY a.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id_profesor' => $id_profesor]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
