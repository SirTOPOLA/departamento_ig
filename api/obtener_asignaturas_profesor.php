<?php
require_once '../includes/conexion.php';

$id_profesor = $_GET['id_profesor'] ?? 0;

$sql = "SELECT a.id_asignatura, a.nombre,
        EXISTS (
            SELECT 1 FROM horarios h
            WHERE h.id_asignatura = a.id_asignatura
        ) AS ya_asignada
        FROM asignatura_profesor ap
        JOIN asignaturas a ON ap.id_asignatura = a.id_asignatura
        WHERE ap.id_profesor = :id_profesor";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id_profesor' => $id_profesor]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
