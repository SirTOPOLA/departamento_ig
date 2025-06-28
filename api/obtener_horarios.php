<?php
// obtener_horario.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => false, 'message' => 'ID de horario no proporcionado o inválido.']);
    exit();
}

$id_horario = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM horarios WHERE id_horario = :id_horario");
    $stmt->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
    $stmt->execute();
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($horario) {
        echo json_encode([
            'status' => true,
            'horario' => $horario
        ]);
    } else {
        echo json_encode([
            'status' => false,
            'message' => 'Horario no encontrado.'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Error al obtener el horario: ' . $e->getMessage()
    ]);
}
?>