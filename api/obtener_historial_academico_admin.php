<?php
// Incluye la configuración de la base de datos y cualquier función necesaria
require_once '../config/database.php'; // Asegúrate de que la ruta sea correcta
require_once '../includes/functions.php'; // Si tienes funciones como set_flash_message que quieres usar para logs o depuración

header('Content-Type: application/json'); // Indica que la respuesta será JSON

$respuesta = ['success' => false, 'mensaje' => '']; // Variables en español

// Verifica si se ha recibido una acción
if (isset($_GET['accion'])) { // Usar 'accion' en lugar de 'action'
    $accion = $_GET['accion'];

    switch ($accion) {
        case 'obtener_inscripciones_pendientes': // Acción en español
            $id_usuario = filter_var($_GET['id_usuario'] ?? null, FILTER_VALIDATE_INT);
            $semestre_actual = get_current_semester($pdo);
            $id_semestre_actual = $semestre_actual['id'] ?? null;

            if (!$id_usuario || !$id_semestre_actual) {
                $respuesta['mensaje'] = 'ID de usuario o semestre actual no proporcionado o inválido.';
                echo json_encode($respuesta);
                exit;
            }

            try {
                // Obtener el id real del estudiante de la tabla 'estudiantes'
                $stmt_estudiante_id = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                $stmt_estudiante_id->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
                $stmt_estudiante_id->execute();
                $id_estudiante_db = $stmt_estudiante_id->fetchColumn();

                if (!$id_estudiante_db) {
                    $respuesta['mensaje'] = 'No se encontró el registro del estudiante en la base de datos.';
                    echo json_encode($respuesta);
                    exit;
                }

                $stmt = $pdo->prepare("
                    SELECT
                        ie.id AS id_inscripcion,
                        ie.id_asignatura,
                        a.nombre_asignatura,
                        a.codigo_asignatura,
                        a.semestre_recomendado,
                        c.nombre_curso AS nombre_curso_asignatura,
                        (
                            SELECT cur.nombre_curso
                            FROM curso_estudiante ce
                            JOIN cursos cur ON ce.id_curso = cur.id
                            WHERE ce.id_estudiante = e.id
                            AND ce.id_anio = (SELECT id_anio_academico FROM semestres WHERE id = :id_semestre_actual_sub)
                            AND ce.estado = 'activo'
                            LIMIT 1
                        ) AS curso_actual_estudiante
                    FROM inscripciones_estudiantes ie
                    JOIN asignaturas a ON ie.id_asignatura = a.id
                    JOIN cursos c ON a.id_curso = c.id
                    JOIN estudiantes e ON ie.id_estudiante = e.id
                    WHERE ie.id_estudiante = :id_estudiante
                    AND ie.confirmada = 0
                    AND ie.id_semestre = :id_semestre_actual
                    ORDER BY a.nombre_asignatura ASC
                ");
                $stmt->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);
                $stmt->bindParam(':id_semestre_actual', $id_semestre_actual, PDO::PARAM_INT);
                $stmt->bindParam(':id_semestre_actual_sub', $id_semestre_actual, PDO::PARAM_INT); // Para la subconsulta
                $stmt->execute();
                $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC); // Variables en español

                $respuesta['success'] = true;
                $respuesta['inscripciones'] = $inscripciones;

            } catch (PDOException $e) {
                error_log("Error al obtener inscripciones pendientes: " . $e->getMessage());
                $respuesta['mensaje'] = 'Error de base de datos al obtener inscripciones pendientes.';
            }
            break;

        case 'obtener_historial_academico': // Acción en español
            $id_usuario = filter_var($_GET['id_usuario'] ?? null, FILTER_VALIDATE_INT);

            if (!$id_usuario) {
                $respuesta['mensaje'] = 'ID de usuario no proporcionado o inválido.';
                echo json_encode($respuesta);
                exit;
            }

            try {
                $stmt_estudiante_id = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                $stmt_estudiante_id->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
                $stmt_estudiante_id->execute();
                $id_estudiante_db = $stmt_estudiante_id->fetchColumn();

                if (!$id_estudiante_db) {
                    $respuesta['mensaje'] = 'No se encontró el registro del estudiante en la base de datos.';
                    echo json_encode($respuesta);
                    exit;
                }

                $stmt = $pdo->prepare("
                    SELECT
                        ha.id,
                        ha.id_asignatura,
                        a.nombre_asignatura,
                        a.codigo_asignatura,
                        ha.id_semestre,
                        s.numero_semestre,
                        aa.nombre_anio,
                        ha.nota_final,
                        ha.estado_final
                    FROM historial_academico ha
                    JOIN asignaturas a ON ha.id_asignatura = a.id
                    JOIN semestres s ON ha.id_semestre = s.id
                    JOIN anios_academicos aa ON s.id_anio_academico = aa.id
                    WHERE ha.id_estudiante = :id_estudiante
                    ORDER BY aa.nombre_anio DESC, s.numero_semestre DESC, a.nombre_asignatura ASC
                ");
                $stmt->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);
                $stmt->execute();
                $datos_historial = $stmt->fetchAll(PDO::FETCH_ASSOC); // Variables en español

                $respuesta['success'] = true;
                $respuesta['historial'] = $datos_historial;

            } catch (PDOException $e) {
                error_log("Error al obtener historial académico: " . $e->getMessage());
                $respuesta['mensaje'] = 'Error de base de datos al obtener historial.';
            }
            break;

        default:
            $respuesta['mensaje'] = 'Acción no reconocida.';
            break;
    }
} else {
    $respuesta['mensaje'] = 'No se especificó ninguna acción.';
}

echo json_encode($respuesta);
exit;
?>