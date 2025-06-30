<?php
// --- START TEMPORARY DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END TEMPORARY DEBUGGING ---

require_once '../includes/functions.php'; // Asegúrate de que esta ruta sea correcta desde 'api/'
// Asegúrate de que el usuario esté logeado y sea estudiante, si esto no se hace en un middleware
check_login_and_role('Estudiante');

require_once '../config/database.php'; // Asegúrate de que esta ruta sea correcta desde 'api/'

// Establece la cabecera para indicar que la respuesta es JSON
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Solicitud no válida o error interno.']; // Cambié 'status' a 'success' para ser más claro con el JS

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo no permitido.';
    echo json_encode($response);
    exit;

}
    // Aquí es donde obtendrías los detalles del estudiante si no están en la sesión o los pasas por POST
    // (Asegúrate de que $_SESSION['user_id'] esté disponible aquí. Si no, tendrás que pasarlo por formData en JS)
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'Sesión de usuario no encontrada. Por favor, inicie sesión nuevamente.';
        echo json_encode($response);
        exit;
    }

    $stmt_student_details = $pdo->prepare("SELECT id, id_curso_inicio FROM estudiantes WHERE id_usuario = :id_usuario");
    $stmt_student_details->bindParam(':id_usuario', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt_student_details->execute();
    $student_details = $stmt_student_details->fetch(PDO::FETCH_ASSOC);

    if (!$student_details) {
        $response['message'] = 'Error: No se encontró el perfil de estudiante asociado a su usuario.';
        echo json_encode($response);
        exit;
    }
    $id_estudiante_actual = $student_details['id'];
    // $id_curso_inicio_estudiante = $student_details['id_curso_inicio']; // No se usa directamente en esta lógica POST

    $selected_asignaturas_ids = $_POST['selected_asignaturas'] ?? [];

    // Obtener el semestre académico actual
    $current_semester = get_current_semester($pdo);

    if (!$current_semester) {
        $response['message'] = 'No hay un semestre académico activo para la inscripción.';
    } elseif (empty($selected_asignaturas_ids)) {
        $response['message'] = 'Selecciona al menos una asignatura.';
    } elseif (count($selected_asignaturas_ids) > 6) {
        $response['message'] = 'No puedes inscribirte en más de 6 asignaturas.';
    } else {
        try {
            $pdo->beginTransaction();

            // Validar reprobadas obligatorias
            $stmt_reproved = $pdo->prepare("
                SELECT ha.id_asignatura
                FROM historial_academico ha
                WHERE ha.id_estudiante = :id_estudiante
                AND ha.estado_final = 'REPROBADO'
            ");
            $stmt_reproved->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
            $stmt_reproved->execute();
            $reproved_ids = array_column($stmt_reproved->fetchAll(PDO::FETCH_ASSOC), 'id_asignatura');

            foreach ($reproved_ids as $reproved_id) {
                if (!in_array($reproved_id, $selected_asignaturas_ids)) {
                    throw new Exception('Debes seleccionar todas las asignaturas reprobadas obligatorias.');
                }
            }

            // Validar prerrequisitos (si es necesario verificar del lado del servidor de nuevo)
            // (La validación de prerrequisitos es compleja; asegúrate de que tu lógica sea robusta)
            $stmt_prereq = $pdo->prepare("
                SELECT id, id_prerequisito FROM asignaturas
                WHERE id IN (" . implode(',', array_fill(0, count($selected_asignaturas_ids), '?')) . ")
            ");
            $stmt_prereq->execute($selected_asignaturas_ids);
            $asignaturas_con_prerequisitos = $stmt_prereq->fetchAll(PDO::FETCH_ASSOC);

            foreach ($asignaturas_con_prerequisitos as $asig) {
                // Si la asignatura es una reprobada, no apliques la lógica de prerrequisito (ya es obligatoria)
                if (in_array($asig['id'], $reproved_ids)) continue;

                if ($asig['id_prerequisito']) {
                    $stmt_check_prereq = $pdo->prepare("
                        SELECT COUNT(*) FROM historial_academico
                        WHERE id_estudiante = :id_estudiante
                        AND id_asignatura = :id_prerequisito
                        AND estado_final = 'APROBADO'
                    ");
                    $stmt_check_prereq->execute([
                        ':id_estudiante' => $id_estudiante_actual,
                        ':id_prerequisito' => $asig['id_prerequisito'],
                    ]);
                    if ($stmt_check_prereq->fetchColumn() == 0) {
                        throw new Exception("No puedes inscribirte en '{$asig['nombre_asignatura']}' sin aprobar su prerrequisito.");
                    }
                }
            }


            // Limpiar inscripciones pendientes anteriores para este semestre
            $stmt_delete = $pdo->prepare("
                DELETE FROM inscripciones_estudiantes
                WHERE id_estudiante = :id_estudiante AND id_semestre = :id_semestre AND confirmada = 0
            ");
            $stmt_delete->execute([
                ':id_estudiante' => $id_estudiante_actual,
                ':id_semestre' => $current_semester['id'],
            ]);

            // Insertar nuevas inscripciones
            $stmt_insert = $pdo->prepare("
                INSERT INTO inscripciones_estudiantes (id_estudiante, id_semestre, id_asignatura, confirmada)
                VALUES (:id_estudiante, :id_semestre, :id_asignatura, 0)
            ");
            foreach ($selected_asignaturas_ids as $asig_id) {
                $stmt_insert->execute([
                    ':id_estudiante' => $id_estudiante_actual,
                    ':id_semestre' => $current_semester['id'],
                    ':id_asignatura' => $asig_id,
                ]);
            }

            $pdo->commit();
            $response['success'] = true; // Cambiado de 'status' a 'success'
            $response['message'] = 'Inscripción registrada correctamente.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $response['message'] = 'Error: ' . $e->getMessage();
        }
    }
 

echo json_encode($response);
exit; // ¡CRUCIAL! Asegura que no se imprima nada más.
?>