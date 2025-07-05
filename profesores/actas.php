<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Asegura que solo los profesores puedan acceder a esta página
check_login_and_role('Profesor');

$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = basename(dirname($_SERVER['PHP_SELF']));

$profesor_id = $_SESSION['profesor_id'] ?? null;

// Recuperar y limpiar el mensaje de flash si existe
$message = $_SESSION['flash_message']['message'] ?? '';
$message_type = $_SESSION['flash_message']['type'] ?? '';
unset($_SESSION['flash_message']); // Limpiar el mensaje después de mostrarlo

// Obtener asignaturas y semestres que el profesor imparte
try {
    $stmt_clases = $pdo->prepare("
        SELECT
            h.id_asignatura,
            a.nombre_asignatura,
            h.id_semestre,
            s.id_anio_academico, /* Añadido para obtener el ID del año académico */
            CONCAT(s.numero_semestre, ' - ', sa.nombre_anio) AS nombre_semestre_completo
        FROM horarios h
        JOIN asignaturas a ON h.id_asignatura = a.id
        JOIN semestres s ON h.id_semestre = s.id
        JOIN anios_academicos sa ON s.id_anio_academico = sa.id
        WHERE h.id_profesor = :profesor_id
        GROUP BY h.id_asignatura, a.nombre_asignatura, h.id_semestre, s.id_anio_academico, nombre_semestre_completo
        ORDER BY sa.nombre_anio DESC, s.numero_semestre DESC, a.nombre_asignatura
    ");
    $stmt_clases->execute(['profesor_id' => $profesor_id]);
    $clases = $stmt_clases->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si hay un error, establece el mensaje de flash para mostrarlo después del redireccionamiento
    $_SESSION['flash_message'] = [
        'message' => "Error al cargar las asignaturas disponibles para el profesor.",
        'type' => "danger"
    ];
    error_log("Error cargando asignaturas para profesor: " . $e->getMessage());
    header('Location: ' . $current_page); // Redirige para limpiar el POST
    exit();
}

// Variables para almacenar la asignatura y el semestre seleccionados
$selected_asignatura_id = null;
$selected_semestre_id = null;
$selected_anio_academico_id = null; // Variable para almacenar el ID del año académico
$notas_estudiantes = [];

// Determinar asignatura y semestre seleccionados (desde GET o POST)
// Si viene de un POST exitoso (después del redirect), estos valores no estarán en POST,
// por lo que se deben limpiar para que el formulario se muestre sin selecciones.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['asignatura_id']) && isset($_GET['semestre_id'])) {
    $selected_asignatura_id = filter_var($_GET['asignatura_id'], FILTER_VALIDATE_INT);
    $selected_semestre_id = filter_var($_GET['semestre_id'], FILTER_VALIDATE_INT);
    $selected_anio_academico_id = filter_var($_GET['anio_academico_id'] ?? null, FILTER_VALIDATE_INT); // Obtener también el año académico del GET
}
// NOTA: Si el formulario se envía con POST y hay un error, los campos se mantienen.
// Si el formulario se envía con POST y es exitoso, se redirige con GET y se limpian.


// Obtener el ID del año académico para la asignatura y semestre seleccionados
// Esto es importante para la validación de duplicados y se asegura de que selected_anio_academico_id esté poblado correctamente
if ($selected_asignatura_id && $selected_semestre_id && is_null($selected_anio_academico_id)) {
    foreach ($clases as $clase) {
        if ($clase['id_asignatura'] == $selected_asignatura_id && $clase['id_semestre'] == $selected_semestre_id) {
            $selected_anio_academico_id = $clase['id_anio_academico'];
            break;
        }
    }
}



// Cargar notas de los estudiantes inscritos si hay asignatura y semestre seleccionados
// Esta parte se ejecuta después del redireccionamiento si el POST fue exitoso,
// o directamente si la página se carga con GET o por primera vez.
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
              AND ie.confirmada = 1 -- Asegúrate de que solo se muestren inscripciones confirmadas
            ORDER BY u.nombre_completo
        ");
        $stmt_notas->execute([
            'asignatura_id' => $selected_asignatura_id,
            'semestre_id' => $selected_semestre_id
        ]);
        $notas_estudiantes = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Error al cargar la lista de estudiantes inscritos.";
        $message_type = "danger";
        $notas_estudiantes = [];
        error_log("Error cargando notas de estudiantes inscritos: " . $e->getMessage());
    }
}
?>

<?php include '../includes/header.php'; ?>

<main>
    <div class="container-fluid px-4">
        <h1 class="mt-4">Gestión de Notas</h1>

        <?php if ($message): // Muestra mensajes de éxito/error/info desde la sesión ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-list me-1"></i>
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
                                        data-semestre-id="<?php echo htmlspecialchars($clase['id_semestre']); ?>"
                                        data-anio-academico-id="<?php echo htmlspecialchars($clase['id_anio_academico']); ?>"
                                        <?php echo ($selected_asignatura_id == $clase['id_asignatura'] && $selected_semestre_id == $clase['id_semestre']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($clase['nombre_asignatura'] . ' (' . $clase['nombre_semestre_completo'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="hidden_semestre_id" name="semestre_id" value="<?php echo htmlspecialchars($selected_semestre_id ?? ''); ?>">
                            <input type="hidden" id="hidden_anio_academico_id" name="anio_academico_id" value="<?php echo htmlspecialchars($selected_anio_academico_id ?? ''); ?>">
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
                $selected_clase_info = array_filter($clases, function($clase) use ($selected_asignatura_id, $selected_semestre_id) {
                    return $clase['id_asignatura'] == $selected_asignatura_id && $clase['id_semestre'] == $selected_semestre_id;
                });
                if (!empty($selected_clase_info)) {
                    $clase_info = reset($selected_clase_info);
                    echo htmlspecialchars($clase_info['nombre_asignatura'] . ' (' . $clase_info['nombre_semestre_completo'] . ')');
                } else {
                    echo "Asignatura Desconocida"; // Fallback si no se encuentra la info de la clase
                }
            ?></h2>

            <?php
            // Mensajes de depuración para los IDs seleccionados
            echo '<div class="alert alert-secondary mt-3">';
            echo '<strong>Depuración:</strong> Asignatura ID = ' . htmlspecialchars($selected_asignatura_id) . ', Semestre ID = ' . htmlspecialchars($selected_semestre_id) . ', Año Académico ID = ' . htmlspecialchars($selected_anio_academico_id);
            echo '</div>';
            ?>

            <?php if (!empty($notas_estudiantes)): ?>
                <form method="POST" action="../api/enviar_actas_profesor.php">
                    <input type="hidden" name="action" value="submit_acta">
                    <input type="hidden" name="asignatura_id" value="<?php echo htmlspecialchars($selected_asignatura_id); ?>">
                    <input type="hidden" name="semestre_id" value="<?php echo htmlspecialchars($selected_semestre_id); ?>">
                    <input type="hidden" name="anio_academico_id" value="<?php echo htmlspecialchars($selected_anio_academico_id); ?>">

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
                                                // Una vez aprobada, la nota no debe ser editable por el profesor.
                                                $input_disabled = 'disabled';
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
                    <button type="button" class="btn btn-info mt-3 me-2"
        data-bs-toggle="modal" data-bs-target="#actaNotasModal"
        id="btnVerActa"
        <?php echo empty($notas_estudiantes) ? 'disabled' : ''; ?>>
    <i class="fas fa-print me-1"></i> Ver/Imprimir Acta
</button>

<button type="submit" class="btn btn-primary mt-3" onclick="return confirm('¿Está seguro de que desea ENVIAR esta acta para revisión del administrador? Las notas no podrán ser modificadas hasta su revisión.');" <?php echo empty($notas_estudiantes) ? 'disabled' : ''; ?>>
    Enviar Acta
</button>
                     
                </form>

            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    No hay estudiantes inscritos en esta asignatura y semestre.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                Por favor, seleccione una asignatura y un semestre para gestionar las notas.
            </div>
        <?php endif; ?>
    </div>
</main>


<!-- modal para listar actas enviadas -->
<div class="modal fade" id="actaNotasModal" tabindex="-1" aria-labelledby="actaNotasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actaNotasModalLabel">Vista Previa y Impresión de Acta de Notas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="actaPrintFrame" style="width: 100%; height: 80vh; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="btnPrintActa">
                    <i class="fas fa-print me-1"></i> Imprimir Acta
                </button>
            </div>
        </div>
    </div>
</div>



<?php include '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const asignaturaSelect = document.getElementById('asignatura_id');
        const hiddenSemestreInput = document.getElementById('hidden_semestre_id');
        const hiddenAnioAcademicoInput = document.getElementById('hidden_anio_academico_id');

        // Función para actualizar los IDs ocultos
        function updateHiddenInputs() {
            const selectedOption = asignaturaSelect.options[asignaturaSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const semestreId = selectedOption.getAttribute('data-semestre-id');
                const anioAcademicoId = selectedOption.getAttribute('data-anio-academico-id');
                hiddenSemestreInput.value = semestreId;
                hiddenAnioAcademicoInput.value = anioAcademicoId;
                console.log(`[JS] ID de semestre oculto actualizado a: ${semestreId}`);
                console.log(`[JS] ID de año académico oculto actualizado a: ${anioAcademicoId}`);
            } else {
                hiddenSemestreInput.value = '';
                hiddenAnioAcademicoInput.value = '';
                console.log("[JS] IDs ocultos limpiados (ninguna opción válida seleccionada).");
            }
        }

        // Asigna los IDs ocultos cuando cambia la selección de asignatura
        asignaturaSelect.addEventListener('change', updateHiddenInputs);

        // Asegura que los campos ocultos estén correctamente configurados en la carga inicial
        // Si hay una selección previa (ej. al cargar la página por GET), actualiza los campos.
        // Si no hay selección (ej. después de un envío POST exitoso y redirección), se quedarán vacíos.
        if (asignaturaSelect.value) {
            updateHiddenInputs();
        } else {
            hiddenSemestreInput.value = '';
            hiddenAnioAcademicoInput.value = '';
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const asignaturaSelect = document.getElementById('asignatura_id');
        const hiddenSemestreInput = document.getElementById('hidden_semestre_id');
        const hiddenAnioAcademicoInput = document.getElementById('hidden_anio_academico_id');
        
        // Elementos del modal para el acta
        const actaNotasModal = document.getElementById('actaNotasModal');
        const actaPrintFrame = document.getElementById('actaPrintFrame');
        const btnPrintActa = document.getElementById('btnPrintActa');

        // Función para actualizar los IDs ocultos (ya existente)
        function updateHiddenInputs() {
            const selectedOption = asignaturaSelect.options[asignaturaSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const semestreId = selectedOption.getAttribute('data-semestre-id');
                const anioAcademicoId = selectedOption.getAttribute('data-anio-academico-id');
                hiddenSemestreInput.value = semestreId;
                hiddenAnioAcademicoInput.value = anioAcademicoId;
                // console.log(`[JS] ID de semestre oculto actualizado a: ${semestreId}`);
                // console.log(`[JS] ID de año académico oculto actualizado a: ${anioAcademicoId}`);
            } else {
                hiddenSemestreInput.value = '';
                hiddenAnioAcademicoInput.value = '';
                // console.log("[JS] IDs ocultos limpiados (ninguna opción válida seleccionada).");
            }
        }

        // Asigna los IDs ocultos cuando cambia la selección de asignatura (ya existente)
        asignaturaSelect.addEventListener('change', updateHiddenInputs);

        // Asegura que los campos ocultos estén correctamente configurados en la carga inicial (ya existente)
        if (asignaturaSelect.value) {
            updateHiddenInputs();
        } else {
            hiddenSemestreInput.value = '';
            hiddenAnioAcademicoInput.value = '';
        }

        // --- Nueva lógica para el Modal de Acta ---

        // Evento que se dispara cuando el modal se muestra completamente
        actaNotasModal.addEventListener('shown.bs.modal', function () {
            const selectedAsignaturaId = asignaturaSelect.value;
            const selectedSemestreId = hiddenSemestreInput.value;
            const selectedAnioAcademicoId = hiddenAnioAcademicoInput.value;

            if (selectedAsignaturaId && selectedSemestreId && selectedAnioAcademicoId) {
                // Construye la URL para el generador del acta
                const actaUrl = `listar_actas.php?asignatura_id=${selectedAsignaturaId}&semestre_id=${selectedSemestreId}&anio_academico_id=${selectedAnioAcademicoId}`;
                actaPrintFrame.src = actaUrl; // Carga el contenido en el iframe
                console.log(`[Modal JS] Cargando acta en iframe desde: ${actaUrl}`);
            } else {
                actaPrintFrame.src = 'about:blank'; // Limpia el iframe si no hay selección
                console.warn('[Modal JS] No se pudo cargar el acta: Asignatura o Semestre no seleccionados.');
            }
        });

        // Evento para el botón de imprimir dentro del modal
        btnPrintActa.addEventListener('click', function() {
            // Llama a la función de impresión del contenido del iframe
            if (actaPrintFrame.contentWindow) {
                actaPrintFrame.contentWindow.focus(); // Pone el foco en el iframe
                actaPrintFrame.contentWindow.print(); // Dispara el diálogo de impresión
                console.log('[Modal JS] Iniciando impresión del acta.');
            } else {
                console.error('[Modal JS] No se pudo acceder al contenido del iframe para imprimir.');
            }
        });

        // Evento que se dispara cuando el modal se oculta
        actaNotasModal.addEventListener('hidden.bs.modal', function () {
            actaPrintFrame.src = 'about:blank'; // Limpia el iframe para evitar que el contenido persista
            console.log('[Modal JS] Modal oculto, iframe limpiado.');
        });
    });
</script>