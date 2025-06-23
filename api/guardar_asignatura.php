<?php
require_once '../includes/conexion.php';

$id = isset($_POST['id_asignatura']) ? (int)$_POST['id_asignatura'] : 0;
$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$curso_id = (int)($_POST['curso_id'] ?? 0);
$semestre_id = (int)($_POST['semestre_id'] ?? 0);

if ($nombre === '' || $curso_id <= 0 || $semestre_id <= 0) {
  echo json_encode(['status' => false, 'message' => 'Todos los campos requeridos']);
  exit;
}

if ($id > 0) {
  // Actualizar
  $stmt = $pdo->prepare("UPDATE asignaturas SET nombre=?, descripcion=?, curso_id=?, semestre_id=? WHERE id_asignatura=?");
  $ok = $stmt->execute([$nombre, $descripcion, $curso_id, $semestre_id, $id]);
  $msg = $ok ? 'Asignatura actualizada correctamente' : 'Error al actualizar';
} else {
  // Insertar nueva
  $stmt = $pdo->prepare("INSERT INTO asignaturas (nombre, descripcion, curso_id, semestre_id) VALUES (?, ?, ?, ?)");
  $ok = $stmt->execute([$nombre, $descripcion, $curso_id, $semestre_id]);
  $msg = $ok ? 'Asignatura registrada correctamente' : 'Error al registrar';
}

echo json_encode(['status' => $ok, 'message' => $msg]);
