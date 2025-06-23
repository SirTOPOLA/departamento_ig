<?php
require_once '../includes/conexion.php';

$response = ['status' => false, 'message' => ''];

$id = isset($_POST['id_curso']) ? (int) $_POST['id_curso'] : 0;

if ($id <= 0) {
  $response['message'] = 'ID inválido';
  echo json_encode($response);
  exit;
}

try {
  $stmt = $pdo->prepare("DELETE FROM cursos WHERE id_curso = :id");
  $stmt->execute(['id' => $id]);

  if ($stmt->rowCount()) {
    $response['status'] = true;
    $response['message'] = 'Curso eliminado correctamente';
  } else {
    $response['message'] = 'No se encontró el curso';
  }
} catch (PDOException $e) {
  $response['message'] = 'Error al eliminar: ' . $e->getMessage();
}

echo json_encode($response);
