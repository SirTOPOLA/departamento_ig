<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

$response = ['status' => false, 'message' => '', 'data' => []];

if (!isset($_GET['id_profesor']) || !is_numeric($_GET['id_profesor']) ||
    !isset($_GET['id_asignatura']) || !is_numeric($_GET['id_asignatura']) ||
    !isset($_GET['id_anio']) || !is_numeric($_GET['id_anio'])) {
    $response['message'] = 'Parámetros de consulta incompletos o inválidos.';
    echo json_encode($response);
    exit();
}

$id_profesor = filter_var($_GET['id_profesor'], FILTER_VALIDATE_INT);
$id_asignatura = filter_var($_GET['id_asignatura'], FILTER_VALIDATE_INT);
$id_anio = filter_var($_GET['id_anio'], FILTER_VALIDATE_INT);

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // Verificar que el profesor esté asignado a esta asignatura
        $stmtCheckAssignment = $pdo->prepare("
            SELECT COUNT(*) FROM asignatura_profesor
            WHERE id_profesor = :id_profesor AND id_asignatura = :id_asignatura
        ");
        $stmtCheckAssignment->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
        $stmtCheckAssignment->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
        $stmtCheckAssignment->execute();
        if ($stmtCheckAssignment->fetchColumn() == 0) {
            $response['message'] = 'El profesor no está asignado a esta asignatura.';
            echo json_encode($response);
            exit();
        }

        // Obtener estudiantes inscritos en la asignatura para el año dado, y sus notas
        $stmtStudents = $pdo->prepare("
            SELECT
                u.id_usuario AS id_estudiante,
                u.nombre AS nombre_estudiante,
                u.apellido AS apellido_estudiante,
                e.matricula,
                i.id_inscripcion,
                n.parcial_1,
                n.parcial_2,
                n.examen_final,
                n.promedio,
                n.observaciones
            FROM
                inscripciones i
            JOIN
                usuarios u ON i.id_estudiante = u.id_usuario
            JOIN
                estudiantes e ON u.id_usuario = e.id_estudiante
            LEFT JOIN
                notas n ON i.id_inscripcion = n.id_inscripcion -- Unir con notas si existen
            WHERE
                i.id_asignatura = :id_asignatura
                AND i.id_anio = :id_anio
                AND i.estado = 'confirmado' -- Solo alumnos con inscripción confirmada
            ORDER BY
                u.apellido, u.nombre
        ");
        $stmtStudents->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
        $stmtStudents->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
        $stmtStudents->execute();
        $alumnos_y_notas = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = $alumnos_y_notas;

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener alumnos y notas: ' . $e->getMessage();
    error_log("Error en obtener_alumnos_y_notas.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener alumnos y notas: ' . $e->getMessage();
    error_log("Error inesperado en obtener_alumnos_y_notas.php: " . $e->getMessage());
}

echo json_encode($response);
?>
