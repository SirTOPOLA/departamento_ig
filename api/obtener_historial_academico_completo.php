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
        $pdo->beginTransaction(); // Para consultas múltiples y consistencia

        // 1. Obtener información básica del estudiante
        $stmtStudent = $pdo->prepare("
            SELECT
                u.nombre,
                u.apellido,
                e.matricula
            FROM
                usuarios u
            JOIN
                estudiantes e ON u.id_usuario = e.id_estudiante
            WHERE
                u.id_usuario = :id_estudiante AND u.rol = 'estudiante'
        ");
        $stmtStudent->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtStudent->execute();
        $student_info = $stmtStudent->fetch(PDO::FETCH_ASSOC);

        if (!$student_info) {
            $response['message'] = 'Estudiante no encontrado.';
            $pdo->rollBack();
            echo json_encode($response);
            exit();
        }

        // 2. Obtener todos los cursos en los que el estudiante ha estado inscrito
        // Ordenar por año académico para una vista cronológica
        $stmtCourses = $pdo->prepare("
            SELECT
                ce.id AS id_curso_estudiante,
                ce.id_curso,
                ce.id_anio,
                ce.estado AS curso_estado,
                c.nombre AS curso_nombre,
                c.turno AS curso_turno,
                c.grupo AS curso_grupo,
                an.anio AS anio_academico_nombre
            FROM
                curso_estudiante ce
            JOIN
                cursos c ON ce.id_curso = c.id_curso
            JOIN
                anios_academicos an ON ce.id_anio = an.id_anio
            WHERE
                ce.id_estudiante = :id_estudiante
            ORDER BY
                an.anio ASC, ce.fecha_registro ASC
        ");
        $stmtCourses->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtCourses->execute();
        $courses_history_raw = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

        $structured_history = [];

        foreach ($courses_history_raw as $course_entry) {
            $current_course_id = $course_entry['id_curso'];
            $current_anio_id = $course_entry['id_anio'];

            // Obtener semestres asociados a este curso
            $stmtSemesters = $pdo->prepare("
                SELECT
                    s.id_semestre,
                    s.nombre AS semestre_nombre
                FROM
                    semestres s
                WHERE
                    s.curso_id = :id_curso
                ORDER BY
                    s.nombre ASC
            ");
            $stmtSemesters->bindParam(':id_curso', $current_course_id, PDO::PARAM_INT);
            $stmtSemesters->execute();
            $semesters_raw = $stmtSemesters->fetchAll(PDO::FETCH_ASSOC);

            $semestres_data = [];

            foreach ($semesters_raw as $semester_entry) {
                $current_semestre_id = $semester_entry['id_semestre'];

                // Obtener asignaturas con resultados del historial para este estudiante, curso, año y semestre
                $stmtSubjects = $pdo->prepare("
                    SELECT
                        ha.id_asignatura,
                        ha.resultado,
                        ha.nota_final,
                        ha.observacion,
                        ha.fecha AS fecha_resultado,
                        a.nombre AS asignatura_nombre,
                        a.codigo AS asignatura_codigo
                    FROM
                        historial_academico ha
                    JOIN
                        asignaturas a ON ha.id_asignatura = a.id_asignatura
                    WHERE
                        ha.id_estudiante = :id_estudiante
                        AND ha.id_anio = :id_anio
                        AND a.curso_id = :id_curso -- Asignatura pertenece al curso
                        AND a.semestre_id = :id_semestre -- Asignatura pertenece al semestre
                    ORDER BY
                        a.nombre ASC
                ");
                $stmtSubjects->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
                $stmtSubjects->bindParam(':id_anio', $current_anio_id, PDO::PARAM_INT);
                $stmtSubjects->bindParam(':id_curso', $current_course_id, PDO::PARAM_INT);
                $stmtSubjects->bindParam(':id_semestre', $current_semestre_id, PDO::PARAM_INT);
                $stmtSubjects->execute();
                $subjects_data = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

                $semester_entry['asignaturas'] = $subjects_data;
                $semestres_data[] = $semester_entry;
            }
            $course_entry['semestres'] = $semestres_data;
            $structured_history[] = $course_entry;
        }

        $response['status'] = true;
        $response['data'] = [
            'student_info' => $student_info,
            'courses_history' => $structured_history
        ];

        $pdo->commit();

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error de base de datos al obtener historial académico completo: ' . $e->getMessage();
    error_log("Error en obtener_historial_academico_completo.php: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error inesperado al obtener historial académico completo: ' . $e->getMessage();
    error_log("Error inesperado en obtener_historial_academico_completo.php: " . $e->getMessage());
}

echo json_encode($response);
?>
