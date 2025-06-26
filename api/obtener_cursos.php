<?php
require '../includes/conexion.php';

try {
    $stmt = $pdo->query("SELECT id_curso, nombre, turno, grupo FROM cursos ORDER BY nombre");
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => true, 'data' => $cursos]);
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Error al obtener cursos']);
}
