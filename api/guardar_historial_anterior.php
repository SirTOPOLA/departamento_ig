<?php
// --- INICIO DE DEPURACIÓN TEMPORAL ---
// Habilitar la visualización de errores para depuración.
// ¡Desactivar en producción!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

require_once '../includes/functions.php';
// Asegura que solo un administrador pueda acceder a esta página.
check_login_and_role('Administrador');

require_once '../config/database.php'; // Conexión PDO
 

// --- Lógica de Procesamiento POST para Confirmar/Rechazar Inscripciones y Gestionar Historial ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    $semestre_actual = get_current_semester($pdo);
    // Verificar si se obtuvo un semestre actual y su ID de año académico
    if (!$semestre_actual || !isset($semestre_actual['id']) || !isset($semestre_actual['id_anio_academico'])) {
        set_flash_message('danger', 'Error: No hay un semestre académico activo definido o no se pudo obtener su año académico. Por favor, configure el año y semestre actual.');
        header('Location: ../admin/estudiantes.php');
        exit;
    }
    $id_semestre_actual = $semestre_actual['id'];
    $id_anio_academico_actual = $semestre_actual['id_anio_academico'];

    try {
        $pdo->beginTransaction(); // Iniciar transacción para operaciones atómicas

        if ($accion === 'confirm_single_enrollment') {
            $id_inscripcion = filter_var($_POST['id_inscripcion'] ?? null, FILTER_VALIDATE_INT);
            if ($id_inscripcion === null) {
                set_flash_message('danger', 'Error: ID de inscripción no válido para confirmar.');
            } else {
                $resultado = confirmar_inscripcion_individual_logica($pdo, $id_inscripcion, $id_semestre_actual, $id_anio_academico_actual);
                set_flash_message($resultado['exito'] ? 'success' : 'danger', $resultado['mensaje']);
            }
        } elseif ($accion === 'reject_single_enrollment') {
            $id_inscripcion = filter_var($_POST['id_inscripcion'] ?? null, FILTER_VALIDATE_INT);
            if ($id_inscripcion === null) {
                set_flash_message('danger', 'Error: ID de inscripción no válido para rechazar.');
            } else {
                $stmt_rechazar = $pdo->prepare("DELETE FROM inscripciones_estudiantes WHERE id = :id_inscripcion AND id_semestre = :id_semestre");
                if ($stmt_rechazar === false) {
                    error_log("Prepare error (reject_single_enrollment): " . json_encode($pdo->errorInfo()));
                    throw new PDOException("Error al preparar la consulta de rechazo.");
                }
                $stmt_rechazar->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
                $stmt_rechazar->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
                if (!$stmt_rechazar->execute()) {
                    error_log("Execute error (reject_single_enrollment): " . json_encode($stmt_rechazar->errorInfo()));
                    throw new PDOException("Error al ejecutar la consulta de rechazo.");
                }

                if ($stmt_rechazar->rowCount() > 0) {
                    set_flash_message('info', 'Asignatura rechazada (eliminada) correctamente para el estudiante.');
                } else {
                    set_flash_message('warning', 'La asignatura no pudo ser encontrada o ya ha sido eliminada.');
                }
            }
        } elseif ($accion === 'confirm_student_enrollments') {
            $id_estudiante_usuario = filter_var($_POST['id_estudiante'] ?? null, FILTER_VALIDATE_INT);
            if ($id_estudiante_usuario === null) {
                set_flash_message('danger', 'Error: ID de estudiante no válido para confirmar inscripciones.');
            } else {
                // Obtener el ID de la tabla estudiantes desde el id_usuario
                $stmt_obtener_id_estudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                $stmt_obtener_id_estudiante->bindParam(':id_usuario', $id_estudiante_usuario, PDO::PARAM_INT);
                $stmt_obtener_id_estudiante->execute();
                $id_estudiante_db = $stmt_obtener_id_estudiante->fetchColumn();

                if ($id_estudiante_db) {
                    // Seleccionar todas las inscripciones pendientes del estudiante para el semestre actual
                    $stmt_inscripciones_pendientes = $pdo->prepare("
                        SELECT ie.id AS id_inscripcion, ie.id_asignatura, a.id_curso
                        FROM inscripciones_estudiantes ie
                        JOIN asignaturas a ON ie.id_asignatura = a.id
                        WHERE ie.id_estudiante = :id_estudiante
                        AND ie.confirmada = 0
                        AND ie.id_semestre = :id_semestre
                    ");
                    $stmt_inscripciones_pendientes->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);
                    $stmt_inscripciones_pendientes->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
                    $stmt_inscripciones_pendientes->execute();
                    $inscripciones_pendientes = $stmt_inscripciones_pendientes->fetchAll(PDO::FETCH_ASSOC);

                    $contador_confirmadas = 0;
                    $contador_omitidas = 0;

                    foreach ($inscripciones_pendientes as $inscripcion) {
                        $resultado = confirmar_inscripcion_individual_logica($pdo, $inscripcion['id_inscripcion'], $id_semestre_actual, $id_anio_academico_actual);
                        if ($resultado['exito']) {
                            $contador_confirmadas++;
                        } else {
                            $contador_omitidas++;
                            // Log opcional del motivo por el que se saltó
                            error_log("Inscripción ID {$inscripcion['id_inscripcion']} omitida para estudiante {$id_estudiante_db}: " . $resultado['mensaje']);
                        }
                    }

                    if ($contador_confirmadas > 0) {
                        $mensaje = "Se confirmaron {$contador_confirmadas} inscripciones para el estudiante.";
                        if ($contador_omitidas > 0) {
                            $mensaje .= " Se omitieron {$contador_omitidas} inscripciones porque el estudiante no está en el curso correspondiente.";
                        }
                        set_flash_message('success', $mensaje);
                    } else {
                        if (count($inscripciones_pendientes) > 0 && $contador_omitidas == count($inscripciones_pendientes)) {
                            set_flash_message('warning', 'No se confirmó ninguna inscripción. Todas las asignaturas pendientes requieren que el estudiante esté matriculado en el curso correspondiente.');
                        } else {
                            set_flash_message('info', 'No hay inscripciones pendientes para este estudiante o ya estaban confirmadas.');
                        }
                    }
                } else {
                    set_flash_message('danger', 'Error: No se encontró el registro del estudiante en la base de datos.');
                }
            }
        } elseif ($accion === 'confirm_all_enrollments') {
            // Se itera sobre todas las inscripciones pendientes para aplicar la lógica de validación por curso.
            $stmt_todas_pendientes = $pdo->prepare("
                SELECT ie.id AS id_inscripcion, ie.id_estudiante, ie.id_asignatura, a.id_curso
                FROM inscripciones_estudiantes ie
                JOIN estudiantes e ON ie.id_estudiante = e.id
                JOIN asignaturas a ON ie.id_asignatura = a.id
                WHERE ie.confirmada = 0 AND ie.id_semestre = :id_semestre
            ");
            $stmt_todas_pendientes->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
            $stmt_todas_pendientes->execute();
            $todas_inscripciones_pendientes = $stmt_todas_pendientes->fetchAll(PDO::FETCH_ASSOC);

            $contador_confirmadas = 0;
            $contador_omitidas = 0;

            foreach ($todas_inscripciones_pendientes as $inscripcion) {
                $resultado = confirmar_inscripcion_individual_logica($pdo, $inscripcion['id_inscripcion'], $id_semestre_actual, $id_anio_academico_actual);
                if ($resultado['exito']) {
                    $contador_confirmadas++;
                } else {
                    $contador_omitidas++;
                    error_log("Inscripción ID {$inscripcion['id_inscripcion']} omitida para estudiante {$inscripcion['id_estudiante']}: " . $resultado['mensaje']);
                }
            }

            if ($contador_confirmadas > 0) {
                $mensaje = "Se confirmaron {$contador_confirmadas} inscripciones en total.";
                if ($contador_omitidas > 0) {
                    $mensaje .= " Se omitieron {$contador_omitidas} inscripciones porque los estudiantes no están en los cursos correspondientes.";
                }
                set_flash_message('success', $mensaje);
            } else {
                if (count($todas_inscripciones_pendientes) > 0 && $contador_omitidas == count($todas_inscripciones_pendientes)) {
                    set_flash_message('warning', 'No se confirmó ninguna inscripción. Todas las asignaturas pendientes requieren que el estudiante esté matriculado en el curso correspondiente.');
                } else {
                    set_flash_message('info', 'No hay inscripciones pendientes para confirmar en este momento.');
                }
            }
        } elseif ($accion === 'manage_academic_history') {
            // Lógica para manejar el historial académico (add, edit, delete)
            $operacion_historial = $_POST['operacion_historial'] ?? '';
            $data_historial = $_POST; // Todos los datos del formulario

            $resultado = manejar_historial_academico_logica($pdo, $data_historial, $operacion_historial);
            set_flash_message($resultado['success'] ? 'success' : 'danger', $resultado['message']);
        }

        $pdo->commit(); // Confirmar la transacción

    } catch (PDOException $e) {
        $pdo->rollBack(); // Revertir la transacción en caso de error
        // En producción, solo loguear $e->getMessage() y mostrar un mensaje genérico al usuario
        set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
        error_log("Transaction Error in estudiantes.php: " . $e->getMessage()); // Log the error detallado
    } catch (Exception $e) {
        $pdo->rollBack(); // Revertir si hubo una excepción antes de PDO (ej. validaciones)
        set_flash_message('danger', 'Error: ' . $e->getMessage());
        error_log("General Error in estudiantes.php: " . $e->getMessage());
    }

    header('Location: ../admin/estudiantes.php');
    exit;
}