<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

check_login_and_role('Profesor');

$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = basename(dirname($_SERVER['PHP_SELF']));

$profesor_id = $_SESSION['profesor_id'] ?? null;

// Obtener asignaturas y semestres del profesor
try {
    $stmt_clases = $pdo->prepare("
        SELECT
            h.id_asignatura,
            a.nombre_asignatura,
            h.id_semestre,
            CONCAT(s.numero_semestre, ' - ', sa.nombre_anio) AS nombre_semestre_completo
        FROM horarios h
        JOIN asignaturas a ON h.id_asignatura = a.id
        JOIN semestres s ON h.id_semestre = s.id
        JOIN anios_academicos sa ON s.id_anio_academico = sa.id
        WHERE h.id_profesor = :profesor_id
        GROUP BY h.id_asignatura, a.nombre_asignatura, h.id_semestre, nombre_semestre_completo
        ORDER BY sa.nombre_anio DESC, s.numero_semestre DESC, a.nombre_asignatura
    ");
    $stmt_clases->execute(['profesor_id' => $profesor_id]);
    $clases = $stmt_clases->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error al cargar las asignaturas.";
    $message_type = "danger";
    $clases = [];
    error_log("Error cargando asignaturas: " . $e->getMessage());
}

$selected_asignatura_id = $_GET['asignatura_id'] ?? null;
$selected_semestre_id = $_GET['semestre_id'] ?? null;
$notas_estudiantes = [];

// Procesar envío del acta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_acta') {
    $selected_asignatura_id = filter_var($_POST['asignatura_id'], FILTER_VALIDATE_INT);
    $selected_semestre_id = filter_var($_POST['semestre_id'], FILTER_VALIDATE_INT);
    $notas_enviadas = $_POST['notas'] ?? [];

    if ($selected_asignatura_id && $selected_semestre_id && !empty($notas_enviadas)) {
        try {
            $pdo->beginTransaction();
            $grades_processed = 0;
            $validation_errors = [];

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

                if ($inscripcion_id === false || $nota === false || $nota < 0 || $nota > 10) {
                    $validation_errors[] = "Nota inválida para inscripción ID $inscripcion_id.";
                    continue;
                }

                $estado_nota = ($nota >= 5) ? 'APROBADO' : 'REPROBADO';

                $stmt_check_status = $pdo->prepare("
                    SELECT estado_envio_acta FROM notas WHERE id_inscripcion = :id_inscripcion
                ");
                $stmt_check_status->execute(['id_inscripcion' => $inscripcion_id]);
                $current_status = $stmt_check_status->fetchColumn();

                if ($current_status === 'APROBADA_ADMIN') {
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
                $message = "Errores al guardar notas:<br>" . implode('<br>', $validation_errors);
                $message_type = "danger";
            } elseif ($grades_processed > 0) {
                $pdo->commit();
                $message = "Acta enviada correctamente. $grades_processed notas procesadas.";
                $message_type = "success";
            } else {
                $pdo->rollBack();
                $message = "No se procesaron notas válidas.";
                $message_type = "info";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error al procesar el acta.";
            $message_type = "danger";
            error_log("Error al guardar acta: " . $e->getMessage());
        }
    }
}

// Cargar notas si hay asignatura y semestre seleccionados
if ($selected_asignatura_id && $selected_semestre_id) {
    try {
        $stmt_notas = $pdo->prepare("
            SELECT
                ie.id AS inscripcion_id,
                e.id AS estudiante_id,
                u.nombre_completo AS nombre_estudiante,
                n.nota,
                n.estado AS estado_nota_bd,
                n.estado_envio_acta,
                n.observaciones_admin
            FROM inscripciones_estudiantes ie
            JOIN estudiantes e ON ie.id_estudiante = e.id
            JOIN usuarios u ON e.id_usuario = u.id
            LEFT JOIN notas n ON ie.id = n.id_inscripcion
            WHERE ie.id_asignatura = :asignatura_id
              AND ie.id_semestre = :semestre_id
            ORDER BY u.nombre_completo
        ");
        $stmt_notas->execute([
            'asignatura_id' => $selected_asignatura_id,
            'semestre_id' => $selected_semestre_id
        ]);
        $notas_estudiantes = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Error al cargar las notas.";
        $message_type = "danger";
        $notas_estudiantes = [];
        error_log("Error cargando notas: " . $e->getMessage());
    }
}
?>


<?php include '../includes/header.php'; ?>

 
            <h1 class="mt-4">Gestión de Notas</h1>

             

            <div class="card mb-4">
                <div class="card-header">
                    Seleccionar Asignatura y Semestre
                </div>
                <div class="card-body">
                    <form method="GET" action="actas.php">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="asignatura_id" class="form-label">Asignatura:</label>
                                <select class="form-select" id="asignatura_id" name="asignatura_id" required>
                                    <option value="">Seleccione una asignatura</option>
                                    <?php foreach ($clases as $clase): ?>
                                        <option value="<?php echo htmlspecialchars($clase['id_asignatura']); ?>"
                                                <?php echo ($selected_asignatura_id == $clase['id_asignatura'] && $selected_semestre_id == $clase['id_semestre']) ? 'selected' : ''; ?>
                                                data-semestre-id="<?php echo htmlspecialchars($clase['id_semestre']); ?>">
                                            <?php echo htmlspecialchars($clase['nombre_asignatura'] . ' (' . $clase['nombre_semestre_completo'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="hidden_semestre_id" name="semestre_id" value="<?php echo htmlspecialchars($selected_semestre_id); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Cargar Notas</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_asignatura_id && $selected_semestre_id): ?>
                <h2 class="mt-4">Notas para <?php
                    $selected_clase = array_filter($clases, function($clase) use ($selected_asignatura_id, $selected_semestre_id) {
                        return $clase['id_asignatura'] == $selected_asignatura_id && $clase['id_semestre'] == $selected_semestre_id;
                    });
                    if (!empty($selected_clase)) {
                        $clase_info = reset($selected_clase);
                        echo htmlspecialchars($clase_info['nombre_asignatura'] . ' (' . $clase_info['nombre_semestre_completo'] . ')');
                    }
                ?></h2>

                <?php if (!empty($notas_estudiantes)): ?>
                    
<form method="POST" action="actas.php">
                        <input type="hidden" name="action" value="submit_acta">
                        <input type="hidden" name="asignatura_id" value="<?php echo htmlspecialchars($selected_asignatura_id); ?>">
                        <input type="hidden" name="semestre_id" value="<?php echo htmlspecialchars($selected_semestre_id); ?>">

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Nota</th>
                                        <th>Estado Actual</th>
                                        <th>Observaciones Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notas_estudiantes as $nota_e):
                                        // La nota es editable si está en BORRADOR, RECHAZADA_ADMIN o si no existe aún (null)
                                        $is_editable = ($nota_e['estado_envio_acta'] == 'BORRADOR' || $nota_e['estado_envio_acta'] == 'RECHAZADA_ADMIN' || is_null($nota_e['estado_envio_acta']));
                                        $input_disabled = $is_editable ? '' : 'disabled';
                                        $badge_class = 'secondary';
                                        $status_text = 'No Guardada';
                                        $admin_obs_display = '-';

                                        if (!is_null($nota_e['estado_envio_acta'])) {
                                            switch ($nota_e['estado_envio_acta']) {
                                                case 'BORRADOR':
                                                    $badge_class = 'primary';
                                                    $status_text = 'Borrador';
                                                    break;
                                                case 'ENVIADA_PROFESOR':
                                                    $badge_class = 'warning';
                                                    $status_text = 'Enviada (Pendiente Aprobación Admin)';
                                                    break;
                                                case 'APROBADA_ADMIN':
                                                    $badge_class = 'success';
                                                    $status_text = 'Aprobada (Final)';
                                                    break;
                                                case 'RECHAZADA_ADMIN':
                                                    $badge_class = 'danger';
                                                    $status_text = 'Rechazada (Requiere Corrección)';
                                                    $admin_obs_display = !empty($nota_e['observaciones_admin']) ? htmlspecialchars($nota_e['observaciones_admin']) : 'Sin observaciones.';
                                                    break;
                                                default:
                                                    $badge_class = 'secondary';
                                                    $status_text = 'Desconocido';
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($nota_e['nombre_estudiante']); ?></td>
                                            <td>
                                                <input type="number" step="0.01" min="0" max="10" class="form-control form-control-sm"
                                                       name="notas[<?php echo htmlspecialchars($nota_e['inscripcion_id']); ?>]"
                                                       value="<?php echo htmlspecialchars($nota_e['nota'] ?? ''); ?>"
                                                       <?php echo $input_disabled; ?> required>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $admin_obs_display; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        
<button type="submit" class="btn btn-primary mt-3" onclick="return confirm('¿Está seguro de que desea ENVIAR esta acta para revisión del administrador? Las notas no podrán ser modificadas hasta su revisión.');">Enviar Acta</button>
                    </form>

                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        No hay estudiantes inscritos o notas para esta asignatura y semestre.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    Por favor, seleccione una asignatura y un semestre para gestionar las notas.
                </div>
            <?php endif; ?>
      

<?php include '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const asignaturaSelect = document.getElementById('asignatura_id');
        const hiddenSemestreInput = document.getElementById('hidden_semestre_id');

        // Function to update the hidden semester ID
        function updateHiddenSemestreId() {
            const selectedOption = asignaturaSelect.options[asignaturaSelect.selectedIndex];
            // Ensure a valid option is selected (not the "Seleccione..." one)
            if (selectedOption && selectedOption.value) {
                const semestreId = selectedOption.getAttribute('data-semestre-id');
                hiddenSemestreInput.value = semestreId;
            } else {
                hiddenSemestreInput.value = ''; // Clear if no valid selection
            }
        }

        // Set the hidden semestre_id when the asignatura_id select changes
        asignaturaSelect.addEventListener('change', updateHiddenSemestreId);

        // Ensure the hidden field is correctly set on initial load if a value is pre-selected
        // Also ensure it's called immediately if an option is already selected on page load
        if (asignaturaSelect.value) {
            updateHiddenSemestreId();
        }
    });
</script>