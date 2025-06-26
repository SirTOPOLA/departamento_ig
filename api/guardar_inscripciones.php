<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'data' => []];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit();
}

$id_estudiante = filter_input(INPUT_POST, 'id_estudiante', FILTER_VALIDATE_INT);
$id_anio = filter_input(INPUT_POST, 'id_anio', FILTER_VALIDATE_INT);
$id_semestre = filter_input(INPUT_POST, 'id_semestre', FILTER_VALIDATE_INT);
$asignaturas_ids = isset($_POST['asignaturas']) ? $_POST['asignaturas'] : [];
$tipos_inscripcion = isset($_POST['tipos_inscripcion']) ? $_POST['tipos_inscripcion'] : []; // Formato: "id:tipo"

// Conversión de tipos de inscripción a un mapa para fácil acceso
$tipos_map = [];
foreach ($tipos_inscripcion as $item) {
    list($asignatura_id, $tipo) = explode(':', $item, 2);
    $tipos_map[$asignatura_id] = $tipo;
}


if (!$id_estudiante || !$id_anio || !$id_semestre || empty($asignaturas_ids)) {
    $response['message'] = 'Datos incompletos para la inscripción.';
    echo json_encode($response);
    exit();
}

if (count($asignaturas_ids) > 6) {
    $response['message'] = 'No se pueden inscribir más de 6 asignaturas por semestre.';
    echo json_encode($response);
    exit();
}

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Error: Conexión PDO no disponible.');
    }

    $pdo->beginTransaction(); // Inicia una transacción

    // 1. Obtener historial académico del estudiante para validación de prerrequisitos/aprobación
    $stmtHistorial = $pdo->prepare("
        SELECT id_asignatura, resultado
        FROM historial_academico
        WHERE id_estudiante = :id_estudiante
    ");
    $stmtHistorial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
    $stmtHistorial->execute();
    $historial_data = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);
    $aprobadas = array_map(function($item){ return $item['id_asignatura']; }, array_filter($historial_data, function($item){ return $item['resultado'] === 'aprobado'; }));
    $reprobadas = array_map(function($item){ return $item['id_asignatura']; }, array_filter($historial_data, function($item){ return $item['resultado'] === 'reprobado'; }));


    // 2. Obtener todos los requisitos de asignaturas para validar
    $stmtAllRequisitos = $pdo->query("SELECT asignatura_id, requisito_id FROM asignatura_requisitos");
    $all_requisitos = $stmtAllRequisitos->fetchAll(PDO::FETCH_ASSOC);
    $requisitos_map = [];
    foreach ($all_requisitos as $req) {
        $requisitos_map[$req['asignatura_id']][] = $req['requisito_id'];
    }

    // 3. Obtener inscripciones existentes para este estudiante, año y semestre para evitar duplicados
    $stmtExistingInscripciones = $pdo->prepare("
        SELECT id_asignatura, estado
        FROM inscripciones
        WHERE id_estudiante = :id_estudiante AND id_anio = :id_anio AND id_semestre = :id_semestre
    ");
    $stmtExistingInscripciones->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
    $stmtExistingInscripciones->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
    $stmtExistingInscripciones->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
    $stmtExistingInscripciones->execute();
    $existing_inscripciones = $stmtExistingInscripciones->fetchAll(PDO::FETCH_ASSOC);
    $already_enrolled_this_period = array_column($existing_inscripciones, 'id_asignatura');


    $inserted_count = 0;
    foreach ($asignaturas_ids as $id_asignatura) {
        $id_asignatura = filter_var($id_asignatura, FILTER_VALIDATE_INT);
        if ($id_asignatura === false || $id_asignatura === null) {
            error_log("ID de asignatura inválido detectado: " . $id_asignatura);
            continue;
        }

        // Validación de duplicados para este periodo
        if (in_array($id_asignatura, $already_enrolled_this_period)) {
            // Si ya está inscrito, y está en el POST, lo ignoramos o actualizamos su tipo si cambia
            // Por simplicidad, si ya existe una inscripción para este periodo, no intentamos insertarla de nuevo.
            continue;
        }

        // Validación de asignatura ya aprobada (a menos que sea una reprobada que se está arrastrando)
        if (in_array($id_asignatura, $aprobadas) && (!isset($tipos_map[$id_asignatura]) || $tipos_map[$id_asignatura] !== 'arrastre')) {
            $response['message'] = 'La asignatura (ID: ' . $id_asignatura . ') ya ha sido aprobada. No se puede reinscribir.';
            $pdo->rollBack();
            echo json_encode($response);
            exit();
        }

        // Validación de prerrequisitos
        if (isset($requisitos_map[$id_asignatura])) {
            foreach ($requisitos_map[$id_asignatura] as $req_id) {
                if (!in_array($req_id, $aprobadas)) {
                    $response['message'] = 'Falta cumplir prerrequisitos para la asignatura (ID: ' . $id_asignatura . ').';
                    $pdo->rollBack();
                    echo json_encode($response);
                    exit();
                }
            }
        }

        // Determinar el tipo de inscripción
        $tipo = isset($tipos_map[$id_asignatura]) ? $tipos_map[$id_asignatura] : 'regular';
        // Si la asignatura estaba reprobada, automáticamente es arrastre
        if (in_array($id_asignatura, $reprobadas)) {
            $tipo = 'arrastre';
        }


        // Insertar en la tabla de inscripciones
        $stmtInsert = $pdo->prepare("
            INSERT INTO inscripciones (id_estudiante, id_asignatura, id_anio, id_semestre, estado, tipo, fecha_inscripcion)
            VALUES (:id_estudiante, :id_asignatura, :id_anio, :id_semestre, 'preinscrito', :tipo, NOW())
        ");
        $stmtInsert->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtInsert->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
        $stmtInsert->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
        $stmtInsert->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
        $stmtInsert->bindParam(':tipo', $tipo);

        if (!$stmtInsert->execute()) {
            $response['message'] = 'Error al registrar la inscripción para la asignatura (ID: ' . $id_asignatura . '). Detalles: ' . implode(" - ", $stmtInsert->errorInfo());
            $pdo->rollBack();
            echo json_encode($response);
            exit();
        }
        $inserted_count++;
    }

    $pdo->commit(); // Confirma la transacción
    $response['status'] = true;
    $response['message'] = "Inscripción completada para {$inserted_count} asignaturas.";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    error_log("Error en guardar_inscripciones.php (PDO): " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error inesperado: ' . $e->getMessage();
    error_log("Error inesperado en guardar_inscripciones.php: " . $e->getMessage());
}

echo json_encode($response);
?>
