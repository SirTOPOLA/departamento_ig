<?php
// get_professor_students.php
require_once '../includes/functions.php';
// check_login_and_role('Profesor'); // Opcional, si quieres una capa de seguridad extra aquí

require_once '../config/database.php';

header('Content-Type: application/json');

$id_horario = filter_var($_GET['id_horario'] ?? null, FILTER_VALIDATE_INT);

if (!$id_horario) {
    echo json_encode(['success' => false, 'message' => 'ID de horario no proporcionado.']);
    exit;
}

try {
    // Obtener el id_semestre y id_asignatura de este horario desde la tabla `horarios`
    $stmt_horario_info = $pdo->prepare("SELECT id_semestre, id_asignatura FROM horarios WHERE id = :id_horario");
    $stmt_horario_info->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt_horario_info->execute();
    $horario_info = $stmt_horario_info->fetch(PDO::FETCH_ASSOC);

    if (!$horario_info) {
        echo json_encode(['success' => false, 'message' => 'Horario no encontrado.']);
        exit;
    }

    $id_semestre = $horario_info['id_semestre'];
    $id_asignatura = $horario_info['id_asignatura'];

    // Obtener los estudiantes inscritos y confirmados para esta asignatura y semestre
    // desde `inscripciones_estudiantes`, `estudiantes` y `usuarios`
    $stmt_students = $pdo->prepare("
        SELECT
            u.nombre_completo,
            e.codigo_registro,
            u.email,
            ie.confirmada
        FROM inscripciones_estudiantes ie
        JOIN estudiantes e ON ie.id_estudiante = e.id
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE ie.id_asignatura = :id_asignatura
        AND ie.id_semestre = :id_semestre
        AND ie.confirmada = 1 -- Solo estudiantes con inscripción confirmada
        ORDER BY u.nombre_completo ASC
    ");
    $stmt_students->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
    $stmt_students->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
    $stmt_students->execute();
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'students' => $students]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>