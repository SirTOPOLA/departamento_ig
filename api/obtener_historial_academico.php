<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'data' => []];

if (!isset($_GET['id_estudiante']) || !is_numeric($_GET['id_estudiante'])) {
    $response['message'] = 'ID de estudiante no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

$id_estudiante = filter_var($_GET['id_estudiante'], FILTER_VALIDATE_INT);

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("
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
                ha.id_estudiante = :id_estudiante
            ORDER BY
                an.anio DESC, ha.fecha DESC
        ");
        $stmt->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmt->execute();
        $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = $historial;

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener historial académico: ' . $e->getMessage();
    error_log("Error en obtener_historial_academico.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener historial académico: ' . $e->getMessage();
    error_log("Error inesperado en obtener_historial_academico.php: " . $e->getMessage());
}

echo json_encode($response);
?>
