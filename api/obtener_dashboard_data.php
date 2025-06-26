<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

$response = ['status' => false, 'message' => '', 'data' => null];

if (!isset($_GET['id_estudiante']) || !is_numeric($_GET['id_estudiante'])) {
    $response['message'] = 'ID de estudiante no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

$id_estudiante = filter_var($_GET['id_estudiante'], FILTER_VALIDATE_INT);

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->beginTransaction(); // Iniciar transacción para consultas múltiples si es necesario, aunque aquí son SELECTs

        // 1. Obtener información básica del estudiante (desde usuarios y estudiantes)
        $stmtUsuario = $pdo->prepare("
            SELECT
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.email,
                u.dni,
                e.matricula
            FROM
                usuarios u
            LEFT JOIN
                estudiantes e ON u.id_usuario = e.id_estudiante
            WHERE
                u.id_usuario = :id_estudiante AND u.rol = 'estudiante'
        ");
        $stmtUsuario->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtUsuario->execute();
        $estudiante = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$estudiante) {
            $response['message'] = 'Estudiante no encontrado o no tiene el rol correcto.';
            $pdo->rollBack(); // Opcional, ya que no se hicieron cambios
            echo json_encode($response);
            exit();
        }

        // 2. Obtener el curso activo y año académico del estudiante
        $stmtCursoActivo = $pdo->prepare("
            SELECT
                ce.id_curso,
                ce.id_anio,
                c.nombre AS curso_nombre,
                c.turno,
                c.grupo,
                an.anio AS anio_academico
            FROM
                curso_estudiante ce
            JOIN
                cursos c ON ce.id_curso = c.id_curso
            JOIN
                anios_academicos an ON ce.id_anio = an.id_anio
            WHERE
                ce.id_estudiante = :id_estudiante AND ce.estado = 'activo'
            LIMIT 1
        ");
        $stmtCursoActivo->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtCursoActivo->execute();
        $curso_activo = $stmtCursoActivo->fetch(PDO::FETCH_ASSOC);

        $estudiante['curso_actual'] = $curso_activo ?: null;

        // 3. Obtener el rendimiento académico general (promedio, aprobadas, reprobadas)
        $stmtRendimiento = $pdo->prepare("
            SELECT
                AVG(ha.nota_final) AS promedio_general,
                SUM(CASE WHEN ha.resultado = 'aprobado' THEN 1 ELSE 0 END) AS aprobadas_count,
                SUM(CASE WHEN ha.resultado = 'reprobado' THEN 1 ELSE 0 END) AS reprobadas_count,
                SUM(CASE WHEN ha.resultado NOT IN ('aprobado', 'reprobado') THEN 1 ELSE 0 END) AS otros_resultados_count
            FROM
                historial_academico ha
            WHERE
                ha.id_estudiante = :id_estudiante
        ");
        $stmtRendimiento->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmtRendimiento->execute();
        $rendimiento = $stmtRendimiento->fetch(PDO::FETCH_ASSOC);

        $estudiante['promedio_general'] = $rendimiento['promedio_general'];
        $estudiante['aprobadas_count'] = $rendimiento['aprobadas_count'];
        $estudiante['reprobadas_count'] = $rendimiento['reprobadas_count'];
        $estudiante['otros_resultados_count'] = $rendimiento['otros_resultados_count'];

        $response['status'] = true;
        $response['data'] = $estudiante;
        $pdo->commit(); // Confirma la transacción (aunque no hay cambios, buena práctica)

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error de base de datos al obtener datos del dashboard: ' . $e->getMessage();
    error_log("Error en obtener_dashboard_data.php: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error inesperado al obtener datos del dashboard: ' . $e->getMessage();
    error_log("Error inesperado en obtener_dashboard_data.php: " . $e->getMessage());
}

echo json_encode($response);
?>
