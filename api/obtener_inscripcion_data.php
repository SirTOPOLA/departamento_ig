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
        $pdo->beginTransaction(); // Opcional, solo para lectura, pero buena práctica si se extiende

        // 1. Obtener el id_curso del estudiante (asumiendo que tiene un curso_estudiante activo)
        $stmtCursoActivo = $pdo->prepare("
            SELECT id_curso, id_anio
            FROM curso_estudiante
            WHERE id_estudiante = :id_estudiante AND estado = 'activo'
            LIMIT 1
        ");
        $stmtCursoActivo->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtCursoActivo->execute();
        $curso_estudiante_activo = $stmtCursoActivo->fetch(PDO::FETCH_ASSOC);

        if (!$curso_estudiante_activo) {
            // El estudiante no tiene un curso activo asignado, esto es un pre-requisito
            $response['message'] = 'El estudiante no está inscrito en un curso activo. No se pueden inscribir asignaturas.';
            $pdo->rollBack();
            echo json_encode($response);
            exit();
        }

        $id_curso_estudiante = $curso_estudiante_activo['id_curso'];
        $id_anio_actual = $curso_estudiante_activo['id_anio'];

        // 2. Obtener Años Académicos
        $stmtAnios = $pdo->query("SELECT id_anio, anio FROM anios_academicos WHERE activo = 1 ORDER BY anio DESC");
        $anios_academicos = $stmtAnios->fetchAll(PDO::FETCH_ASSOC);

        // Intentar obtener el año académico activo actual para preseleccionar
        $anio_activo_info = null;
        if ($id_anio_actual) {
            $stmtAnioActual = $pdo->prepare("SELECT id_anio, anio FROM anios_academicos WHERE id_anio = :id_anio LIMIT 1");
            $stmtAnioActual->bindParam(':id_anio', $id_anio_actual, PDO::PARAM_INT);
            $stmtAnioActual->execute();
            $anio_activo_info = $stmtAnioActual->fetch(PDO::FETCH_ASSOC);
        }


        // 3. Obtener Semestres (asumo que se relacionan con cursos, o son globales)
        // Aquí obtendremos los semestres asociados al curso del estudiante
        $stmtSemestres = $pdo->prepare("SELECT id_semestre, nombre FROM semestres WHERE curso_id = :id_curso ORDER BY nombre ASC");
        $stmtSemestres->bindParam(':id_curso', $id_curso_estudiante, PDO::PARAM_INT);
        $stmtSemestres->execute();
        $semestres = $stmtSemestres->fetchAll(PDO::FETCH_ASSOC);


        // 4. Obtener Asignaturas (solo las del curso del estudiante) y sus requisitos
        $stmtAsignaturas = $pdo->prepare("
            SELECT
                a.id_asignatura,
                a.nombre,
                a.codigo,
                a.descripcion,
                a.curso_id,
                a.semestre_id,
                s.nombre AS semestre_nombre
            FROM
                asignaturas a
            JOIN
                semestres s ON a.semestre_id = s.id_semestre
            WHERE
                a.curso_id = :id_curso_estudiante
            ORDER BY
                a.semestre_id, a.nombre ASC
        ");
        $stmtAsignaturas->bindParam(':id_curso_estudiante', $id_curso_estudiante, PDO::PARAM_INT);
        $stmtAsignaturas->execute();
        $asignaturas = $stmtAsignaturas->fetchAll(PDO::FETCH_ASSOC);

        // Cargar requisitos para cada asignatura
        foreach ($asignaturas as &$asignatura) {
            $stmtRequisitos = $pdo->prepare("
                SELECT requisito_id
                FROM asignatura_requisitos
                WHERE asignatura_id = :id_asignatura
            ");
            $stmtRequisitos->bindParam(':id_asignatura', $asignatura['id_asignatura'], PDO::PARAM_INT);
            $stmtRequisitos->execute();
            $asignatura['requisitos'] = $stmtRequisitos->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($asignatura); // Romper la referencia al último elemento


        // 5. Obtener Historial Académico del estudiante (para prerrequisitos y asignaturas ya aprobadas/reprobadas)
        $stmtHistorial = $pdo->prepare("
            SELECT id_asignatura, resultado, id_anio
            FROM historial_academico
            WHERE id_estudiante = :id_estudiante
        ");
        $stmtHistorial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtHistorial->execute();
        $historial_academico = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

        // 6. Obtener Inscripciones activas/preinscritas del estudiante (para el año y semestre más reciente o actual)
        // Esto es para mostrar si ya está inscrito en algo para el periodo
        $stmtInscripcionesActivas = $pdo->prepare("
            SELECT id_asignatura, id_anio, id_semestre, estado, tipo
            FROM inscripciones
            WHERE id_estudiante = :id_estudiante
              AND (estado = 'preinscrito' OR estado = 'confirmado')
              AND id_anio = :id_anio_actual -- Considerar solo las del año académico actual
            ORDER BY fecha_inscripcion DESC
        ");
        $stmtInscripcionesActivas->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtInscripcionesActivas->bindParam(':id_anio_actual', $id_anio_actual, PDO::PARAM_INT);
        $stmtInscripcionesActivas->execute();
        $inscripciones_activas = $stmtInscripcionesActivas->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = [
            'anios_academicos' => $anios_academicos,
            'anio_activo' => $anio_activo_info, // Para preseleccionar
            'semestres' => $semestres,
            'asignaturas' => $asignaturas,
            'historial_academico' => $historial_academico,
            'inscripciones_activas' => $inscripciones_activas,
            'current_course_id' => $id_curso_estudiante,
            'current_academic_year_id' => $id_anio_actual
        ];
        $pdo->commit();

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error de base de datos al obtener datos de inscripción: ' . $e->getMessage();
    error_log("Error en obtener_inscripcion_data.php: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error inesperado al obtener datos de inscripción: ' . $e->getMessage();
    error_log("Error inesperado en obtener_inscripcion_data.php: " . $e->getMessage());
}

echo json_encode($response);
?>
