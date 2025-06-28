<?php
// guardar_horario.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$response = [
    'status' => false,
    'message' => 'Error desconocido.'
];

// Recoger datos del formulario
$id_horario = $_POST['id_horario'] ?? null;
$id_periodo = $_POST['id_periodo'] ?? null;
$id_profesor = $_POST['id_profesor'] ?? null;
$id_asignatura = $_POST['id_asignatura'] ?? null;
$aula_id = $_POST['aula_id'] ?? null;
$dia = $_POST['dia'] ?? null;
$hora_inicio = $_POST['hora_inicio'] ?? null;
$hora_fin = $_POST['hora_fin'] ?? null;

// Validaciones básicas de entrada
if (!$id_periodo || !$id_profesor || !$id_asignatura || !$aula_id || !$dia || !$hora_inicio || !$hora_fin) {
    $response['message'] = 'Todos los campos son obligatorios.';
    echo json_encode($response);
    exit();
}

// Convertir horas a formato TIME si es necesario (ya deberían venir así del input type="time")
// $hora_inicio = date('H:i:s', strtotime($hora_inicio));
// $hora_fin = date('H:i:s', strtotime($hora_fin));

try {
    $pdo->beginTransaction();

    // --- 1. Obtener configuración de horarios para el día ---
    $stmtConfig = $pdo->prepare("SELECT * FROM configuracion_horarios WHERE dia_semana = :dia_semana");
    $stmtConfig->bindParam(':dia_semana', $dia, PDO::PARAM_STR);
    $stmtConfig->execute();
    $configDia = $stmtConfig->fetch(PDO::FETCH_ASSOC);

    // Si no hay configuración específica para el día, usar la 'Default'
    if (!$configDia) {
        $stmtConfig = $pdo->prepare("SELECT * FROM configuracion_horarios WHERE dia_semana = 'Default'");
        $stmtConfig->execute();
        $configDia = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    }

    if (!$configDia) {
        $response['message'] = 'No se encontró configuración de horario para este día ni una configuración por defecto.';
        echo json_encode($response);
        $pdo->rollBack();
        exit();
    }

    // --- 2. Validaciones de Rango Horario y Duración de Clase ---
    $new_start_ts = strtotime($hora_inicio);
    $new_end_ts = strtotime($hora_fin);
    $config_start_ts = strtotime($configDia['hora_inicio_permitida']);
    $config_end_ts = strtotime($configDia['hora_fin_permitida']);

    if ($new_end_ts <= $new_start_ts) {
        $response['message'] = 'La hora de fin debe ser posterior a la hora de inicio.';
        echo json_encode($response);
        $pdo->rollBack();
        exit();
    }
    if ($new_start_ts < $config_start_ts || $new_end_ts > $config_end_ts) {
        $response['message'] = "Las horas deben estar entre " . substr($configDia['hora_inicio_permitida'], 0, 5) . " y " . substr($configDia['hora_fin_permitida'], 0, 5) . " para el día " . $dia . ".";
        echo json_encode($response);
        $pdo->rollBack();
        exit();
    }

    $duracion_minutos = ($new_end_ts - $new_start_ts) / 60;
    if ($duracion_minutos < $configDia['min_duracion_clase_min'] || $duracion_minutos > $configDia['max_duracion_clase_min']) {
        $response['message'] = "La duración de la clase debe ser entre " . ($configDia['min_duracion_clase_min'] / 60) . " y " . ($configDia['max_duracion_clase_min'] / 60) . " horas.";
        echo json_encode($response);
        $pdo->rollBack();
        exit();
    }

    // --- 3. Validaciones de Solapamiento (Profesor y Aula) ---
    // Consulta para verificar solapamiento del profesor
    $sqlSolapamientoProfesor = "SELECT id_horario FROM horarios
                                WHERE id_profesor = :id_profesor
                                AND dia = :dia
                                AND id_periodo = :id_periodo
                                AND (
                                    (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                                )";
    if ($id_horario) { // Excluir el propio horario si es una edición
        $sqlSolapamientoProfesor .= " AND id_horario != :id_horario";
    }
    $stmtSolapamientoProfesor = $pdo->prepare($sqlSolapamientoProfesor);
    $stmtSolapamientoProfesor->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmtSolapamientoProfesor->bindParam(':dia', $dia, PDO::PARAM_STR);
    $stmtSolapamientoProfesor->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
    $stmtSolapamientoProfesor->bindParam(':hora_inicio', $hora_inicio, PDO::PARAM_STR);
    $stmtSolapamientoProfesor->bindParam(':hora_fin', $hora_fin, PDO::PARAM_STR);
    if ($id_horario) {
        $stmtSolapamientoProfesor->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    }
    $stmtSolapamientoProfesor->execute();
    if ($stmtSolapamientoProfesor->rowCount() > 0) {
        $response['message'] = 'El profesor ya tiene un horario que se solapa con el horario propuesto.';
        echo json_encode($response);
        $pdo->rollBack();
        exit();
    }

    // Consulta para verificar solapamiento del aula
    $sqlSolapamientoAula = "SELECT id_horario FROM horarios
                            WHERE aula_id = :aula_id
                            AND dia = :dia
                            AND id_periodo = :id_periodo
                            AND (
                                (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                            )";
    if ($id_horario) { // Excluir el propio horario si es una edición
        $sqlSolapamientoAula .= " AND id_horario != :id_horario";
    }
    $stmtSolapamientoAula = $pdo->prepare($sqlSolapamientoAula);
    $stmtSolapamientoAula->bindParam(':aula_id', $aula_id, PDO::PARAM_INT);
    $stmtSolapamientoAula->bindParam(':dia', $dia, PDO::PARAM_STR);
    $stmtSolapamientoAula->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
    $stmtSolapamientoAula->bindParam(':hora_inicio', $hora_inicio, PDO::PARAM_STR);
    $stmtSolapamientoAula->bindParam(':hora_fin', $hora_fin, PDO::PARAM_STR);
    if ($id_horario) {
        $stmtSolapamientoAula->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    }
    $stmtSolapamientoAula->execute();
    if ($stmtSolapamientoAula->rowCount() > 0) {
        $response['message'] = 'El aula seleccionada ya está ocupada en el horario propuesto.';
        echo json_encode($response);
        $pdo->rollBack();
        exit();
    }

    // --- 4. Validar Horas Máximas por Día por Profesor y Regla Mixta ---
    $sqlHorariosDiaProfesor = "SELECT hora_inicio, hora_fin FROM horarios
                               WHERE id_profesor = :id_profesor
                               AND dia = :dia
                               AND id_periodo = :id_periodo";
    if ($id_horario) {
        $sqlHorariosDiaProfesor .= " AND id_horario != :id_horario";
    }
    $stmtHorariosDiaProfesor = $pdo->prepare($sqlHorariosDiaProfesor);
    $stmtHorariosDiaProfesor->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmtHorariosDiaProfesor->bindParam(':dia', $dia, PDO::PARAM_STR);
    $stmtHorariosDiaProfesor->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
    if ($id_horario) {
        $stmtHorariosDiaProfesor->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    }
    $stmtHorariosDiaProfesor->execute();
    $horariosExistentes = $stmtHorariosDiaProfesor->fetchAll(PDO::FETCH_ASSOC);

    $horas_usadas_profesor = 0;
    $count_1h = 0;
    $count_2h = 0;

    foreach ($horariosExistentes as $h) {
        $hi_ts = strtotime($h['hora_inicio']);
        $hf_ts = strtotime($h['hora_fin']);
        $d = ($hf_ts - $hi_ts) / 60; // Duración en minutos
        $horas_usadas_profesor += $d;
        if (abs($d - 60) < 1) $count_1h++;
        else if (abs($d - 120) < 1) $count_2h++;
    }

    $total_horas_profesor = $horas_usadas_profesor + $duracion_minutos;

    if ($total_horas_profesor > ($configDia['max_horas_dia_profesor'] * 60)) {
        $response['message'] = "El profesor no puede exceder las " . $configDia['max_horas_dia_profesor'] . " horas por día. Total: " . ($total_horas_profesor / 60) . " horas.";
        echo json_encode($response);
        $pdo->rollBack();
        exit();
    }

    // Actualizar conteos para la regla mixta con la nueva clase
    if (abs($duracion_minutos - 60) < 1) $count_1h++;
    else if (abs($duracion_minutos - 120) < 1) $count_2h++;

    // Aplicar la regla de combinación mixta si está activada y se alcanza el máximo de horas
    if ($configDia['requiere_mixto_horas'] && ($total_horas_profesor / 60) == $configDia['max_horas_dia_profesor']) {
        if ($count_1h < $configDia['min_clases_1h_mixto'] || $count_2h < $configDia['min_clases_2h_mixto']) {
            $response['message'] = "Para un total de " . $configDia['max_horas_dia_profesor'] . " horas, debe haber al menos " . $configDia['min_clases_1h_mixto'] . " clase(s) de 1h y " . $configDia['min_clases_2h_mixto'] . " clase(s) de 2h.";
            echo json_encode($response);
            $pdo->rollBack();
            exit();
        }
    }


    // --- 5. Insertar o Actualizar Horario ---
    if ($id_horario) {
        // Actualizar
        $stmt = $pdo->prepare("UPDATE horarios SET
                                id_periodo = :id_periodo,
                                id_asignatura = :id_asignatura,
                                id_profesor = :id_profesor,
                                aula_id = :aula_id,
                                dia = :dia,
                                hora_inicio = :hora_inicio,
                                hora_fin = :hora_fin
                                WHERE id_horario = :id_horario");
        $stmt->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
        $message = 'Horario actualizado correctamente.';
    } else {
        // Insertar
        $stmt = $pdo->prepare("INSERT INTO horarios (id_periodo, id_asignatura, id_profesor, aula_id, dia, hora_inicio, hora_fin) VALUES (
                                :id_periodo, :id_asignatura, :id_profesor, :aula_id, :dia, :hora_inicio, :hora_fin)");
        $message = 'Horario guardado correctamente.';
    }

    $stmt->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
    $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
    $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmt->bindParam(':aula_id', $aula_id, PDO::PARAM_INT);
    $stmt->bindParam(':dia', $dia, PDO::PARAM_STR);
    $stmt->bindParam(':hora_inicio', $hora_inicio, PDO::PARAM_STR);
    $stmt->bindParam(':hora_fin', $hora_fin, PDO::PARAM_STR);

    $stmt->execute();

    $pdo->commit();
    $response['status'] = true;
    $response['message'] = $message;
    echo json_encode($response);

} catch (PDOException $e) {
    $pdo->rollBack();
    $response['message'] = 'Error en la base de datos al guardar/actualizar el horario: ' . $e->getMessage();
    error_log('Error en guardar_horario.php: ' . $e->getMessage()); // Para depuración en el servidor
    echo json_encode($response);
} catch (Exception $e) {
    $pdo->rollBack();
    $response['message'] = 'Error inesperado: ' . $e->getMessage();
    error_log('Error inesperado en guardar_horario.php: ' . $e->getMessage()); // Para depuración en el servidor
    echo json_encode($response);
}
?>
