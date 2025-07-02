<?php
// fetch_students.php

// --- INICIO DE DEPURACIÓN TEMPORAL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

require_once '../includes/functions.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Asegúrate de que solo los profesores logueados puedan acceder
check_login_and_role('Profesor');

$current_user_id = $_SESSION['user_id'];

// Obtener el id_profesor del usuario logueado
$stmt_profesor_id = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
$stmt_profesor_id->bindParam(':id_usuario', $current_user_id, PDO::PARAM_INT);
$stmt_profesor_id->execute();
$id_profesor_actual = $stmt_profesor_id->fetchColumn();

if (!$id_profesor_actual) {
    echo json_encode(['status' => 'error', 'message' => 'Error: No se encontró el perfil de profesor asociado a su usuario.']);
    exit;
}

// Obtener id_horario de la solicitud GET
$id_horario = filter_input(INPUT_GET, 'id_horario', FILTER_VALIDATE_INT);

if (!$id_horario) {
    echo json_encode(['status' => 'error', 'message' => 'ID de horario no proporcionado o inválido.']);
    exit;
}

try {
    // Validar que el horario pertenece al profesor actual y al semestre activo
    $current_semester = get_current_semester($pdo);
    if (!$current_semester) {
        echo json_encode(['status' => 'error', 'message' => 'No hay un semestre activo para esta operación.']);
        exit;
    }

    $stmt_validate_schedule = $pdo->prepare("
        SELECT COUNT(*)
        FROM horarios
        WHERE id = :id_horario
        AND id_profesor = :id_profesor
        AND id_semestre = :id_semestre
    ");
    $stmt_validate_schedule->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt_validate_schedule->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
    $stmt_validate_schedule->bindParam(':id_semestre', $current_semester['id'], PDO::PARAM_INT);
    $stmt_validate_schedule->execute();

    if ($stmt_validate_schedule->fetchColumn() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Acceso denegado o horario no encontrado para este profesor en el semestre actual.']);
        exit;
    }

    // Obtener la lista de estudiantes inscritos en este horario (asignatura-curso-semestre)
    // Asumimos una tabla `inscripciones` que vincula estudiantes a horarios
    // o una lógica más compleja que relacione `estudiantes`, `horarios`, `cursos`, `asignaturas` y `semestres`.
    // Por simplicidad, asumiremos que las inscripciones se hacen a `horarios`.

    $stmt_students = $pdo->prepare("
        SELECT
            e.id AS id_estudiante,
            e.nombre,
            e.apellido,
            e.numero_identificacion,
            e.email
        FROM estudiantes e
        JOIN inscripciones i ON e.id = i.id_estudiante
        WHERE i.id_horario = :id_horario
        AND i.estado_inscripcion = 'confirmado' -- O el estado que uses para estudiantes activos
        ORDER BY e.apellido, e.nombre ASC
    ");
    $stmt_students->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt_students->execute();
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'students' => $students]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>