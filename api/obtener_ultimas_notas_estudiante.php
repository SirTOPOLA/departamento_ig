<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

$response = ['status' => false, 'message' => '', 'data' => []];

// Validación del parámetro
if (!isset($_GET['id_estudiante']) || !is_numeric($_GET['id_estudiante'])) {
    $response['message'] = 'ID de estudiante no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

$id_estudiante = (int) $_GET['id_estudiante'];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // Obtener las últimas 5 notas según las inscripciones confirmadas
        $stmt = $pdo->prepare("
            SELECT 
                a.nombre AS asignatura,
                n.parcial_1,
                n.parcial_2,
                n.examen_final,
                n.promedio,
                n.observaciones,
                an.anio AS anio_academico,
                s.nombre AS semestre
            FROM notas n
            INNER JOIN inscripciones i ON i.id_inscripcion = n.id_inscripcion
            INNER JOIN asignaturas a ON i.id_asignatura = a.id_asignatura
            INNER JOIN anios_academicos an ON i.id_anio = an.id_anio
            INNER JOIN semestres s ON i.id_semestre = s.id_semestre
            WHERE i.id_estudiante = :id_estudiante AND i.estado = 'confirmado'
            ORDER BY n.id_nota DESC
            LIMIT 5
        ");
        $stmt->execute([':id_estudiante' => $id_estudiante]);

        $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = $notas;

    } else {
        $response['message'] = 'Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    error_log("Error en obtener_ultimas_notas_estudiante.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado: ' . $e->getMessage();
    error_log("Error inesperado en obtener_ultimas_notas_estudiante.php: " . $e->getMessage());
}

echo json_encode($response);
?>
