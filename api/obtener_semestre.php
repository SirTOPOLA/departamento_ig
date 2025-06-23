<?php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Obtener semestre por id
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM semestres WHERE id_semestre = :id");
    $stmt->execute([':id' => $id]);
    $semestre = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$semestre) {
        http_response_code(404);
        echo json_encode(['error' => 'Semestre no encontrado']);
        exit;
    }
    echo json_encode($semestre);
    exit;
}

if (isset($_GET['curso_id']) && is_numeric($_GET['curso_id'])) {
    // Obtener semestres por curso_id
    $curso_id = (int) $_GET['curso_id'];

    if ($curso_id <= 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id_semestre, nombre FROM semestres WHERE curso_id = ? ORDER BY nombre");
    $stmt->execute([$curso_id]);
    $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($semestres);
    exit;
}

// Si no hay parámetros válidos
http_response_code(400);
echo json_encode(['error' => 'Parámetros inválidos']);
exit;
