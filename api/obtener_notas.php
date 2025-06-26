<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'data' => []];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $searchTerm = isset($_GET['busqueda']) ? '%' . $_GET['busqueda'] . '%' : '%';

        $sql = "
            SELECT
                n.id_nota,
                n.parcial_1,
                n.parcial_2,
                n.examen_final,
                n.promedio,
                n.observaciones,
                u.nombre AS nombre_estudiante,
                u.apellido AS apellido_estudiante,
                a.nombre AS asignatura_nombre
            FROM
                notas n
            JOIN
                curso_estudiante ce ON n.id_inscripcion = ce.id
            JOIN
                usuarios u ON ce.id_estudiante = u.id_usuario -- Asumiendo que id_estudiante de curso_estudiante es id_usuario de usuarios
            JOIN
                asignaturas a ON a.curso_id = ce.id_curso -- Asumiendo que la asignatura se relaciona al curso de la inscripción
            WHERE
                u.rol = 'estudiante' AND (
                    u.nombre LIKE :searchTermNombre OR
                    u.apellido LIKE :searchTermApellido OR
                    a.nombre LIKE :searchTermAsignatura
                )
            ORDER BY
                n.id_nota DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':searchTermNombre', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':searchTermApellido', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':searchTermAsignatura', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
        $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = $notas;

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener notas: ' . $e->getMessage();
    error_log("Error en obtener_notas.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener notas: ' . $e->getMessage();
    error_log("Error inesperado en obtener_notas.php: " . $e->getMessage());
}

echo json_encode($response);
?>
