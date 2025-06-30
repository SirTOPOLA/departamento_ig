<?php
// get_academic_history.php
require_once '../includes/functions.php';
// check_login_and_role('Administrador'); // Esto es opcional aquí

require_once '../config/database.php'; // Conexión PDO

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

$id_usuario = filter_var($_GET['id_usuario'] ?? null, FILTER_VALIDATE_INT);

if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado.']);
    exit;
}

try {
    $stmt_history = $pdo->prepare("
        SELECT
            sa.numero_semestre,
            aa.nombre_anio,
            s.fecha_inicio AS semestre_fecha_inicio,
            s.fecha_fin AS semestre_fecha_fin,
            a.nombre_asignatura,
            a.creditos,
            c.nombre_curso,
            ha.nota_final,
            ha.estado_final
        FROM historial_academico ha
        JOIN estudiantes e ON ha.id_estudiante = e.id
        JOIN usuarios u ON e.id_usuario = u.id
        JOIN asignaturas a ON ha.id_asignatura = a.id
        JOIN semestres s ON ha.id_semestre = s.id
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        LEFT JOIN cursos c ON a.id_curso = c.id
        WHERE u.id = :id_usuario
        ORDER BY aa.nombre_anio ASC, s.numero_semestre ASC, a.nombre_asignatura ASC
    ");
    $stmt_history->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_history->execute();
    $raw_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

    $formatted_history = [];
    foreach ($raw_history as $entry) {
        $semester_key = "Semestre " . htmlspecialchars($entry['numero_semestre']) . " (" . htmlspecialchars($entry['nombre_anio']) . ")";
        if (!isset($formatted_history[$semester_key])) {
            $formatted_history[$semester_key] = [];
        }
        $formatted_history[$semester_key][] = [
            'nombre_asignatura' => htmlspecialchars($entry['nombre_asignatura']),
            'creditos' => htmlspecialchars($entry['creditos']),
            'nombre_curso' => htmlspecialchars($entry['nombre_curso'] ?? 'N/A'),
            'nota_final' => htmlspecialchars($entry['nota_final']),
            'estado_final' => htmlspecialchars($entry['estado_final']),
        ];
    }

    echo json_encode(['success' => true, 'history' => $formatted_history]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>