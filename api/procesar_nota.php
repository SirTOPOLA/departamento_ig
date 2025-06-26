<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

$response = ['status' => false, 'message' => '', 'processed_count' => 0];
$logFilePath = 'logs/log.txt'; // Asegúrate de que la ruta sea correcta y apunte al archivo .txt

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit();
}

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Conexión PDO no disponible.');
    }

    if (!file_exists($logFilePath) || filesize($logFilePath) == 0) {
        $response['status'] = true;
        $response['message'] = 'No hay notas pendientes para procesar.';
        echo json_encode($response);
        exit();
    }

    $lines = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new Exception('No se pudo leer el archivo de log.');
    }

    $pdo->beginTransaction();

    $processedCount = 0;
    foreach ($lines as $line) {
        $noteData = json_decode($line, true);

        if (!is_array($noteData) || !isset($noteData['id_inscripcion'])) {
            error_log("Línea inválida: " . $line);
            continue;
        }

        $id_inscripcion = (int)$noteData['id_inscripcion'];
        $parcial_1 = $noteData['parcial_1'] ?? null;
        $parcial_2 = $noteData['parcial_2'] ?? null;
        $examen_final = $noteData['examen_final'] ?? null;
        $observaciones = $noteData['observaciones'] ?? null;

        // Verifica que la inscripción exista y obtén datos relacionados
        $stmtInfo = $pdo->prepare("
            SELECT 
                i.id_estudiante,
                i.id_asignatura,
                i.id_anio
            FROM inscripciones i
            WHERE i.id_inscripcion = :id_inscripcion
        ");
        $stmtInfo->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
        $stmtInfo->execute();
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            error_log("No se encontró información de la inscripción con ID $id_inscripcion.");
            continue;
        }

        $id_estudiante = $info['id_estudiante'];
        $id_asignatura = $info['id_asignatura'];
        $id_anio = $info['id_anio'];

        // Insertar o actualizar nota
        $stmtNota = $pdo->prepare("
            INSERT INTO notas (id_inscripcion, parcial_1, parcial_2, examen_final, observaciones)
            VALUES (:id_inscripcion, :parcial_1, :parcial_2, :examen_final, :observaciones)
            ON DUPLICATE KEY UPDATE
                parcial_1 = VALUES(parcial_1),
                parcial_2 = VALUES(parcial_2),
                examen_final = VALUES(examen_final),
                observaciones = VALUES(observaciones)
        ");
        $stmtNota->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
        $stmtNota->bindValue(':parcial_1', $parcial_1);
        $stmtNota->bindValue(':parcial_2', $parcial_2);
        $stmtNota->bindValue(':examen_final', $examen_final);
        $stmtNota->bindValue(':observaciones', $observaciones);
        $stmtNota->execute();

        // Calcular promedio y resultado
        $notas = array_filter([$parcial_1, $parcial_2, $examen_final], fn($n) => $n !== null);
        $promedio = count($notas) ? array_sum($notas) / count($notas) : null;
        $resultado = $promedio === null ? 'abandono' : ($promedio >= 5 ? 'aprobado' : 'reprobado');

        // Insertar o actualizar historial
        $stmtHistCheck = $pdo->prepare("
            SELECT id FROM historial_academico
            WHERE id_estudiante = :id_estudiante
              AND id_asignatura = :id_asignatura
              AND id_anio = :id_anio
            LIMIT 1
        ");
        $stmtHistCheck->execute([
            ':id_estudiante' => $id_estudiante,
            ':id_asignatura' => $id_asignatura,
            ':id_anio' => $id_anio
        ]);
        $historial_id = $stmtHistCheck->fetchColumn();

        if ($historial_id) {
            $stmtUpdateHist = $pdo->prepare("
                UPDATE historial_academico
                SET resultado = :resultado, nota_final = :nota_final, observacion = :observacion, fecha = CURRENT_TIMESTAMP()
                WHERE id = :id
            ");
            $stmtUpdateHist->execute([
                ':resultado' => $resultado,
                ':nota_final' => $promedio,
                ':observacion' => $observaciones,
                ':id' => $historial_id
            ]);
        } else {
            $stmtInsertHist = $pdo->prepare("
                INSERT INTO historial_academico (id_estudiante, id_asignatura, resultado, nota_final, id_anio, observacion, fecha)
                VALUES (:id_estudiante, :id_asignatura, :resultado, :nota_final, :id_anio, :observacion, CURRENT_TIMESTAMP())
            ");
            $stmtInsertHist->execute([
                ':id_estudiante' => $id_estudiante,
                ':id_asignatura' => $id_asignatura,
                ':resultado' => $resultado,
                ':nota_final' => $promedio,
                ':id_anio' => $id_anio,
                ':observacion' => $observaciones
            ]);
        }

        $processedCount++;
    }

    // Vaciar el archivo log tras procesar
    file_put_contents($logFilePath, '');

    $pdo->commit();

    $response['status'] = true;
    $response['message'] = "Se procesaron {$processedCount} notas correctamente.";
    $response['processed_count'] = $processedCount;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    error_log("PDO Error: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("General Error: " . $e->getMessage());
}

echo json_encode($response);
?>
