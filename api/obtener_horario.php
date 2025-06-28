<?php
// obtener_horario.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => false, 'message' => 'ID de horario no proporcionado o inválido.']);
    exit();
}

$id_horario = $_GET['id'];

try {
    // Cambio aquí: seleccionar id_anio en lugar de id_periodo
    $stmt = $pdo->prepare("SELECT h.*, a.nombre AS asignatura_nombre, u.nombre AS profesor_nombre, u.apellido AS profesor_apellido, au.nombre AS aula_nombre, aa.anio AS anio_academico_nombre
                           FROM horarios h
                           JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
                           JOIN profesores p ON h.id_profesor = p.id_profesor
                           JOIN usuarios u ON p.id_profesor = u.id_usuario
                           JOIN aulas au ON h.aula_id = au.id_aula
                           JOIN anios_academicos aa ON h.id_anio = aa.id_anio
                           WHERE h.id_horario = :id_horario");
    $stmt->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt->execute();
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($horario) {
        echo json_encode([
            'status' => true,
            'horario' => $horario
        ]);
    } else {
        echo json_encode([
            'status' => false,
            'message' => 'Horario no encontrado.'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Error al obtener el horario: ' . $e->getMessage()
    ]);
}
?>