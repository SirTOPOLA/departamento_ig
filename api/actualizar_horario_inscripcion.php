<?php
// api/actualizar_horario_inscripcion.php

// Incluye la configuración de la base de datos
require_once '../config/database.php'; // Adjust path as necessary

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Valida los parámetros de entrada POST
$id_enrollment = filter_input(INPUT_POST, 'id_enrollment', FILTER_VALIDATE_INT);
$id_horario = filter_input(INPUT_POST, 'id_horario', FILTER_VALIDATE_INT);

if (!$id_enrollment || !$id_horario) {
    $response['message'] = 'Faltan datos de inscripción o horario.';
    echo json_encode($response);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verificar si el horario seleccionado tiene cupo disponible (importante para evitar sobreventa)
    $stmt_check_capacity = $pdo->prepare("
        SELECT
            a.capacidad,
            (SELECT COUNT(*) FROM inscripciones_estudiantes WHERE id_horario = h.id AND confirmada = 1) AS estudiantes_inscritos
        FROM
            horarios h
        JOIN
            aulas a ON h.id_aula = a.id
        WHERE
            h.id = :id_horario
    ");
    $stmt_check_capacity->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt_check_capacity->execute();
    $horario_info = $stmt_check_capacity->fetch(PDO::FETCH_ASSOC);

    if (!$horario_info || $horario_info['estudiantes_inscritos'] >= $horario_info['capacidad']) {
        $pdo->rollBack();
        $response['message'] = 'El horario seleccionado no tiene cupo disponible o no existe.';
        echo json_encode($response);
        exit;
    }

    // 2. Actualizar la inscripción del estudiante con el nuevo id_horario y confirmarla
    $stmt_update_enrollment = $pdo->prepare("
        UPDATE inscripciones_estudiantes
        SET id_horario = :id_horario
        WHERE id = :id_enrollment AND confirmada = 0
    "); // Only update if not already confirmed

    $stmt_update_enrollment->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt_update_enrollment->bindParam(':id_enrollment', $id_enrollment, PDO::PARAM_INT);
    $stmt_update_enrollment->execute();

    if ($stmt_update_enrollment->rowCount() > 0) {
        // 3. Opcional: Obtener los detalles completos del horario para devolverlos a la UI
        $stmt_get_horario_details = $pdo->prepare("
            SELECT
                h.dia_semana,
                h.hora_inicio,
                h.hora_fin,
                h.turno,
                u.nombre_completo,
                a.nombre_aula
            FROM
                horarios h
            JOIN
                profesores p ON h.id_profesor = p.id
            JOIN
                usuarios u ON p.id_usuario = u.id
            JOIN
                aulas a ON h.id_aula = a.id
            WHERE h.id = :id_horario
        ");
        $stmt_get_horario_details->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
        $stmt_get_horario_details->execute();
        $horario_details = $stmt_get_horario_details->fetch(PDO::FETCH_ASSOC);

        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Horario asignado exitosamente.';
        $response['horario_details'] = $horario_details;

    } else {
        $pdo->rollBack();
        $response['message'] = 'No se pudo actualizar la inscripción o ya estaba confirmada.';
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>