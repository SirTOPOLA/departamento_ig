<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../login.php");
    exit;
}

require '../includes/conexion.php';

$idProfesor = $_SESSION['id_usuario']; // Suponiendo que aquí guardas el id_usuario del profesor en sesión

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
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: rgba(0, 0, 0, 0.02);
        }
        .table thead th {
            background-color: #007bff;
            color: white;
            border-bottom: none;
        }
        /* Estilos para el modal de gestión de notas/alumnos */
        .grade-input {
            width: 70px;
            display: inline-block;
        }
        .status-badge {
            font-size: 0.85em;
            padding: 0.3em 0.6em;
            border-radius: 0.5rem;
        }
        .grades-action-cell {
            min-width: 120px;
        }
    </style>
 


 <div class="container-fluid py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 text-primary"><i class="bi bi-person-workspace me-3"></i> Dashboard de Profesor</h2>
        <h4 class="mb-0 text-muted">Bienvenido, <span id="professorNameHeader">Profesor</span>!</h4>
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
                        <p class="mb-1 text-muted small">Email:</p>
                        <p id="profileEmail" class="card-text text-dark text-break-word">Cargando...</p>
                        <p class="mb-1 text-muted small">Especialidad:</p>
                        <p id="profileEspecialidad" class="card-text text-dark">Cargando...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Mis Asignaturas -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-journal-bookmark-fill me-2"></i> Mis Asignaturas</div>
                <div class="card-body">
                    <div id="assignedSubjectsList">
                        <p class="text-center text-muted">Cargando asignaturas...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Alumnos a mi cargo / Resumen rápido -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-people-fill me-2"></i> Mis Alumnos</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <div>
                            <div class="metric-value" id="totalStudentsCount">0</div>
                            <div class="metric-label">Total de Alumnos</div>
                        </div>
                        <i class="bi bi-person-lines-fill icon-large"></i>
                    </div>
                    <p class="text-muted small mb-0">Alumnos inscritos en tus asignaturas.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección: Gestión de Clases y Alumnos (selección por asignatura) -->
    <div class="card mb-5 shadow-sm">
        <div class="card-header"><i class="bi bi-clipboard-data-fill me-2"></i> Gestión de Clases y Alumnos</div>
        <div class="card-body">
            <div class="row g-3 mb-4 align-items-end">
                <div class="col-md-4">
                    <label for="selectGestionAsignatura" class="form-label">Seleccionar Asignatura</label>
                    <select id="selectGestionAsignatura" class="form-select rounded-pill">
                        <option value="">Seleccione una asignatura...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="selectGestionAnio" class="form-label">Año Académico</label>
                    <select id="selectGestionAnio" class="form-select rounded-pill">
                        <option value="">Seleccione un año...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary rounded-pill w-100" id="btnLoadStudentsForSubject" disabled>
                        <i class="bi bi-people-fill me-2"></i> Cargar Alumnos
                    </button>
                </div>
            </div>

            <hr>

            <h5 class="mb-3 text-secondary" id="studentsListTitle">Alumnos Inscritos: <span class="badge bg-secondary" id="loadedStudentsCount">0</span></h5>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>#</th>
                            <th>Estudiante</th>
                            <th>Matrícula</th>
                            <th>Parcial 1</th>
                            <th>Parcial 2</th>
                            <th>Examen Final</th>
                            <th>Promedio</th>
                            <th>Observaciones</th>
                            <th class="text-center grades-action-cell">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="studentsGradesTableBody">
                        <!-- Estudiantes y sus notas se cargarán aquí -->
                        <tr><td colspan="9" class="text-center py-4 text-muted">Seleccione una asignatura y año para ver los alumnos.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Modales de Mensaje/Confirmación (reutilizados) -->
<div class="modal fade" id="customMessageModal" tabindex="-1" aria-labelledby="customMessageModalLabel" aria-hidden="true">
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
</div>

<div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header bg-warning text-dark rounded-top-3">
                <h5 class="modal-title">Confirmación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center">
                <p id="customConfirmText"></p>
            </div>
            <div class="modal-footer d-flex justify-content-center border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" id="confirmActionButton">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- Configuración Global ---
    const LOGGED_IN_PROFESSOR_ID = <?=$idProfesor ?>; // <<-- CAMBIAR ESTO POR EL ID DEL PROFESOR DE LA SESIÓN

    // --- Referencias DOM del Dashboard ---
    const professorNameHeader = document.getElementById('professorNameHeader');
    const profileFullName = document.getElementById('profileFullName');
    const profileEmail = document.getElementById('profileEmail');
    const profileEspecialidad = document.getElementById('profileEspecialidad');
    const assignedSubjectsList = document.getElementById('assignedSubjectsList');
    const totalStudentsCount = document.getElementById('totalStudentsCount');

    // --- Referencias DOM del Panel de Gestión de Clases ---
    const selectGestionAsignatura = document.getElementById('selectGestionAsignatura');
    const selectGestionAnio = document.getElementById('selectGestionAnio');
    const btnLoadStudentsForSubject = document.getElementById('btnLoadStudentsForSubject');
    const studentsListTitle = document.getElementById('studentsListTitle');
    const loadedStudentsCount = document.getElementById('loadedStudentsCount');
    const studentsGradesTableBody = document.getElementById('studentsGradesTableBody');

    // --- Variables para Almacenar Datos ---
    let allProfessorData = null; // Perfil del profesor
    let allAssignedSubjects = []; // Asignaturas asignadas al profesor
    let allAcademicYears = []; // Años académicos disponibles
    let currentLoadedStudents = []; // Estudiantes cargados para la asignatura/año seleccionados

    // --- Funciones de Utilidad (Modales de Mensaje/Confirmación) ---
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

    function mostrarConfirmacionModal(message, onConfirm) {
        let confModal = document.getElementById('customConfirmModal');
        if (!confModal) {
            confModal = document.createElement('div');
            confModal.id = 'customConfirmModal';
            confModal.classList.add('modal', 'fade');
            confModal.setAttribute('tabindex', '-1');
            confModal.setAttribute('aria-hidden', 'true');
            confModal.innerHTML = `
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content rounded-4 shadow-lg">
                        <div class="modal-header bg-warning text-dark rounded-top-3">
                            <h5 class="modal-title">Confirmación</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body text-center">
                            <p id="customConfirmText"></p>
                        </div>
                        <div class="modal-footer d-flex justify-content-center border-0">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary rounded-pill px-4" id="confirmActionButton">Confirmar</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(confModal);
        }

        document.getElementById('customConfirmText').textContent = message;
        const bsModal = new bootstrap.Modal(confModal);
        bsModal.show();

        const confirmButton = document.getElementById('confirmActionButton');
        const newConfirmHandler = () => {
            onConfirm();
            bsModal.hide();
            confirmButton.removeEventListener('click', newConfirmHandler);
        };
        confirmButton.addEventListener('click', newConfirmHandler);

        const dismissHandler = () => {
            confirmButton.removeEventListener('click', newConfirmHandler);
            confModal.removeEventListener('hidden.bs.modal', dismissHandler);
        };
        confModal.addEventListener('hidden.bs.modal', dismissHandler);
    }

    /**
     * Helper para poblar dropdowns.
     */
    function populateDropdown(selectElement, data, valueKey, textKey, defaultOptionText) {
        selectElement.innerHTML = `<option value="">${defaultOptionText}</option>`;
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = item[textKey];
            selectElement.appendChild(option);
        });
    }

    // --- Lógica de Carga del Dashboard del Profesor ---
    async function loadProfessorDashboard() {
        if (!LOGGED_IN_PROFESSOR_ID) {
            mostrarMensajeModal('No se ha especificado un ID de profesor. Por favor, inicie sesión.');
            return;
        }

        try {
            const res = await fetch(`../api/obtener_dashboard_profesor_data.php?id_profesor=${LOGGED_IN_PROFESSOR_ID}`);
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status && data.data) {
                allProfessorData = data.data.profesor_info;
                allAssignedSubjects = data.data.asignaturas_asignadas;
                allAcademicYears = data.data.anios_academicos;
                const totalStudentsUnderCharge = data.data.total_alumnos_a_cargo;

                // Renderizar Perfil
                professorNameHeader.textContent = `${allProfessorData.nombre || ''} ${allProfessorData.apellido || ''}`;
                profileFullName.textContent = `${allProfessorData.nombre || ''} ${allProfessorData.apellido || ''}`;
                profileEmail.textContent = allProfessorData.email || 'N/A';
                profileEspecialidad.textContent = allProfessorData.especialidad || 'No definida';

                // Renderizar Asignaturas Asignadas
                renderAssignedSubjects(allAssignedSubjects);

                // Renderizar Conteo de Alumnos
                totalStudentsCount.textContent = totalStudentsUnderCharge;

                // Poblar los selects para la gestión de clases
                populateDropdown(selectGestionAsignatura, allAssignedSubjects, 'id_asignatura', 'nombre', 'Seleccione una asignatura...');
                populateDropdown(selectGestionAnio, allAcademicYears, 'id_anio', 'anio', 'Seleccione un año...');

            } else {
                mostrarMensajeModal('Error al cargar datos del dashboard del profesor: ' + (data.message || 'Datos inválidos.'));
                console.error("Error al cargar dashboard profesor:", data.message || data);
            }
        } catch (err) {
            mostrarMensajeModal('Error de conexión al cargar el dashboard del profesor. Por favor, intente de nuevo.');
            console.error("Error de conexión al cargar dashboard profesor:", err);
        }
    }

    /**
     * Renderiza la lista de asignaturas asignadas al profesor.
     * @param {Array} subjects - Array de objetos de asignaturas.
     */
    function renderAssignedSubjects(subjects) {
        assignedSubjectsList.innerHTML = '';
        if (subjects.length === 0) {
            assignedSubjectsList.innerHTML = '<p class="text-center text-muted">No tienes asignaturas asignadas.</p>';
            return;
        }

        const ul = document.createElement('ul');
        ul.classList.add('list-group', 'list-group-flush');
        subjects.forEach(subject => {
            const li = document.createElement('li');
            li.classList.add('list-group-item', 'd-flex', 'justify-content-between', 'align-items-center');
            li.innerHTML = `
                <div>
                    <strong>${subject.nombre}</strong> <span class="badge bg-secondary">${subject.codigo}</span>
                    <br><small class="text-muted">Curso: ${subject.curso_nombre || 'N/A'} | Semestre: ${subject.semestre_nombre || 'N/A'}</small>
                </div>
                <span class="badge bg-primary rounded-pill">${subject.alumnos_inscritos_count || 0} alumnos</span>
            `;
            ul.appendChild(li);
        });
        assignedSubjectsList.appendChild(ul);
    }

    // --- Lógica de Gestión de Clases y Alumnos ---

    // Habilitar/deshabilitar el botón "Cargar Alumnos"
    selectGestionAsignatura.addEventListener('change', toggleLoadStudentsButton);
    selectGestionAnio.addEventListener('change', toggleLoadStudentsButton);

    function toggleLoadStudentsButton() {
        const asignaturaId = selectGestionAsignatura.value;
        const anioId = selectGestionAnio.value;
        btnLoadStudentsForSubject.disabled = !(asignaturaId && anioId);
    }

    // Evento para cargar alumnos al hacer clic en el botón
    btnLoadStudentsForSubject.addEventListener('click', async () => {
        const asignaturaId = selectGestionAsignatura.value;
        const anioId = selectGestionAnio.value;

        if (!asignaturaId || !anioId) {
            mostrarMensajeModal('Por favor, selecciona una asignatura y un año académico.');
            return;
        }

        studentsGradesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Cargando alumnos...</td></tr>';
        loadedStudentsCount.textContent = '0';
        studentsListTitle.textContent = `Alumnos Inscritos: ${selectGestionAsignatura.options[selectGestionAsignatura.selectedIndex].textContent} (${selectGestionAnio.options[selectGestionAnio.selectedIndex].textContent})`;

        try {
            const res = await fetch(`../api/obtener_alumnos_y_notas.php?id_profesor=${LOGGED_IN_PROFESSOR_ID}&id_asignatura=${asignaturaId}&id_anio=${anioId}`);
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status && Array.isArray(data.data)) {
                currentLoadedStudents = data.data; // Cachea los datos de los alumnos
                renderStudentsGradesTable(currentLoadedStudents);
                loadedStudentsCount.textContent = currentLoadedStudents.length;
            } else {
                studentsGradesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Error al cargar alumnos: ' + (data.message || 'Datos inválidos.') + '</td></tr>';
                console.error("Error al cargar alumnos y notas:", data.message || data);
            }
        } catch (err) {
            studentsGradesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Error de conexión al cargar alumnos y notas.</td></tr>';
            console.error("Error de conexión al cargar alumnos y notas:", err);
        }
    });

    /**
     * Renderiza la tabla de estudiantes y sus notas.
     * @param {Array} students - Array de objetos de estudiantes con sus notas.
     */
    function renderStudentsGradesTable(students) {
        studentsGradesTableBody.innerHTML = '';
        if (students.length === 0) {
            studentsGradesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No hay alumnos inscritos en esta asignatura para el año seleccionado.</td></tr>';
            return;
        }
        
        students.forEach((student, index) => {
            const row = document.createElement('tr');
            row.dataset.inscripcionId = student.id_inscripcion; // Guarda el ID de la inscripción
            row.innerHTML = `
                <td>${index + 1}</td>
                <td>${student.nombre_estudiante || ''} ${student.apellido_estudiante || ''}</td>
                <td>${student.matricula || 'N/A'}</td>
                <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm grade-input" value="${student.parcial_1 !== null ? student.parcial_1 : ''}" data-grade-type="parcial_1"></td>
                <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm grade-input" value="${student.parcial_2 !== null ? student.parcial_2 : ''}" data-grade-type="parcial_2"></td>
                <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm grade-input" value="${student.examen_final !== null ? student.examen_final : ''}" data-grade-type="examen_final"></td>
                <td><strong class="calculated-promedio">${student.promedio !== null ? student.promedio : 'N/A'}</strong></td>
                <td><textarea class="form-control form-control-sm" rows="1" data-grade-type="observaciones">${student.observaciones || ''}</textarea></td>
                <td class="text-center grades-action-cell">
                    <button class="btn btn-sm btn-success rounded-pill px-3 me-1 btn-save-grades" data-inscripcion-id="${student.id_inscripcion}" title="Guardar Notas">
                        <i class="bi bi-save"></i>
                    </button>
                    <!-- <button class="btn btn-sm btn-info rounded-pill px-3 btn-manage-attendance" data-inscripcion-id="${student.id_inscripcion}" title="Gestionar Asistencia">
                        <i class="bi bi-calendar-check"></i>
                    </button> -->
                </td>
            `;
            studentsGradesTableBody.appendChild(row);
        });

        // Adjuntar event listeners para guardar notas
        document.querySelectorAll('.btn-save-grades').forEach(button => {
            button.addEventListener('click', async (e) => {
                // FIX: Usa e.currentTarget para asegurarte de que siempre obtienes el botón
                let clickedButton = e.currentTarget;
                let inscripcionId = clickedButton.dataset.inscripcionId; 
                console.log("Saving notes for inscripcionId:", inscripcionId); // Debugging line

                const row = clickedButton.closest('tr'); // Encuentra la fila contenedora
                const parcial1 = row.querySelector('[data-grade-type="parcial_1"]').value;
                const parcial2 = row.querySelector('[data-grade-type="parcial_2"]').value;
                const examenFinal = row.querySelector('[data-grade-type="examen_final"]').value;
                const observaciones = row.querySelector('[data-grade-type="observaciones"]').value;

                // Validación básica de notas
                if (parcial1 === '' && parcial2 === '' && examenFinal === '') {
                    mostrarMensajeModal('Debe ingresar al menos una nota para guardar.');
                    return;
                }

                // Deshabilitar botón mientras se guarda
                clickedButton.disabled = true;
                clickedButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                try {
                    const formData = new FormData();
                    formData.append('id_inscripcion', inscripcionId);
                    formData.append('parcial_1', parcial1 || null); // Enviar null si está vacío
                    formData.append('parcial_2', parcial2 || null);
                    formData.append('examen_final', examenFinal || null);
                    formData.append('observaciones', observaciones);

                    const res = await fetch('../api/guardar_notas.php', { // Ya actualizado a guardar en log.txt
                        method: 'POST',
                        body: formData
                    });
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    const data = await res.json();

                    if (data.status) {
                        mostrarMensajeModal('Notas guardadas exitosamente en el log.', () => {
                            // En este punto, las notas están en el log.txt, no en la BD.
                            // Si quieres que el promedio se actualice en el UI, debes recalcularlo aquí en el frontend.
                            const p1 = parseFloat(parcial1) || 0;
                            const p2 = parseFloat(parcial2) || 0;
                            const ef = parseFloat(examenFinal) || 0;

                            let count = 0;
                            if (parcial1 !== '') count++;
                            if (parcial2 !== '') count++;
                            if (examenFinal !== '') count++;

                            if (count > 0) {
                                const promedio = ((p1 + p2 + ef) / count).toFixed(2);
                                row.querySelector('.calculated-promedio').textContent = promedio;
                            } else {
                                row.querySelector('.calculated-promedio').textContent = 'N/A';
                            }
                            // Opcional: podrías recargar la tabla de alumnos si el log se procesara inmediatamente
                            // o si solo quieres ver los datos actualizados reflejados.
                            // loadStudentsForSubject(); // Recargaría la sección
                        });
                    } else {
                        mostrarMensajeModal('Error al guardar notas: ' + (data.message || 'Error desconocido.'));
                        console.error("Error al guardar notas:", data.message || data);
                    }
                } catch (err) {
                    mostrarMensajeModal('Error de conexión al guardar notas. Intente de nuevo.');
                    console.error("Error de conexión al guardar notas:", err);
                } finally {
                    clickedButton.disabled = false;
                    clickedButton.innerHTML = '<i class="bi bi-save"></i>';
                }
            });
        });

        // Event listener para calcular el promedio en tiempo real (opcional, para UX)
        document.querySelectorAll('.grade-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const row = e.target.closest('tr');
                const p1 = parseFloat(row.querySelector('[data-grade-type="parcial_1"]').value) || 0;
                const p2 = parseFloat(row.querySelector('[data-grade-type="parcial_2"]').value) || 0;
                const ef = parseFloat(row.querySelector('[data-grade-type="examen_final"]').value) || 0;

                let count = 0;
                if (row.querySelector('[data-grade-type="parcial_1"]').value !== '') count++;
                if (row.querySelector('[data-grade-type="parcial_2"]').value !== '') count++;
                if (row.querySelector('[data-grade-type="examen_final"]').value !== '') count++;

                if (count > 0) {
                    const promedio = ((p1 + p2 + ef) / count).toFixed(2);
                    row.querySelector('.calculated-promedio').textContent = promedio;
                } else {
                    row.querySelector('.calculated-promedio').textContent = 'N/A';
                }
            });
        });
    }

    // --- Carga inicial del Dashboard ---
    document.addEventListener('DOMContentLoaded', loadProfessorDashboard);
</script>
<?php include 'footer.php'; ?>