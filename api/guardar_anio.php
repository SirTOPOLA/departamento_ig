<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id = $_POST['id_anio'] ?? null;
$anio = $_POST['anio'] ?? null;
$inicio = $_POST['fecha_inicio'] ?? null;
$fin = $_POST['fecha_fin'] ?? null;
$activo = isset($_POST['activo']) ? 1 : 0;

if (!$anio || !$inicio || !$fin) {
  echo json_encode(['status' => false, 'message' => 'Faltan datos requeridos.']);
  exit;
}

// VALIDACIÓN 1: Formato correcto
if (!preg_match('/^\d{4}-\d{4}$/', $anio)) {
  echo json_encode(['status' => false, 'message' => 'El campo "Año" debe tener el formato xxxx-yyyy.']);
  exit;
}

// VALIDACIÓN 2: xxxx < yyyy
list($anio_ini, $anio_fin) = explode('-', $anio);
if ((int)$anio_ini >= (int)$anio_fin) {
  echo json_encode(['status' => false, 'message' => 'El año inicial debe ser menor que el año final.']);
  exit;
}

try {
  $dateIni = new DateTime($inicio);
  $dateFin = new DateTime($fin);

  // VALIDACIÓN 3: duración mínima 5 meses
  $interval = $dateIni->diff($dateFin);
  $totalMeses = ($interval->y * 12) + $interval->m;
  if ($totalMeses < 5) {
    echo json_encode(['status' => false, 'message' => 'La duración debe ser al menos de 5 meses.']);
    exit;
  }

  // VALIDACIÓN 4: Coherencia entre fechas y campo "anio"
  if ((int)$dateIni->format('Y') != (int)$anio_ini || (int)$dateFin->format('Y') != (int)$anio_fin) {
    echo json_encode(['status' => false, 'message' => 'Las fechas no coinciden con el año académico especificado.']);
    exit;
  }

  // VALIDACIÓN 5: Duplicados (excepto si estamos actualizando el mismo)
  $query = $pdo->prepare("SELECT COUNT(*) FROM anios_academicos WHERE anio = :anio" . ($id ? " AND id_anio != :id" : ""));
  $params = ['anio' => $anio];
  if ($id) $params['id'] = $id;
  $query->execute($params);
  if ($query->fetchColumn() > 0) {
    echo json_encode(['status' => false, 'message' => 'Este año académico ya existe.']);
    exit;
  }

  // Lógica de guardado
  if ($id) {
    // Si se desactiva el único activo, prevenirlo
    if (!$activo) {
      $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM anios_academicos WHERE activo = 1 AND id_anio != :id");
      $stmtCheck->execute(['id' => $id]);
      $otrosActivos = $stmtCheck->fetchColumn();

      if ($otrosActivos == 0) {
        echo json_encode(['status' => false, 'message' => 'Debe haber al menos un año académico activo.']);
        exit;
      }
    }

    // Si se marca como activo, desactivar los demás
    if ($activo) {
      $pdo->prepare("UPDATE anios_academicos SET activo = 0 WHERE id_anio != :id")->execute(['id' => $id]);
    }

    $stmt = $pdo->prepare("UPDATE anios_academicos 
                           SET anio = :anio, fecha_inicio = :inicio, fecha_fin = :fin, activo = :activo 
                           WHERE id_anio = :id");
    $stmt->execute([
      'anio' => $anio,
      'inicio' => $inicio,
      'fin' => $fin,
      'activo' => $activo,
      'id' => $id
    ]);

  } else {
    // Nueva inserción
    if ($activo) {
      $pdo->exec("UPDATE anios_academicos SET activo = 0");
    }
    $stmt = $pdo->prepare("INSERT INTO anios_academicos (anio, fecha_inicio, fecha_fin, activo)
                           VALUES (:anio, :inicio, :fin, :activo)");
    $stmt->execute([
      'anio' => $anio,
      'inicio' => $inicio,
      'fin' => $fin,
      'activo' => $activo
    ]);
  }

  echo json_encode(['status' => true, 'message' => 'Año académico guardado correctamente.']);

} catch (Exception $e) {
  echo json_encode(['status' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
