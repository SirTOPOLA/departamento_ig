<?php
// api/obtener_horarios_profesores.php

// Incluye la configuración de la base de datos
require_once '../config/database.php'; // Adjust path as necessary

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'horarios' => []];

// --- START MODIFICATION FOR FormData (reading $_POST) ---
// Valida los parámetros de entrada POST
$id_asignatura = filter_input(INPUT_POST, 'id_asignatura', FILTER_VALIDATE_INT);
$id_semestre = filter_input(INPUT_POST, 'id_semestre', FILTER_VALIDATE_INT);
// --- END MODIFICATION FOR FormData ---


if (!$id_asignatura || !$id_semestre) {
    $response['message'] = 'Faltan parámetros de asignatura o semestre.';
    echo json_encode($response);
    exit;
}

try {
    // Obtener los horarios disponibles para la asignatura y el semestre dados
    // y unir con la información del profesor y el aula.
    // También, asegúrate de que no haya un choque de capacidad de aula
    // (aunque el chequeo completo de capacidad y superposición de horario es más complejo y podría estar en un nivel superior)

    $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.dia_semana,
            h.hora_inicio,
            h.hora_fin,
            h.turno,
            u.nombre_completo AS nombre_profesor,
            a.nombre_aula,
            a.capacidad,
            -- Contar cuántos estudiantes ya están inscritos en este horario
            (SELECT COUNT(*) FROM inscripciones_estudiantes WHERE id_horario = h.id AND confirmada = 1) AS estudiantes_inscritos
        FROM
            horarios h
        JOIN
            profesores p ON h.id_profesor = p.id
        JOIN
            usuarios u ON p.id_usuario = u.id
        JOIN
            aulas a ON h.id_aula = a.id
        WHERE
            h.id_asignatura = :id_asignatura AND h.id_semestre = :id_semestre
        HAVING
            estudiantes_inscritos < a.capacidad -- Filtrar horarios que aún tienen capacidad
        ORDER BY
            h.dia_semana, h.hora_inicio
    ");

    $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
    $stmt->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
    $stmt->execute();
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['horarios'] = $horarios;

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>