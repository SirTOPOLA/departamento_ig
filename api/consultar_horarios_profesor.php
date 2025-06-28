<?php
// consultar_horarios_profesor.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$response = [
    'status' => false,
    'message' => 'Parámetros inválidos.',
    'solapamiento_profesor' => false,
    'solapamiento_aula' => false,
    'horarios_dia' => []
];

// Validar y obtener los parámetros
$id_profesor = $_GET['id_profesor'] ?? null;
$aula_id = $_GET['aula_id'] ?? null;
$dia = $_GET['dia'] ?? null;
$hora_inicio = $_GET['hora_inicio'] ?? null;
$hora_fin = $_GET['hora_fin'] ?? null;
$id_horario = $_GET['id_horario'] ?? null; // ID del horario actual si es edición
$id_anio = $_GET['id_anio'] ?? null; // Cambio aquí: ID del año académico

if (!$id_profesor || !$dia || !$hora_inicio || !$hora_fin || !$aula_id || !$id_anio) { // Cambio aquí: id_anio
    echo json_encode($response);
    exit();
}

try {
    // 1. Consultar horarios existentes del profesor para el día y año
    $sqlProfesor = "SELECT hora_inicio, hora_fin FROM horarios
                    WHERE id_profesor = :id_profesor
                    AND dia = :dia
                    AND id_anio = :id_anio"; // Cambio aquí: id_anio
    if ($id_horario) {
        $sqlProfesor .= " AND id_horario != :id_horario";
    }
    $stmtProfesor = $pdo->prepare($sqlProfesor);
    $stmtProfesor->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmtProfesor->bindParam(':dia', $dia, PDO::PARAM_STR);
    $stmtProfesor->bindParam(':id_anio', $id_anio, PDO::PARAM_INT); // Cambio aquí: id_anio
    if ($id_horario) {
        $stmtProfesor->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    }
    $stmtProfesor->execute();
    $horariosProfesor = $stmtProfesor->fetchAll(PDO::FETCH_ASSOC);

    // Verificar solapamiento para el profesor
    foreach ($horariosProfesor as $h) {
        $exist_start = strtotime($h['hora_inicio']);
        $exist_end = strtotime($h['hora_fin']);
        $new_start = strtotime($hora_inicio);
        $new_end = strtotime($hora_fin);

        // Check for overlap: (StartA < EndB) and (EndA > StartB)
        if ($new_start < $exist_end && $new_end > $exist_start) {
            $response['solapamiento_profesor'] = true;
            break;
        }
    }

    // 2. Consultar horarios existentes del aula para el día y año
    $sqlAula = "SELECT hora_inicio, hora_fin FROM horarios
                WHERE aula_id = :aula_id
                AND dia = :dia
                AND id_anio = :id_anio"; // Cambio aquí: id_anio
    if ($id_horario) {
        $sqlAula .= " AND id_horario != :id_horario";
    }
    $stmtAula = $pdo->prepare($sqlAula);
    $stmtAula->bindParam(':aula_id', $aula_id, PDO::PARAM_INT);
    $stmtAula->bindParam(':dia', $dia, PDO::PARAM_STR);
    $stmtAula->bindParam(':id_anio', $id_anio, PDO::PARAM_INT); // Cambio aquí: id_anio
    if ($id_horario) {
        $stmtAula->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    }
    $stmtAula->execute();
    $horariosAula = $stmtAula->fetchAll(PDO::FETCH_ASSOC);

    // Verificar solapamiento para el aula
    foreach ($horariosAula as $h) {
        $exist_start = strtotime($h['hora_inicio']);
        $exist_end = strtotime($h['hora_fin']);
        $new_start = strtotime($hora_inicio);
        $new_end = strtotime($hora_fin);

        if ($new_start < $exist_end && $new_end > $exist_start) {
            $response['solapamiento_aula'] = true;
            break;
        }
    }

    $response['status'] = true;
    $response['message'] = 'Validaciones completadas.';
    $response['horarios_dia'] = $horariosProfesor; // Devolver los horarios del profesor para el cálculo de horas totales

    echo json_encode($response);

} catch (PDOException $e) {
    $response['status'] = false;
    $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    echo json_encode($response);
}
?>