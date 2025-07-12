<?php
require_once '../config/database.php'; // Conexión PDO
header('Content-Type: application/json');

$response = ['status' => false, 'message' => '', 'data' => []];

if (!isset($_GET['id_semestre']) || !is_numeric($_GET['id_semestre']) ||
    !isset($_GET['id_estudiante']) || !is_numeric($_GET['id_estudiante'])) {
    $response['message'] = 'Parámetros inválidos para cargar asignaturas.';
    echo json_encode($response);
    exit();
}

$id_semestre = (int)$_GET['id_semestre'];
$id_estudiante = (int)$_GET['id_estudiante']; // id_estudiante de la tabla `estudiantes`

try {
    // Primero, obtener el id_curso_asociado_al_semestre del semestre dado
    $stmt_semestre_curso = $pdo->prepare("SELECT id_curso_asociado_al_semestre FROM semestres WHERE id = :id_semestre");
    $stmt_semestre_curso->execute([':id_semestre' => $id_semestre]);
    $curso_asociado = $stmt_semestre_curso->fetch(PDO::FETCH_ASSOC);

    $id_curso_asociado = $curso_asociado['id_curso_asociado_al_semestre'] ?? null;

    if (!$id_curso_asociado) {
        // Si el semestre no tiene un curso asociado, aún podemos listar asignaturas sin filtro de curso,
        // o devolver un mensaje de que no hay asignaturas definidas para ese semestre/curso.
        // Por ahora, devolveremos un array vacío si no hay curso asociado para no cargar todas las asignaturas
        // de la base de datos sin un contexto claro.
        $response['status'] = true;
        $response['message'] = 'El semestre no tiene un curso asociado, o no se encontraron asignaturas.';
        echo json_encode($response);
        exit();
    }

    // Unir asignaturas con historial académico para el estudiante y semestre específicos
    // y filtrar por el curso asociado al semestre
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.nombre_asignatura,
            a.creditos,
            ha.nota_final,
            ha.estado_final,
            ha.id AS id_historial -- ID de la entrada en historial_academico, si existe
        FROM
            asignaturas a
        LEFT JOIN
            historial_academico ha ON a.id = ha.id_asignatura
            AND ha.id_estudiante = :id_estudiante
            AND ha.id_semestre = :id_semestre
        WHERE
            a.id_curso = :id_curso_asociado_al_semestre AND a.semestre_recomendado = (
                SELECT s.numero_semestre FROM semestres s WHERE s.id = :id_semestre_asignatura
            )
        ORDER BY
            a.nombre_asignatura ASC
    ");
    $stmt->execute([
        ':id_estudiante' => $id_estudiante,
        ':id_semestre' => $id_semestre,
        ':id_curso_asociado_al_semestre' => $id_curso_asociado,
        ':id_semestre_asignatura' => $id_semestre // Usamos este id_semestre para la subconsulta de semestre_recomendado
    ]);
    $asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['status'] = true;
    $response['message'] = 'Asignaturas y historial cargados exitosamente.';
    $response['data'] = $asignaturas;

} catch (PDOException $e) {
    error_log("Error PDO en get_asignaturas_con_historial.php: " . $e->getMessage());
    $response['message'] = 'Error de base de datos al cargar asignaturas o historial.';
}

echo json_encode($response);
?>