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
$id_anio = filter_input(INPUT_GET, 'id_anio', FILTER_VALIDATE_INT);
$id_semestre = filter_input(INPUT_GET, 'id_semestre', FILTER_VALIDATE_INT);

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // 1. Obtener Años Académicos
        $stmtAnios = $pdo->query("SELECT id_anio, anio FROM anios_academicos WHERE activo = 1 ORDER BY anio DESC");
        $anios_academicos = $stmtAnios->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener Semestres (globales o por curso, aquí asumimos por curso asociado al estudiante)
        // Primero, obtener el curso actual del estudiante para filtrar semestres
        $stmtCurrentCourse = $pdo->prepare("SELECT id_curso, id_anio FROM curso_estudiante WHERE id_estudiante = :id_estudiante AND estado = 'activo' LIMIT 1");
        $stmtCurrentCourse->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtCurrentCourse->execute();
        $current_course_info = $stmtCurrentCourse->fetch(PDO::FETCH_ASSOC);
        $current_course_id = $current_course_info['id_curso'] ?? null;
        $current_academic_year_id = $current_course_info['id_anio'] ?? null;


        $semestres = [];
        if ($current_course_id) {
            $stmtSemestres = $pdo->prepare("SELECT id_semestre, nombre FROM semestres WHERE curso_id = :id_curso ORDER BY nombre ASC");
            $stmtSemestres->bindParam(':id_curso', $current_course_id, PDO::PARAM_INT);
            $stmtSemestres->execute();
            $semestres = $stmtSemestres->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Si el estudiante no tiene un curso activo, aún se pueden mostrar todos los semestres si es necesario
            // O bien, puedes decidir no mostrar semestres si no hay un curso activo para el estudiante.
            $stmtSemestres = $pdo->query("SELECT id_semestre, nombre FROM semestres ORDER BY nombre ASC");
            $semestres = $stmtSemestres->fetchAll(PDO::FETCH_ASSOC);
        }


        // 3. Obtener Preinscripciones del estudiante para el año y semestre seleccionados (o todas las preinscritas si no hay filtro)
        $sqlPreinscripciones = "
            SELECT
                i.id_inscripcion,
                i.id_asignatura,
                i.id_anio,
                i.id_semestre,
                i.estado,
                i.tipo,
                a.nombre AS asignatura_nombre,
                s.nombre AS semestre_nombre,
                an.anio AS anio_academico
            FROM
                inscripciones i
            JOIN
                asignaturas a ON i.id_asignatura = a.id_asignatura
            JOIN
                semestres s ON i.id_semestre = s.id_semestre
            JOIN
                anios_academicos an ON i.id_anio = an.id_anio
            WHERE
                i.id_estudiante = :id_estudiante AND i.estado IN ('preinscrito', 'confirmado', 'rechazado')
        ";
        $params = [':id_estudiante' => $id_estudiante];

        if ($id_anio) {
            $sqlPreinscripciones .= " AND i.id_anio = :id_anio";
            $params[':id_anio'] = $id_anio;
        }
        if ($id_semestre) {
            $sqlPreinscripciones .= " AND i.id_semestre = :id_semestre";
            $params[':id_semestre'] = $id_semestre;
        }
        $sqlPreinscripciones .= " ORDER BY an.anio DESC, s.nombre ASC, a.nombre ASC";

        $stmtPreinscripciones = $pdo->prepare($sqlPreinscripciones);
        $stmtPreinscripciones->execute($params);
        $preinscripciones = $stmtPreinscripciones->fetchAll(PDO::FETCH_ASSOC);

        // 4. Obtener Historial Académico del estudiante (para saber si ya aprobó/convalidó)
        $stmtHistorial = $pdo->prepare("
            SELECT id_asignatura, resultado, id_anio, nota_final
            FROM historial_academico
            WHERE id_estudiante = :id_estudiante
        ");
        $stmtHistorial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtHistorial->execute();
        $historial_academico = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);


        $response['status'] = true;
        $response['data'] = [
            'anios_academicos' => $anios_academicos,
            'semestres' => $semestres,
            'preinscripciones' => $preinscripciones,
            'historial_academico' => $historial_academico,
            'current_academic_year_id' => $current_academic_year_id // Para preseleccionar el año en el frontend
        ];

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener preinscripciones para admin: ' . $e->getMessage();
    error_log("Error en obtener_preinscripciones_para_admin.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener preinscripciones para admin: ' . $e->getMessage();
    error_log("Error inesperado en obtener_preinscripciones_para_admin.php: " . $e->getMessage());
}

echo json_encode($response);
?>
