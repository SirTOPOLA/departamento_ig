<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../index.php");
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];
require '../includes/conexion.php';
?>

<?php include 'header.php'; ?>

<style>
    body {
        font-family: "Inter", sans-serif;
        background-color: #f0f2f5; /* Fondo más suave */
    }
    .container-fluid {
        padding: 2rem;
    }
    .card {
        border-radius: 1rem;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }
    .card-header {
        background-color: #007bff;
        color: white;
        border-top-left-radius: 1rem;
        border-top-right-radius: 1rem;
        padding: 1.25rem 1.5rem;
        font-weight: 600;
    }
    .icon-large {
        font-size: 2.5rem;
        color: #007bff;
    }
    .metric-value {
        font-size: 2.2rem;
        font-weight: 700;
        color: #343a40;
    }
    .metric-label {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .list-group-item-colored {
        background-color: #e9f5ff; /* Un color de fondo para resaltar */
        border-left: 5px solid #007bff;
        margin-bottom: 0.5rem;
        border-radius: 0.5rem;
    }
    .progress-bar-custom {
        background-color: #28a745; /* Color verde para progreso */
    }
    .table-striped > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(0, 0, 0, 0.02);
    }
    .table thead th {
        background-color: #007bff;
        color: white;
        border-bottom: none;
    }
</style>

<div class="container-fluid py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 text-primary"><i class="bi bi-speedometer2 me-3"></i> Dashboard Académico</h2>
        <h4 class="mb-0 text-muted">Bienvenido, <span id="studentNameHeader">Estudiante</span>!</h4>
    </div>

    <!-- Sección de Tarjetas Resumen -->
    <div class="row g-4 mb-5">
        <!-- Tarjeta: Mi Perfil -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-person-fill me-2"></i> Mi Perfil</div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="mb-1 text-muted small">Nombre Completo:</p>
                        <h5 id="profileFullName" class="card-title text-dark">Cargando...</h5>
                        <p class="mb-1 text-muted small">Matrícula:</p>
                        <p id="profileMatricula" class="card-text text-dark">Cargando...</p>
                        <p class="mb-1 text-muted small">Usuario:</p>
                        <p id="profileEmail" class="card-text text-dark text-break-word">Cargando...</p>
                        <p class="mb-1 text-muted small">DIP:</p>
                        <p id="profileDNI" class="card-text text-dark">Cargando...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Estado Académico Actual -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-mortarboard-fill me-2"></i> Curso Actual</div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="mb-1 text-muted small">Curso:</p>
                        <h5 id="currentCourseName" class="card-title text-dark">Cargando...</h5>
                        <p class="mb-1 text-muted small">Turno y Grupo:</p>
                        <p id="currentCourseDetails" class="card-text text-dark">Cargando...</p>
                        <p class="mb-1 text-muted small">Año Académico:</p>
                        <p id="currentAcademicYear" class="card-text text-dark">Cargando...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Rendimiento Académico -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-graph-up-arrow me-2"></i> Rendimiento General</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <div>
                            <div class="metric-value" id="overallAverage">N/A</div>
                            <div class="metric-label">Promedio General</div>
                        </div>
                        <i class="bi bi-calculator icon-large"></i>
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-success me-2">Aprobadas: <span id="subjectsApproved">0</span></span>
                        <span class="badge bg-danger">Reprobadas: <span id="subjectsReproved">0</span></span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div id="progressApproved" class="progress-bar progress-bar-custom" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted">Progreso basado en asignaturas aprobadas.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de Horario y Últimas Notas -->
    <div class="row g-4 mb-5">
        <!-- Tarjeta: Mi Horario -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-calendar-week-fill me-2"></i> Mi Horario (Semana Actual)</div>
                <div class="card-body">
                    <div id="horarioContent">
                        <p class="text-center text-muted">Cargando horario...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Últimas Notas -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-bar-chart-fill me-2"></i> Últimas Notas</div>
                <div class="card-body">
                    <div id="latestGradesContent">
                        <p class="text-center text-muted">Cargando últimas notas...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de Acciones Rápidas -->
    <div class="text-center mb-5">
        <h4 class="mb-4 text-primary">Accesos Rápidos</h4>
        <button class="btn btn-outline-primary rounded-pill px-5 py-3 me-3 mb-2" onclick="verHistorialAcademico(<?= $id_estudiante ?>, document.getElementById('profileFullName').textContent)">
            <i class="bi bi-journal-bookmark-fill me-2"></i> Ver Historial Académico Completo
        </button>
        <button class="btn btn-outline-info rounded-pill px-5 py-3 mb-2" onclick="verCursosAsignaturas(<?= $id_estudiante ?>, document.getElementById('profileFullName').textContent)">
            <i class="bi bi-book-half me-2"></i> Ver Cursos y Asignaturas Activos
        </button>
        <a href="inscripciones.php" class="btn btn-outline-success rounded-pill px-5 py-3 mb-2">
            <i class="bi bi-book-half me-2"></i> Incribirse
        </a>
    </div>

</div>

<!-- Modales reutilizados del módulo de estudiantes (asegúrate de que estén en el mismo archivo o sean cargados) -->
<!-- MODAL PARA HISTORIAL ACADÉMICO -->
<div class="modal fade" id="modalHistorialAcademico" tabindex="-1" aria-labelledby="modalHistorialAcademicoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header bg-primary text-white rounded-top-3 p-4">
                <h5 class="modal-title fs-5" id="modalHistorialAcademicoLabel">📚 Historial Académico de <span id="historialEstudianteNombre"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div id="historialAcademicoContent">
                    <p class="text-center text-muted">Cargando historial...</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-center border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA DETALLES DE CURSOS ACTIVOS Y ASIGNATURAS -->
<div class="modal fade" id="modalCursosAsignaturas" tabindex="-1" aria-labelledby="modalCursosAsignaturasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header bg-primary text-white rounded-top-3 p-4">
                <h5 class="modal-title fs-5" id="modalCursosAsignaturasLabel">📖 Cursos Activos y Asignaturas de <span id="cursosAsignaturasEstudianteNombre"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div id="cursosAsignaturasContent">
                    <p class="text-center text-muted">Cargando cursos y asignaturas...</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-center border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>


<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Configura el ID del estudiante logueado (EN PRODUCCIÓN, ESTO VIENE DE LA SESIÓN DEL USUARIO)
    const LOGGED_IN_STUDENT_ID = <?= $id_estudiante ?>; // EJEMPLO: ID del estudiante 1.

    // Referencias a elementos del DOM para el dashboard
    const studentNameHeader = document.getElementById('studentNameHeader');
    const profileFullName = document.getElementById('profileFullName');
    const profileMatricula = document.getElementById('profileMatricula');
    const profileEmail = document.getElementById('profileEmail');
    const profileDNI = document.getElementById('profileDNI');
    const currentCourseName = document.getElementById('currentCourseName');
    const currentCourseDetails = document.getElementById('currentCourseDetails');
    const currentAcademicYear = document.getElementById('currentAcademicYear');
    const overallAverage = document.getElementById('overallAverage');
    const subjectsApproved = document.getElementById('subjectsApproved');
    const subjectsReproved = document.getElementById('subjectsReproved');
    const progressApproved = document.getElementById('progressApproved');
    const horarioContent = document.getElementById('horarioContent');
    const latestGradesContent = document.getElementById('latestGradesContent');

    // Referencias a modales (reutilizados)
    const modalHistorialAcademico = new bootstrap.Modal(document.getElementById('modalHistorialAcademico'));
    const modalCursosAsignaturas = new bootstrap.Modal(document.getElementById('modalCursosAsignaturas'));

    /**
     * Muestra un modal personalizado para mensajes.
     * @param {string} message - El mensaje a mostrar.
     * @param {function} [callback] - Función a ejecutar después de cerrar el modal.
     */
    function mostrarMensajeModal(message, callback = () => {}) {
        let msgModal = document.getElementById('customMessageModal');
        if (!msgModal) {
            msgModal = document.createElement('div');
            msgModal.id = 'customMessageModal';
            msgModal.classList.add('modal', 'fade');
            msgModal.setAttribute('tabindex', '-1');
            msgModal.setAttribute('aria-hidden', 'true');
            msgModal.innerHTML = `
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content rounded-4 shadow-lg">
                        <div class="modal-header bg-primary text-white rounded-top-3">
                            <h5 class="modal-title">Mensaje</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body text-center">
                            <p id="customMessageText"></p>
                        </div>
                        <div class="modal-footer d-flex justify-content-center border-0">
                            <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">Aceptar</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(msgModal);
        }

        document.getElementById('customMessageText').textContent = message;
        const bsModal = new bootstrap.Modal(msgModal);
        bsModal.show();

        const dismissHandler = () => {
            callback();
            msgModal.removeEventListener('hidden.bs.modal', dismissHandler);
        };
        msgModal.addEventListener('hidden.bs.modal', dismissHandler);
    }

    /**
     * Carga todos los datos del dashboard para el estudiante.
     */
    async function loadDashboardData() {
        if (!LOGGED_IN_STUDENT_ID) {
            mostrarMensajeModal('No se ha especificado un ID de estudiante. Por favor, inicie sesión.');
            return;
        }

        try {
            // Cargar datos principales del dashboard
            const dashboardRes = await fetch(`../api/obtener_dashboard_data.php?id_estudiante=${LOGGED_IN_STUDENT_ID}`);
            if (!dashboardRes.ok) throw new Error(`HTTP error! status: ${dashboardRes.status}`);
            const dashboardData = await dashboardRes.json();

            if (dashboardData.status && dashboardData.data) {
                renderDashboardSummary(dashboardData.data);
            } else {
                mostrarMensajeModal('Error al cargar datos del dashboard: ' + (dashboardData.message || 'Datos inválidos.'));
                console.error("Error al cargar dashboard summary:", dashboardData.message || dashboardData);
            }

            // Cargar horario
            const horarioRes = await fetch(`../api/obtener_horario_estudiante.php?id_estudiante=${LOGGED_IN_STUDENT_ID}`);
            if (!horarioRes.ok) throw new Error(`HTTP error! status: ${horarioRes.status}`);
            const horarioData = await horarioRes.json();

            if (horarioData.status && Array.isArray(horarioData.data)) {
                renderHorario(horarioData.data);
            } else {
                horarioContent.innerHTML = '<p class="text-center text-danger">Error al cargar horario: ' + (horarioData.message || 'Datos inválidos.') + '</p>';
                console.error("Error al cargar horario:", horarioData.message || horarioData);
            }

            // Cargar últimas notas
            const notasRes = await fetch(`../api/obtener_ultimas_notas_estudiante.php?id_estudiante=${LOGGED_IN_STUDENT_ID}`);
            if (!notasRes.ok) throw new Error(`HTTP error! status: ${notasRes.status}`);
            const notasData = await notasRes.json();

            if (notasData.status && Array.isArray(notasData.data)) {
                renderLatestGrades(notasData.data);
            } else {
                latestGradesContent.innerHTML = '<p class="text-center text-danger">Error al cargar últimas notas: ' + (notasData.message || 'Datos inválidos.') + '</p>';
                console.error("Error al cargar últimas notas:", notasData.message || notasData);
            }

        } catch (err) {
            mostrarMensajeModal('Error de conexión al cargar el dashboard. Por favor, intente de nuevo.');
            console.error("Error general al cargar dashboard:", err);
        }
    }

    /**
     * Renderiza la información de resumen del estudiante en el dashboard.
     * @param {object} data - Objeto con los datos del estudiante y su resumen académico.
     */
    function renderDashboardSummary(data) {
        // Mi Perfil
        studentNameHeader.textContent = `${data.nombre || ''} ${data.apellido || ''}`;
        profileFullName.textContent = `${data.nombre || ''} ${data.apellido || ''}`;
        profileMatricula.textContent = data.matricula || 'No asignada';
        profileEmail.textContent = data.email || 'N/A';
        profileDNI.textContent = data.dni || 'N/A';

        // Curso Actual
        if (data.curso_actual) {
            currentCourseName.textContent = data.curso_actual.nombre || 'N/A';
            currentCourseDetails.textContent = `${data.curso_actual.turno || 'N/A'}, Grupo ${data.curso_actual.grupo || 'N/A'}`;
            currentAcademicYear.textContent = data.curso_actual.anio_academico || 'N/A';
        } else {
            currentCourseName.textContent = 'No inscrito en curso activo';
            currentCourseDetails.textContent = 'N/A';
            currentAcademicYear.textContent = 'N/A';
        }

        // Rendimiento General
        // FIX: Asegurar que data.promedio es un número antes de llamar toFixed
        overallAverage.textContent = (typeof data.promedio === 'number' && data.promedio !== null) ? data.promedio.toFixed(2) : 'N/A';

        // FIX: Corregir typo en data.apP_robadas_count
        subjectsApproved.textContent = data.aprobadas_count !== null ? data.aprobadas_count : '0';
        subjectsReproved.textContent = data.reprobadas_count !== null ? data.reprobadas_count : '0';

        const totalSubjects = (data.aprobadas_count || 0) + (data.reprobadas_count || 0) + (data.otros_resultados_count || 0);
        if (totalSubjects > 0) {
            const approvedPercentage = (data.aprobadas_count / totalSubjects) * 100;
            progressApproved.style.width = `${approvedPercentage}%`;
            progressApproved.setAttribute('aria-valuenow', approvedPercentage);
        } else {
            progressApproved.style.width = '0%';
            progressApproved.setAttribute('aria-valuenow', 0);
        }
    }

    /**
     * Renderiza el horario del estudiante.
     * @param {Array} horario - Array de objetos de horario.
     */
    function renderHorario(horario) {
        if (horario.length === 0) {
            horarioContent.innerHTML = '<p class="text-center text-muted">No hay clases programadas para su horario actual.</p>';
            return;
        }

        let tableHtml = `
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Día</th>
                        <th>Hora</th>
                        <th>Asignatura</th>
                        <th>Profesor</th>
                        <th>Aula</th>
                    </tr>
                </thead>
                <tbody>
        `;
        horario.forEach(clase => {
            tableHtml += `
                <tr>
                    <td>${clase.dia || 'N/A'}</td>
                    <td>${clase.hora_inicio || 'N/A'} - ${clase.hora_fin || 'N/A'}</td>
                    <td>${clase.asignatura_nombre || 'N/A'}</td>
                    <td>${clase.profesor_nombre || 'N/A'} ${clase.profesor_apellido || ''}</td>
                    <td>${clase.aula_nombre || 'N/A'}</td>
                </tr>
            `;
        });
        tableHtml += `</tbody></table>`;
        horarioContent.innerHTML = tableHtml;
    }

    /**
     * Renderiza las últimas notas del estudiante.
     * @param {Array} notas - Array de objetos de notas.
     */
    function renderLatestGrades(notas) {
        if (notas.length === 0) {
            latestGradesContent.innerHTML = '<p class="text-center text-muted">No hay notas recientes registradas.</p>';
            return;
        }

        let tableHtml = `
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Asignatura</th>
                        <th>P1</th>
                        <th>P2</th>
                        <th>Final</th>
                        <th>Prom.</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
        `;
        notas.forEach(nota => {
            tableHtml += `
                <tr>
                    <td>${nota.asignatura_nombre || 'N/A'}</td>
                    <td>${nota.parcial_1 !== null ? nota.parcial_1 : '-'}</td>
                    <td>${nota.parcial_2 !== null ? nota.parcial_2 : '-'}</td>
                    <td>${nota.examen_final !== null ? nota.examen_final : '-'}</td>
                    <td><strong>${nota.promedio !== null ? nota.promedio : 'N/A'}</strong></td>
                    <td class="small text-break-word">${nota.observaciones || 'Ninguna'}</td>
                </tr>
            `;
        });
        tableHtml += `</tbody></table>`;
        latestGradesContent.innerHTML = tableHtml;
    }

    /**
     * Muestra el modal con el historial académico de un estudiante.
     * Reutiliza la lógica del módulo de estudiantes.
     * @param {number} idEstudiante - ID del estudiante.
     * @param {string} nombreCompleto - Nombre completo del estudiante.
     */
    async function verHistorialAcademico(idEstudiante, nombreCompleto) { // Changed LOGGED_IN_STUDENT_I to idEstudiante for clarity
        document.getElementById('historialEstudianteNombre').textContent = nombreCompleto;
        const historialContent = document.getElementById('historialAcademicoContent');
        historialContent.innerHTML = '<p class="text-center text-muted">Cargando historial...</p>';

        try {
            const res = await fetch(`../api/obtener_historial_academico.php?id_estudiante=${idEstudiante}`);
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status && Array.isArray(data.data)) {
                if (data.data.length === 0) {
                    historialContent.innerHTML = '<p class="text-center text-muted">Este estudiante no tiene historial académico registrado.</p>';
                } else {
                    let tableHtml = `
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Año Académico</th>
                                    <th>Asignatura</th>
                                    <th>Resultado</th>
                                    <th>Nota Final</th>
                                    <th>Observación</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    data.data.forEach(item => {
                        tableHtml += `
                                <tr>
                                    <td>${item.anio_academico || 'N/A'}</td>
                                    <td>${item.asignatura_nombre || 'N/A'}</td>
                                    <td><span class="badge ${item.resultado === 'aprobado' ? 'bg-success' : item.resultado === 'reprobado' ? 'bg-danger' : 'bg-secondary'}">${item.resultado || 'N/A'}</span></td>
                                    <td>${item.nota_final !== null ? item.nota_final : 'N/A'}</td>
                                    <td class="text-break-word">${item.observacion || 'Ninguna'}</td>
                                    <td>${new Date(item.fecha).toLocaleDateString()}</td>
                                </tr>
                        `;
                    });
                    tableHtml += `</tbody></table>`;
                    historialContent.innerHTML = tableHtml;
                }
            } else {
                historialContent.innerHTML = '<p class="text-center text-danger">Error al cargar el historial: ' + (data.message || 'Datos inválidos') + '</p>';
                console.error("Error o formato de datos incorrecto para historial:", data.message || data);
            }
        } catch (err) {
            historialContent.innerHTML = '<p class="text-center text-danger">Error de conexión al obtener el historial.</p>';
            console.error("Error de conexión al obtener historial académico:", err);
        } finally {
            modalHistorialAcademico.show();
        }
    }

    /**
     * Muestra el modal con los cursos activos y sus asignaturas para un estudiante.
     * Reutiliza la lógica del módulo de estudiantes.
     * @param {number} idEstudiante - ID del estudiante.
     * @param {string} nombreCompleto - Nombre completo del estudiante.
     */
    async function verCursosAsignaturas(idEstudiante, nombreCompleto) { // Changed LOGGED_IN_STUDENT_I to idEstudiante for clarity
        document.getElementById('cursosAsignaturasEstudianteNombre').textContent = nombreCompleto;
        const cursosAsignaturasContent = document.getElementById('cursosAsignaturasContent');
        cursosAsignaturasContent.innerHTML = '<p class="text-center text-muted">Cargando cursos y asignaturas...</p>';

        try {
            const res = await fetch(`../api/obtener_cursos_activos_estudiante.php?id_estudiante=${idEstudiante}`);
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status && Array.isArray(data.data)) {
                if (data.data.length === 0) {
                    cursosAsignaturasContent.innerHTML = '<p class="text-center text-muted">Este estudiante no está inscrito en cursos activos.</p>';
                } else {
                    let contentHtml = '';
                    data.data.forEach(cursoInscripcion => {
                        contentHtml += `
                            <div class="card mb-3 shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="bi bi-book me-2"></i> ${cursoInscripcion.curso_nombre || 'N/A'} (${cursoInscripcion.turno || 'N/A'}, Grupo ${cursoInscripcion.grupo || 'N/A'}) - Año: ${cursoInscripcion.anio_academico || 'N/A'}</h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><strong>Inscripción:</strong> ${new Date(cursoInscripcion.fecha_registro).toLocaleDateString()} (Estado: <span class="badge bg-success">${cursoInscripcion.estado || 'N/A'}</span>)</p>
                                    <h6>Asignaturas:</h6>
                                    `;
                        if (cursoInscripcion.asignacion_asignaturas && cursoInscripcion.asignacion_asignaturas.length > 0) {
                            contentHtml += `
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Nombre Asignatura</th>
                                                        <th>Semestre</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                            `;
                            cursoInscripcion.asignacion_asignaturas.forEach(asignatura => {
                                contentHtml += `
                                                    <tr>
                                                        <td>${asignatura.codigo || 'N/A'}</td>
                                                        <td>${asignatura.nombre || 'N/A'}</td>
                                                        <td>${asignatura.semestre_nombre || 'N/A'}</td>
                                                    </tr>
                                            `;
                            });
                            contentHtml += `
                                                </tbody>
                                            </table>
                                            `;
                        } else {
                            contentHtml += '<p class="text-muted">No hay asignaturas asociadas a este curso.</p>';
                        }
                        contentHtml += `
                                </div>
                            </div>
                        `;
                    });
                    cursosAsignaturasContent.innerHTML = contentHtml;
                }
            } else {
                cursosAsignaturasContent.innerHTML = '<p class="text-center text-danger">Error al cargar cursos y asignaturas: ' + (data.message || 'Datos inválidos') + '</p>';
                console.error("Error o formato de datos incorrecto para cursos/asignaturas:", data.message || data);
            }
        } catch (err) {
            cursosAsignaturasContent.innerHTML = '<p class="text-center text-danger">Error de conexión al obtener cursos y asignaturas.</p>';
            console.error("Error de conexión al obtener cursos y asignaturas:", err);
        } finally {
            modalCursosAsignaturas.show();
        }
    }

    // Carga los datos del dashboard al cargar la página
    document.addEventListener('DOMContentLoaded', loadDashboardData);

</script>

<?php include 'footer.php'; ?>
