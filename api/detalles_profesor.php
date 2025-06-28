<?php
// detalles_profesor.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$response = ['status' => false, 'message' => ''];

$id_profesor = $_GET['id_profesor'] ?? null;

if (!$id_profesor || !is_numeric($id_profesor)) {
    $response['message'] = 'ID de profesor no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

try {
    // Obtener detalles del profesor
    $stmtProfesor = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.dni, u.estado, p.especialidad, u.telefono, u.direccion
        FROM usuarios u
        INNER JOIN profesores p ON u.id_usuario = p.id_profesor
        WHERE u.id_usuario = :id_profesor AND u.rol = 'profesor'
    ");
    $stmtProfesor->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmtProfesor->execute();
    $profesor = $stmtProfesor->fetch(PDO::FETCH_ASSOC);

    if (!$profesor) {
        $response['message'] = 'Profesor no encontrado.';
        echo json_encode($response);
        exit();
    }

    // Obtener asignaturas asignadas al profesor, incluyendo el nombre del curso y el turno
    $stmtAsignaturas = $pdo->prepare("
        SELECT a.nombre, c.nombre AS curso_nombre, c.turno AS curso_turno
        FROM asignaturas a
        JOIN asignatura_profesor ap ON a.id_asignatura = ap.id_asignatura
        JOIN cursos c ON a.curso_id = c.id_curso -- JOIN para obtener el curso y su turno
        WHERE ap.id_profesor = :id_profesor
        ORDER BY a.nombre
    ");
    $stmtAsignaturas->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmtAsignaturas->execute();
    $asignaturas = $stmtAsignaturas->fetchAll(PDO::FETCH_ASSOC);

    $response['status'] = true;
    $response['profesor'] = $profesor;
    $response['asignaturas'] = $asignaturas;

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    error_log('Error en detalles_profesor.php: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado: ' . $e->getMessage();
    error_log('Error inesperado en detalles_profesor.php: ' . $e->getMessage());
}

echo json_encode($response);
?>
