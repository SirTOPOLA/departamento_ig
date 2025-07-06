<?php
// api/obtener_grupos_por_asignatura.php

require_once '../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'grupos' => []];

$id_asignatura = filter_input(INPUT_POST, 'id_asignatura', FILTER_VALIDATE_INT);
$id_semestre = filter_input(INPUT_POST, 'id_semestre', FILTER_VALIDATE_INT);

if (!$id_asignatura || !$id_semestre) {
    $response['message'] = 'Faltan parámetros: asignatura o semestre.';
    echo json_encode($response);
    exit;
}

try {
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
    INNER JOIN horarios h ON h.id_grupo_asignatura = ga.id AND h.id_semestre = :id_semestre1
    INNER JOIN aulas au ON h.id_aula = au.id
    LEFT JOIN inscripciones_estudiantes ie 
        ON ie.id_grupo_asignatura = ga.id AND ie.id_semestre = :id_semestre2 AND ie.confirmada = 1
    WHERE ga.id_asignatura = :id_asignatura
      AND ga.id_curso IN (
          SELECT id_curso_asociado_al_semestre FROM semestres WHERE id = :id_semestre3
      )
    GROUP BY ga.id, ga.grupo, ga.turno, u.nombre_completo, au.nombre_aula, au.capacidad
    HAVING inscritos < au.capacidad
    ORDER BY ga.turno ASC, ga.grupo ASC
");

$stmt->bindParam(':id_semestre1', $id_semestre, PDO::PARAM_INT);
$stmt->bindParam(':id_semestre2', $id_semestre, PDO::PARAM_INT);
$stmt->bindParam(':id_semestre3', $id_semestre, PDO::PARAM_INT);
$stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
$stmt->execute();

  

    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['grupos'] = $grupos;

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>
