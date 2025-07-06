<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

$id_inscripcion = filter_input(INPUT_POST, 'id_inscripcion', FILTER_VALIDATE_INT);
$id_grupo = filter_input(INPUT_POST, 'id_grupo', FILTER_VALIDATE_INT);

if (!$id_inscripcion || !$id_grupo) {
    $response['message'] = 'Parámetros incompletos.';
    echo json_encode($response);
    exit;
}

try {
    // Verificar si el grupo tiene capacidad disponible
    $stmt = $pdo->prepare("
        SELECT a.capacidad, COUNT(ie.id) AS inscritos
        FROM grupos_asignaturas ga
        INNER JOIN horarios h ON h.id_grupo_asignatura = ga.id
        INNER JOIN aulas a ON h.id_aula = a.id
        LEFT JOIN inscripciones_estudiantes ie 
            ON ie.id_grupo_asignatura = ga.id AND ie.confirmada = 1
        WHERE ga.id = :id_grupo
        GROUP BY a.capacidad
    ");
    $stmt->bindParam(':id_grupo', $id_grupo, PDO::PARAM_INT);
    $stmt->execute();
    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        $response['message'] = 'Grupo no encontrado.';
        echo json_encode($response);
        exit;
    }

    if ((int)$grupo['inscritos'] >= (int)$grupo['capacidad']) {
        $response['message'] = 'El grupo ya está lleno.';
        echo json_encode($response);
        exit;
    }

    // Actualizar inscripción
    $update = $pdo->prepare("
        UPDATE inscripciones_estudiantes
        SET id_grupo_asignatura = :id_grupo
        WHERE id = :id_inscripcion
    ");
    $update->bindParam(':id_grupo', $id_grupo, PDO::PARAM_INT);
    $update->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
    $update->execute();

    if ($update->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Grupo asignado correctamente.';
    } else {
        $response['message'] = 'No se realizó ningún cambio. ¿Ya estaba asignado?';
    }

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);
exit;
