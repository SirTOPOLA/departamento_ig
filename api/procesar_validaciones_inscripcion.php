<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'updated_inscripciones' => 0, 'updated_historial' => 0];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit();
}

$id_estudiante = filter_input(INPUT_POST, 'id_estudiante', FILTER_VALIDATE_INT);
$inscripciones_data = $_POST['inscripciones'] ?? []; // Array asociativo: id_inscripcion => [estado, resultado_historial, nota_final, observacion]

if (!$id_estudiante || empty($inscripciones_data)) {
    $response['message'] = 'Datos incompletos para procesar las validaciones.';
    echo json_encode($response);
    exit();
}

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Error: Conexión PDO no disponible.');
    }

    $pdo->beginTransaction(); // Inicia una transacción

    $updatedInscripcionesCount = 0;
    $updatedHistorialCount = 0;

    foreach ($inscripciones_data as $id_inscripcion => $data) {
        $id_inscripcion = filter_var($id_inscripcion, FILTER_VALIDATE_INT);
        $new_estado = filter_var($data['estado'] ?? '', FILTER_SANITIZE_STRING);
        $resultado_historial = filter_var($data['resultado_historial'] ?? '', FILTER_SANITIZE_STRING);
        $nota_final = filter_var($data['nota_final'] ?? null, FILTER_VALIDATE_FLOAT);
        $observacion = filter_var($data['observacion'] ?? '', FILTER_SANITIZE_STRING);

        if (!$id_inscripcion || !in_array($new_estado, ['preinscrito', 'confirmado', 'rechazado'])) {
            error_log("Datos de inscripción inválidos para ID: {$id_inscripcion}");
            continue;
        }

        // 1. Actualizar el estado de la inscripción
        $stmtUpdateInscripcion = $pdo->prepare("
            UPDATE inscripciones
            SET estado = :new_estado
            WHERE id_inscripcion = :id_inscripcion AND id_estudiante = :id_estudiante
        ");
        $stmtUpdateInscripcion->bindParam(':new_estado', $new_estado);
        $stmtUpdateInscripcion->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
        $stmtUpdateInscripcion->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        if ($stmtUpdateInscripcion->execute()) {
            $updatedInscripcionesCount += $stmtUpdateInscripcion->rowCount();
        } else {
            error_log("Error al actualizar inscripción {$id_inscripcion}: " . implode(" - ", $stmtUpdateInscripcion->errorInfo()));
        }

        // 2. Gestionar el Historial Académico si se especificó un resultado para el historial
        if (!empty($resultado_historial) && in_array($resultado_historial, ['aprobado', 'reprobado', 'regular' ,'abandono', 'convalidado'])) {
            // Obtener detalles de la inscripción para el historial
            $stmtInscripcionDetails = $pdo->prepare("
                SELECT id_asignatura, id_anio
                FROM inscripciones
                WHERE id_inscripcion = :id_inscripcion AND id_estudiante = :id_estudiante
            ");
            $stmtInscripcionDetails->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
            $stmtInscripcionDetails->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
            $stmtInscripcionDetails->execute();
            $inscripcion_details = $stmtInscripcionDetails->fetch(PDO::FETCH_ASSOC);

            if ($inscripcion_details) {
                $id_asignatura = $inscripcion_details['id_asignatura'];
                $id_anio_historial = $inscripcion_details['id_anio'];

                // Intentar insertar o actualizar el historial académico
                $stmtInsertUpdateHistorial = $pdo->prepare("
                    INSERT INTO historial_academico (id_estudiante, id_asignatura, resultado, nota_final, id_anio, observacion, fecha)
                    VALUES (:id_estudiante, :id_asignatura, :resultado, :nota_final, :id_anio, :observacion, NOW())
                    ON DUPLICATE KEY UPDATE
                        resultado = VALUES(resultado),
                        nota_final = VALUES(nota_final),
                        observacion = VALUES(observacion),
                        fecha = VALUES(fecha)
                ");
                $stmtInsertUpdateHistorial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
                $stmtInsertUpdateHistorial->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                $stmtInsertUpdateHistorial->bindParam(':resultado', $resultado_historial);
                $stmtInsertUpdateHistorial->bindValue(':nota_final', $nota_final);
                $stmtInsertUpdateHistorial->bindParam(':id_anio', $id_anio_historial, PDO::PARAM_INT);
                $stmtInsertUpdateHistorial->bindValue(':observacion', $observacion);

                if ($stmtInsertUpdateHistorial->execute()) {
                    $updatedHistorialCount++;
                } else {
                    error_log("Error al insertar/actualizar historial para inscripcion {$id_inscripcion}: " . implode(" - ", $stmtInsertUpdateHistorial->errorInfo()));
                }
            } else {
                error_log("No se encontraron detalles de inscripción para ID: {$id_inscripcion} para historial.");
            }
        }
    }

    $pdo->commit(); // Confirma la transacción
    $response['status'] = true;
    $response['message'] = "Se actualizaron {$updatedInscripcionesCount} inscripciones y se gestionaron {$updatedHistorialCount} registros de historial.";
    $response['updated_inscripciones'] = $updatedInscripcionesCount;
    $response['updated_historial'] = $updatedHistorialCount;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error de base de datos al procesar validaciones: ' . $e->getMessage();
    error_log("Error en procesar_validaciones_inscripcion.php (PDO): " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error inesperado al procesar validaciones: ' . $e->getMessage();
    error_log("Error inesperado en procesar_validaciones_inscripcion.php: " . $e->getMessage());
}

echo json_encode($response);
?>
