<?php
require_once '../includes/conexion.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  http_response_code(400);
  echo json_encode(['error' => 'ID inválido']);
  exit;
}

$id = intval($_GET['id']);

try {
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
  $stmt->execute([$id]);
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$usuario) {
    http_response_code(404);
    echo json_encode(['error' => 'Usuario no encontrado']);
    exit;
  }

  // Si es profesor, obtener especialidad
  if ($usuario['rol'] === 'profesor') {
    $extra = $pdo->prepare("SELECT especialidad FROM profesores WHERE id_profesor = ?");
    $extra->execute([$id]);
    $profesor = $extra->fetch(PDO::FETCH_ASSOC);
    $usuario['especialidad'] = $profesor['especialidad'] ?? '';
  }

  // Si es estudiante, obtener matrícula y curso
  if ($usuario['rol'] === 'estudiante') {
    $extra = $pdo->prepare("SELECT matricula, curso_actual FROM estudiantes WHERE id_estudiante = ?");
    $extra->execute([$id]);
    $estudiante = $extra->fetch(PDO::FETCH_ASSOC);
    $usuario['matricula'] = $estudiante['matricula'] ?? '';
    $usuario['curso_actual'] = $estudiante['curso_actual'] ?? '';
  }

  echo json_encode($usuario);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error al obtener datos']);
}
?>
