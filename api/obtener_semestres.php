<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'data' => []];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $searchTerm = isset($_GET['busqueda']) ? '%' . $_GET['busqueda'] . '%' : '%';

        $sql = "
            SELECT
                s.id_semestre,
                s.nombre,
                c.nombre AS nombre_curso,
                c.turno
            FROM
                semestres s
            JOIN
                cursos c ON s.curso_id = c.id_curso
            WHERE
                s.nombre LIKE :searchTermSemestre OR
                c.nombre LIKE :searchTermCurso
            ORDER BY
                c.nombre, s.nombre
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':searchTermSemestre', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':searchTermCurso', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
        $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['data'] = $semestres;

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos al obtener semestres: ' . $e->getMessage();
    error_log("Error en obtener_semestres.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado al obtener semestres: ' . $e->getMessage();
    error_log("Error inesperado en obtener_semestres.php: " . $e->getMessage());
}

echo json_encode($response);
?>
