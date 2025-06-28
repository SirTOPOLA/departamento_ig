<?php
// obtener_configuracion_horarios.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM configuracion_horarios");
    $configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => true,
        'configuraciones' => $configuraciones
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Error al obtener la configuración de horarios: ' . $e->getMessage()
    ]);
}
?>