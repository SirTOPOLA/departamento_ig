<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Asegúrate de que solo los administradores puedan acceder
check_login_and_role('Administrador');

$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = basename(dirname($_SERVER['PHP_SELF']));

$admin_id = $_SESSION['user_id'] ?? null;

// DEBUG: Verificar si el admin_id está disponible
if (is_null($admin_id)) {
    error_log("ERROR (admin/actas.php): admin_id no está definido en la sesión.");
    // Considera redirigir o mostrar un error al usuario si el ID del admin es crucial y falta
    // header("Location: ../login.php?error=admin_id_missing"); exit();
}


$message = '';
$message_type = '';

// Obtener el filtro de estado de las actas
$filter_status = $_GET['status'] ?? 'ENVIADA_PROFESOR'; // Por defecto, mostrar las pendientes de revisión

// Procesar acciones: aprobar o rechazar actas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $inscripcion_ids_str = $_POST['inscripcion_ids'] ?? '';
    $observaciones_admin = trim($_POST['observaciones_admin'] ?? '');

    $inscripcion_ids = array_filter(array_map('intval', explode(',', $inscripcion_ids_str)));

    // DEBUG: Log de IDs de inscripción recibidos
    error_log("DEBUG (admin/actas.php): Acción recibida: " . $action);
    error_log("DEBUG (admin/actas.php): IDs de inscripción recibidos: " . implode(',', $inscripcion_ids));
    error_log("DEBUG (admin/actas.php): Observaciones: " . $observaciones_admin);


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
                // Los IDs de inscripción se pasan directamente como parámetros posicionales
                $stmt_fetch_details->execute($inscripcion_ids);
                $notes_to_approve_details = $stmt_fetch_details->fetchAll(PDO::FETCH_ASSOC);

                error_log("DEBUG (admin/actas.php): Detalles de notas a aprobar: " . print_r($notes_to_approve_details, true));


                if (empty($notes_to_approve_details)) {
                    throw new Exception("No se encontraron notas válidas para aprobar con los IDs proporcionados o no están en estado 'ENVIADA_PROFESOR'.");
                }

                // 2. Preparar la sentencia para insertar/actualizar en historial_academico
                // NOTA: Esto requiere una clave UNIQUE en (id_estudiante, id_asignatura, id_semestre) en historial_academico
                $stmt_upsert_historial = $pdo->prepare("
                    INSERT INTO historial_academico (id_estudiante, id_asignatura, id_semestre, nota_final, estado_final, fecha_actualizacion)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        nota_final = VALUES(nota_final),
                        estado_final = VALUES(estado_final),
                        fecha_actualizacion = NOW()
                ");

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
                        // Esto es ÚTIL para depuración, pero en producción podrías querer que esto detenga la transacción.
                        error_log("ERROR (admin/actas.php - Historial): PDOException al upsert en historial_academico para inscripcion_id " . $note_detail['id_inscripcion'] . ": " . $e_historial->getMessage());
                        // Si el error es por una clave duplicada y la UNIQUE constraint está bien, no es un problema.
                        // Si es otro error, necesitamos verlo.
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
                // Parámetros: Primero el ID del admin, luego todos los IDs de inscripción
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
                // Parámetros: Primero el ID del admin, luego las observaciones, luego todos los IDs de inscripción
                $params = array_merge([$admin_id, $observaciones_admin], $inscripcion_ids);
                error_log("DEBUG (admin/actas.php): Parámetros para rechazar: " . print_r($params, true));
                $stmt->execute($params);
                $rows_affected = $stmt->rowCount();
                $message = "Acta(s) rechazada(s) correctamente. $rows_affected nota(s) actualizada(s).";
                $message_type = "warning";
            }

            $pdo->commit();
            // Redirigir para evitar reenvío del formulario y actualizar la vista
            header("Location: actas.php?status=" . urlencode($filter_status) . "&msg=" . urlencode($message) . "&type=" . urlencode($message_type));
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            // Es crucial mostrar el mensaje de la excepción real para depurar
            $message = "Error al procesar el acta: " . $e->getMessage();
            $message_type = "danger";
            error_log("ERROR (admin/actas.php): Excepción al procesar acta: " . $e->getMessage());
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Es crucial mostrar el mensaje de la excepción PDO real para depurar
            $message = "Error de base de datos al procesar el acta: " . $e->getMessage();
            $message_type = "danger";
            error_log("ERROR (admin/actas.php): PDOException al procesar acta: " . $e->getMessage());
        }
    }
}

// Cargar actas según el filtro
$actas = [];
try {
    $sql = "
        SELECT
            n.id AS nota_id,
            n.id_inscripcion,
            n.nota,
            n.estado AS estado_nota,
            n.estado_envio_acta,
            n.fecha_envio_acta,
            n.fecha_revision_admin,
            n.observaciones_admin,
            ie.id_estudiante,
            ie.id_asignatura,
            ie.id_semestre,
            u_estudiante.nombre_completo AS nombre_estudiante,
            a.nombre_asignatura,
            s.numero_semestre,
            sa.nombre_anio,
            u_profesor.nombre_completo AS nombre_profesor,
            h.id_profesor
        FROM notas n
        JOIN inscripciones_estudiantes ie ON n.id_inscripcion = ie.id
        JOIN estudiantes e ON ie.id_estudiante = e.id
        JOIN usuarios u_estudiante ON e.id_usuario = u_estudiante.id
        JOIN asignaturas a ON ie.id_asignatura = a.id
        JOIN semestres s ON ie.id_semestre = s.id
        JOIN anios_academicos sa ON s.id_anio_academico = sa.id
        JOIN horarios h ON ie.id_asignatura = h.id_asignatura AND ie.id_semestre = h.id_semestre
        JOIN profesores p ON h.id_profesor = p.id
        JOIN usuarios u_profesor ON p.id_usuario = u_profesor.id
        WHERE 1=1
    ";

    $params = [];

    // Lógica de filtrado basada ÚNICAMENTE en estado_envio_acta
    if ($filter_status !== 'all') {
        $sql .= " AND n.estado_envio_acta = :status";
        $params['status'] = $filter_status;
    } else {
        // Si el filtro es 'all', mostramos todos los estados relevantes para el administrador
        // (excluyendo 'BORRADOR' que es un estado interno del profesor antes de enviar)
        $sql .= " AND n.estado_envio_acta IN ('ENVIADA_PROFESOR', 'APROBADA_ADMIN', 'RECHAZADA_ADMIN')";
    }

    $sql .= " ORDER BY n.fecha_envio_acta DESC, nombre_asignatura ASC, nombre_estudiante ASC";

    error_log("DEBUG (admin/actas.php): SQL para cargar actas: " . $sql);
    error_log("DEBUG (admin/actas.php): Parámetros para cargar actas: " . print_r($params, true));

    $stmt_actas = $pdo->prepare($sql);
    $stmt_actas->execute($params);
    $actas_raw = $stmt_actas->fetchAll(PDO::FETCH_ASSOC);

    $actas_agrupadas = [];
    foreach ($actas_raw as $nota) {
        $key = $nota['id_asignatura'] . '-' . $nota['id_semestre'] . '-' . $nota['id_profesor'];
        if (!isset($actas_agrupadas[$key])) {
            $actas_agrupadas[$key] = [
                'id_asignatura' => $nota['id_asignatura'],
                'nombre_asignatura' => $nota['nombre_asignatura'],
                'id_semestre' => $nota['id_semestre'],
                'nombre_semestre_completo' => $nota['numero_semestre'] . ' - ' . $nota['nombre_anio'],
                'id_profesor' => $nota['id_profesor'],
                'nombre_profesor' => $nota['nombre_profesor'],
                'estado_envio_acta' => $nota['estado_envio_acta'],
                'fecha_envio_acta' => $nota['fecha_envio_acta'],
                'fecha_revision_admin' => $nota['fecha_revision_admin'],
                'observaciones_admin' => $nota['observaciones_admin'],
                'notas' => []
            ];
        }
        $actas_agrupadas[$key]['notas'][] = $nota;
    }
    $actas = array_values($actas_agrupadas);
} catch (PDOException $e) {
    $message = "Error al cargar las actas: " . $e->getMessage();
    $message_type = "danger";
    error_log("ERROR (admin/actas.php): PDOException al cargar actas: " . $e->getMessage());
}

// Mostrar mensaje pasado por URL (después del POST)
if (isset($_GET['msg'], $_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>


<?php include '../includes/header.php'; ?>

<h1 class="mt-4">Gestión de Actas</h1>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        Filtrar Actas
    </div>
    <div class="card-body">
        <form method="GET" action="actas.php">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="status_filter" class="form-label">Estado del Acta:</label>
                    <select class="form-select" id="status_filter" name="status">
                        <option value="ENVIADA_PROFESOR" <?php echo ($filter_status == 'ENVIADA_PROFESOR') ? 'selected' : ''; ?>>Pendientes de Revisión</option>
                        <option value="APROBADA_ADMIN" <?php echo ($filter_status == 'APROBADA_ADMIN') ? 'selected' : ''; ?>>Aprobadas</option>
                        <option value="RECHAZADA_ADMIN" <?php echo ($filter_status == 'RECHAZADA_ADMIN') ? 'selected' : ''; ?>>Rechazadas</option>
                        <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>Todas</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-info">Filtrar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($actas)): ?>
    <div class="accordion" id="actasAccordion">
        <?php foreach ($actas as $index => $acta):
            $is_pending = ($acta['estado_envio_acta'] === 'ENVIADA_PROFESOR');
            $accordion_id = 'actaCollapse' . $index;
            $header_id = 'actaHeading' . $index;

            $badge_class = 'secondary';
            $status_text = 'Desconocido';
            switch ($acta['estado_envio_acta']) {
                case 'BORRADOR': $badge_class = 'primary'; $status_text = 'Borrador (Profesor)'; break;
                case 'ENVIADA_PROFESOR': $badge_class = 'warning'; $status_text = 'Enviada (Pendiente Revisión)'; break;
                case 'APROBADA_ADMIN': $badge_class = 'success'; $status_text = 'Aprobada (Final)'; break;
                case 'RECHAZADA_ADMIN': $badge_class = 'danger'; $status_text = 'Rechazada (Requiere Corrección)'; break;
            }
        ?>
            <div class="accordion-item mb-3">
                <h2 class="accordion-header" id="<?php echo $header_id; ?>">
                    <button class="accordion-button <?php echo $is_pending ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $accordion_id; ?>" aria-expanded="<?php echo $is_pending ? 'true' : 'false'; ?>" aria-controls="<?php echo $accordion_id; ?>">
                        Acta de: <strong><?php echo htmlspecialchars($acta['nombre_asignatura']); ?></strong> (<?php echo htmlspecialchars($acta['nombre_semestre_completo']); ?>) - Profesor: <strong><?php echo htmlspecialchars($acta['nombre_profesor']); ?></strong>
                        <span class="badge bg-<?php echo $badge_class; ?> ms-3"><?php echo $status_text; ?></span>
                        <?php if ($acta['fecha_envio_acta']): ?>
                            <small class="text-muted ms-2">Enviada: <?php echo date('d/m/Y H:i', strtotime($acta['fecha_envio_acta'])); ?></small>
                        <?php endif; ?>
                    </button>
                </h2>
                <div id="<?php echo $accordion_id; ?>" class="accordion-collapse collapse <?php echo $is_pending ? 'show' : ''; ?>" aria-labelledby="<?php echo $header_id; ?>" data-bs-parent="#actasAccordion">
                    <div class="accordion-body">
                        <?php if ($acta['estado_envio_acta'] === 'RECHAZADA_ADMIN' && !empty($acta['observaciones_admin'])): ?>
                            <div class="alert alert-warning" role="alert">
                                <strong>Observaciones del Administrador:</strong> <?php echo htmlspecialchars($acta['observaciones_admin']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Nota</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_acta_inscripcion_ids = [];
                                    foreach ($acta['notas'] as $nota_estudiante):
                                        $current_acta_inscripcion_ids[] = $nota_estudiante['id_inscripcion'];
                                        $nota_badge_class = ($nota_estudiante['estado_nota'] === 'APROBADO') ? 'success' : 'danger';
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($nota_estudiante['nombre_estudiante']); ?></td>
                                            <td><?php echo htmlspecialchars($nota_estudiante['nota'] ?? 'N/A'); ?></td>
                                            <td><span class="badge bg-<?php echo $nota_badge_class; ?>"><?php echo htmlspecialchars($nota_estudiante['estado_nota'] ?? 'Pendiente'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($is_pending): // Solo mostrar botones de acción si está pendiente de revisión ?>
                            <div class="mt-3 d-flex justify-content-end">
                                <form method="POST" action="actas.php" class="me-2">
                                    <input type="hidden" name="action" value="approve_acta">
                                    <input type="hidden" name="inscripcion_ids" value="<?php echo implode(',', $current_acta_inscripcion_ids); ?>">
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('¿Está seguro de APROBAR esta acta? Esta acción es final.');">Aprobar Acta</button>
                                </form>

                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal"
                                        data-inscripcion-ids="<?php echo implode(',', $current_acta_inscripcion_ids); ?>">
                                    Rechazar Acta
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info" role="alert">
        No hay actas disponibles con el estado seleccionado.
    </div>
<?php endif; ?>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="actas.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Rechazar Acta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject_acta">
                    <input type="hidden" name="inscripcion_ids" id="modal_inscripcion_ids">
                    <div class="mb-3">
                        <label for="observaciones_admin" class="form-label">Observaciones para el Profesor:</label>
                        <textarea class="form-control" id="observaciones_admin" name="observaciones_admin" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Script para pasar los IDs de inscripción al modal de rechazo
        const rejectModal = document.getElementById('rejectModal');
        rejectModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Botón que activó el modal
            const inscripcionIds = button.getAttribute('data-inscripcion-ids'); // Extrae info de los atributos data-*

            const modalInscripcionIdsInput = rejectModal.querySelector('#modal_inscripcion_ids');
            modalInscripcionIdsInput.value = inscripcionIds;
        });
    });
</script>