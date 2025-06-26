<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'data' => []];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // Consulta para obtener usuarios con rol 'estudiante' y su matrícula
        // Asumo que id_usuario en la tabla usuarios es el mismo id_estudiante en la tabla estudiantes.
        // Si no es así, la relación debe ser manejada en tu diseño de BD y la consulta ajustada.
        $stmt = $pdo->prepare("
            SELECT
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.email,
                u.dni,
                u.estado,
                e.matricula
            FROM
                usuarios u
            LEFT JOIN
                estudiantes e ON u.id_usuario = e.id_estudiante
            WHERE
                u.rol = 'estudiante'
            ORDER BY
                u.apellido, u.nombre
        ");
        $stmt->execute();
        $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = $estudiantes;

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener estudiantes: ' . $e->getMessage();
    error_log("Error en obtener_estudiantes.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener estudiantes: ' . $e->getMessage();
    error_log("Error inesperado en obtener_estudiantes.php: " . $e->getMessage());
}

echo json_encode($response);
?>
