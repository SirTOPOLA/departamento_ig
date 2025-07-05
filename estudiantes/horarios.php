<?php

// --- INICIO DE DEPURACIÓN TEMPORAL ---
// Habilita la visualización de errores para depuración.
// ¡Recuerda desactivarlo en un entorno de producción!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

// Incluye las funciones de ayuda, como la verificación de sesión y rol.
require_once '../includes/functions.php';
// Asegura que el usuario esté logueado y tenga el rol de 'Estudiante'.
// Esta función debería redirigir si las condiciones no se cumplen.
check_login_and_role('Estudiante');

// Incluye el archivo de configuración de la base de datos.
require_once '../config/database.php';

// Define el título de la página.
$titulo_pagina = "Mis Asignaturas Inscritas";
// Incluye el encabezado HTML de la página.
include_once '../includes/header.php';

// Obtiene los mensajes flash si existen (mensajes de éxito/error temporales).
$mensajes_flash = get_flash_messages();

// Obtiene el ID del usuario actual de la sesión.
$id_usuario_actual = $_SESSION['user_id'];

// --- Obtener el ID del estudiante asociado al usuario logueado ---
$stmt_estudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
$stmt_estudiante->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
$stmt_estudiante->execute();
$datos_estudiante = $stmt_estudiante->fetch(PDO::FETCH_ASSOC);

// Almacena el ID del estudiante. Si no se encuentra, será null.
$id_estudiante = $datos_estudiante['id'] ?? null;

// Maneja el caso si el ID del estudiante no se encuentra.
if (!$id_estudiante) {
    echo "<div class='alert alert-danger'>Error: No se pudo encontrar el ID del estudiante asociado al usuario. Por favor, contacte a soporte.</div>";
    include_once '../includes/footer.php';
    exit;
}

// --- Obtener el semestre actual o más reciente ---
// Esto es crucial para filtrar las inscripciones relevantes.
// Asumimos que un semestre "actual" es uno cuya fecha de fin aún no ha pasado.
$stmt_semestre_actual = $pdo->prepare("
    SELECT id AS id_semestre, numero_semestre
    FROM semestres
    WHERE fecha_fin >= CURDATE() -- Semestres actuales o futuros
    ORDER BY fecha_inicio DESC
    LIMIT 1
");
$stmt_semestre_actual->execute();
$semestre_actual = $stmt_semestre_actual->fetch(PDO::FETCH_ASSOC);

$inscripciones = []; // Inicializa la variable de inscripciones

if (!$semestre_actual) {
    // Si no hay un semestre actual, muestra un mensaje informativo.
    echo "<div class='alert alert-info'>No hay un semestre activo o futuro definido en el sistema.</div>";
} else {
    // --- Consulta para obtener las asignaturas inscritas del estudiante con todos los detalles ---
    $stmt_inscripciones = $pdo->prepare("
        SELECT
            ie.id AS id_inscripcion,
            ie.confirmada,
            ie.fecha_inscripcion,
            a.nombre_asignatura,
            a.creditos,
            c.nombre_curso,
            s.numero_semestre,
            h.dia_semana,
            h.hora_inicio,
            h.hora_fin,
            h.turno,
            u_profesor.nombre_completo AS nombre_profesor,
            au.nombre_aula,
            h.id AS id_horario_elegido, -- ID del horario específico elegido
            a.id AS id_asignatura_inscripcion, -- ID de la asignatura para esta inscripción
            s.id AS id_semestre_inscripcion -- ID del semestre para esta inscripción
        FROM
            inscripciones_estudiantes ie
        JOIN
            asignaturas a ON ie.id_asignatura = a.id
        JOIN
            semestres s ON ie.id_semestre = s.id
        LEFT JOIN -- LEFT JOIN porque el id_horario puede ser NULL si aún no se ha asignado
            horarios h ON ie.id_horario = h.id
        LEFT JOIN -- LEFT JOIN para los detalles del profesor si hay un horario asignado
            profesores p ON h.id_profesor = p.id
        LEFT JOIN -- LEFT JOIN para el nombre del usuario (profesor)
            usuarios u_profesor ON p.id_usuario = u_profesor.id
        LEFT JOIN -- LEFT JOIN para los detalles del aula
            aulas au ON h.id_aula = au.id
        LEFT JOIN -- LEFT JOIN para el nombre del curso (desde la asignatura asociada)
            cursos c ON a.id_curso = c.id
        WHERE
            ie.id_estudiante = :id_estudiante
            AND ie.id_semestre = :id_semestre_actual -- Filtra por el semestre actual
        ORDER BY
            a.nombre_asignatura
    ");

    $stmt_inscripciones->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
    $stmt_inscripciones->bindParam(':id_semestre_actual', $semestre_actual['id_semestre'], PDO::PARAM_INT);
    $stmt_inscripciones->execute();
    $inscripciones = $stmt_inscripciones->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-book-reader me-2"></i>Mis Asignaturas Inscritas</h2>
        <?php if ($semestre_actual): ?>
            <span class="badge bg-primary fs-5">Semestre Actual: <?= htmlspecialchars($semestre_actual['numero_semestre']) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($mensajes_flash)): ?>
        <?php foreach ($mensajes_flash as $mensaje): ?>
            <div class="alert alert-<?= htmlspecialchars($mensaje['type']) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($inscripciones)): ?>
        <div class="alert alert-info text-center" role="alert">
            No tienes asignaturas inscritas para este semestre.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Asignatura</th>
                        <th>Estado</th>
                        <th>Curso</th>
                        <th>Semestre</th>
                        <th>Horario Asignado</th>
                        <th>Profesor</th>
                        <th>Aula</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscripciones as $inscripcion): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($inscripcion['nombre_asignatura'] ?? 'N/A') ?></strong><br>
                                <small class="text-muted">Créditos: <?= htmlspecialchars($inscripcion['creditos'] ?? 'N/A') ?></small>
                            </td>
                            <td>
                                <span class="badge rounded-pill
                                    <?php echo (int)$inscripcion['confirmada'] === 1 ? 'bg-success' : 'bg-warning text-dark'; ?>"
                                    id="estado-<?= (int)($inscripcion['id_inscripcion'] ?? 0) ?>">
                                    <?php echo (int)$inscripcion['confirmada'] === 1 ? '<i class="fas fa-check-circle me-1"></i> Confirmada' : '<i class="fas fa-hourglass-half me-1"></i> Pendiente'; ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($inscripcion['nombre_curso'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($inscripcion['numero_semestre'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ((int)$inscripcion['confirmada'] === 1 && !empty($inscripcion['turno'])): ?>
                                    <span class="text-muted">Turno Asignado</span>
                                    <div id="info-horario-<?= (int)($inscripcion['id_inscripcion'] ?? 0) ?>" style="display:block;">
                                        <small class="text-muted">
                                            Turno: <?= htmlspecialchars($inscripcion['turno']) ?> (<?= htmlspecialchars(substr($inscripcion['hora_inicio'], 0, 5)) ?> - <?= htmlspecialchars(substr($inscripcion['hora_fin'], 0, 5)) ?>)<br>
                                            Día: <?= htmlspecialchars($inscripcion['dia_semana']) ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Sin Asignar</span>
                                    <div id="info-horario-<?= (int)($inscripcion['id_inscripcion'] ?? 0) ?>" style="display:none;"></div>
                                <?php endif; ?>
                            </td>
                            <td id="profesor-<?= (int)($inscripcion['id_inscripcion'] ?? 0) ?>">
                                <?= htmlspecialchars($inscripcion['nombre_profesor'] ?? 'N/A') ?>
                            </td>
                            <td id="aula-<?= (int)($inscripcion['id_inscripcion'] ?? 0) ?>">
                                <?= htmlspecialchars($inscripcion['nombre_aula'] ?? 'N/A') ?>
                            </td>
                            <td id="acciones-<?= (int)($inscripcion['id_inscripcion'] ?? 0) ?>">
                                <?php if ((int)$inscripcion['confirmada'] === 0): ?>
                                    <button type="button" class="btn btn-sm btn-info seleccionar-horario-btn"
                                        data-bs-toggle="modal" data-bs-target="#modalSeleccionarHorario"
                                        data-id-inscripcion="<?= (int) ($inscripcion['id_inscripcion'] ?? 0) ?>"
                                        data-id-asignatura="<?= (int) ($inscripcion['id_asignatura_inscripcion'] ?? 0) ?>"
                                        data-id-semestre="<?= (int) ($inscripcion['id_semestre_inscripcion'] ?? 0) ?>">
                                        <i class="fas fa-clock me-1"></i> Elegir Turno
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                                        <i class="fas fa-check me-1"></i> Asignado
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalSeleccionarHorario" tabindex="-1" aria-labelledby="etiquetaModalSeleccionarHorario" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="etiquetaModalSeleccionarHorario"><i class="fas fa-calendar-alt me-2"></i> Elegir Turno y Profesor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="id-inscripcion-modal">

                <div id="contenedor-lista-horarios">
                    <p class="text-muted text-center">Cargando horarios disponibles...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Función para mostrar alertas de manera consistente en el modal.
        function mostrarAlerta(mensaje, tipo = 'danger') {
            const contenedorAlerta = document.querySelector('#modalSeleccionarHorario .modal-body');
            // Remueve cualquier alerta existente antes de agregar una nueva.
            const alertaExistente = contenedorAlerta.querySelector('.alert');
            if (alertaExistente) {
                alertaExistente.remove();
            }

            const nuevaAlerta = document.createElement('div');
            nuevaAlerta.className = `alert alert-${tipo} alert-dismissible fade show mt-3`;
            nuevaAlerta.setAttribute('role', 'alert');
            nuevaAlerta.innerHTML = `
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            `;
            // Agrega la alerta al principio del cuerpo del modal.
            contenedorAlerta.prepend(nuevaAlerta);
            // Remueve la alerta automáticamente después de 5 segundos.
            setTimeout(() => nuevaAlerta.remove(), 5000);
        }

        // --- Cuando se hace clic en el botón "Elegir Turno/Profesor" ---
        document.querySelectorAll('.seleccionar-horario-btn').forEach(boton => {
            boton.addEventListener('click', function () {
                const idInscripcion = this.dataset.idInscripcion;
                const idAsignatura = this.dataset.idAsignatura;
                const idSemestre = this.dataset.idSemestre;

                // Guarda el ID de la inscripción en un campo oculto dentro del modal.
                document.getElementById('id-inscripcion-modal').value = idInscripcion;

                // Limpia el contenido previo y muestra un mensaje de carga.
                const contenedorListaHorarios = document.getElementById('contenedor-lista-horarios');
                contenedorListaHorarios.innerHTML = '<p class="text-muted text-center"><i class="fas fa-spinner fa-spin me-2"></i> Cargando horarios disponibles...</p>';

                // Limpia cualquier alerta previa en el modal.
                const alertaExistenteModal = contenedorListaHorarios.closest('.modal-body').querySelector('.alert');
                if (alertaExistenteModal) {
                    alertaExistenteModal.remove();
                }

                // Crea un objeto FormData para enviar los datos al servidor.
                const datosFormulario = new FormData();
                datosFormulario.append('id_asignatura', idAsignatura);
                datosFormulario.append('id_semestre', idSemestre);

                // Realiza la petición Fetch para obtener los horarios disponibles (usando POST y FormData).
                fetch('../api/obtener_horarios_profesores.php', {
                    method: 'POST', // Método POST para FormData
                    body: datosFormulario // El cuerpo de la petición es el objeto FormData
                })
                    .then(respuesta => {
                        // Verifica si la respuesta HTTP es exitosa (código 2xx).
                        if (!respuesta.ok) {
                            throw new Error(`Error HTTP! Estado: ${respuesta.status}`);
                        }
                        return respuesta.json(); // Parsea la respuesta como JSON.
                    })
                    .then(datos => {
                        if (datos.success) {
                            if (datos.horarios.length > 0) {
                                // Construye la tabla HTML con los horarios disponibles.
                                let htmlTabla = '<div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Profesor</th><th>Turno</th><th>Día</th><th>Aula</th><th>Acción</th></tr></thead><tbody>';
                                datos.horarios.forEach(horario => {
                                    htmlTabla += `
                                        <tr>
                                            <td>${horario.nombre_completo}</td>
                                            <td>${horario.turno} (${horario.hora_inicio.substring(0, 5)} - ${horario.hora_fin.substring(0, 5)})</td>
                                            <td>${horario.dia_semana}</td>
                                            <td>${horario.nombre_aula} (Cap.: ${horario.capacidad})</td>
                                            <td>
                                                <button class="btn btn-sm btn-success elegir-horario-btn"
                                                        data-id-horario="${horario.id}"
                                                        data-nombre-profesor="${horario.nombre_completo}"
                                                        data-turno="${horario.turno}"
                                                        data-hora-inicio="${horario.hora_inicio}"
                                                        data-hora-fin="${horario.hora_fin}"
                                                        data-dia-semana="${horario.dia_semana}"
                                                        data-nombre-aula="${horario.nombre_aula}">
                                                    <i class="fas fa-check-circle me-1"></i> Elegir
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                                htmlTabla += '</tbody></table></div>';
                                contenedorListaHorarios.innerHTML = htmlTabla;
                            } else {
                                // Mensaje si no hay horarios disponibles.
                                contenedorListaHorarios.innerHTML = '<div class="alert alert-info text-center" role="alert">No hay turnos/profesores disponibles para esta asignatura en este semestre.</div>';
                            }
                        } else {
                            // Muestra un mensaje de error si la API no tuvo éxito.
                            contenedorListaHorarios.innerHTML = `<div class="alert alert-danger text-center" role="alert">Error al cargar horarios: ${datos.message}</div>`;
                        }
                    })
                    .catch(error => {
                        // Captura errores de red o del fetch.
                        console.error("Error en Fetch:", error);
                        contenedorListaHorarios.innerHTML = '<div class="alert alert-danger text-center" role="alert">Error de conexión al cargar horarios. Por favor, intente de nuevo.</div>';
                    });
            });
        });

        // --- Cuando se hace clic en el botón "Elegir" dentro del modal (usando delegación de eventos) ---
        document.getElementById('contenedor-lista-horarios').addEventListener('click', function (evento) {
            // Verifica si el clic fue en un botón con la clase 'elegir-horario-btn' o en un elemento dentro de él.
            if (evento.target.classList.contains('elegir-horario-btn') || evento.target.closest('.elegir-horario-btn')) {
                const botonElegir = evento.target.closest('.elegir-horario-btn');
                const idHorario = botonElegir.dataset.idHorario;
                // Recupera el ID de la inscripción almacenado en el campo oculto del modal.
                const idInscripcion = document.getElementById('id-inscripcion-modal').value;

                // Obtiene detalles del horario para actualizar la UI inmediatamente.
                const nombreProfesor = botonElegir.dataset.nombreProfesor;
                const turno = botonElegir.dataset.turno;
                const horaInicio = botonElegir.dataset.horaInicio;
                const horaFin = botonElegir.dataset.horaFin;
                const diaSemana = botonElegir.dataset.diaSemana;
                const nombreAula = botonElegir.dataset.nombreAula;

                // Validación básica de datos.
                if (!idHorario || !idInscripcion) {
                    mostrarAlerta('Error: Datos incompletos para seleccionar el horario.');
                    return;
                }

                // Deshabilita el botón y muestra un spinner mientras se procesa la solicitud.
                botonElegir.disabled = true;
                botonElegir.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Cargando...';

                // Crea un objeto FormData para enviar los datos al servidor.
                const datosFormulario = new FormData();
                datosFormulario.append('id_inscripcion', idInscripcion);
                datosFormulario.append('id_horario', idHorario);

                // Envía la petición POST para actualizar el horario de la inscripción.
                fetch('../api/actualizar_horario_inscripcion.php', {
                    method: 'POST',
                    body: datosFormulario
                })
                    .then(respuesta => {
                        if (!respuesta.ok) {
                            throw new Error(`Error HTTP! Estado: ${respuesta.status}`);
                        }
                        return respuesta.json();
                    })
                    .then(datos => {
                        if (datos.success) {
                            mostrarAlerta('Horario seleccionado exitosamente.', 'success');

                            // --- Actualiza la interfaz de usuario (UI) ---
                            // 1. Actualiza la insignia de estado en la tabla principal.
                            const insigniaEstado = document.getElementById(`estado-${idInscripcion}`);
                            if (insigniaEstado) {
                                insigniaEstado.classList.remove('bg-warning', 'text-dark');
                                insigniaEstado.classList.add('bg-success');
                                insigniaEstado.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirmada';
                            }

                            // 2. Reemplaza el botón "Elegir Turno" con "Asignado" y deshabilítalo.
                            // Obtenemos la celda de acciones directamente por su ID único.
                            const celdaAcciones = document.getElementById(`acciones-${idInscripcion}`);
                            if (celdaAcciones) {
                                celdaAcciones.innerHTML = `
                                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                                        <i class="fas fa-check me-1"></i> Asignado
                                    </button>
                                `;
                            }

                            // 3. Actualiza la celda "Horario Asignado" en la fila correspondiente.
                            const celdaHorario = document.getElementById(`info-horario-${idInscripcion}`).parentNode; // Parent is the <td>
                            if (celdaHorario) {
                                celdaHorario.innerHTML = `
                                    <span class="text-muted">Turno Asignado</span>
                                    <div id="info-horario-${idInscripcion}" style="display:block;">
                                        <small class="text-muted">
                                            Turno: ${turno} (${horaInicio.substring(0, 5)} - ${horaFin.substring(0, 5)})<br>
                                            Día: ${diaSemana}
                                        </small>
                                    </div>
                                `;
                            }

                            // 4. Actualiza la celda "Profesor" en la fila correspondiente.
                            const celdaProfesor = document.getElementById(`profesor-${idInscripcion}`);
                            if (celdaProfesor) {
                                celdaProfesor.textContent = nombreProfesor;
                            }

                            // 5. Actualiza la celda "Aula" en la fila correspondiente.
                            const celdaAula = document.getElementById(`aula-${idInscripcion}`);
                            if (celdaAula) {
                                celdaAula.textContent = nombreAula;
                            }


                            // Cierra el modal después de una selección exitosa.
                            const modalSeleccionarHorario = bootstrap.Modal.getInstance(document.getElementById('modalSeleccionarHorario'));
                            if (modalSeleccionarHorario) {
                                modalSeleccionarHorario.hide();
                            }

                        } else {
                            // Muestra un mensaje de error si la selección falló en el servidor.
                            mostrarAlerta(`Error al seleccionar el horario: ${datos.message}`);
                        }
                    })
                    .catch(error => {
                        // Captura errores de red o del fetch.
                        console.error("Error en Fetch:", error);
                        mostrarAlerta('Error de conexión al seleccionar el horario. Por favor, intente de nuevo.');
                    })
                    .finally(() => {
                        // Re-habilita el botón en caso de error (aunque el modal se cierra en caso de éxito).
                        botonElegir.disabled = false;
                        botonElegir.innerHTML = '<i class="fas fa-check-circle me-1"></i> Elegir';
                    });
            }
        });
    });
</script>
<?php include_once '../includes/footer.php'; // Incluye el pie de página HTML ?>