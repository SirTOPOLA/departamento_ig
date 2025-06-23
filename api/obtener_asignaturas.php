<?php
require '../includes/conexion.php';
session_start();

header('Content-Type: application/json');

$semestre_id = $_GET['semestre_id'] ?? null;
$curso_id = $_GET['curso_id'] ?? null;
$id_estudiante = $_SESSION['id_usuario'] ?? null; // asegúrate de usar el nombre correcto aquí

if (!$semestre_id || !$curso_id || !$id_estudiante) {
  http_response_code(400);
  echo json_encode(['error' => 'Faltan parámetros']);
  exit;
}

// Obtener asignaturas
$stmt = $pdo->prepare("
  SELECT id_asignatura, nombre 
  FROM asignaturas 
  WHERE semestre_id = ? AND curso_id = ?
");
$stmt->execute([$semestre_id, $curso_id]);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resultado = [];

foreach ($asignaturas as $asig) {
  // Requisitos
  $stmtReq = $pdo->prepare("
    SELECT a2.nombre 
    FROM asignatura_requisitos ar
    JOIN asignaturas a2 ON ar.requisito_id = a2.id_asignatura
    WHERE ar.asignatura_id = ?
  ");
  $stmtReq->execute([$asig['id_asignatura']]);
  $requisitos = $stmtReq->fetchAll(PDO::FETCH_COLUMN);

  $cumple = true;
  foreach ($requisitos as $nombreReq) {
    $stmtCheck = $pdo->prepare("
      SELECT 1 FROM asignatura_estudiante ae
      JOIN asignaturas a ON a.id_asignatura = ae.id_asignatura
      WHERE ae.id_estudiante = ? AND a.nombre = ?
    ");
    $stmtCheck->execute([$id_estudiante, $nombreReq]);
    if (!$stmtCheck->fetch()) {
      $cumple = false;
      break;
    }
  }

  $resultado[] = [
    'id_asignatura' => $asig['id_asignatura'],
    'nombre' => $asig['nombre'],
    'habilitado' => $cumple,
    'requisitos' => $requisitos
  ];
}

echo json_encode($resultado);
