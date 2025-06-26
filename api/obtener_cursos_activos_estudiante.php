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
        // Primero, obtén los cursos activos en los que está inscrito el estudiante
        $stmt_cursos = $pdo->prepare("
            SELECT
                ce.id,
                ce.id_curso,
                ce.id_anio,
                ce.estado,
                ce.fecha_registro,
                c.nombre AS curso_nombre,
                c.turno,
                c.grupo,
                an.anio AS anio_academico
            FROM
                curso_estudiante ce
            JOIN
                cursos c ON ce.id_curso = c.id_curso
            LEFT JOIN
                anios_academicos an ON ce.id_anio = an.id_anio
            WHERE
                ce.id_estudiante = :id_estudiante AND ce.estado = 'activo'
            ORDER BY
                an.anio DESC, c.nombre ASC
        ");
        $stmt_cursos->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmt_cursos->execute();
        $cursos_activos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

        // Para cada curso activo, obtén las asignaturas asociadas al curso (desde la tabla `asignaturas`)
        $result_data = [];
        foreach ($cursos_activos as $curso) {
            // Inicializa el array de asignaturas para cada curso
            $curso['asignacion_asignaturas'] = []; // Renombrado a asignacion_asignaturas

            $stmt_asignaturas = $pdo->prepare("
                SELECT
                    a.id_asignatura,
                    a.nombre,
                    a.codigo,
                    s.nombre AS semestre_nombre -- Obtener el nombre del semestre
                FROM
                    asignaturas a
                JOIN
                    semestres s ON a.semestre_id = s.id_semestre
                WHERE
                    a.curso_id = :id_curso
                ORDER BY
                    a.nombre ASC
            ");
            $stmt_asignaturas->bindParam(':id_curso', $curso['id_curso'], PDO::PARAM_INT);
            $stmt_asignaturas->execute();
            $asignaturas = $stmt_asignaturas->fetchAll(PDO::FETCH_ASSOC);

            $curso['asignacion_asignaturas'] = $asignaturas;
            $result_data[] = $curso;
        }

        $response['status'] = true;
        $response['data'] = $result_data;

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener cursos y asignaturas: ' . $e->getMessage();
    error_log("Error en obtener_cursos_activos_estudiante.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener cursos y asignaturas: ' . $e->getMessage();
    error_log("Error inesperado en obtener_cursos_activos_estudiante.php: " . $e->getMessage());
}

echo json_encode($response);
?>
