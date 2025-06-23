<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id = isset($_GET['id_profesor']) ? (int)$_GET['id_profesor'] : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

// Obtener datos del profesor
$stmt = $pdo->prepare("SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.dni, u.telefono, p.especialidad
                       FROM usuarios u
                       JOIN profesores p ON u.id_usuario = p.id_profesor
                       WHERE u.id_usuario = ?");
$stmt->execute([$id]);
$profesor = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener asignaturas
$stmt2 = $pdo->prepare("SELECT a.nombre
                        FROM asignaturas a
                        JOIN asignatura_profesor ap ON a.id_asignatura = ap.id_asignatura
                        WHERE ap.id_profesor = ?");
$stmt2->execute([$id]);
$asignaturas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'profesor' => $profesor,
  'asignaturas' => $asignaturas
]);
