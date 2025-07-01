<?php
// get_inscripciones_pendientes.php
// Este script se encarga de obtener las inscripciones pendientes de un estudiante específico
// para un semestre dado, incluyendo detalles de la asignatura y el curso al que pertenece.

// Incluir funciones auxiliares y la configuración de la base de datos
require_once '../includes/functions.php';
require_once '../config/database.php'; // Conexión PDO

// Establecer el encabezado para indicar que la respuesta será JSON
header('Content-Type: application/json');

// Filtrar y validar los parámetros de entrada (GET)
$id_usuario = filter_var($_GET['id_usuario'] ?? null, FILTER_VALIDATE_INT);
$id_semestre_actual = filter_var($_GET['id_semestre'] ?? null, FILTER_VALIDATE_INT);

// Verificar que los IDs necesarios han sido proporcionados y son válidos
if (!$id_usuario || !$id_semestre_actual) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario o ID de semestre no proporcionado o inválido.']);
    exit; // Terminar la ejecución si los parámetros no son válidos
}

try {
    // Preparar la consulta SQL para obtener las inscripciones pendientes
    // Se unen las tablas para obtener el nombre de la asignatura, créditos,
    // el curso al que pertenece la asignatura y el semestre recomendado.
    $stmt_inscripciones_pendientes = $pdo->prepare("
        SELECT
            ie.id AS id_inscripcion,         -- ID de la inscripción
            a.nombre_asignatura,            -- Nombre de la asignatura
            a.creditos,                     -- Créditos de la asignatura
            c.nombre_curso,                 -- Nombre del curso al que pertenece la asignatura
            a.semestre_recomendado,         -- Semestre recomendado para la asignatura
            ie.fecha_inscripcion            -- Fecha en que el estudiante solicitó la inscripción
        FROM inscripciones_estudiantes ie
        JOIN estudiantes e ON ie.id_estudiante = e.id  -- Unir con la tabla estudiantes
        JOIN usuarios u ON e.id_usuario = u.id          -- Unir con la tabla usuarios para filtrar por id_usuario
        JOIN asignaturas a ON ie.id_asignatura = a.id   -- Unir con la tabla asignaturas
        LEFT JOIN cursos c ON a.id_curso = c.id         -- Unir con la tabla cursos (LEFT JOIN por si la asignatura no tiene curso asociado)
        WHERE u.id = :id_usuario                        -- Filtrar por el ID de usuario del estudiante
        AND ie.confirmada = 0                           -- Solo inscripciones que no han sido confirmadas
        AND ie.id_semestre = :id_semestre_actual        -- Filtrar por el semestre académico actual
        ORDER BY a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");

    // Vincular los parámetros a la consulta preparada
    $stmt_inscripciones_pendientes->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_inscripciones_pendientes->bindParam(':id_semestre_actual', $id_semestre_actual, PDO::PARAM_INT);

    // Ejecutar la consulta
    $stmt_inscripciones_pendientes->execute();

    // Obtener todos los resultados como un array asociativo
    $inscripciones = $stmt_inscripciones_pendientes->fetchAll(PDO::FETCH_ASSOC);

    // Devolver la respuesta en formato JSON con éxito y los datos de las inscripciones
    echo json_encode(['success' => true, 'enrollments' => $inscripciones]);

} catch (PDOException $e) {
    // Capturar cualquier error de la base de datos y devolver una respuesta JSON de error
    error_log("Error en get_inscripciones_pendientes.php: " . $e->getMessage()); // Registrar el error para depuración
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al obtener inscripciones: ' . $e->getMessage()]);
}
?>
