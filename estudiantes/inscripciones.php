<?php

require_once '../includes/functions.php';
// Asegura que el usuario logueado sea un estudiante
check_login_and_role('Estudiante');

require_once '../config/database.php';

// --- Lógica de Procesamiento POST para la Inscripción (DEBE ESTAR ANTES DE CUALQUIER SALIDA HTML) ---
// Obtener el id_estudiante del estudiante y el ID del CURSO en el que se encuentra ACTUALMENTE para el AÑO ACADÉMICO ACTUAL
$stmt_detalles_estudiante = $pdo->prepare("
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
$stmt_detalles_estudiante->bindParam(':id_usuario', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt_detalles_estudiante->execute();
$contexto_estudiante = $stmt_detalles_estudiante->fetch(PDO::FETCH_ASSOC);

if (!$contexto_estudiante) {
    set_flash_message('danger', 'Error: No se encontró el contexto académico actual para su perfil de estudiante. Contacte a la administración.');
    header('Location: ../logout.php'); // Redirige a cerrar sesión o a una página de error
    exit;
}
$id_estudiante_actual = $contexto_estudiante['id_estudiante'];
$id_curso_actual_estudiante = $contexto_estudiante['id_curso_actual_estudiante']; // ID del curso ACTUAL del estudiante (ej. 'primero')
$id_semestre_actual_en_curso = $contexto_estudiante['id_semestre_actual_estudiante_en_curso']; // ID del semestre actual asociado al curso del estudiante
$numero_semestre_actual_en_curso = $contexto_estudiante['numero_semestre_actual_estudiante_en_curso']; // Número del semestre actual (ej. 1, 2)

// Obtener el semestre actual real (esta función debería devolver el verdadero semestre actual independientemente del curso del estudiante)
// Esto es para el período de inscripción general, no necesariamente lo que se *espera* que el estudiante esté cursando.
$semestre_actual = get_current_semester($pdo);

// Si get_current_semester no encuentra uno para el contexto del estudiante, priorizar el semestre actual real del estudiante
if (!$semestre_actual && $id_semestre_actual_en_curso) {
    $stmt_semestre_especifico = $pdo->prepare("SELECT * FROM semestres WHERE id = :id_semestre");
    $stmt_semestre_especifico->bindParam(':id_semestre', $id_semestre_actual_en_curso, PDO::PARAM_INT);
    $stmt_semestre_especifico->execute();
    $semestre_actual = $stmt_semestre_especifico->fetch(PDO::FETCH_ASSOC);
}

// Verificar si se pudo determinar algún semestre
if (!$semestre_actual) {
    set_flash_message('danger', 'Error: No se pudo determinar el semestre académico actual. Contacte a la administración.');
    // header('Location: ../dashboard.php'); // Redirige a una página segura
    // exit;
}


// --- Obtener datos para la vista del estudiante ---
$titulo_pagina = "Inscripción Semestral"; // Título ajustado
include_once '../includes/header.php'; // Incluir cabecera aquí, después de toda la lógica POST y las redirecciones

$mensajes_flash = get_flash_messages();


// Asignaturas en las que el estudiante ya está inscrito para el semestre actual (pendiente o confirmada)
$inscripciones_actuales = [];
if ($semestre_actual) {
    $stmt_inscripciones_actuales = $pdo->prepare("
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
        JOIN semestres s ON ie.id_semestre = s.id
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        WHERE ie.id_estudiante = :id_estudiante
        AND ie.id_semestre = :id_semestre_actual_inscripcion
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");
    $stmt_inscripciones_actuales->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_inscripciones_actuales->bindParam(':id_semestre_actual_inscripcion', $semestre_actual['id'], PDO::PARAM_INT);
    $stmt_inscripciones_actuales->execute();
    $inscripciones_actuales = $stmt_inscripciones_actuales->fetchAll(PDO::FETCH_ASSOC);
}


// Asignaturas aprobadas por el estudiante (para verificación de prerrequisitos)
$ids_asignaturas_aprobadas = [];
$stmt_asignaturas_aprobadas = $pdo->prepare("
    SELECT id_asignatura FROM historial_academico
    WHERE id_estudiante = :id_estudiante AND estado_final = 'APROBADO'
");
$stmt_asignaturas_aprobadas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_asignaturas_aprobadas->execute();
$ids_asignaturas_aprobadas = $stmt_asignaturas_aprobadas->fetchAll(PDO::FETCH_COLUMN);

// Asignaturas reprobadas por el estudiante (obligatorias para recursar)
$asignaturas_reprobadas = [];
$ids_asignaturas_reprobadas = [];
if ($semestre_actual) { // Solo si hay un semestre activo para inscripción
    $stmt_asignaturas_reprobadas = $pdo->prepare("
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
            SELECT id_asignatura FROM inscripciones_estudiantes WHERE id_estudiante = :id_estudiante_actual_inscripcion AND id_semestre = :id_semestre_actual_inscripcion AND confirmada = 1
        )
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");
    $stmt_asignaturas_reprobadas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_asignaturas_reprobadas->bindParam(':id_estudiante_actual_inscripcion', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_asignaturas_reprobadas->bindParam(':id_semestre_actual_inscripcion', $semestre_actual['id'], PDO::PARAM_INT);
    $stmt_asignaturas_reprobadas->execute();
    $asignaturas_reprobadas = $stmt_asignaturas_reprobadas->fetchAll(PDO::FETCH_ASSOC);
    $ids_asignaturas_reprobadas = array_column($asignaturas_reprobadas, 'id');
}


// Asignaturas disponibles para inscripción para el curso actual del estudiante y *semestres anteriores*
$asignaturas_disponibles_para_modal = [];
if ($semestre_actual) {
    // Ahora obtenemos todas las asignaturas potenciales para el curso actual del estudiante (de curso_estudiante)
    // hasta su semestre actual en ese curso, excluyendo las aprobadas o las que ya están inscritas.
    $stmt_asignaturas_disponibles = $pdo->prepare("
        SELECT
            a.id,
            a.nombre_asignatura,
            a.creditos,
            a.id_prerequisito,
            pa.nombre_asignatura AS nombre_prerequisito,
            c.nombre_curso,
            a.semestre_recomendado
        FROM
            asignaturas a
        LEFT JOIN asignaturas pa ON a.id_prerequisito = pa.id
        JOIN cursos c ON a.id_curso = c.id
        WHERE
            a.id_curso = :id_curso_estudiante
            AND a.semestre_recomendado <= :numero_semestre_actual_estudiante_en_curso -- Solo mostrar asignaturas hasta el semestre recomendado actual del estudiante
            AND a.id NOT IN (
                SELECT id_asignatura FROM historial_academico WHERE id_estudiante = :id_estudiante_historial_aprobado AND estado_final = 'APROBADO'
            )
            AND a.id NOT IN (
                SELECT id_asignatura FROM inscripciones_estudiantes WHERE id_estudiante = :id_estudiante_inscrito AND id_semestre = :id_semestre_inscrito
            )
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");

    $stmt_asignaturas_disponibles->bindParam(':id_curso_estudiante', $id_curso_actual_estudiante, PDO::PARAM_INT);
    $stmt_asignaturas_disponibles->bindParam(':numero_semestre_actual_estudiante_en_curso', $numero_semestre_actual_en_curso, PDO::PARAM_INT);
    $stmt_asignaturas_disponibles->bindParam(':id_estudiante_historial_aprobado', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_asignaturas_disponibles->bindParam(':id_estudiante_inscrito', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_asignaturas_disponibles->bindParam(':id_semestre_inscrito', $semestre_actual['id'], PDO::PARAM_INT); // Este es el ID del semestre de inscripción actual

    $stmt_asignaturas_disponibles->execute();
    $asignaturas_disponibles_para_modal = $stmt_asignaturas_disponibles->fetchAll(PDO::FETCH_ASSOC);

    // Fusionar las asignaturas reprobadas en la lista de disponibles, asegurando que aparezcan
    foreach ($asignaturas_reprobadas as $reprobada) {
        $encontrada = false;
        foreach ($asignaturas_disponibles_para_modal as $disponible) {
            if ($disponible['id'] == $reprobada['id']) {
                $encontrada = true;
                break;
            }
        }
        if (!$encontrada) {
            $asignaturas_disponibles_para_modal[] = $reprobada;
        }
    }
}
?>

<h1 class="mt-4">Inscripción Semestral</h1>
<p class="lead">Gestiona tu inscripción para el semestre actual y revisa tus asignaturas.</p>

<?php if (!$semestre_actual): ?>
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
            <?php if (count($inscripciones_actuales) > 0): ?>
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
                            <?php if (!empty($inscripciones_actuales)): ?>
                                <?php foreach ($inscripciones_actuales as $inscripcion): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($inscripcion['nombre_asignatura']) ?>
                                            <br><small class="text-muted">ID: <?= (int) ($inscripcion['id_asignatura'] ?? 0) ?></small>
                                        </td>
                                        <td class="text-center"><?= (int) $inscripcion['creditos'] ?></td>
                                        <td><?= htmlspecialchars($inscripcion['nombre_curso'] ?? '—') ?></td>
                                        <td>
                                            <?= htmlspecialchars($inscripcion['semestre_actual_numero'] ?? '—') ?>
                                            <span class="text-muted">(<?= htmlspecialchars($inscripcion['nombre_anio'] ?? '—') ?>)</span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($inscripcion['confirmada'])): ?>
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



    <?php if ($semestre_actual): ?>
        <div class="modal fade" id="enrollmentModal" tabindex="-1" aria-labelledby="enrollmentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="enrollmentModalLabel">Inscribirse en Asignaturas para el Semestre:
                            <?php echo htmlspecialchars($semestre_actual['numero_semestre'] . 'º Semestre - ' . $semestre_actual['nombre_anio']); ?>
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
                            <button type="submit" class="btn btn-primary" id="enrollSubmitBtn" disabled>Enviar Solicitud</button>
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
    const mensajesFlash = <?php echo json_encode($mensajes_flash ?? []); ?>; // Asegura que mensajes_flash siempre esté definido
    const modalInscripcion = document.getElementById('enrollmentModal'); // El modal principal
    const formularioInscripcion = document.getElementById('enrollmentForm'); // El formulario del modal
    const botonEnviarInscripcion = document.getElementById('enrollSubmitBtn'); // Botón de envío del formulario del modal

    // Estas variables se redefinirán CADA VEZ que el contenido del modal se cargue vía AJAX
    let checkboxesModal;
    let contadorSeleccionadasModal;
    let reprobadasObligatorias; // Checkboxes para asignaturas reprobadas
    let listaAsignaturasDisponibles; // Contenedor para asignaturas disponibles

    // Variables para elementos de filtro (también se redefinirán)
    let filtroCurso;
    let filtroSemestre;
    let filtroBusqueda;

    // Nuevo elemento para el mensaje de "sin resultados"
    let mensajeSinResultados;

    // --- Funciones de Ayuda ---

    // Función para mostrar un Toast de Bootstrap (sin cambios)
    function mostrarToast(tipo, mensaje) {
        const contenedorToast = document.querySelector('.toast-container');
        if (!contenedorToast) {
            console.error('No se encontró el contenedor de toasts. Asegúrate de que el div.toast-container exista en el HTML.');
            return;
        }
        const idToast = 'toast-' + Date.now();

        let colorFondo = '';
        switch (tipo) {
            case 'success': colorFondo = 'bg-success'; break;
            case 'danger': colorFondo = 'bg-danger'; break;
            case 'warning': colorFondo = 'bg-warning text-dark'; break;
            case 'info': colorFondo = 'bg-info'; break;
            default: colorFondo = 'bg-secondary'; break;
        }

        const htmlToast = `
            <div id="${idToast}" class="toast align-items-center text-white ${colorFondo} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body">
                        ${mensaje}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        contenedorToast.insertAdjacentHTML('beforeend', htmlToast);

        const elementoToast = document.getElementById(idToast);
        const toast = new bootstrap.Toast(elementoToast);
        toast.show();

        elementoToast.addEventListener('hidden.bs.toast', function () {
            elementoToast.remove();
        });
    }

    // --- Lógica del Modal y sus Filtros ---

    // Esta función se llamará CADA VEZ que el contenido del modal se cargue vía AJAX
    function inicializarContenidoModal() {
        // 1. Volver a obtener las referencias a los elementos dentro del modal, ya que su contenido es nuevo
        // Asegúrate de que el selector para 'checkboxesModal' incluya tanto las asignaturas regulares como las reprobadas.
        checkboxesModal = document.querySelectorAll('#modalContentContainer input[name="selected_asignaturas[]"]');
        contadorSeleccionadasModal = document.getElementById('modalSelectedCount');
        // Selector ajustado para obtener solo los inputs de tipo checkbox dentro de las etiquetas con la clase reprobada-obligatoria
        reprobadasObligatorias = document.querySelectorAll('.reprobada-obligatoria input[type="checkbox"]');
        listaAsignaturasDisponibles = document.getElementById('availableAsignaturasList');

        // 2. Referencias a los elementos de filtro que también se cargan con AJAX
        filtroCurso = document.getElementById('filterCourse');
        filtroSemestre = document.getElementById('filterSemester');
        filtroBusqueda = document.getElementById('filterSearch');

        // 3. Crear o re-seleccionar el elemento del mensaje de "sin resultados" si no existe
        mensajeSinResultados = document.getElementById('noResultsFilterMessage');
        if (!mensajeSinResultados) {
            mensajeSinResultados = document.createElement('p');
            mensajeSinResultados.id = 'noResultsFilterMessage';
            mensajeSinResultados.classList.add('alert', 'alert-info', 'text-center', 'mt-3');
            mensajeSinResultados.style.display = 'none'; // Oculto por defecto
            if (listaAsignaturasDisponibles) {
                // Insertar después de listaAsignaturasDisponibles o su padre si está disponible
                listaAsignaturasDisponibles.parentNode.insertBefore(mensajeSinResultados, listaAsignaturasDisponibles.nextSibling);
            }
        }

        // 4. Adjuntar event listeners a los checkboxes del modal
        checkboxesModal.forEach(checkbox => {
            checkbox.addEventListener('change', actualizarContadorSeleccionadasModal);
        });

        // 5. Adjuntar event listeners para los filtros
        if (filtroCurso) filtroCurso.addEventListener('change', filtrarAsignaturas);
        if (filtroSemestre) filtroSemestre.addEventListener('change', filtrarAsignaturas);
        if (filtroBusqueda) filtroBusqueda.addEventListener('keyup', filtrarAsignaturas);

        // 6. Llamar a actualizarContadorSeleccionadasModal y filtrarAsignaturas para el estado inicial
        actualizarContadorSeleccionadasModal();
        filtrarAsignaturas(); // Aplicar filtros al cargar el contenido del modal

        // 7. Reinicializar tooltips de Bootstrap para el contenido recién cargado
        var listaActivadoresTooltip = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var listaTooltips = listaActivadoresTooltip.map(function (elementoActivadorTooltip) {
            return new bootstrap.Tooltip(elementoActivadorTooltip)
        });
    }

    function actualizarContadorSeleccionadasModal() {
        let contador = 0;
        let todasReprobadasSeleccionadas = true;

        // Contar asignaturas reprobadas obligatorias (ya están marcadas y deshabilitadas en el HTML)
        reprobadasObligatorias.forEach(checkbox => {
            if (!checkbox.checked) {
                todasReprobadasSeleccionadas = false; // No debería ocurrir si están pre-marcadas correctamente
            }
            contador++; // Siempre contar las asignaturas reprobadas como seleccionadas
        });

        // Contar asignaturas no reprobadas seleccionadas
        if (listaAsignaturasDisponibles) {
            // Seleccionar solo los checkboxes que no están marcados como reprobada-obligatoria y están marcados
            listaAsignaturasDisponibles.querySelectorAll('input[name="selected_asignaturas[]"]:checked:not(.reprobada-obligatoria)').forEach(checkbox => {
                // Solo contar si está marcado y no está deshabilitado (deshabilitado significa que es una reprobada que ya contamos)
                if (!checkbox.disabled) {
                    contador++;
                }
            });
        }

        if (contadorSeleccionadasModal) {
            let puedeEnviar = true;
            let mensaje = `Asignaturas seleccionadas: ${contador}`;
            let claseColorTexto = 'text-muted';

            if (!todasReprobadasSeleccionadas) {
                puedeEnviar = false;
                mensaje = 'Debes seleccionar todas las asignaturas reprobadas obligatorias.';
                claseColorTexto = 'text-danger';
            } else if (contador === 0 && reprobadasObligatorias.length === 0) {
                puedeEnviar = false;
                mensaje = 'Por favor, selecciona al menos una asignatura.';
                claseColorTexto = 'text-danger';
            } else if (contador > 6) {
                puedeEnviar = false;
                mensaje = `Asignaturas seleccionadas: ${contador} (Máximo 6 asignaturas permitidas)`;
                claseColorTexto = 'text-danger';
            } else if (contador < reprobadasObligatorias.length) {
                // Esto cubre el caso en que una asignatura reprobada pueda desmarcarse de alguna manera.
                puedeEnviar = false;
                mensaje = `Debes seleccionar todas las asignaturas reprobadas (${reprobadasObligatorias.length}) para poder inscribirte.`;
                claseColorTexto = 'text-danger';
            }

            if (botonEnviarInscripcion) {
                if (puedeEnviar) {
                    botonEnviarInscripcion.removeAttribute('disabled');
                } else {
                    botonEnviarInscripcion.setAttribute('disabled', 'disabled');
                }
            }
            contadorSeleccionadasModal.innerText = mensaje;
            contadorSeleccionadasModal.classList.remove('text-muted', 'text-danger');
            contadorSeleccionadasModal.classList.add(claseColorTexto);
        }
    }


    // Función para manejar el envío del formulario del modal
    if (formularioInscripcion) {
        formularioInscripcion.addEventListener('submit', async function (evento) {
            evento.preventDefault();

            console.log("--- Inicio del evento de envío del formulario (AJAX Fetch) ---");

            if (botonEnviarInscripcion) {
                botonEnviarInscripcion.setAttribute('disabled', 'disabled');
                botonEnviarInscripcion.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
            }

            try {
                let valoresSeleccionados = new Set();

                // Añadir todos los checkboxes actualmente marcados (incluyendo los reprobados que están pre-marcados y deshabilitados)
                formularioInscripcion.querySelectorAll('input[name="selected_asignaturas[]"]:checked').forEach(checkbox => {
                    valoresSeleccionados.add(checkbox.value);
                });

                // Añadir explícitamente las asignaturas reprobadas deshabilitadas para asegurar que siempre se envíen
                reprobadasObligatorias.forEach(checkbox => {
                    valoresSeleccionados.add(checkbox.value);
                });

                console.log("Valores seleccionados recopilados (Set):", Array.from(valoresSeleccionados));

                // --- Validaciones del lado del cliente ---
                const contadorFinal = valoresSeleccionados.size;

                let todasReprobadasIncluidas = true;
                reprobadasObligatorias.forEach(checkbox => {
                    if (!valoresSeleccionados.has(checkbox.value)) {
                        todasReprobadasIncluidas = false;
                    }
                });

                if (!todasReprobadasIncluidas) {
                    mostrarToast('danger', 'Debes seleccionar todas las asignaturas reprobadas obligatorias.');
                    console.warn("Fallo de validación: Faltan asignaturas reprobadas obligatorias.");
                    return;
                }

                if (contadorFinal === 0) {
                    mostrarToast('danger', 'Por favor, selecciona al menos una asignatura para inscribirte.');
                    console.warn("Fallo de validación: No se ha seleccionado ninguna asignatura.");
                    return;
                }

                if (contadorFinal > 6) {
                    mostrarToast('danger', 'No puedes inscribirte en más de 6 asignaturas por semestre (incluyendo las reprobadas).');
                    console.warn("Fallo de validación: Demasiadas asignaturas seleccionadas.");
                    return;
                }

                console.log("Validaciones del lado del cliente pasadas. Construyendo FormData para el envío.");

                const datosFormulario = new FormData();
                Array.from(valoresSeleccionados).forEach(valor => {
                    datosFormulario.append('selected_asignaturas[]', valor);
                    console.log(`Añadiendo a FormData para envío: selected_asignaturas[] = ${valor}`);
                });

                // Enviar la solicitud usando la API Fetch
                const respuesta = await fetch('../api/inscripciones.php', { // Este endpoint manejará la inscripción real
                    method: 'POST',
                    body: datosFormulario
                });

                if (!respuesta.ok) {
                    const textoError = await respuesta.text();
                    console.error("Error en la respuesta HTTP:", textoError);
                    throw new Error(`Error HTTP: ${respuesta.status} - ${respuesta.statusText}. Respuesta: ${textoError.substring(0, 100)}...`);
                }

                const resultado = await respuesta.json();

                console.log("Respuesta del servidor:", resultado);

                if (resultado.success) {
                    mostrarToast('success', resultado.message);
                    const modal = bootstrap.Modal.getInstance(modalInscripcion);
                    if (modal) modal.hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    mostrarToast('danger', resultado.message);
                }

            } catch (error) {
                console.error("Error durante el envío del formulario con Fetch:", error);
                mostrarToast('danger', `Ocurrió un error inesperado al procesar la solicitud. Por favor, inténtalo de nuevo. Detalle: ${error.message}`);
            } finally {
                if (botonEnviarInscripcion) {
                    botonEnviarInscripcion.removeAttribute('disabled');
                    botonEnviarInscripcion.innerHTML = 'Enviar Solicitud';
                }
            }
        });
    }


    // Función para manejar el filtrado de asignaturas dentro del modal
    function filtrarAsignaturas() {
        if (!filtroCurso || !filtroSemestre || !filtroBusqueda || !listaAsignaturasDisponibles || !mensajeSinResultados) {
            console.warn('Elementos de filtro o lista de asignaturas no encontrados. El contenido de AJAX puede no haberse cargado todavía.');
            return;
        }

        const cursoSeleccionado = filtroCurso.value.toLowerCase();
        const semestreSeleccionado = filtroSemestre.value.toLowerCase();
        const terminoBusqueda = filtroBusqueda.value.toLowerCase();

        let contadorVisible = 0;
        const itemsAsignatura = listaAsignaturasDisponibles.querySelectorAll('.asignatura-item');

        itemsAsignatura.forEach(item => {
            const nombreCurso = item.dataset.course.toLowerCase();
            const numeroSemestre = item.dataset.semester.toLowerCase();
            const nombreAsignatura = item.dataset.name.toLowerCase();
            const esReprobada = item.classList.contains('reprobada-obligatoria'); // Verificar si es una asignatura reprobada

            const coincideCurso = cursoSeleccionado === '' || nombreCurso.includes(cursoSeleccionado);
            const coincideSemestre = semestreSeleccionado === '' || numeroSemestre.includes(semestreSeleccionado);
            const coincideBusqueda = terminoBusqueda === '' || nombreAsignatura.includes(terminoBusqueda);

            // Las asignaturas reprobadas siempre son visibles, independientemente de los filtros, ya que son obligatorias
            if (esReprobada) {
                item.style.display = 'flex';
                contadorVisible++;
            } else if (coincideCurso && coincideSemestre && coincideBusqueda) {
                item.style.display = 'flex';
                contadorVisible++;
            } else {
                item.style.display = 'none';
            }
        });

        // Mostrar u ocultar el mensaje de "sin resultados"
        if (contadorVisible === 0 && itemsAsignatura.length > 0) {
            mensajeSinResultados.textContent = 'No se encontraron asignaturas que coincidan con los filtros aplicados.';
            mensajeSinResultados.style.display = 'block';
        } else {
            mensajeSinResultados.style.display = 'none';
        }
    }

    // --- Event Listeners Globales ---

    document.addEventListener('DOMContentLoaded', function () {
        // Manejar mensajes flash
        mensajesFlash.forEach(msg => {
            mostrarToast(msg.type, msg.message);
        });
    });
</script>