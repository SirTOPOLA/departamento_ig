<?php
// obtener_asignaturas_profesor.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id_profesor']) || !is_numeric($_GET['id_profesor'])) {
    echo json_encode(['status' => false, 'message' => 'ID de profesor no proporcionado o inválido.']);
    exit();
}

$id_profesor = $_GET['id_profesor'];
// Obtener el ID del año académico. Es crucial para verificar si ya está asignada en este año.
$id_anio = $_GET['id_anio'] ?? null; 

if (!$id_anio) {
    echo json_encode(['status' => false, 'message' => 'ID de año académico no proporcionado.']);
    exit();
}

try {
    // 1. Consulta para obtener las asignaturas que el profesor PUEDE impartir
    //    Se utiliza la tabla `asignatura_profesor` para esta relación.
    $sqlAsignaturasProfesor = "SELECT a.id_asignatura, a.nombre
                               FROM asignaturas a
                               JOIN asignatura_profesor ap ON a.id_asignatura = ap.id_asignatura
                               WHERE ap.id_profesor = :id_profesor
                               ORDER BY a.nombre";
    $stmtAsignaturasProfesor = $pdo->prepare($sqlAsignaturasProfesor);
    $stmtAsignaturasProfesor->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmtAsignaturasProfesor->execute();
    $asignaturasDisponibles = $stmtAsignaturasProfesor->fetchAll(PDO::FETCH_ASSOC);

    // 2. Consulta para obtener las asignaturas que el profesor YA TIENE asignadas en horarios
    //    para el AÑO ACADÉMICO ESPECÍFICO.
    $sqlHorariosAsignados = "SELECT DISTINCT id_asignatura 
                             FROM horarios 
                             WHERE id_profesor = :id_profesor 
                             AND id_anio = :id_anio";
    
    $stmtHorariosAsignados = $pdo->prepare($sqlHorariosAsignados);
    $stmtHorariosAsignados->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
    $stmtHorariosAsignados->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
    $stmtHorariosAsignados->execute();
    $asignaturasAsignadasEnHorario = $stmtHorariosAsignados->fetchAll(PDO::FETCH_COLUMN, 0);

    // 3. Marcar las asignaturas que ya tienen horario asignado para el año actual
    $result = [];
    foreach ($asignaturasDisponibles as $asig) {
        $asig['ya_asignada'] = in_array($asig['id_asignatura'], $asignaturasAsignadasEnHorario);
        $result[] = $asig;
    }

    echo json_encode($result);

} catch (PDOException $e) {
    error_log('Error al obtener asignaturas del profesor: ' . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'Error al cargar asignaturas: ' . $e->getMessage()]);
}
?>
