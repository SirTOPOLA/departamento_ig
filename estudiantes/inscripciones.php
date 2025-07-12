<?php

require_once '../includes/functions.php';
// Asegura que el usuario logueado sea un Estudiante
check_login_and_role('Estudiante');

require_once '../config/database.php';

// --- Lógica de Procesamiento POST para Inscripción (DEBE ESTAR ANTES DE CUALQUIER SALIDA HTML) ---
// Obtener el id_estudiante del estudiante y el ID del CURSO en el que está ACTUALMENTE para el AÑO ACADÉMICO ACTUAL
$stmt_student_details = $pdo->prepare("
    SELECT
        e.id AS id_estudiante,
        ce.id_curso AS id_curso_actual_estudiante,
        s.id AS id_semestre_actual_estudiante_en_curso,
        s.numero_semestre AS numero_semestre_actual_estudiante_en_curso
    FROM
        estudiantes e
    JOIN
        curso_estudiante ce ON e.id = ce.id_estudiante
    JOIN
        anios_academicos aa ON ce.id_anio = aa.id
    JOIN
        semestres s ON aa.id = s.id_anio_academico AND s.id_curso_asociado_al_semestre = ce.id_curso
    WHERE
        e.id_usuario = :id_usuario
        AND aa.nombre_anio = (SELECT nombre_anio FROM anios_academicos ORDER BY id DESC LIMIT 1) -- Asume que el último año es el actual
        AND CURDATE() BETWEEN s.fecha_inicio AND s.fecha_fin
    ORDER BY s.numero_semestre DESC -- En caso de que múltiples semestres estén activos para el mismo año/curso (improbable, pero para robustez)
    LIMIT 1
");
$stmt_student_details->bindParam(':id_usuario', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt_student_details->execute();
$student_context = $stmt_student_details->fetch(PDO::FETCH_ASSOC);
 

if (!$student_context) {
    set_flash_message('danger', 'Error: No se encontró el contexto académico actual para su perfil de estudiante. Contacte a la administración.');
    header('Location: ../logout.php'); // Redirige a la página de cierre de sesión o a una página de error
    exit;
}
$id_estudiante_actual = $student_context['id_estudiante'];
$id_curso_actual_estudiante = $student_context['id_curso_actual_estudiante']; // ID del Curso ACTUAL del estudiante (ej. 'primero')
$id_semestre_actual_en_curso = $student_context['id_semestre_actual_estudiante_en_curso']; // ID del semestre actual asociado al curso actual del estudiante
$numero_semestre_actual_en_curso = $student_context['numero_semestre_actual_estudiante_en_curso']; // Número del semestre actual (ej. 1, 2)

// Obtener el semestre actual real (esta función debe devolver el semestre actual verdadero independientemente del curso del estudiante)
// Esto es para el período de inscripción general, no necesariamente lo que se espera que el estudiante esté cursando.
$current_semester = get_current_semester($pdo);

// Si get_current_semester no encuentra uno para el contexto del estudiante, priorizar el semestre actual real del estudiante
if (!$current_semester && $id_semestre_actual_en_curso) {
    $stmt_specific_semester = $pdo->prepare("SELECT * FROM semestres WHERE id = :id_semestre");
    $stmt_specific_semester->bindParam(':id_semestre', $id_semestre_actual_en_curso, PDO::PARAM_INT);
    $stmt_specific_semester->execute();
    $current_semester = $stmt_specific_semester->fetch(PDO::FETCH_ASSOC);
}

// Verificar si se pudo determinar un semestre
if (!$current_semester) {
    set_flash_message('danger', 'Error: No se pudo determinar el semestre académico actual. Contacte a la administración.');
    // header('Location: ../dashboard.php'); // Redirige a una página segura
    // exit;
}


// --- Obtener datos para la vista del estudiante ---
$page_title = "Inscripción Semestral"; // Título ajustado
include_once '../includes/header.php'; // Incluye el encabezado aquí, después de toda la lógica POST y las redirecciones

$flash_messages = get_flash_messages();


// Asignaturas en las que el estudiante ya está inscrito para el semestre actual (pendientes o confirmadas)
$current_enrollments = [];
if ($current_semester) {
    $stmt_current_enrollments = $pdo->prepare("
        SELECT
            ie.id AS id_inscripcion,
            ie.id_asignatura,
            a.nombre_asignatura,
            a.creditos,
            ie.id_semestre,
            s.numero_semestre AS semestre_actual_numero,
            s.id AS id_semestre_id, -- Renombrado para evitar conflicto con `id_semestre` en ie.
            aa.nombre_anio,
            c.nombre_curso,
            a.semestre_recomendado AS numero_semestre_asignatura,
            ie.confirmada,
            ga.id AS id_grupo_asignatura, -- Añadir esto
            ga.grupo, -- Añadir esto
            ga.turno AS grupo_turno, -- Añadir esto
            u.nombre_completo AS nombre_profesor,
            GROUP_CONCAT(
                DISTINCT CONCAT(h.dia_semana, ' (', SUBSTRING(h.hora_inicio, 1, 5), '-', SUBSTRING(h.hora_fin, 1, 5), ') @ ', au.nombre_aula)
                ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'), h.hora_inicio
                SEPARATOR '; '
            ) AS horarios_info
        FROM inscripciones_estudiantes ie
        JOIN asignaturas a ON ie.id_asignatura = a.id
        JOIN semestres s ON ie.id_semestre = s.id
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        LEFT JOIN grupos_asignaturas ga ON ie.id_grupo_asignatura = ga.id
        LEFT JOIN cursos c ON a.id_curso = c.id
        LEFT JOIN profesores p ON ga.id_profesor = p.id
        LEFT JOIN usuarios u ON p.id_usuario = u.id
        LEFT JOIN horarios h ON h.id_grupo_asignatura = ga.id AND h.id_semestre = s.id
        LEFT JOIN aulas au ON h.id_aula = au.id
        WHERE ie.id_estudiante = :id_estudiante
          AND ie.id_semestre = :id_semestre_actual_inscripcion
        GROUP BY ie.id -- Agrupar por el ID de inscripción para evitar duplicación
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");

    $stmt_current_enrollments->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_current_enrollments->bindParam(':id_semestre_actual_inscripcion', $current_semester['id'], PDO::PARAM_INT);
    $stmt_current_enrollments->execute();
    $current_enrollments = $stmt_current_enrollments->fetchAll(PDO::FETCH_ASSOC);
}


// Asignaturas aprobadas por el estudiante (para verificación de prerrequisitos)
$approved_asignaturas_ids = [];
$stmt_approved_asignaturas = $pdo->prepare("
    SELECT id_asignatura FROM historial_academico
    WHERE id_estudiante = :id_estudiante AND estado_final = 'APROBADO'
");
$stmt_approved_asignaturas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_approved_asignaturas->execute();
$approved_asignaturas_ids = $stmt_approved_asignaturas->fetchAll(PDO::FETCH_COLUMN);

// Asignaturas reprobadas por el estudiante (obligatorias de retomar)
$reproved_asignaturas = [];
$reproved_asignaturas_ids = [];
if ($current_semester) { // Solo si hay un semestre activo para la inscripción
    $stmt_reproved_asignaturas = $pdo->prepare("
        SELECT
            ha.id_asignatura AS id,
            a.nombre_asignatura,
            a.creditos,
            c.nombre_curso,
            s.id AS id_semestre,
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


// Asignaturas disponibles para inscripción para el curso actual del estudiante y *semestres anteriores*
$available_asignaturas_for_modal = [];
if ($current_semester) {
    // Ahora obtenemos todas las asignaturas potenciales para el curso actual del estudiante (de curso_estudiante)
    // hasta su semestre actual en ese curso, excluyendo las aprobadas o las que ya están inscritas.
    $stmt_available_asignaturas = $pdo->prepare("
        SELECT
            a.id,
            a.nombre_asignatura,
            a.creditos,
            a.id_prerequisito,
            pa.nombre_asignatura AS prerequisito_nombre,
            c.nombre_curso,
            a.semestre_recomendado
        FROM
            asignaturas a
        LEFT JOIN asignaturas pa ON a.id_prerequisito = pa.id
        JOIN cursos c ON a.id_curso = c.id
        WHERE
            a.id_curso = :id_curso_estudiante
            AND a.semestre_recomendado <= :numero_semestre_actual_estudiante_en_curso -- Mostrar solo asignaturas hasta el semestre recomendado actual del estudiante
            AND a.id NOT IN (
                SELECT id_asignatura FROM historial_academico WHERE id_estudiante = :id_estudiante_historial_aprobado AND estado_final = 'APROBADO'
            )
            AND a.id NOT IN (
                SELECT id_asignatura FROM inscripciones_estudiantes WHERE id_estudiante = :id_estudiante_enrolled AND id_semestre = :id_semestre_enrolled
            )
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");

    $stmt_available_asignaturas->bindParam(':id_curso_estudiante', $id_curso_actual_estudiante, PDO::PARAM_INT);
    $stmt_available_asignaturas->bindParam(':numero_semestre_actual_estudiante_en_curso', $numero_semestre_actual_en_curso, PDO::PARAM_INT);
    $stmt_available_asignaturas->bindParam(':id_estudiante_historial_aprobado', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_available_asignaturas->bindParam(':id_estudiante_enrolled', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_available_asignaturas->bindParam(':id_semestre_enrolled', $current_semester['id'], PDO::PARAM_INT); // Este es el ID del semestre de inscripción real

    $stmt_available_asignaturas->execute();
    $available_asignaturas_for_modal = $stmt_available_asignaturas->fetchAll(PDO::FETCH_ASSOC);

    // Fusionar asignaturas reprobadas en la lista de disponibles, asegurando que aparezcan
    foreach ($reproved_asignaturas as $reproved) {
        $found = false;
        foreach ($available_asignaturas_for_modal as $available) {
            if ($available['id'] == $reproved['id']) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $available_asignaturas_for_modal[] = $reproved;
        }
    }
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
                                <th>Acción</th>
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
                                        <td>
                                            <?php if (empty($enrollment['id_horario'])): ?>
                                                <button type="button" class="btn btn-sm btn-info select-horario-btn" data-bs-toggle="modal"
                                                    data-bs-target="#selectHorarioModal"
                                                    data-estudiante-id="<?= (int) ($id_estudiante_actual ?? 0) ?>"
                                                    data-inscripcion-id="<?= (int) ($enrollment['id_inscripcion'] ?? 0) ?>"
                                                    data-enrollment-id="<?= (int) ($enrollment['id'] ?? 0) ?>"
                                                    data-subject-id="<?= (int) ($enrollment['id_asignatura'] ?? 0) ?>"
                                                    data-semestre-id="<?= (int) ($enrollment['id_semestre'] ?? 0) ?>"
                                                    id="select-btn-<?= (int) ($enrollment['id'] ?? 0) ?>">
                                                    <i class="fas fa-clock me-1"></i> Elegir Turno
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted" id="select-btn-<?= (int) ($enrollment['id'] ?? 0) ?>">Turno
                                                    Asignado</span>
                                                <div id="horario-info-<?= (int) ($enrollment['id'] ?? 0) ?>" style="display:block;">
                                                    <small class="text-muted">
                                                        Profesor: <?= htmlspecialchars($enrollment['nombre_profesor'] ?? '-') ?><br>
                                                        Turno: <?= htmlspecialchars($enrollment['turno'] ?? '-') ?>
                                                        (<?= htmlspecialchars(substr($enrollment['hora_inicio'] ?? '', 0, 5)) ?> -
                                                        <?= htmlspecialchars(substr($enrollment['hora_fin'] ?? '', 0, 5)) ?>)<br>
                                                        Día: <?= htmlspecialchars($enrollment['dia_semana'] ?? '-') ?><br>
                                                        Aula: <?= htmlspecialchars($enrollment['nombre_aula'] ?? '-') ?>
                                                    </small>
                                                </div>
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
                            <span id="modalSelectedCount" class="me-auto text-muted">Asignaturas seleccionadas: 0</span>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary" id="enrollSubmitBtn" disabled>Enviar
                                Solicitud</button>
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




<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
</div>
<div class="modal fade" id="selectHorarioModal" tabindex="-1" aria-labelledby="selectHorarioModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="selectHorarioModalLabel"><i class="fas fa-calendar-alt me-2"></i> Elegir
                    Turno y Profesor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-enrollment-id">

                <div id="horarios-list-container">
                    <p class="text-muted text-center">Selecciona una asignatura para ver los horarios disponibles.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- Contenedor de Toasts -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1055">
    <div id="toast-container"></div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
  

        // Función para mostrar alertas de forma consistente en el modal
        function mostrarAlerta(mensaje, tipo = 'danger') {
            const contenedorAlerta = document.querySelector('.modal-body');

            // Elimina cualquier alerta existente antes de mostrar una nueva
            const alertaExistente = contenedorAlerta.querySelector('.alert');
            if (alertaExistente) {
                alertaExistente.remove();
            }

            // Crea una nueva alerta
            const nuevaAlerta = document.createElement('div');
            nuevaAlerta.className = `alert alert-${tipo} alert-dismissible fade show mt-3`;
            nuevaAlerta.setAttribute('role', 'alert');
            nuevaAlerta.innerHTML = `
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        `;
            contenedorAlerta.prepend(nuevaAlerta); // Inserta la alerta al inicio del modal
            setTimeout(() => nuevaAlerta.remove(), 5000); // Elimina la alerta después de 5 segundos
        }

        // Cuando se hace clic en "Elegir Turno/Profesor"
        // Cuando se hace clic en "Elegir Turno/Profesor"
        document.querySelectorAll('.select-horario-btn').forEach(boton => {
            boton.addEventListener('click', function () {
                const idInscripcion = this.dataset.inscripcionId;
                const idAsignatura = this.dataset.subjectId;
                const idSemestre = this.dataset.semestreId;
                const idEstudiante = this.dataset.estudianteId;

                console.log('idInscripcion: '+idInscripcion)
                console.log('estudiante: '+idEstudiante)
                console.log('semestre: '+idSemestre)
                console.log('Asignatura: '+idAsignatura)
                // Guarda el ID de la inscripción en un campo oculto del modal
                document.getElementById('modal-enrollment-id').value = idInscripcion;

                // Contenedor de grupos
                const contenedorGrupos = document.getElementById('horarios-list-container');
                contenedorGrupos.innerHTML = '<p class="text-muted text-center"><i class="fas fa-spinner fa-spin me-2"></i> Cargando grupos disponibles...</p>';

                // Elimina alertas anteriores
                const alertaAnterior = contenedorGrupos.closest('.modal-body').querySelector('.alert');
                if (alertaAnterior) alertaAnterior.remove();

                // Preparar datos POST
                const formData = new FormData();
                formData.append('id_asignatura', idAsignatura);
                formData.append('id_semestre', idSemestre);
                formData.append('id_estudiante', idEstudiante);

                // Fetch al nuevo endpoint
                fetch('../api/obtener_grupos_por_asignatura.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        console.log(data)
                        if (data.success) {
                            if (data.grupos.length > 0) {
                                // Construir tabla de grupos disponibles
                                let html = `
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Profesor</th>
                                        <th>Turno</th>
                                        <th>Aula</th>
                                        <th>Capacidad</th>
                                        <th>Inscritos</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                                data.grupos.forEach(grupo => {
                                    html += `
                            <tr>
                                <td>${grupo.nombre_profesor}</td>
                                <td>${grupo.turno}</td>
                                <td>${grupo.nombre_aula}</td>
                                <td>${grupo.capacidad}</td>
                                <td>${grupo.inscritos}</td>
                                <td>
                                    <button class="btn btn-sm btn-success choose-grupo-btn"
                                            data-grupo-id="${grupo.id_grupo}"
                                            data-profesor="${grupo.nombre_profesor}"
                                            data-turno="${grupo.turno}"
                                            data-aula="${grupo.nombre_aula}">
                                        <i class="fas fa-check-circle me-1"></i> Elegir
                                    </button>
                                </td>
                            </tr>
                        `;
                                });

                                html += '</tbody></table></div>';
                                contenedorGrupos.innerHTML = html;

                                // Aquí puedes agregar evento .choose-grupo-btn si deseas procesar selección
                            } else {
                                contenedorGrupos.innerHTML = `
                        <div class="alert alert-info text-center" role="alert">
                            No hay grupos disponibles para esta asignatura.
                        </div>`;
                            }
                        } else {
                            contenedorGrupos.innerHTML = `
                    <div class="alert alert-danger text-center" role="alert">
                        Error al cargar los grupos: ${data.message}
                    </div>`;
                        }
                    })
                    .catch(error => {
                        console.error("Error en Fetch:", error);
                        contenedorGrupos.innerHTML = `
                <div class="alert alert-danger text-center" role="alert">
                    Error de conexión al cargar grupos. Intente de nuevo.
                </div>`;
                    });
            });
        });


        // Delegar evento porque los botones se cargan dinámicamente
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('choose-grupo-btn')) {
                const boton = e.target;
                const grupoId = boton.dataset.grupoId;
                const idInscripcion = document.getElementById('modal-enrollment-id').value;

                if (!idInscripcion || !grupoId) {
                    alert('Faltan datos para asignar el grupo.');
                    return;
                }

                boton.disabled = true;
                boton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Asignando...';

                const formData = new FormData();
                formData.append('id_inscripcion', idInscripcion);
                formData.append('id_grupo', grupoId);

                console.log('idgrupo: '+grupoId)
                console.log('idInscripcion: '+idInscripcion)

                fetch('../api/asignar_grupo_a_inscripcion.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            boton.innerHTML = '<i class="fas fa-check-circle me-1"></i> Asignado';
                            boton.classList.remove('btn-success');
                            boton.classList.add('btn-secondary');
                            boton.disabled = true;

                            mostrarToast('Grupo asignado correctamente.', 'success');

                            setTimeout(() => {
                                const modal = bootstrap.Modal.getInstance(document.getElementById('modal-horarios'));
                                modal.hide();
                                location.reload();
                            }, 1500);

                        } else {
                            mostrarToast(data.message || 'No se pudo asignar el grupo.', 'error');

                            boton.innerHTML = '<i class="fas fa-times-circle me-1"></i> Error';
                            boton.classList.remove('btn-success');
                            boton.classList.add('btn-danger');
                            setTimeout(() => {
                                boton.innerHTML = '<i class="fas fa-check-circle me-1"></i> Elegir';
                                boton.classList.remove('btn-danger');
                                boton.classList.add('btn-success');
                                boton.disabled = false;
                            }, 2000);
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        mostrarToast('Error de red. Verifica tu conexión o intenta más tarde.', 'error');

                        boton.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Fallo';
                        boton.classList.add('btn-danger');
                        setTimeout(() => {
                            boton.innerHTML = '<i class="fas fa-check-circle me-1"></i> Elegir';
                            boton.classList.remove('btn-danger');
                            boton.classList.add('btn-success');
                            boton.disabled = false;
                        }, 2000);
                    });

            }
        });





        // Delegación de evento: cuando se hace clic en "Elegir" dentro del modal
        document.getElementById('horarios-list-container').addEventListener('click', function (event) {


            if (event.target.classList.contains('choose-horario-btn') || event.target.closest('.choose-horario-btn')) {
                console.log('lo he dado')
                const botonElegir = event.target.closest('.choose-horario-btn');
                const idHorario = botonElegir.dataset.horarioId;
                const idInscripcion = document.getElementById('modal-enrollment-id').value;

                console.log('idHorario: ' + idHorario)
                console.log('idInscripcion: ' + idInscripcion)
                // Detalles para actualizar la UI
                const nombreProfesor = botonElegir.dataset.nombreProfesor;
                const turno = botonElegir.dataset.turno;
                const horaInicio = botonElegir.dataset.horaInicio;
                const horaFin = botonElegir.dataset.horaFin;
                const diaSemana = botonElegir.dataset.diaSemana;
                const nombreAula = botonElegir.dataset.nombreAula;

                // Validación básica
                if (!idHorario || !idInscripcion) {
                    mostrarAlerta('Error: Datos incompletos para seleccionar el horario.');
                    return;
                }

                // Desactiva el botón mientras se procesa
                botonElegir.disabled = true;
                botonElegir.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Cargando...';

                // Prepara datos para enviar al servidor
                const formData = new FormData();
                formData.append('id_enrollment', idInscripcion);
                formData.append('id_horario', idHorario);

                // Enviar petición POST para guardar el horario seleccionado
                fetch('../api/actualizar_horario_inscripcion.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Error HTTP: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            mostrarAlerta('Horario seleccionado exitosamente.', 'success');

                            // 1. Cambiar el estado de la inscripción
                            const estadoBadge = document.getElementById(`status-${idInscripcion}`);
                            if (estadoBadge) {
                                estadoBadge.classList.remove('bg-warning', 'text-dark');
                                estadoBadge.classList.add('bg-success');
                                estadoBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirmada';
                            }

                            // 2. Reemplaza el botón por información del turno asignado
                            const contenedorBoton = document.getElementById(`select-btn-${idInscripcion}`).parentNode;
                            if (contenedorBoton) {
                                contenedorBoton.innerHTML = `
                                <span class="text-muted">Turno Asignado</span>
                                <div id="horario-info-${idInscripcion}" style="display:block;">
                                    <small class="text-muted">
                                        Profesor: ${nombreProfesor}<br>
                                        Turno: ${turno} (${horaInicio.substring(0, 5)} - ${horaFin.substring(0, 5)})<br>
                                        Día: ${diaSemana}<br>
                                        Aula: ${nombreAula}
                                    </small>
                                </div>
                            `;
                            }

                            // Cierra el modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('selectHorarioModal'));
                            if (modal) {
                                modal.hide();
                            }

                        } else {
                            mostrarAlerta(`Error al seleccionar el horario: ${data.message}`);
                        }
                    })
                    .catch(error => {
                        console.error("Error en Fetch:", error);
                        mostrarAlerta('Error de conexión al seleccionar el horario. Por favor, intente de nuevo.');
                    })
                    .finally(() => {
                        // Rehabilita el botón si hubo error (si fue éxito el modal ya se cerró)
                        botonElegir.disabled = false;
                        botonElegir.innerHTML = '<i class="fas fa-check-circle me-1"></i> Elegir';
                    });
            }
        });
    });


</script>
<?php include_once '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


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
        console.log("initializeModalContent ejecutado.");

        // 1. Volver a obtener las referencias a los elementos dentro del modal, ya que su contenido es nuevo
        // Asegúrate de que estos selectores apunten a los IDs correctos que *ahora* generará tu PHP.
        modalCheckboxes = document.querySelectorAll('#modalContentContainer input[name="selected_asignaturas[]"]');
        modalSelectedCountSpan = document.getElementById('modalSelectedCount'); // Correct ID for the span
        reprobadasObligatorias = document.querySelectorAll('.reprobada-obligatoria input[type="checkbox"]');
        availableAsignaturasList = document.getElementById('availableAsignaturasList'); // Correct ID for the list container

        // 2. Referencias a los elementos de filtro que también se cargan con AJAX (con los IDs corregidos)
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
            if (availableAsignaturasList && availableAsignaturasList.parentNode) { // Ensure parent exists
                // Insertar después de availableAsignaturasList
                availableAsignaturasList.parentNode.insertBefore(noResultsMessage, availableAsignaturasList.nextSibling);
            }
        }

        // 4. Adjuntar event listeners a los checkboxes del modal
        // Primero, removemos listeners para evitar duplicados si initializeModalContent se llama múltiples veces.
        modalCheckboxes.forEach(checkbox => {
            checkbox.removeEventListener('change', updateModalSelectedCount);
            checkbox.addEventListener('change', updateModalSelectedCount);
        });

        // 5. Adjuntar event listeners para los filtros
        // Similar, removemos antes de añadir para evitar duplicados.
        if (filterCourse) {
            filterCourse.removeEventListener('change', filterAsignaturas);
            filterCourse.addEventListener('change', filterAsignaturas);
        }
        if (filterSemester) {
            filterSemester.removeEventListener('change', filterAsignaturas);
            filterSemester.addEventListener('change', filterAsignaturas);
        }
        if (filterSearch) {
            filterSearch.removeEventListener('keyup', filterAsignaturas);
            filterSearch.addEventListener('keyup', filterAsignaturas);
        }

        // 6. Llamar a updateModalSelectedCount y filterAsignaturas para el estado inicial
        updateModalSelectedCount();
        filterAsignaturas(); // Aplicar filtros al cargar el contenido del modal

        // 7. Reinicializar tooltips de Bootstrap para el contenido recién cargado
        // Asegúrate de que 'bootstrap' esté disponible globalmente (es decir, Bootstrap JS cargado)
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        console.log("Número de checkboxes encontrados:", modalCheckboxes.length);
        console.log("reprobadasObligatorias encontrados:", reprobadasObligatorias.length);
    }

    function updateModalSelectedCount() {
        console.log("updateModalSelectedCount ejecutado.");
        let count = 0;
        let allReprobadasSelected = true;

        // Contar todas las asignaturas seleccionadas (regulares + reprobadas obligatorias)
        if (modalCheckboxes) {
            Array.from(modalCheckboxes).forEach(checkbox => {
                if (checkbox.checked) {
                    count++;
                }
            });
        }

        // Verificar si todas las asignaturas reprobadas obligatorias están seleccionadas
        if (reprobadasObligatorias) {
            reprobadasObligatorias.forEach(checkbox => {
                if (!checkbox.checked) {
                    allReprobadasSelected = false;
                }
            });
        }

        if (modalSelectedCountSpan) {
            let canSubmit = true;
            let message = `Asignaturas seleccionadas: ${count}`;
            let textColorClass = 'text-muted';

            if (!allReprobadasSelected) {
                canSubmit = false;
                message = 'Debes seleccionar todas las asignaturas reprobadas obligatorias.';
                textColorClass = 'text-danger';
            } else if (count === 0) {
                // If count is 0 and all reprobadas are selected (meaning there are no reprobadas),
                // then no subjects are selected, so disable.
                canSubmit = false;
                message = 'Por favor, selecciona al menos una asignatura.';
                textColorClass = 'text-danger';
            } else if (count > 6) {
                canSubmit = false;
                message = `Asignaturas seleccionadas: ${count} (Máximo 6 asignaturas permitidas)`;
                textColorClass = 'text-danger';
            }

            if (submitEnrollmentBtn) {
                if (canSubmit) {
                    submitEnrollmentBtn.removeAttribute('disabled');
                } else {
                    submitEnrollmentBtn.setAttribute('disabled', 'disabled');
                }
            }
            modalSelectedCountSpan.innerText = message;
            // Remove existing text color classes before adding new one
            modalSelectedCountSpan.classList.remove('text-muted', 'text-danger');
            modalSelectedCountSpan.classList.add(textColorClass);
        }

        console.log("Conteo actual de asignaturas seleccionadas:", count);
        console.log("submitEnrollmentBtn estado (habilitado/deshabilitado):", submitEnrollmentBtn ? submitEnrollmentBtn.disabled : 'No encontrado (botón no referencia)');
    }


    // Function to handle the modal form submission
    if (enrollmentForm) {
        enrollmentForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            console.log("--- Inicio del evento de envío del formulario (AJAX Fetch) ---");

            if (submitEnrollmentBtn) {
                submitEnrollmentBtn.setAttribute('disabled', 'disabled');
                submitEnrollmentBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
            }

            try {
                let selectedValues = new Set();

                // Add all currently checked checkboxes
                // modalCheckboxes should already contain both regular and reprobated (if loaded).
                if (modalCheckboxes) {
                    modalCheckboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            selectedValues.add(checkbox.value);
                        }
                    });
                }


                // Explicitly add disabled reprobated subjects to ensure they are always sent,
                // as disabled inputs are not sent by FormData by default.
                // This ensures that even if they are not picked up by the initial `modalCheckboxes` (e.g., if the
                // `name="selected_asignaturas[]"` is only on the hidden input, not the actual checkbox),
                // their values are still included.
                if (reprobadasObligatorias) {
                    reprobadasObligatorias.forEach(checkbox => {
                        if (checkbox.checked && checkbox.disabled) { // Only add if they are checked AND disabled (mandatory)
                            selectedValues.add(checkbox.value);
                        }
                    });
                }


                console.log("Valores seleccionados recopilados (Set):", Array.from(selectedValues));

                // --- Client-side Validations ---
                const finalCount = selectedValues.size;

                let allReprobadasIncluded = true;
                if (reprobadasObligatorias) {
                    reprobadasObligatorias.forEach(checkbox => {
                        // Check if a reprobated checkbox exists and is not checked (if it's not disabled, it should be checked)
                        // If it's disabled, its value should be in selectedValues due to the explicit addition above
                        if (!checkbox.checked && !checkbox.disabled) { // If it's *not* disabled and *not* checked, it's an error.
                            allReprobadasIncluded = false;
                        }
                        // For disabled checkboxes, we ensure they are in the selectedValues set.
                        // The HTML uses <input type="hidden" name="selected_asignaturas[]"> for reprobadas,
                        // and a disabled checkbox. The hidden input guarantees submission.
                        // However, for consistency and explicit validation, we check that their values are in `selectedValues`.
                        if (checkbox.disabled && !selectedValues.has(checkbox.value)) {
                            allReprobadasIncluded = false; // Should not happen with the explicit addition above, but good for validation
                        }
                    });
                }


                if (!allReprobadasIncluded) {
                    showToast('danger', 'Debes seleccionar todas las asignaturas reprobadas obligatorias.');
                    console.warn("Fallo de validación: Faltan asignaturas reprobadas obligatorias.");
                    return;
                }

                if (finalCount === 0) {
                    showToast('danger', 'Por favor, selecciona al menos una asignatura para inscribirte.');
                    console.warn("Fallo de validación: No se ha seleccionado ninguna asignatura.");
                    return;
                }

                if (finalCount > 6) {
                    showToast('danger', 'No puedes inscribirte en más de 6 asignaturas por semestre (incluyendo las reprobadas).');
                    console.warn("Fallo de validación: Demasiadas asignaturas seleccionadas.");
                    return;
                }

                console.log("Validaciones del lado del cliente pasadas. Construyendo FormData para el envío.");

                const formData = new FormData();
                Array.from(selectedValues).forEach(value => {
                    formData.append('selected_asignaturas[]', value);
                    console.log(`Añadiendo a FormData para envío: selected_asignaturas[] = ${value}`);
                });

                // Send the request using Fetch API
                const response = await fetch('../api/inscripciones.php', { // This endpoint will handle the actual enrollment
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error("Error en la respuesta HTTP:", errorText);
                    throw new Error(`Error HTTP: ${response.status} - ${response.statusText}. Respuesta: ${errorText.substring(0, 100)}...`);
                }

                const result = await response.json();

                console.log("Respuesta del servidor:", result);

                if (result.exito) {
                    showToast('success', result.mensaje);
                    const modal = bootstrap.Modal.getInstance(enrollmentModal);
                    if (modal) modal.hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000); // Reload the page after 1 second to show updated data
                } else {
                    showToast('danger', result.mensaje);
                }

            } catch (error) {
                console.error("Error durante el envío del formulario con Fetch:", error);
                showToast('danger', `Ocurrió un error inesperado al procesar la solicitud. Por favor, inténtalo de nuevo. Detalle: ${error.mensaje}`);
            } finally {
                if (submitEnrollmentBtn) {
                    submitEnrollmentBtn.removeAttribute('disabled');
                    submitEnrollmentBtn.innerHTML = 'Enviar Solicitud';
                }
            }
        });
    }

    // Function to handle filtering assignments within the modal
    function filterAsignaturas() {
        // Check if elements are available, they should be after initializeModalContent runs
        if (!filterCourse || !filterSemester || !filterSearch || !availableAsignaturasList || !noResultsMessage) {
            console.warn('Filter elements or assignment list not found. AJAX content may not have loaded yet in filterAsignaturas.');
            return;
        }

        const selectedCourse = filterCourse.value.toLowerCase();
        const selectedSemester = filterSemester.value.toLowerCase();
        const searchTerm = filterSearch.value.toLowerCase();

        let visibleRegularItemsCount = 0;
        const allAsignaturaItems = availableAsignaturasList.querySelectorAll('.asignatura-item');

        // First, handle reprobated items (always visible unless the filter specifically hides them, which we prevent)
        // Note: The HTML structure has reprobated items outside 'availableAsignaturasList'
        //       so we need to query them separately if they are to be included in filtering logic.
        //       However, based on your current HTML, 'reprobada-obligatoria' class is on the LABEL, not on the checkbox directly.
        //       And these are *not* within the '#availableAsignaturasList'.
        //       So, the filter should only apply to items *within* '#availableAsignaturasList'.
        //       Reprobated subjects in the dedicated 'Asignaturas Reprobadas (Obligatorias)' section
        //       are handled separately and are always displayed.

        // Re-get all subject items in the entire modal body that are part of the 'available' list
        // and are not explicitly the 'reprobada-obligatoria' label elements.
        const filterableAsignaturaItems = document.querySelectorAll('#availableAsignaturasList .asignatura-item');

        filterableAsignaturaItems.forEach(item => {
            const courseName = item.dataset.course ? item.dataset.course.toLowerCase() : '';
            const semesterNumber = item.dataset.semester ? item.dataset.semester.toLowerCase() : '';
            const asignaturaName = item.dataset.name ? item.dataset.name.toLowerCase() : '';

            const courseMatch = selectedCourse === '' || courseName.includes(selectedCourse);
            const semesterMatch = selectedSemester === '' || semesterNumber.includes(selectedSemester);
            const searchMatch = searchTerm === '' || asignaturaName.includes(searchTerm);

            if (courseMatch && semesterMatch && searchMatch) {
                item.style.display = 'flex';
                visibleRegularItemsCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Show or hide the "no results" message
        // This message should only appear if the filters hid all *filterable* items.
        // Reprobated mandatory subjects are not filterable in this context (they are always shown).
        if (visibleRegularItemsCount === 0 && filterableAsignaturaItems.length > 0) {
            noResultsMessage.textContent = 'No se encontraron asignaturas que coincidan con los filtros aplicados.';
            noResultsMessage.style.display = 'block';
        } else {
            noResultsMessage.style.display = 'none';
        }
    }

    // --- Global Event Listeners ---

    document.addEventListener('DOMContentLoaded', function () {
        // Show any initial flash messages
        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });

        // Load modal content via AJAX when the modal is shown
        if (enrollmentModal) {
            enrollmentModal.addEventListener('show.bs.modal', async function () {
                const modalBody = document.getElementById('modalContentContainer');
                // Display loading spinner
                modalBody.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p>Cargando asignaturas disponibles...</p>
                    </div>
                `;
                // Ensure the submit button is disabled initially while content loads
                if (submitEnrollmentBtn) {
                    submitEnrollmentBtn.setAttribute('disabled', 'disabled');
                }

                try {
                    // Fetch the actual modal content (filters and subject list)
                    // Ensure these PHP variables are defined in the main PHP page before this script runs.
                    // Example: $id_estudiante_actual, $id_curso_actual_estudiante, etc.
                    const response = await fetch(`../api/modal_inscripciones_asignaturas.php?id_estudiante=<?= $id_estudiante_actual ?>&id_curso_actual_estudiante=<?= $id_curso_actual_estudiante ?>&numero_semestre_actual_estudiante_en_curso=<?= $numero_semestre_actual_en_curso ?>&id_semestre_actual_inscripcion=<?= $current_semester['id'] ?>`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const data = await response.text();
                    modalBody.innerHTML = data; // Insert fetched content

                    initializeModalContent(); // Re-initialize elements and listeners after new content is loaded

                } catch (error) {
                    console.error("Error al cargar el contenido del modal:", error);
                    modalBody.innerHTML = `<div class="alert alert-danger">Error al cargar las asignaturas: ${error.message}. Por favor, intente de nuevo.</div>`;
                    if (submitEnrollmentBtn) {
                        submitEnrollmentBtn.setAttribute('disabled', 'disabled');
                    }
                }
            });
        }
    });

</script>