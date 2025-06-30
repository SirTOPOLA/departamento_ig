<?php
// get_pending_enrollments.php
require_once '../includes/functions.php';
// check_login_and_role('Administrador'); // Esto es opcional aquí

require_once '../config/database.php'; // Conexión PDO

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

$id_usuario = filter_var($_GET['id_usuario'] ?? null, FILTER_VALIDATE_INT);
$id_semestre_actual = filter_var($_GET['id_semestre'] ?? null, FILTER_VALIDATE_INT);

if (!$id_usuario || !$id_semestre_actual) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario o semestre no proporcionado.']);
    exit;
}

try {
    $stmt_pending_enrollments = $pdo->prepare("
        SELECT
            ie.id AS id_inscripcion,
            a.nombre_asignatura,
            a.creditos,
            c.nombre_curso,
            a.semestre_recomendado,
            ie.fecha_inscripcion
        FROM inscripciones_estudiantes ie
        JOIN estudiantes e ON ie.id_estudiante = e.id
        JOIN usuarios u ON e.id_usuario = u.id
        JOIN asignaturas a ON ie.id_asignatura = a.id
        LEFT JOIN cursos c ON a.id_curso = c.id
        WHERE u.id = :id_usuario
        AND ie.confirmada = 0
        AND ie.id_semestre = :id_semestre_actual
        ORDER BY a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");
    $stmt_pending_enrollments->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_pending_enrollments->bindParam(':id_semestre_actual', $id_semestre_actual, PDO::PARAM_INT);
    $stmt_pending_enrollments->execute();
    $enrollments = $stmt_pending_enrollments->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'enrollments' => $enrollments]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>