<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id = $_POST['id_horario'] ?? null;
$asig = $_POST['id_asignatura'] ?? 0;
$prof = $_POST['id_profesor'] ?? 0;
$aula = $_POST['aula_id'] ?? 0;
$dia = $_POST['dia'] ?? '';
$inicio = $_POST['hora_inicio'] ?? '';
$fin = $_POST['hora_fin'] ?? '';

if (!$asig || !$prof || !$aula || !$dia || !$inicio || !$fin) {
  echo json_encode(['status' => false, 'message' => 'Faltan campos']);
  exit;
}

// Recibir datos
$id_horario = $_POST['id_horario'] ?? null; // si es edición
$id_asignatura = $_POST['id_asignatura'];
$id_profesor = $_POST['id_profesor'];
$aula_id = $_POST['aula_id'];
$dia = $_POST['dia'];
$hora_inicio = $_POST['hora_inicio'];
$hora_fin = $_POST['hora_fin'];

// Verificar solapamiento en el mismo aula
$sqlVerificar = "SELECT * FROM horarios
  WHERE aula_id = :aula_id
    AND dia = :dia
    AND (
        (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
    )";

// Excluir el horario actual si estamos editando
if ($id_horario) {
    $sqlVerificar .= " AND id_horario != :id_horario";
}

$stmt = $pdo->prepare($sqlVerificar);
$stmt->bindParam(':aula_id', $aula_id);
$stmt->bindParam(':dia', $dia);
$stmt->bindParam(':hora_inicio', $hora_inicio);
$stmt->bindParam(':hora_fin', $hora_fin);
if ($id_horario) {
    $stmt->bindParam(':id_horario', $id_horario);
}
$stmt->execute();

if ($stmt->rowCount() > 0) {
    echo json_encode([
        'status' => false,
        'message' => ' Ya existe un horario en este aula durante ese intervalo de tiempo.'
    ]);
    exit;
}


// Verificar solapamiento en horario del mismo profesor
$sqlProfesor = "SELECT * FROM horarios
  WHERE id_profesor = :id_profesor
    AND dia = :dia
    AND (
        (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
    )";

if ($id_horario) {
    $sqlProfesor .= " AND id_horario != :id_horario";
}

$stmt2 = $pdo->prepare($sqlProfesor);
$stmt2->bindParam(':id_profesor', $id_profesor);
$stmt2->bindParam(':dia', $dia);
$stmt2->bindParam(':hora_inicio', $hora_inicio);
$stmt2->bindParam(':hora_fin', $hora_fin);
if ($id_horario) {
    $stmt2->bindParam(':id_horario', $id_horario);
}
$stmt2->execute();

if ($stmt2->rowCount() > 0) {
    echo json_encode([
        'status' => false,
        'message' => '⚠️ El profesor ya tiene asignado un horario en ese intervalo de tiempo.'
    ]);
    exit;
}



try {
  if ($id) {
    $stmt = $pdo->prepare("UPDATE horarios SET id_asignatura=?, id_profesor=?, aula_id=?, dia=?, hora_inicio=?, hora_fin=? WHERE id_horario=?");
    $stmt->execute([$asig, $prof, $aula, $dia, $inicio, $fin, $id]);
    echo json_encode(['status' => true, 'message' => 'Horario actualizado']);
  } else {
    $stmt = $pdo->prepare("INSERT INTO horarios (id_asignatura, id_profesor, aula_id, dia, hora_inicio, hora_fin)
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$asig, $prof, $aula, $dia, $inicio, $fin]);
    echo json_encode(['status' => true, 'message' => 'Horario registrado']);
  }
} catch (PDOException $e) {
  echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
