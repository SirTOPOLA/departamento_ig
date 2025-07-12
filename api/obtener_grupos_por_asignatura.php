<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'grupos' => []];

$id_asignatura = filter_input(INPUT_POST, 'id_asignatura', FILTER_VALIDATE_INT);
$id_estudiante = filter_input(INPUT_POST, 'id_estudiante', FILTER_VALIDATE_INT);

if (!$id_asignatura || !$id_estudiante) {
    $response['message'] = 'Faltan parámetros obligatorios.';
    echo json_encode($response);
    exit;
}

try {
    // 1. Obtener el semestre_recomendado y curso de la asignatura
    $stmtAsignatura = $pdo->prepare("
        SELECT id_curso, semestre_recomendado 
        FROM asignaturas 
        WHERE id = :id_asignatura
    ");
    $stmtAsignatura->execute(['id_asignatura' => $id_asignatura]);
    $asignatura = $stmtAsignatura->fetch(PDO::FETCH_ASSOC);

    if (!$asignatura) {
        throw new Exception('Asignatura no encontrada.');
    }

    $semestre_recomendado = $asignatura['semestre_recomendado'];
    $curso_asignatura = $asignatura['id_curso'];

    // 2. Obtener el año académico actual del estudiante
    $stmtAnio = $pdo->prepare("
        SELECT id_anio 
        FROM curso_estudiante 
        WHERE id_estudiante = :id_estudiante 
          AND estado = 'activo'
        ORDER BY fecha_registro DESC
        LIMIT 1
    ");
    $stmtAnio->execute(['id_estudiante' => $id_estudiante]);
    $id_anio = $stmtAnio->fetchColumn();

    if (!$id_anio) {
        throw new Exception('No se encontró año académico activo para el estudiante.');
    }

    // 3. Obtener el curso actual del estudiante
    $stmtCurso = $pdo->prepare("
        SELECT id_curso 
        FROM curso_estudiante 
        WHERE id_estudiante = :id_estudiante 
          AND id_anio = :id_anio 
          AND estado = 'activo'
        LIMIT 1
    ");
    $stmtCurso->execute([
        'id_estudiante' => $id_estudiante,
        'id_anio' => $id_anio
    ]);
    $id_curso_estudiante = $stmtCurso->fetchColumn();

    if (!$id_curso_estudiante) {
        throw new Exception('El estudiante no tiene curso activo para ese año académico.');
    }

    // 4. Obtener el semestre correspondiente (curso + semestre recomendado)
    $stmtSemestre = $pdo->prepare("
        SELECT id 
        FROM semestres 
        WHERE id_anio_academico = :id_anio
          AND numero_semestre = :semestre_recomendado
          AND id_curso_asociado_al_semestre = :id_curso
        LIMIT 1
    ");
    $stmtSemestre->execute([
        'id_anio' => $id_anio,
        'semestre_recomendado' => $semestre_recomendado,
        'id_curso' => $id_curso_estudiante // Usamos curso del estudiante, no de la asignatura
    ]);
    $id_semestre = $stmtSemestre->fetchColumn();

    if (!$id_semestre) {
        throw new Exception('No se encontró un semestre asociado para esa asignatura.');
    }

    // 5. Obtener grupos disponibles
    $stmt = $pdo->prepare("
        SELECT 
            ga.id AS id_grupo,
            ga.grupo,
            ga.turno,
            u.nombre_completo AS nombre_profesor,
            au.nombre_aula,
            au.capacidad,
            COUNT(ie.id) AS inscritos
        FROM grupos_asignaturas ga
        INNER JOIN profesores p ON ga.id_profesor = p.id
        INNER JOIN usuarios u ON p.id_usuario = u.id
        INNER JOIN horarios h ON h.id_grupo_asignatura = ga.id
            AND h.id_semestre = :id_semestre1
        INNER JOIN aulas au ON h.id_aula = au.id
        LEFT JOIN inscripciones_estudiantes ie ON ie.id_grupo_asignatura = ga.id
            AND ie.id_semestre = :id_semestre2
        WHERE ga.id_asignatura = :id_asignatura
          AND ga.id_curso = :id_curso_estudiante
        GROUP BY 
            ga.id, ga.grupo, ga.turno, 
            u.nombre_completo, 
            au.nombre_aula, au.capacidad
        ORDER BY ga.turno ASC, ga.grupo ASC
    ");

    $stmt->execute([
        'id_semestre1' => $id_semestre,
        'id_semestre2' => $id_semestre,
        'id_asignatura' => $id_asignatura,
        'id_curso_estudiante' => $id_curso_estudiante
    ]);

    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['grupos'] = $grupos;

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
