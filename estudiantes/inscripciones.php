<?php

require_once '../includes/functions.php';
// Ensure the logged-in user is a student
check_login_and_role('Estudiante');

require_once '../config/database.php';

// --- POST Processing Logic for Enrollment (MUST BE BEFORE ANY HTML OUTPUT) ---
// Get the student's id_estudiante and id_curso_inicio from the logged-in user
$stmt_student_details = $pdo->prepare("SELECT id, id_curso_inicio FROM estudiantes WHERE id_usuario = :id_usuario");
$stmt_student_details->bindParam(':id_usuario', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt_student_details->execute();
$student_details = $stmt_student_details->fetch(PDO::FETCH_ASSOC);

if (!$student_details) {
    set_flash_message('danger', 'Error: No se encontró el perfil de estudiante asociado a su usuario.');
    header('Location: ../logout.php'); // Redirect to logout or an error page
    exit;
}
$id_estudiante_actual = $student_details['id'];
$id_curso_inicio_estudiante = $student_details['id_curso_inicio']; // Student's Course ID

// Get the current academic semester using the improved function
$current_semester = get_current_semester($pdo);

// --- Get data for the student view ---
$page_title = "Inscripción Semestral"; // Adjusted title
include_once '../includes/header.php'; // Include header here, after all POST logic and redirects

$flash_messages = get_flash_messages();


// Subjects the student is already enrolled in for the current semester (pending or confirmed)
$current_enrollments = [];
if ($current_semester) {
    $stmt_current_enrollments = $pdo->prepare("
        SELECT
            ie.id_asignatura,
            a.nombre_asignatura,
            a.creditos,
            c.nombre_curso,
            a.semestre_recomendado AS numero_semestre_asignatura,  
            s.numero_semestre AS semestre_actual_numero,  
            aa.nombre_anio,
            ie.confirmada
        FROM inscripciones_estudiantes ie
        JOIN asignaturas a ON ie.id_asignatura = a.id
        LEFT JOIN cursos c ON a.id_curso = c.id
        JOIN semestres s ON ie.id_semestre = s.id -- Join with semestres to get the academic year info for the enrolled semester
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        WHERE ie.id_estudiante = :id_estudiante
        AND ie.id_semestre = :id_semestre_actual_inscripcion
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");
    $stmt_current_enrollments->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_current_enrollments->bindParam(':id_semestre_actual_inscripcion', $current_semester['id'], PDO::PARAM_INT);
    $stmt_current_enrollments->execute();
    $current_enrollments = $stmt_current_enrollments->fetchAll(PDO::FETCH_ASSOC);
}


// Subjects approved by the student (for prerequisite verification)
$approved_asignaturas_ids = [];
$stmt_approved_asignaturas = $pdo->prepare("
    SELECT id_asignatura FROM historial_academico
    WHERE id_estudiante = :id_estudiante AND estado_final = 'APROBADO'
");
$stmt_approved_asignaturas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_approved_asignaturas->execute();
$approved_asignaturas_ids = $stmt_approved_asignaturas->fetchAll(PDO::FETCH_COLUMN);

// Subjects reprobated by the student (mandatory to retake)
$reproved_asignaturas = [];
$reproved_asignaturas_ids = [];
if ($current_semester) { // Only if there's an active semester for enrollment
    $stmt_reproved_asignaturas = $pdo->prepare("
        SELECT
            ha.id_asignatura AS id,
            a.nombre_asignatura,
            a.creditos,
            c.nombre_curso,
            a.semestre_recomendado AS numero_semestre_asignatura,  
            s.numero_semestre AS semestre_historial_numero,  
            aa.nombre_anio
        FROM historial_academico ha
        JOIN asignaturas a ON ha.id_asignatura = a.id
        LEFT JOIN cursos c ON a.id_curso = c.id
        JOIN semestres s ON ha.id_semestre = s.id  
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        WHERE ha.id_estudiante = :id_estudiante
        AND ha.estado_final = 'REPROBADO'
        AND a.id NOT IN (
            SELECT id_asignatura FROM inscripciones_estudiantes WHERE id_estudiante = :id_estudiante_current AND id_semestre = :id_semestre_current AND confirmada = 1
        )
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");
    $stmt_reproved_asignaturas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_reproved_asignaturas->bindParam(':id_estudiante_current', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_reproved_asignaturas->bindParam(':id_semestre_current', $current_semester['id'], PDO::PARAM_INT);
    $stmt_reproved_asignaturas->execute();
    $reproved_asignaturas = $stmt_reproved_asignaturas->fetchAll(PDO::FETCH_ASSOC);
    $reproved_asignaturas_ids = array_column($reproved_asignaturas, 'id');
}


// Available subjects for enrollment for the student's current course
$available_asignaturas_current_course = [];
if ($current_semester) {
    // Get the current active semester number (e.g., 1, 2, 3...)
    $current_semester_number = $current_semester['numero_semestre'];

    // Determine if the current semester is odd or even
    $is_current_semester_odd = ($current_semester_number % 2 != 0);

    $stmt_available_asignaturas = $pdo->prepare("
        SELECT
            a.id,
            a.nombre_asignatura,
            a.creditos,
            a.id_prerequisito,
            pa.nombre_asignatura AS prerequisito_nombre,
            c.nombre_curso,
            a.semestre_recomendado -- Use semestre_recomendado from the asignaturas table
        FROM asignaturas a
        LEFT JOIN asignaturas pa ON a.id_prerequisito = pa.id
        JOIN cursos c ON a.id_curso = c.id -- Use JOIN because each subject must have an associated course
        WHERE a.id_curso = :id_curso_estudiante
        AND a.id NOT IN (
            SELECT id_asignatura FROM historial_academico WHERE id_estudiante = :id_estudiante_historial_aprobado AND estado_final = 'APROBADO'
        )
        AND a.id NOT IN (
             SELECT id_asignatura FROM inscripciones_estudiantes WHERE id_estudiante = :id_estudiante_enrolled AND id_semestre = :id_semestre_enrolled
        )
        -- NEW CONDITION: Ensure the subject's recommended semester matches the current semester's parity
        -- If the current semester is odd, only show subjects with an odd semestre_recomendado.
        -- If the current semester is even, only show subjects with an even semestre_recomendado.
        AND (
            (:is_current_semester_odd AND (a.semestre_recomendado % 2 != 0))
            OR
            (:is_current_semester_even AND (a.semestre_recomendado % 2 = 0))
        )
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");

    $stmt_available_asignaturas->bindParam(':id_curso_estudiante', $id_curso_inicio_estudiante, PDO::PARAM_INT);
    $stmt_available_asignaturas->bindParam(':id_estudiante_historial_aprobado', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_available_asignaturas->bindParam(':id_estudiante_enrolled', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_available_asignaturas->bindParam(':id_semestre_enrolled', $current_semester['id'], PDO::PARAM_INT);

    // Bind parameters for the new condition
    $stmt_available_asignaturas->bindValue(':is_current_semester_odd', $is_current_semester_odd ? 1 : 0, PDO::PARAM_INT);
    $stmt_available_asignaturas->bindValue(':is_current_semester_even', !$is_current_semester_odd ? 1 : 0, PDO::PARAM_INT); // This will be true if the current semester is even

    $stmt_available_asignaturas->execute();
    $available_asignaturas_current_course = $stmt_available_asignaturas->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h1 class="mt-4">Inscripción Semestral</h1>
<p class="lead">Gestiona tu inscripción para el semestre actual y revisa tus asignaturas.</p>

<?php if (!$current_semester): ?>
    <div class="alert alert-info">
        Actualmente no hay un semestre académico activo para la inscripción. Por favor, contacta a la administración.
    </div>
<?php else: ?>
    

    <div class="d-flex justify-content-end ">
        <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#enrollmentModal">
            <i class="fas fa-plus-circle me-2"></i> Inscribirme en Asignaturas
        </button>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Tus Asignaturas Inscritas para el Semestre Actual</h5>
        </div>
        <div class="card-body">
            <?php if (count($current_enrollments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Asignatura</th>
                                <th>Créditos</th>
                                <th>Curso</th>
                                <th>Semestre</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($current_enrollments)): ?>
                                <?php foreach ($current_enrollments as $enrollment): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($enrollment['nombre_asignatura']) ?>
                                            <br><small class="text-muted">ID: <?= (int) ($enrollment['id_asignatura'] ?? 0) ?></small>
                                        </td>
                                        <td class="text-center"><?= (int) $enrollment['creditos'] ?></td>
                                        <td><?= htmlspecialchars($enrollment['nombre_curso'] ?? '—') ?></td>
                                        <td>
                                            <?= htmlspecialchars($enrollment['semestre_actual_numero'] ?? '—') ?>
                                            <span class="text-muted">(<?= htmlspecialchars($enrollment['nombre_anio'] ?? '—') ?>)</span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($enrollment['confirmada'])): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> Confirmada
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-hourglass-half me-1"></i> Pendiente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-1"></i> No hay inscripciones registradas.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No estás inscrito en ninguna asignatura para el semestre actual.</p>
            <?php endif; ?>
        </div>
    </div>



    <?php if ($current_semester): ?>
        <div class="modal fade" id="enrollmentModal" tabindex="-1" aria-labelledby="enrollmentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="enrollmentModalLabel">Inscribirse en Asignaturas para el Semestre:
                            <?php echo htmlspecialchars($current_semester['numero_semestre'] . 'º Semestre - ' . $current_semester['nombre_anio']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="enrollmentForm">
                        <div class="modal-body" id="modalContentContainer">


                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p>Cargando asignaturas disponibles...</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary" id="enrollSubmitBtn">Enviar Solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center mt-4" role="alert">
            No hay un semestre académico activo para la inscripción en este momento.
        </div>
    <?php endif; ?>

<?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
</div>

<?php include_once '../includes/footer.php'; ?>

<script>
    // Variables globales que no dependen del contenido del modal
    const flashMessages = <?php echo json_encode($flash_messages ?? []); ?>; // Asegura que flash_messages siempre esté definido
    const enrollmentModal = document.getElementById('enrollmentModal'); // El modal principal
    const enrollmentForm = document.getElementById('enrollmentForm'); // El formulario del modal
    const submitEnrollmentBtn = document.getElementById('enrollSubmitBtn'); // Botón de envío del formulario del modal

    // Estas variables se redefinirán CADA VEZ que el contenido del modal se cargue vía AJAX
    let modalCheckboxes;
    let modalSelectedCountSpan;
    let reprobadasObligatorias; // Checkboxes para asignaturas reprobadas
    let availableAsignaturasList; // Contenedor para asignaturas disponibles

    // Variables para elementos de filtro (también se redefinirán)
    let filterCourse;
    let filterSemester;
    let filterSearch;

    // Nuevo elemento para el mensaje de "sin resultados"
    let noResultsMessage;

    // --- Funciones de Ayuda ---

    // Función para mostrar un Toast de Bootstrap (sin cambios)
    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            console.error('No se encontró el contenedor de toasts. Asegúrate de que el div.toast-container exista en el HTML.');
            return;
        }
        const toastId = 'toast-' + Date.now();

        let bgColor = '';
        switch (type) {
            case 'success': bgColor = 'bg-success'; break;
            case 'danger': bgColor = 'bg-danger'; break;
            case 'warning': bgColor = 'bg-warning text-dark'; break;
            case 'info': bgColor = 'bg-info'; break;
            default: bgColor = 'bg-secondary'; break;
        }

        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    // --- Lógica del Modal y sus Filtros ---

    // Esta función se llamará CADA VEZ que el contenido del modal se cargue vía AJAX
    function initializeModalContent() {
        // 1. Volver a obtener las referencias a los elementos dentro del modal, ya que su contenido es nuevo
        modalCheckboxes = document.querySelectorAll('#modalContentContainer input[name="selected_asignaturas[]"]');
        modalSelectedCountSpan = document.getElementById('modalSelectedCount');
        // Selector ajustado para obtener solo los inputs de tipo checkbox dentro de las etiquetas con la clase reprobada-obligatoria
        reprobadasObligatorias = document.querySelectorAll('.reprobada-obligatoria input[type="checkbox"]');
        availableAsignaturasList = document.getElementById('availableAsignaturasList');

        // 2. Referencias a los elementos de filtro que también se cargan con AJAX
        filterCourse = document.getElementById('filterCourse');
        filterSemester = document.getElementById('filterSemester');
        filterSearch = document.getElementById('filterSearch');

        // 3. Crear o re-seleccionar el elemento del mensaje de "sin resultados" si no existe
        noResultsMessage = document.getElementById('noResultsFilterMessage');
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('p');
            noResultsMessage.id = 'noResultsFilterMessage';
            noResultsMessage.classList.add('alert', 'alert-info', 'text-center', 'mt-3');
            noResultsMessage.style.display = 'none'; // Oculto por defecto
            if (availableAsignaturasList) {
                // Insertar después de availableAsignaturasList o su padre si está disponible
                availableAsignaturasList.parentNode.insertBefore(noResultsMessage, availableAsignaturasList.nextSibling);
            }
        }

        // 4. Adjuntar event listeners a los checkboxes del modal
        modalCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateModalSelectedCount);
        });

        // 5. Adjuntar event listeners para los filtros
        if (filterCourse) filterCourse.addEventListener('change', filterAsignaturas);
        if (filterSemester) filterSemester.addEventListener('change', filterAsignaturas);
        if (filterSearch) filterSearch.addEventListener('keyup', filterAsignaturas);

        // 6. Llamar a updateModalSelectedCount y filterAsignaturas para el estado inicial
        updateModalSelectedCount();
        filterAsignaturas(); // Aplicar filtros al cargar el contenido del modal

        // 7. Reinicializar tooltips de Bootstrap para el contenido recién cargado
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    }

    function updateModalSelectedCount() {
        let count = 0;
        let allReprobadasSelected = true;

        // Contar asignaturas reprobadas obligatorias (ya marcadas y deshabilitadas)
        reprobadasObligatorias.forEach(checkbox => {
            if (!checkbox.checked) {
                allReprobadasSelected = false; // Teóricamente no debería ocurrir si ya está marcado y deshabilitado
            }
            count++;
        });

        // Contar asignaturas normales seleccionadas que NO están deshabilitadas
        // Es importante seleccionar solo aquellas dentro del contenedor de asignaturas disponibles.
        if (availableAsignaturasList) {
            availableAsignaturasList.querySelectorAll('input[name="selected_asignaturas[]"]:not(.reprobada-obligatoria)').forEach(checkbox => {
                if (checkbox.checked && !checkbox.disabled) { // Solo contar si está marcado y no deshabilitado
                    count++;
                }
            });
        }

        if (modalSelectedCountSpan) { // Asegurarse de que el span exista antes de actualizarlo
            modalSelectedCountSpan.innerText = `Asignaturas seleccionadas: ${count}`;

            // Habilitar/deshabilitar el botón de envío
            // *** Se agregó la comprobación para submitEnrollmentBtn aquí ***
            if (!allReprobadasSelected || count === 0 || count > 6) {
                if (submitEnrollmentBtn) {
                    submitEnrollmentBtn.setAttribute('disabled', 'disabled');
                }
                modalSelectedCountSpan.classList.add('text-danger');
                if (count > 6) {
                    modalSelectedCountSpan.innerText += ' (Máximo 6 asignaturas permitidas)';
                } else if (count === 0 && reprobadasObligatorias.length === 0) {
                    modalSelectedCountSpan.innerText = 'Por favor, selecciona al menos una asignatura.';
                } else if (!allReprobadasSelected) {
                    modalSelectedCountSpan.innerText = 'Debes seleccionar las asignaturas reprobadas obligatorias.';
                }
            } else {
                if (submitEnrollmentBtn) {
                    submitEnrollmentBtn.removeAttribute('disabled');
                }
                modalSelectedCountSpan.classList.remove('text-danger');
            }
        }
    }


    // Función para manejar el envío del formulario del modal
    // Esta función debe asegurar que las asignaturas reprobadas deshabilitadas sean incluidas


    if (enrollmentForm) {
        enrollmentForm.addEventListener('submit', async function (event) { // Marcamos la función como 'async'
            event.preventDefault(); // Detiene el envío predeterminado del formulario

            console.log("--- Inicio del evento de envío del formulario (AJAX Fetch) ---");

            // Deshabilitar el botón de envío y mostrar un spinner
            if (submitEnrollmentBtn) {
                submitEnrollmentBtn.setAttribute('disabled', 'disabled');
                submitEnrollmentBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
            }

            try {
                // Un Set para almacenar los IDs de las asignaturas seleccionadas, evitando duplicados
                let selectedValues = new Set();

                // Recopila todas las casillas de verificación MARCADO del formulario del modal
                enrollmentForm.querySelectorAll('input[name="selected_asignaturas[]"]:checked').forEach(checkbox => {
                    selectedValues.add(checkbox.value);
                });

                // Asegúrate de que TODAS las asignaturas reprobadas obligatorias se incluyan.
                // Sus checkboxes están deshabilitados, lo que podría impedir que se recolecten
                // automáticamente por FormData si no están explícitamente marcadas.
                // Las añadimos al Set para garantizar que siempre se incluyan.
                reprobadasObligatorias.forEach(checkbox => {
                    selectedValues.add(checkbox.value);
                });

                console.log("Valores seleccionados recopilados (Set):", Array.from(selectedValues));

                // --- Validaciones del lado del cliente ---
                const finalCount = selectedValues.size;

                let allReprobadasIncluded = true;
                // Verifica que todas las asignaturas reprobadas obligatorias estén en el set final
                reprobadasObligatorias.forEach(checkbox => {
                    if (!selectedValues.has(checkbox.value)) {
                        allReprobadasIncluded = false;
                    }
                });

                if (!allReprobadasIncluded) {
                    showToast('danger', 'Debes seleccionar todas las asignaturas reprobadas obligatorias.');
                    console.warn("Fallo de validación: Faltan asignaturas reprobadas obligatorias.");
                    return; // Detener el envío
                }

                if (finalCount === 0) {
                    showToast('danger', 'Por favor, selecciona al menos una asignatura para inscribirte.');
                    console.warn("Fallo de validación: No se ha seleccionado ninguna asignatura.");
                    return; // Detener el envío
                }

                if (finalCount > 6) {
                    showToast('danger', 'No puedes inscribirte en más de 6 asignaturas por semestre (incluyendo las reprobadas).');
                    console.warn("Fallo de validación: Demasiadas asignaturas seleccionadas.");
                    return; // Detener el envío
                }

                console.log("Validaciones del lado del cliente pasadas. Construyendo FormData para el envío.");

                // Crear un nuevo objeto FormData para los datos a enviar
                const formData = new FormData();


                // Añadir cada ID seleccionado al FormData.
                // FormData construirá los parámetros como 'selected_asignaturas[]='
                Array.from(selectedValues).forEach(value => {
                    formData.append('selected_asignaturas[]', value);
                    console.log(`Añadiendo a FormData para envío: selected_asignaturas[] = ${value}`);
                });

                // Enviar la solicitud usando Fetch API
                const response = await fetch('../api/inscripciones.php', { // Usamos enrollmentForm.action para la URL
                    method: 'POST',
                    body: formData // FormData se encarga de establecer el Content-Type correctamente
                });

                // Verificar si la respuesta HTTP fue exitosa (estado 2xx)
                if (!response.ok) {
                    // Leer el texto del error si no es un JSON válido
                    const errorText = await response.text();
                    console.error("Error en la respuesta HTTP:", errorText);
                    throw new Error(`Error HTTP: ${response.status} - ${response.statusText}. Respuesta: ${errorText.substring(0, 100)}...`);
                }

                // Intentar parsear la respuesta como JSON
                const result = await response.json();

                console.log("Respuesta del servidor:", result);

                if (result.success) {
                    showToast('success', result.message);
                    // Cerrar el modal
                    const modal = bootstrap.Modal.getInstance(enrollmentModal);
                    if (modal) modal.hide();
                    // Recargar la página para mostrar las inscripciones actualizadas
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000); // Pequeño retraso para que el toast sea visible
                } else {
                    showToast('danger', result.message);
                }

            } catch (error) {
                // Captura errores de red, errores al parsear JSON, o errores lanzados explícitamente
                console.error("Error durante el envío del formulario con Fetch:", error);
                showToast('danger', `Ocurrió un error inesperado al procesar la solicitud. Por favor, inténtalo de nuevo. Detalle: ${error.message}`);
            } finally {
                // Siempre volver a habilitar el botón y restaurar su texto original
                if (submitEnrollmentBtn) {
                    submitEnrollmentBtn.removeAttribute('disabled');
                    submitEnrollmentBtn.innerHTML = 'Enviar Solicitud';
                }
            }
        });
    }


    // Función para manejar el filtrado de asignaturas dentro del modal
    function filterAsignaturas() {
        if (!filterCourse || !filterSemester || !filterSearch || !availableAsignaturasList || !noResultsMessage) {
            console.warn('Elementos de filtro o lista de asignaturas no encontrados. Es posible que el contenido AJAX aún no se haya cargado completamente.');
            return; // Salir si los elementos aún no están disponibles
        }

        const selectedCourse = filterCourse.value.toLowerCase();
        const selectedSemester = filterSemester.value.toLowerCase();
        const searchTerm = filterSearch.value.toLowerCase();

        let visibleCount = 0;
        const asignaturaItems = availableAsignaturasList.querySelectorAll('.asignatura-item');

        asignaturaItems.forEach(item => {
            const courseName = item.dataset.course.toLowerCase();
            const semesterNumber = item.dataset.semester.toLowerCase();
            const asignaturaName = item.dataset.name.toLowerCase();

            const courseMatch = selectedCourse === '' || courseName.includes(selectedCourse);
            const semesterMatch = selectedSemester === '' || semesterNumber.includes(selectedSemester);
            const searchMatch = searchTerm === '' || asignaturaName.includes(searchTerm);

            if (courseMatch && semesterMatch && searchMatch) {
                item.style.display = 'flex'; // O 'block' si tu diseño es así, pero 'flex' es común con list-group-item
                visibleCount++; // Incrementa el contador si el elemento es visible
            } else {
                item.style.display = 'none';
            }
        });

        // Mostrar u ocultar el mensaje de "sin resultados"
        if (visibleCount === 0 && asignaturaItems.length > 0) {
            noResultsMessage.textContent = 'No se encontraron asignaturas que coincidan con los filtros aplicados.';
            noResultsMessage.style.display = 'block';
        } else {
            noResultsMessage.style.display = 'none';
        }
    }

    // --- Event Listeners Globales ---

    document.addEventListener('DOMContentLoaded', function () {
        // Manejar mensajes flash
        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });

        // Inicializar tooltips para elementos presentes en la carga inicial de la página
        var tooltipTriggerListInitial = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipListInitial = tooltipTriggerListInitial.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });

    // Event listener para cuando el modal está a punto de mostrarse (evento de Bootstrap)
    if (enrollmentModal) {
        enrollmentModal.addEventListener('show.bs.modal', function (event) {
            const modalContentContainer = document.getElementById('modalContentContainer');
            // Mostrar un spinner o mensaje de "cargando"
            modalContentContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p>Cargando asignaturas disponibles...</p></div>';

            // Realizar la solicitud AJAX para cargar el contenido del modal
            fetch('../api/modal_inscripciones_asignaturas.php') // Asegúrate de que esta ruta sea correcta
                .then(response => {
                    if (!response.ok) {
                        throw new Error('La respuesta de la red no fue correcta ' + response.status + ' ' + response.statusText);
                    }
                    return response.text();
                })
                .then(html => {
                    // Insertar el HTML recibido en el cuerpo del modal
                    modalContentContainer.innerHTML = html;

                    // IMPORTANTE: Después de cargar el contenido, inicializar todos los manejadores de eventos
                    // y actualizar las referencias a los elementos del DOM.
                    initializeModalContent();
                })
                .catch(error => {
                    console.error('Error al cargar las asignaturas:', error);
                    modalContentContainer.innerHTML = '<div class="alert alert-danger">Error al cargar las asignaturas. Por favor, inténtalo de nuevo más tarde.</div>';
                });
        });
    }

</script>