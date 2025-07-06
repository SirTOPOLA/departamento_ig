<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Asegúrate de que solo los administradores puedan acceder
check_login_and_role('Administrador');

$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = basename(dirname($_SERVER['PHP_SELF']));

$admin_id = $_SESSION['user_id'] ?? null;

// Procesar acciones: aprobar o rechazar actas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $inscripcion_ids_str = $_POST['inscripcion_ids'] ?? '';
    $observaciones_admin = trim($_POST['observaciones_admin'] ?? '');

    $inscripcion_ids = array_filter(array_map('intval', explode(',', $inscripcion_ids_str)));

    if (empty($inscripcion_ids)) {
        $message = "No se seleccionaron notas para procesar.";
        $message_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            $rows_affected = 0;

            // Crear placeholders para la cláusula IN
            $placeholders = implode(',', array_fill(0, count($inscripcion_ids), '?'));

            if ($action === 'approve_acta') {
                // 1. Obtener los detalles de las notas que se van a aprobar
                $stmt_fetch_details = $pdo->prepare("
                    SELECT
                        n.id_inscripcion,
                        n.nota,
                        n.estado AS estado_nota,
                        ie.id_estudiante,
                        ie.id_asignatura,
                        ie.id_semestre
                    FROM notas n
                    JOIN inscripciones_estudiantes ie ON n.id_inscripcion = ie.id
                    WHERE n.id_inscripcion IN ($placeholders)
                    AND n.estado_envio_acta = 'ENVIADA_PROFESOR'
                ");
                $stmt_fetch_details->execute($inscripcion_ids);
                $notes_to_approve_details = $stmt_fetch_details->fetchAll(PDO::FETCH_ASSOC);

                error_log("DEBUG (admin/actas.php): Detalles de notas a aprobar: " . print_r($notes_to_approve_details, true));

                if (empty($notes_to_approve_details)) {
                    throw new Exception("No se encontraron notas válidas para aprobar con los IDs proporcionados o no están en estado 'ENVIADA_PROFESOR'.");
                }

                // 2. Preparar la sentencia para insertar/actualizar en historial_academico
                // Es CRÍTICO que la tabla historial_academico tenga una clave UNIQUE
                // en (id_estudiante, id_asignatura, id_semestre) para que ON DUPLICATE KEY UPDATE funcione.
                // Si no la tienes, debes añadirla:
                // ALTER TABLE historial_academico ADD UNIQUE (id_estudiante, id_asignatura, id_semestre);

                // --- INICIO DE LA MODIFICACIÓN ---
                // Aquí es donde ajustamos la lógica de UPSERT
                $stmt_upsert_historial = $pdo->prepare("
                    INSERT INTO historial_academico (id_estudiante, id_asignatura, id_semestre, nota_final, estado_final, fecha_actualizacion)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        nota_final = CASE
                                        WHEN historial_academico.estado_final NOT IN ('APROBADO', 'REPROBADO')
                                        THEN VALUES(nota_final)
                                        ELSE historial_academico.nota_final -- Mantener la nota existente si ya está APROBADO/REPROBADO
                                     END,
                        estado_final = CASE
                                        WHEN historial_academico.estado_final NOT IN ('APROBADO', 'REPROBADO')
                                        THEN VALUES(estado_final)
                                        ELSE historial_academico.estado_final -- Mantener el estado existente si ya está APROBADO/REPROBADO
                                      END,
                        fecha_actualizacion = CASE
                                                WHEN historial_academico.estado_final NOT IN ('APROBADO', 'REPROBADO')
                                                THEN NOW()
                                                ELSE historial_academico.fecha_actualizacion -- Mantener la fecha existente si ya está APROBADO/REPROBADO
                                              END
                ");
                // --- FIN DE LA MODIFICACIÓN ---

                // 3. Iterar sobre las notas y registrarlas/actualizarlas en historial_academico
                foreach ($notes_to_approve_details as $note_detail) {
                    try {
                        $historial_params = [
                            $note_detail['id_estudiante'],
                            $note_detail['id_asignatura'],
                            $note_detail['id_semestre'],
                            $note_detail['nota'],
                            $note_detail['estado_nota']
                        ];
                        error_log("DEBUG (admin/actas.php - Historial): Ejecutando upsert con parámetros: " . print_r($historial_params, true));
                        $stmt_upsert_historial->execute($historial_params);
                        error_log("DEBUG (admin/actas.php - Historial): Nota de inscripcion_id " . $note_detail['id_inscripcion'] . " (Estudiante: " . $note_detail['id_estudiante'] . ", Asignatura: " . $note_detail['id_asignatura'] . ") registrada/actualizada en historial_academico. Rows affected: " . $stmt_upsert_historial->rowCount());
                    } catch (PDOException $e_historial) {
                        // Capturar errores específicos de historial_academico sin detener la transacción principal
                        // Esto es ÚTIL para depuración. Si la UNIQUE constraint está bien, un error de duplicidad
                        // significa que el ON DUPLICATE KEY UPDATE se encargó, lo cual no es un error de ejecución.
                        // Solo logueamos errores inesperados.
                        error_log("ERROR (admin/actas.php - Historial): PDOException al upsert en historial_academico para inscripcion_id " . $note_detail['id_inscripcion'] . ": " . $e_historial->getMessage());
                    }
                }

                // 4. Actualizar el estado de las notas en la tabla 'notas'
                $stmt = $pdo->prepare("
                    UPDATE notas
                    SET
                        estado_envio_acta = 'APROBADA_ADMIN',
                        fecha_revision_admin = NOW(),
                        id_admin_revisor = ?,
                        acta_final_confirmada = 1,
                        observaciones_admin = NULL
                    WHERE id_inscripcion IN ($placeholders)
                    AND estado_envio_acta = 'ENVIADA_PROFESOR'
                ");
                $params = array_merge([$admin_id], $inscripcion_ids);
                error_log("DEBUG (admin/actas.php): Parámetros para aprobar en notas: " . print_r($params, true));
                $stmt->execute($params);
                $rows_affected = $stmt->rowCount();
                $message = "Acta(s) aprobada(s) correctamente. $rows_affected nota(s) actualizada(s) y registradas en el historial académico.";
                $message_type = "success";

            } elseif ($action === 'reject_acta') {
                if (empty($observaciones_admin)) {
                    throw new Exception("Las observaciones son obligatorias para rechazar un acta.");
                }

                $stmt = $pdo->prepare("
                    UPDATE notas
                    SET
                        estado_envio_acta = 'RECHAZADA_ADMIN',
                        fecha_revision_admin = NOW(),
                        id_admin_revisor = ?,
                        observaciones_admin = ?,
                        acta_final_confirmada = 0
                    WHERE id_inscripcion IN ($placeholders)
                    AND estado_envio_acta = 'ENVIADA_PROFESOR'
                ");
                $params = array_merge([$admin_id, $observaciones_admin], $inscripcion_ids);
                error_log("DEBUG (admin/actas.php): Parámetros para rechazar: " . print_r($params, true));
                $stmt->execute($params);
                $rows_affected = $stmt->rowCount();
                $message = "Acta(s) rechazada(s) correctamente. $rows_affected nota(s) actualizada(s).";
                $message_type = "warning";
            }

            $pdo->commit();
            header("Location: ../admin/actas.php?status=" . urlencode($filter_status) . "&msg=" . urlencode($message) . "&type=" . urlencode($message_type));
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error al procesar el acta: " . $e->getMessage();
            $message_type = "danger";
            error_log("ERROR (admin/actas.php): Excepción al procesar acta: " . $e->getMessage());
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error de base de datos al procesar el acta: " . $e->getMessage();
            $message_type = "danger";
            error_log("ERROR (admin/actas.php): PDOException al procesar acta: " . $e->getMessage());
        }
    }
}
?>