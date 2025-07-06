<?php
// api/actualizar_horario_inscripcion.php

require_once '../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

$id_enrollment = filter_input(INPUT_POST, 'id_enrollment', FILTER_VALIDATE_INT);
$id_horario = filter_input(INPUT_POST, 'id_horario', FILTER_VALIDATE_INT);

if (!$id_enrollment || !$id_horario) {
    $response['message'] = 'Faltan datos de inscripción o horario.';
    echo json_encode($response);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Validar que el horario exista y tenga cupo
    $stmt_check = $pdo->prepare("
        SELECT 
            h.id AS id_horario,
            h.id_grupo_asignatura,
            a.capacidad,
            (
                SELECT COUNT(*) 
                FROM inscripciones_estudiantes ie 
                WHERE ie.id_grupo_asignatura = h.id_grupo_asignatura 
                AND ie.id_horario = h.id 
                AND ie.confirmada = 1
            ) AS estudiantes_inscritos
        FROM horarios h
        JOIN aulas a ON h.id_aula = a.id
        WHERE h.id = :id_horario
    ");
    $stmt_check->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt_check->execute();
    $horario = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$horario || $horario['estudiantes_inscritos'] >= $horario['capacidad']) {
        $pdo->rollBack();
        $response['message'] = 'El horario no existe o no tiene cupo disponible.';
        echo json_encode($response);
        exit;
    }

    // 2. Verificar que el horario pertenece al mismo grupo que la inscripción
    $stmt_verificar_grupo = $pdo->prepare("
        SELECT id_grupo_asignatura FROM inscripciones_estudiantes WHERE id = :id_enrollment AND confirmada = 0
    ");
    $stmt_verificar_grupo->bindParam(':id_enrollment', $id_enrollment, PDO::PARAM_INT);
    $stmt_verificar_grupo->execute();
    $inscripcion = $stmt_verificar_grupo->fetch(PDO::FETCH_ASSOC);

    if (!$inscripcion || $inscripcion['id_grupo_asignatura'] != $horario['id_grupo_asignatura']) {
        $pdo->rollBack();
        $response['message'] = 'El horario seleccionado no corresponde al grupo de la inscripción.';
        echo json_encode($response);
        exit;
    }

    // 3. Actualizar la inscripción con el id_horario
    $stmt_update = $pdo->prepare("
        UPDATE inscripciones_estudiantes 
        SET id_horario = :id_horario 
        WHERE id = :id_enrollment AND confirmada = 0
    ");
    $stmt_update->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt_update->bindParam(':id_enrollment', $id_enrollment, PDO::PARAM_INT);
    $stmt_update->execute();

    if ($stmt_update->rowCount() > 0) {
        // 4. Obtener detalles del horario actualizado
        $stmt_detalle = $pdo->prepare("
            SELECT 
                h.dia_semana,
                h.hora_inicio,
                h.hora_fin,
                h.turno,
                u.nombre_completo AS nombre_profesor,
                a.nombre_aula
            FROM horarios h
            JOIN grupos_asignaturas ga ON h.id_grupo_asignatura = ga.id
            JOIN profesores p ON ga.id_profesor = p.id
            JOIN usuarios u ON p.id_usuario = u.id
            JOIN aulas a ON h.id_aula = a.id
            WHERE h.id = :id_horario
        ");
        $stmt_detalle->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
        $stmt_detalle->execute();
        $horario_detalles = $stmt_detalle->fetch(PDO::FETCH_ASSOC);

        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Horario actualizado correctamente.';
        $response['horario_details'] = $horario_detalles;

    } else {
        $pdo->rollBack();
        $response['message'] = 'No se pudo actualizar. La inscripción ya está confirmada o no se encontró.';
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>
