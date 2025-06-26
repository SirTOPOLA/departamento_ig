<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'data' => []];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT id_curso, nombre, turno, grupo FROM cursos ORDER BY nombre ASC");
        $stmt->execute();
        $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = $cursos;

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener cursos: ' . $e->getMessage();
    error_log("Error en obtener_cursos.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener cursos: ' . $e->getMessage();
    error_log("Error inesperado en obtener_cursos.php: " . $e->getMessage());
}

echo json_encode($response);
?>
