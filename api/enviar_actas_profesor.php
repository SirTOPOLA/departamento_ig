<?php 
require_once '../config/database.php';
require_once '../includes/functions.php';

// Asegura que solo los profesores puedan acceder a esta página
check_login_and_role('Profesor');
$profesor_id = $_SESSION['profesor_id'] ?? null;

// Procesar envío del acta de notas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_acta') {
    $notas_enviadas = $_POST['notas'] ?? [];
    $selected_asignatura_id_post = filter_var($_POST['asignatura_id'] ?? null, FILTER_VALIDATE_INT);
    $selected_semestre_id_post = filter_var($_POST['semestre_id'] ?? null, FILTER_VALIDATE_INT);
    $selected_anio_academico_id_post = filter_var($_POST['anio_academico_id'] ?? null, FILTER_VALIDATE_INT);


    if ($selected_asignatura_id_post && $selected_semestre_id_post && !empty($notas_enviadas) && $selected_anio_academico_id_post) {
        try {
            $pdo->beginTransaction();
            $grades_processed = 0;
            $validation_errors = [];

            // Preparar la consulta para verificar si la asignatura ya fue registrada para el estudiante en el año académico
            // Específicamente, buscamos notas que ya estén APROBADA_ADMIN para esa asignatura en ese año académico.
            $stmt_check_approved_subject = $pdo->prepare("
                SELECT
                    n.id, n.estado_envio_acta
                FROM notas n
                JOIN inscripciones_estudiantes ie ON n.id_inscripcion = ie.id
                JOIN semestres s ON ie.id_semestre = s.id
                WHERE ie.id_estudiante = :id_estudiante
                AND ie.id_asignatura = :id_asignatura
                AND s.id_anio_academico = :id_anio_academico
                AND n.estado_envio_acta = 'APROBADA_ADMIN'
                LIMIT 1
            ");

            $stmt_upsert_nota = $pdo->prepare("
                INSERT INTO notas (id_inscripcion, nota, estado, fecha_registro, estado_envio_acta, acta_final_confirmada, fecha_envio_acta)
                VALUES (:id_inscripcion, :nota, :estado, NOW(), 'ENVIADA_PROFESOR', 0, NOW())
                ON DUPLICATE KEY UPDATE
                    nota = VALUES(nota),
                    estado = VALUES(estado),
                    fecha_registro = NOW(),
                    estado_envio_acta = 'ENVIADA_PROFESOR',
                    acta_final_confirmada = 0,
                    observaciones_admin = NULL,
                    fecha_envio_acta = NOW()
            ");

            foreach ($notas_enviadas as $inscripcion_id => $nota_str) {
                $inscripcion_id = filter_var($inscripcion_id, FILTER_VALIDATE_INT);
                $nota = filter_var($nota_str, FILTER_VALIDATE_FLOAT);

                // Obtener el ID del estudiante para esta inscripción
                $stmt_get_student_id = $pdo->prepare("SELECT id_estudiante FROM inscripciones_estudiantes WHERE id = :inscripcion_id");
                $stmt_get_student_id->execute(['inscripcion_id' => $inscripcion_id]);
                $estudiante_id = $stmt_get_student_id->fetchColumn();

                // Validación de entrada de notas
                if ($inscripcion_id === false || $nota === false || $nota < 0 || $nota > 10) {
                    $validation_errors[] = "Nota inválida para inscripción ID $inscripcion_id. Asegúrese de que la nota esté entre 0 y 10.";
                    continue; // Saltar a la siguiente nota si esta es inválida
                }

                $estado_nota = ($nota >= 5) ? 'APROBADO' : 'REPROBADO';

                // *** Validación clave: Evitar insertar o actualizar si la asignatura ya fue APROBADA_ADMIN para este estudiante en este año académico ***
                $stmt_check_approved_subject->execute([
                    'id_estudiante' => $estudiante_id,
                    'id_asignatura' => $selected_asignatura_id_post,
                    'id_anio_academico' => $selected_anio_academico_id_post
                ]);
                $existing_approved_note = $stmt_check_approved_subject->fetch();

                if ($existing_approved_note) {
                    $validation_errors[] = "La asignatura ya ha sido aprobada por el administrador para el estudiante " . htmlspecialchars($notas_estudiantes[array_search($inscripcion_id, array_column($notas_estudiantes, 'inscripcion_id'))]['nombre_estudiante'] ?? 'ID ' . $estudiante_id) . " en el año académico actual y no puede ser modificada.";
                    continue; // Saltar a la siguiente nota
                }

                // Obtener el estado actual de la nota para no sobrescribir actas que no deben ser modificadas (ej. ENVIADA_PROFESOR o APROBADA_ADMIN por otro camino)
                // Aunque la validación anterior ya cubre APROBADA_ADMIN, esta es una capa extra.
                $stmt_check_status = $pdo->prepare("
                    SELECT estado_envio_acta FROM notas WHERE id_inscripcion = :id_inscripcion
                ");
                $stmt_check_status->execute(['id_inscripcion' => $inscripcion_id]);
                $current_status = $stmt_check_status->fetchColumn();

                if ($current_status === 'ENVIADA_PROFESOR') {
                     $validation_errors[] = "La nota para la inscripción ID $inscripcion_id ya ha sido enviada para revisión y no puede ser modificada hasta que sea rechazada por el administrador.";
                     continue;
                }

                $stmt_upsert_nota->execute([
                    'id_inscripcion' => $inscripcion_id,
                    'nota' => $nota,
                    'estado' => $estado_nota
                ]);
                $grades_processed++;
            }

            if (!empty($validation_errors)) {
                $pdo->rollBack();
                $_SESSION['flash_message'] = [
                    'message' => "Errores al guardar notas:<br>" . implode('<br>', $validation_errors),
                    'type' => "danger"
                ];
            } elseif ($grades_processed > 0) {
                $pdo->commit();
                $_SESSION['flash_message'] = [
                    'message' => "Acta enviada correctamente. $grades_processed notas procesadas.",
                    'type' => "success"
                ];
            } else {
                $pdo->rollBack(); // Si no se procesaron notas válidas, se revierte
                $_SESSION['flash_message'] = [
                    'message' => "No se procesaron notas válidas. Verifique las entradas.",
                    'type' => "info"
                ];
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = [
                'message' => "Error al procesar el acta. Por favor, inténtelo de nuevo.",
                'type' => "danger"
            ];
            error_log("Error al guardar acta: " . $e->getMessage());
        }

        // PRG Pattern: Redirige para limpiar el POST y evitar doble envío
        header('Location: ../profesores/actas.php' ); // Esto redirige sin los parámetros GET del formulario de selección
        exit();
    } else {
        $_SESSION['flash_message'] = [
            'message' => "Por favor, seleccione una asignatura y un semestre, y asegúrese de que haya notas para enviar.",
            'type' => "warning"
        ];
        header('Location: ../profesores/actas.php' ); // Redirige para limpiar el POST
        exit();
    }
}
