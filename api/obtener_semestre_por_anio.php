<?php
require_once '../config/database.php'; // Conexión PDO
header('Content-Type: application/json');

$response = ['status' => false, 'message' => '', 'data' => []];

if (!isset($_GET['id_anio']) || !is_numeric($_GET['id_anio'])) {
    $response['message'] = 'ID de año académico inválido.';
    echo json_encode($response);
    exit();
}

$id_anio = (int)$_GET['id_anio'];

try {
    $stmt = $pdo->prepare("
        SELECT id, numero_semestre
        FROM semestres
        WHERE id_anio_academico = :id_anio
        ORDER BY numero_semestre ASC
    ");
    $stmt->execute([':id_anio' => $id_anio]);
    $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['status'] = true;
    $response['message'] = 'Semestres cargados exitosamente.';
    $response['data'] = $semestres;

} catch (PDOException $e) {
    error_log("Error PDO en get_semestres_por_anio.php: " . $e->getMessage());
    $response['message'] = 'Error de base de datos al cargar semestres.';
}

echo json_encode($response);
?>