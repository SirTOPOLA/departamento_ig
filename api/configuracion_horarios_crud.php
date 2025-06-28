<?php
// configuracion_horarios_crud.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$response = ['status' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null; // Obtener la acción, si se proporciona (ej. 'create')

    $id_config = $_POST['id_config'] ?? null;
    $dia_semana = $_POST['dia_semana'] ?? null; // Necesario para la creación
    $hora_inicio_permitida = $_POST['hora_inicio_permitida'] ?? null;
    $hora_fin_permitida = $_POST['hora_fin_permitida'] ?? null;
    $max_horas_dia_profesor = $_POST['max_horas_dia_profesor'] ?? null;
    $min_duracion_clase_min = $_POST['min_duracion_clase_min'] ?? null;
    $max_duracion_clase_min = $_POST['max_duracion_clase_min'] ?? null;
    $requiere_mixto_horas = isset($_POST['requiere_mixto_horas']) ? (int)$_POST['requiere_mixto_horas'] : 0;
    $min_clases_1h_mixto = $_POST['min_clases_1h_mixto'] ?? 0;
    $min_clases_2h_mixto = $_POST['min_clases_2h_mixto'] ?? 0;

    // Validaciones básicas de entrada para ambos casos (crear/actualizar)
    if (!$dia_semana || !$hora_inicio_permitida || !$hora_fin_permitida || !$max_horas_dia_profesor ||
        !$min_duracion_clase_min || !$max_duracion_clase_min) {
        $response['message'] = 'Todos los campos obligatorios deben ser completados.';
        echo json_encode($response);
        exit();
    }

    try {
        if ($action === 'create') {
            // --- Crear Nueva Configuración ---
            // Verificar si el día de la semana ya existe
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM configuracion_horarios WHERE dia_semana = :dia_semana");
            $stmtCheck->bindParam(':dia_semana', $dia_semana, PDO::PARAM_STR);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                $response['message'] = 'Ya existe una configuración para el día "' . htmlspecialchars($dia_semana) . '". Por favor, edítela en su lugar.';
                echo json_encode($response);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO configuracion_horarios (
                                    dia_semana, hora_inicio_permitida, hora_fin_permitida,
                                    max_horas_dia_profesor, min_duracion_clase_min, max_duracion_clase_min,
                                    requiere_mixto_horas, min_clases_1h_mixto, min_clases_2h_mixto
                                ) VALUES (
                                    :dia_semana, :hora_inicio_permitida, :hora_fin_permitida,
                                    :max_horas_dia_profesor, :min_duracion_clase_min, :max_duracion_clase_min,
                                    :requiere_mixto_horas, :min_clases_1h_mixto, :min_clases_2h_mixto
                                )");
            $message = 'Nueva regla de horario creada correctamente.';

        } else { // Si no es 'create', asumimos que es una actualización
            // --- Actualizar Configuración Existente ---
            if (!$id_config) {
                $response['message'] = 'ID de configuración no proporcionado para la actualización.';
                echo json_encode($response);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE configuracion_horarios SET
                                    hora_inicio_permitida = :hora_inicio_permitida,
                                    hora_fin_permitida = :hora_fin_permitida,
                                    max_horas_dia_profesor = :max_horas_dia_profesor,
                                    min_duracion_clase_min = :min_duracion_clase_min,
                                    max_duracion_clase_min = :max_duracion_clase_min,
                                    requiere_mixto_horas = :requiere_mixto_horas,
                                    min_clases_1h_mixto = :min_clases_1h_mixto,
                                    min_clases_2h_mixto = :min_clases_2h_mixto
                                    WHERE id = :id_config");
            $stmt->bindParam(':id_config', $id_config, PDO::PARAM_INT);
            $message = 'Configuración de horario actualizada correctamente.';
        }

        // Parámetros comunes para INSERT y UPDATE
        $stmt->bindParam(':hora_inicio_permitida', $hora_inicio_permitida, PDO::PARAM_STR);
        $stmt->bindParam(':hora_fin_permitida', $hora_fin_permitida, PDO::PARAM_STR);
        $stmt->bindParam(':max_horas_dia_profesor', $max_horas_dia_profesor, PDO::PARAM_INT);
        $stmt->bindParam(':min_duracion_clase_min', $min_duracion_clase_min, PDO::PARAM_INT);
        $stmt->bindParam(':max_duracion_clase_min', $max_duracion_clase_min, PDO::PARAM_INT);
        $stmt->bindParam(':requiere_mixto_horas', $requiere_mixto_horas, PDO::PARAM_INT);
        $stmt->bindParam(':min_clases_1h_mixto', $min_clases_1h_mixto, PDO::PARAM_INT);
        $stmt->bindParam(':min_clases_2h_mixto', $min_clases_2h_mixto, PDO::PARAM_INT);

        // Si es una creación, también se necesita el dia_semana aquí
        if ($action === 'create') {
            $stmt->bindParam(':dia_semana', $dia_semana, PDO::PARAM_STR);
        }

        $stmt->execute();

        $response['status'] = true;
        $response['message'] = $message;
        echo json_encode($response);

    } catch (PDOException $e) {
        $response['message'] = 'Error de base de datos al guardar/actualizar configuración: ' . $e->getMessage();
        error_log('Error en configuracion_horarios_crud.php (POST): ' . $e->getMessage());
        echo json_encode($response);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- Obtener Configuración o Eliminar ---
    $action = $_GET['action'] ?? null;
    $id_config = $_GET['id'] ?? null;

    if ($action === 'delete' && $id_config) {
        // --- Eliminar Configuración ---
        try {
            // Prevenir la eliminación de la configuración 'Default'
            $stmtCheck = $pdo->prepare("SELECT dia_semana FROM configuracion_horarios WHERE id = :id_config");
            $stmtCheck->bindParam(':id_config', $id_config, PDO::PARAM_INT);
            $stmtCheck->execute();
            $configToDelete = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($configToDelete && $configToDelete['dia_semana'] === 'Default') {
                $response['message'] = 'No se puede eliminar la configuración por defecto.';
                echo json_encode($response);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM configuracion_horarios WHERE id = :id_config");
            $stmt->bindParam(':id_config', $id_config, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $response['status'] = true;
                $response['message'] = 'Configuración eliminada correctamente.';
            } else {
                $response['message'] = 'Configuración no encontrada.';
            }
            echo json_encode($response);
        } catch (PDOException $e) {
            $response['message'] = 'Error al eliminar configuración: ' . $e->getMessage();
            error_log('Error en configuracion_horarios_crud.php (Delete): ' . $e->getMessage());
            echo json_encode($response);
        }
    } elseif ($id_config) {
        // --- Obtener una sola configuración ---
        try {
            $stmt = $pdo->prepare("SELECT * FROM configuracion_horarios WHERE id = :id_config");
            $stmt->bindParam(':id_config', $id_config, PDO::PARAM_INT);
            $stmt->execute();
            $configuracion = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($configuracion) {
                $response['status'] = true;
                $response['configuracion'] = $configuracion;
            } else {
                $response['message'] = 'Configuración no encontrada.';
            }
            echo json_encode($response);
        } catch (PDOException $e) {
            $response['message'] = 'Error al obtener configuración: ' . $e->getMessage();
            error_log('Error en configuracion_horarios_crud.php (GET single): ' . $e->getMessage());
            echo json_encode($response);
        }
    } else {
        $response['message'] = 'Acción GET no válida o ID faltante.';
        echo json_encode($response);
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
    echo json_encode($response);
}
?>
