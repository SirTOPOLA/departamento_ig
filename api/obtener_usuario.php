<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

$response = ['status' => false, 'message' => '', 'data' => null];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $response['message'] = 'ID de usuario no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

$id_usuario = intval($_GET['id']);

try {
    if ($pdo instanceof PDO) {
        // Consulta principal con LEFT JOIN según el rol del usuario
        $stmt = $pdo->prepare("
            SELECT 
                u.id_usuario, u.nombre, u.apellido, u.email, u.dni, u.direccion,
                u.telefono, u.rol,
                est.matricula,
                ce.id_curso,
                ce.id_anio AS id_anio_academico,
                prof.especialidad
            FROM usuarios u
            LEFT JOIN estudiantes est ON u.id_usuario = est.id_estudiante
            LEFT JOIN curso_estudiante ce ON est.id_estudiante = ce.id_estudiante 
                AND ce.estado = 'activo'
            LEFT JOIN profesores prof ON u.id_usuario = prof.id_profesor
            WHERE u.id_usuario = :id_usuario
            LIMIT 1
        ");
        $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $response['status'] = true;
            $response['data'] = $usuario;
        } else {
            $response['message'] = 'Usuario no encontrado.';
        }
    } else {
        $response['message'] = 'Conexión a la base de datos no válida.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    error_log("Error PDO en obtener_usuario.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado: ' . $e->getMessage();
    error_log("Error general en obtener_usuario.php: " . $e->getMessage());
}

echo json_encode($response);
