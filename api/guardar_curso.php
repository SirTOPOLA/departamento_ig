<?php
require_once '../includes/conexion.php';

$response = ['status' => false, 'message' => ''];

$id = isset($_POST['id_curso']) ? (int) $_POST['id_curso'] : 0;
$nombre = trim($_POST['nombre'] ?? '');
$turno = $_POST['turno'] ?? '';
$grupo = isset($_POST['grupo']) ? (int) $_POST['grupo'] : 1;
$descripcion = trim($_POST['descripcion'] ?? '');

// Validación básica
if ($nombre === '' || !in_array($turno, ['tarde', 'noche'])) {
  $response['message'] = 'Datos inválidos';
  echo json_encode($response);
  exit;
}

try {
  if ($id > 0) {
    // Actualizar
    $stmt = $pdo->prepare("UPDATE cursos SET nombre = :nombre, turno = :turno, grupo = :grupo, descripcion = :descripcion WHERE id_curso = :id");
    $stmt->execute([
      'nombre' => $nombre,
      'turno' => $turno,
      'grupo' => $grupo,
      'descripcion' => $descripcion,
      'id' => $id
    ]);
    $response['status'] = true;
    $response['message'] = 'Curso actualizado correctamente';
  } else {
    // Insertar
    $stmt = $pdo->prepare("INSERT INTO cursos (nombre, turno, grupo, descripcion) VALUES (:nombre, :turno, :grupo, :descripcion)");
    $stmt->execute([
      'nombre' => $nombre,
      'turno' => $turno,
      'grupo' => $grupo,
      'descripcion' => $descripcion
    ]);
    $response['status'] = true;
    $response['message'] = 'Curso registrado correctamente';
  }
} catch (PDOException $e) {
  $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
}

echo json_encode($response);
