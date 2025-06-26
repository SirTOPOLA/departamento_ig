<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

$response = ['status' => false, 'message' => '', 'data' => []];

if (!isset($_GET['id_estudiante']) || !is_numeric($_GET['id_estudiante'])) {
    $response['message'] = 'ID de estudiante no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

$id_estudiante = filter_var($_GET['id_estudiante'], FILTER_VALIDATE_INT);

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // Paso 1: Encontrar el id_curso y id_anio activos para el estudiante
        $stmtActiveCourse = $pdo->prepare("
            SELECT ce.id_curso, ce.id_anio
            FROM curso_estudiante ce
            WHERE ce.id_estudiante = :id_estudiante AND ce.estado = 'activo'
            LIMIT 1
        ");
        $stmtActiveCourse->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtActiveCourse->execute();
        $activeCourseInfo = $stmtActiveCourse->fetch(PDO::FETCH_ASSOC);

        if (!$activeCourseInfo) {
            $response['message'] = 'Estudiante no inscrito en un curso activo o no se encontró curso activo.';
            $response['data'] = []; // No hay horario si no hay curso activo
            echo json_encode($response);
            exit();
        }

        $id_curso_activo = $activeCourseInfo['id_curso'];
        $id_anio_activo = $activeCourseInfo['id_anio'];

        // Paso 2: Obtener el horario para las asignaturas asociadas a este curso y año
        // Esto asume que un estudiante en un curso activo para un año, toma todas las asignaturas de ese curso.
        // Si la inscripción es por asignatura (tabla 'inscripciones'), se debería usar esa.
        // Adaptaremos para usar 'inscripciones' para mayor precisión.
        $stmtHorario = $pdo->prepare("
            SELECT
                h.dia,
                h.hora_inicio,
                h.hora_fin,
                a.nombre AS asignatura_nombre,
                u.nombre AS profesor_nombre,
                u.apellido AS profesor_apellido,
                'Aula Desconocida' AS aula_nombre -- Asumo que no hay tabla 'aulas' y este es un placeholder
            FROM
                inscripciones i
            JOIN
                asignaturas a ON i.id_asignatura = a.id_asignatura
            LEFT JOIN
                horarios h ON a.id_asignatura = h.id_asignatura
            LEFT JOIN
                usuarios u ON h.id_profesor = u.id_usuario AND u.rol = 'profesor'
            WHERE
                i.id_estudiante = :id_estudiante
                AND i.id_anio = :id_anio_activo
                AND i.estado = 'confirmado' -- Solo clases confirmadas
            ORDER BY
                FIELD(h.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'),
                h.hora_inicio
        ");
        $stmtHorario->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtHorario->bindParam(':id_anio_activo', $id_anio_activo, PDO::PARAM_INT);
        $stmtHorario->execute();
        $horario = $stmtHorario->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = $horario;

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener horario: ' . $e->getMessage();
    error_log("Error en obtener_horario_estudiante.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener horario: ' . $e->getMessage();
    error_log("Error inesperado en obtener_horario_estudiante.php: " . $e->getMessage());
}

echo json_encode($response);
?>
