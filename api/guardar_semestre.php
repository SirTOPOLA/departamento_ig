<?php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$id = isset($_POST['id_semestre']) && is_numeric($_POST['id_semestre']) ? (int) $_POST['id_semestre'] : null;
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$curso_id = isset($_POST['curso_id']) && is_numeric($_POST['curso_id']) ? (int) $_POST['curso_id'] : 0;

// Validación básica
if ($nombre === '' || $curso_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Datos incompletos o inválidos']);
    exit;
}

try {
    // Contar semestres existentes para el curso, excluyendo el actual si es edición
    if ($id) {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM semestres WHERE curso_id = :curso_id AND id_semestre != :id");
        $stmtCount->execute([':curso_id' => $curso_id, ':id' => $id]);
    } else {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM semestres WHERE curso_id = :curso_id");
        $stmtCount->execute([':curso_id' => $curso_id]);
    }
    $totalSemestres = (int) $stmtCount->fetchColumn();

    if (!$id && $totalSemestres >= 2) {
        echo json_encode(['status' => false, 'message' => 'Este curso ya tiene 2 semestres, no se puede agregar más.']);
        exit;
    }

    // Para edición, también evitamos cambiar a curso que ya tenga 2 semestres (que no sea este)
    if ($id && $totalSemestres >= 2) {
        echo json_encode(['status' => false, 'message' => 'No se puede asignar este curso porque ya tiene 2 semestres.']);
        exit;
    }

    if ($id) {
        // Actualizar semestre existente
        $stmt = $pdo->prepare("UPDATE semestres SET nombre = :nombre, curso_id = :curso_id WHERE id_semestre = :id");
        $stmt->execute([
            ':nombre' => $nombre,
            ':curso_id' => $curso_id,
            ':id' => $id
        ]);
        echo json_encode(['status' => true, 'message' => 'Semestre actualizado correctamente']);
    } else {
        // Insertar nuevo semestre
        $stmt = $pdo->prepare("INSERT INTO semestres (nombre, curso_id) VALUES (:nombre, :curso_id)");
        $stmt->execute([
            ':nombre' => $nombre,
            ':curso_id' => $curso_id
        ]);
        echo json_encode(['status' => true, 'message' => 'Semestre creado correctamente']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}

