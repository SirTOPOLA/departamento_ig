<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id_anio = $_POST['id_anio'] ?? null;
$activo = $_POST['activo'] ?? null;

if (!$id_anio || ($activo !== '0' && $activo !== '1')) {
  echo json_encode(['status' => false, 'message' => 'Datos inválidos.']);
  exit;
}

try {
  // Calcular el año académico actual
  $mes_actual = date('n');
  $anio_inicio = $mes_actual >= 8 ? date('Y') : date('Y') - 1;
  $anio_fin = $anio_inicio + 1;
  $anio_actual = "{$anio_inicio}-{$anio_fin}";

  // Obtener el año correspondiente al id
  $stmt = $pdo->prepare("SELECT anio FROM anios_academicos WHERE id_anio = ?");
  $stmt->execute([$id_anio]);
  $anio_db = $stmt->fetchColumn();

  if (!$anio_db) {
    echo json_encode(['status' => false, 'message' => 'El año académico no existe.']);
    exit;
  }

  // Validar que el cambio solo se permita si es el año académico actual
  if ($anio_db !== $anio_actual) {
    echo json_encode(['status' => false, 'message' => "Solo se puede modificar el estado del año académico actual: <strong>$anio_actual</strong>."]);
    exit;
  }

  // Si se activa, desactivar los demás
  if ($activo == '1') {
    $pdo->prepare("UPDATE anios_academicos SET activo = 0 WHERE id_anio != ?")->execute([$id_anio]);
  }

  // Actualizar el estado
  $stmt = $pdo->prepare("UPDATE anios_academicos SET activo = :activo WHERE id_anio = :id");
  $stmt->execute([
    'activo' => $activo,
    'id' => $id_anio
  ]);

  echo json_encode([
    'status' => true,
    'message' => $activo == '1' ? '✅ Año académico activado.' : '🟡 Año académico desactivado.'
  ]);

} catch (Exception $e) {
  echo json_encode(['status' => false, 'message' => 'Error al actualizar estado.']);
}
