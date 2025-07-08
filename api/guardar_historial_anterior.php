<?php
require_once '../config/database.php'; // Conexión PDO
header('Content-Type: application/json');

$response = ['status' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_historial_multiple') {
    $id_estudiante_db = filter_var($_POST['id_estudiante_db'], FILTER_VALIDATE_INT);
    $id_anio_academico = filter_var($_POST['id_anio_academico'], FILTER_VALIDATE_INT);
    $id_semestre = filter_var($_POST['id_semestre'], FILTER_VALIDATE_INT);
    $asignaturas_data = $_POST['asignaturas'] ?? [];

    if (!$id_estudiante_db || !$id_anio_academico || !$id_semestre) {
        $response['message'] = 'Datos de estudiante, año o semestre inválidos.';
        echo json_encode($response);
        exit();
    }

    if (empty($asignaturas_data)) {
        $response['message'] = 'No se recibieron datos de asignaturas para guardar.';
        echo json_encode($response);
        exit();
    }

    $pdo->beginTransaction();
    try {
        $updates_count = 0;
        $inserts_count = 0;

        foreach ($asignaturas_data as $id_asignatura => $data) {
            $id_asignatura = filter_var($id_asignatura, FILTER_VALIDATE_INT);
            $nota_final = filter_var($data['nota_final'], FILTER_VALIDATE_FLOAT);
            $estado_final = filter_var($data['estado_final'], FILTER_SANITIZE_STRING);
            $id_historial = filter_var($data['id_historial'], FILTER_VALIDATE_INT); // Puede ser nulo o 0 si es nuevo

            // Validar si la nota o el estado están vacíos (no se intentan guardar si no hay datos)
            if (($nota_final === false || $nota_final === null) && empty($estado_final)) {
                continue; // Saltar esta asignatura si ambos campos están vacíos
            }

            // Si la nota_final es vacía pero se envió el estado, se permite.
            // Si la nota_final tiene un valor, se valida que sea un número.
            if ($nota_final === false && !empty($data['nota_final'])) { // Si no es float y no está vacío
                 // Esto maneja casos donde se envía algo no numérico pero no es estrictamente vacío
                 $response['message'] = 'Nota final inválida para la asignatura ID ' . $id_asignatura;
                 $pdo->rollBack();
                 echo json_encode($response);
                 exit();
            }


            // Verificar si ya existe una entrada para esta asignatura en este semestre para este estudiante
            // Usamos id_asignatura, id_estudiante, id_semestre como clave única (o casi única)
            $stmt_check = $pdo->prepare("
                SELECT id FROM historial_academico
                WHERE id_estudiante = :id_estudiante AND id_asignatura = :id_asignatura AND id_semestre = :id_semestre
            ");
            $stmt_check->execute([
                ':id_estudiante' => $id_estudiante_db,
                ':id_asignatura' => $id_asignatura,
                ':id_semestre' => $id_semestre
            ]);
            $existing_history_id = $stmt_check->fetchColumn();


            if ($existing_history_id) {
                // Actualizar entrada existente
                $stmt_update = $pdo->prepare("
                    UPDATE historial_academico
                    SET nota_final = :nota_final, estado_final = :estado_final, fecha_actualizacion = NOW()
                    WHERE id = :id_historial_entry
                ");
                $stmt_update->execute([
                    ':nota_final' => $nota_final,
                    ':estado_final' => $estado_final,
                    ':id_historial_entry' => $existing_history_id // Usar el ID encontrado en la base de datos
                ]);
                $updates_count++;
            } else {
                // Insertar nueva entrada
                $stmt_insert = $pdo->prepare("
                    INSERT INTO historial_academico (id_estudiante, id_asignatura, id_semestre, nota_final, estado_final)
                    VALUES (:id_estudiante, :id_asignatura, :id_semestre, :nota_final, :estado_final)
                ");
                $stmt_insert->execute([
                    ':id_estudiante' => $id_estudiante_db,
                    ':id_asignatura' => $id_asignatura,
                    ':id_semestre' => $id_semestre,
                    ':nota_final' => $nota_final,
                    ':estado_final' => $estado_final
                ]);
                $inserts_count++;
            }
        }

        $pdo->commit();
        $response['status'] = true;
        $response['message'] = "Historial académico guardado exitosamente. (Insertados: {$inserts_count}, Actualizados: {$updates_count})";

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error PDO en guardar_historial_anterior.php: " . $e->getMessage());
        $response['message'] = 'Error de base de datos al guardar historial: ' . $e->getMessage();
    }

} else {
    $response['message'] = 'Solicitud inválida.';
}

echo json_encode($response);
?>