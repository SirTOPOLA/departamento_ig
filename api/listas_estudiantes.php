<?php
// get_grades_for_students.php
require_once '../includes/functions.php';
// check_login_and_role('Profesor'); // Opcional

require_once '../config/database.php';

header('Content-Type: application/json');

$id_horario = filter_var($_GET['id_horario'] ?? null, FILTER_VALIDATE_INT);

if (!$id_horario) {
    echo json_encode(['success' => false, 'message' => 'ID de horario no proporcionado.']);
    exit;
}

try {
    // Obtener información del horario para obtener id_semestre y id_asignatura desde `horarios`
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

    // Obtener estudiantes inscritos y confirmados, junto con sus notas si existen
    // de `inscripciones_estudiantes`, `estudiantes`, `usuarios` y `notas`
    $stmt_students_grades = $pdo->prepare("
        SELECT
            ie.id AS id_inscripcion,
            u.nombre_completo,
            e.codigo_registro,
            n.nota,
            n.estado,
            n.acta_final_confirmada
        FROM inscripciones_estudiantes ie
        JOIN estudiantes e ON ie.id_estudiante = e.id
        JOIN usuarios u ON e.id_usuario = u.id
        LEFT JOIN notas n ON ie.id = n.id_inscripcion -- LEFT JOIN para incluir estudiantes sin nota aún
        WHERE ie.id_asignatura = :id_asignatura
        AND ie.id_semestre = :id_semestre
        AND ie.confirmada = 1 -- Solo estudiantes con inscripción confirmada
        ORDER BY u.nombre_completo ASC
    ");
    $stmt_students_grades->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
    $stmt_students_grades->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
    $stmt_students_grades->execute();
    $students_grades = $stmt_students_grades->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'students' => $students_grades]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>