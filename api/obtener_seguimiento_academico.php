<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'data' => null];

if (!isset($_GET['id_estudiante']) || !is_numeric($_GET['id_estudiante'])) {
    $response['message'] = 'ID de estudiante no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

$id_estudiante = filter_var($_GET['id_estudiante'], FILTER_VALIDATE_INT);

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->beginTransaction(); // Opcional, solo para lectura, pero buena práctica

        // 1. Obtener Historial Académico filtrado
        // Resultado debe ser distinto de 'regular'
        $stmtHistorial = $pdo->prepare("
            SELECT
                ha.resultado,
                ha.nota_final,
                ha.observacion,
                ha.fecha,
                a.nombre AS asignatura_nombre,
                an.anio AS anio_academico
            FROM
                historial_academico ha
            JOIN
                asignaturas a ON ha.id_asignatura = a.id_asignatura
            LEFT JOIN
                anios_academicos an ON ha.id_anio = an.id_anio
            WHERE
                ha.id_estudiante = :id_estudiante AND ha.resultado != 'regular'
            ORDER BY
                an.anio DESC, ha.fecha DESC
        ");
        $stmtHistorial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtHistorial->execute();
        $historial_filtrado = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener Inscripciones Confirmadas
        $stmtInscripciones = $pdo->prepare("
            SELECT
                i.id_inscripcion,
                i.id_asignatura,
                i.id_anio,
                i.id_semestre,
                i.estado,
                i.tipo,
                i.fecha_inscripcion,
                a.nombre AS asignatura_nombre,
                s.nombre AS semestre_nombre,
                an.anio AS anio_academico
            FROM
                inscripciones i
            JOIN
                asignaturas a ON i.id_asignatura = a.id_asignatura
            JOIN
                semestres s ON i.id_semestre = s.id_semestre
            JOIN
                anios_academicos an ON i.id_anio = an.id_anio
            WHERE
                i.id_estudiante = :id_estudiante AND i.estado = 'confirmado'
            ORDER BY
                an.anio DESC, s.nombre ASC, i.fecha_inscripcion DESC
        ");
        $stmtInscripciones->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtInscripciones->execute();
        $inscripciones_confirmadas = $stmtInscripciones->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = [
            'historial_filtrado' => $historial_filtrado,
            'inscripciones_confirmadas' => $inscripciones_confirmadas
        ];
        $pdo->commit();

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error de base de datos al obtener seguimiento académico: ' . $e->getMessage();
    error_log("Error en obtener_seguimiento_academico.php: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error inesperado al obtener seguimiento académico: ' . $e->getMessage();
    error_log("Error inesperado en obtener_seguimiento_academico.php: " . $e->getMessage());
}

echo json_encode($response);
?>
