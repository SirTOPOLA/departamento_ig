<?php
// asignaturas_disponibles.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$response = ['status' => false, 'message' => '', 'assigned_subjects' => [], 'unassigned_subjects' => []];

$id_profesor = $_GET['id_profesor'] ?? null;

if (!$id_profesor || !is_numeric($id_profesor)) {
    $response['message'] = 'ID de profesor no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

try {
    // Obtener TODAS las asignaturas disponibles
    $stmtAllAsignaturas = $pdo->query("SELECT id_asignatura, nombre FROM asignaturas ORDER BY nombre");
    $allAsignaturas = $stmtAllAsignaturas->fetchAll(PDO::FETCH_ASSOC);

    // Obtener los IDs de las asignaturas YA ASIGNADAS a este profesor
    $stmtAsignadas = $pdo->prepare("SELECT id_asignatura FROM asignatura_profesor WHERE id_profesor = :id_profesor");
    $stmtAsignadas->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmtAsignadas->execute();
    $asignadasIds = $stmtAsignadas->fetchAll(PDO::FETCH_COLUMN, 0); // Obtener solo los IDs

    $assignedSubjects = [];
    $unassignedSubjects = [];

    // Clasificar las asignaturas en asignadas y no asignadas
    foreach ($allAsignaturas as $asig) {
        if (in_array($asig['id_asignatura'], $asignadasIds)) {
            $assignedSubjects[] = $asig;
        } else {
            $unassignedSubjects[] = $asig;
        }
    }

    $response['status'] = true;
    $response['assigned_subjects'] = $assignedSubjects;
    $response['unassigned_subjects'] = $unassignedSubjects;

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    error_log('Error en asignaturas_disponibles.php: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error inesperado: ' . $e->getMessage();
    error_log('Error inesperado en asignaturas_disponibles.php: ' . $e->getMessage());
}

echo json_encode($response);
?>
