
<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../index.php");
    exit;
}

$id_estudiante =  $_SESSION['id_usuario'];
require '../includes/conexion.php';
 
?>

<?php include 'header.php'; ?>
<style>
        body {
            font-family: "Inter", sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
        }
        .btn.rounded-pill,
        .form-select.rounded-pill,
        .form-control.rounded-pill {
            border-radius: 50rem !important;
        }
        .modal-header.bg-primary {
            background-color: #007bff !important;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        .subject-item {
            cursor: pointer;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            background-color: #fff;
        }
        .subject-item:hover {
            background-color: #e9f5ff;
            border-color: #007bff;
        }
        .subject-item.selected {
            background-color: #cce5ff;
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .subject-item.disabled {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.7;
            border-style: dashed;
        }
        .subject-item.disabled .form-check-input {
            pointer-events: none;
        }
        .subject-item .badge {
            font-size: 0.75em;
            vertical-align: middle;
            margin-left: 5px;
        }
        .prerequisite-not-met {
            color: #dc3545;
            font-weight: bold;
        }
        .already-passed {
            color: #28a745;
            font-weight: bold;
        }
        .current-enrollment {
            color: #ffc107;
            font-weight: bold;
        }
        .reproved-subject {
            color: #fd7e14;
            font-weight: bold;
        }
    </style>
 

<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="mb-4 text-primary"><i class="bi bi-person-fill me-3"></i> Seccíon de Estudiante</h2>
        <p class="lead text-muted">Aquí puedes gestionar tus asignaturas.</p>
        <button class="btn btn-primary rounded-pill px-5 py-3 mt-4" onclick="openInscripcionModal(LOGGED_IN_STUDENT_ID)">
            <i class="bi bi-plus-circle me-2"></i> Inscribir Asignaturas
        </button>
    </div>
</div>

<!-- Modal de Inscripción de Asignaturas -->
<div class="modal fade" id="modalInscripcionAsignaturas" tabindex="-1" aria-labelledby="modalInscripcionAsignaturasLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form id="formInscripcionAsignaturas" class="modal-content rounded-4 shadow-lg">
      <div class="modal-header bg-primary text-white rounded-top-3 p-4">
        <h5 class="modal-title fs-5" id="modalInscripcionAsignaturasLabel"><i class="bi bi-plus-circle me-2"></i> Inscribir Asignaturas</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="id_estudiante" id="inscripcionEstudianteId">

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="inscripcionAnioAcademico" class="form-label">Año Académico</label>
                <select id="inscripcionAnioAcademico" name="id_anio" class="form-select rounded-pill" required>
                    <option value="">Cargando años...</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="inscripcionSemestre" class="form-label">Semestre de Inscripción</label>
                <select id="inscripcionSemestre" name="id_semestre" class="form-select rounded-pill" required>
                    <option value="">Cargando semestres...</option>
                </select>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-center" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>
            <div>
                Selecciona hasta 6 asignaturas para inscribirte en el semestre.
                <span id="selectedSubjectsCount" class="badge bg-secondary ms-2">0/6</span>
            </div>
        </div>
        <div id="validationMessages" class="alert alert-warning d-none" role="alert">
            <strong>Advertencias:</strong>
            <ul class="mb-0" id="messageList"></ul>
        </div>

        <h6 class="mb-3">Asignaturas Disponibles:</h6>
        <div id="asignaturasList" class="row row-cols-1 row-cols-md-2 g-3">
            <!-- Asignaturas se cargarán aquí -->
            <div class="col"><p class="text-center text-muted">Cargando asignaturas...</p></div>
        </div>

      </div>
      <div class="modal-footer d-flex justify-content-center border-0 p-4 pt-0">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i> Cancelar</button>
        <button type="submit" class="btn btn-success rounded-pill px-4" id="btnGuardarInscripciones"><i class="bi bi-save me-2"></i> Guardar Inscripción</button>
      </div>
    </form>
  </div>
</div>

<!-- Modales de Mensaje/Confirmación (reutilizados del módulo de notas) -->
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
    const LOGGED_IN_STUDENT_ID = <?= $id_estudiante ?>;  // <<-- CAMBIAR ESTO POR EL ID DEL ESTUDIANTE DE LA SESIÓN

    // --- Referencias DOM del Modal de Inscripción ---
    const modalInscripcionAsignaturas = new bootstrap.Modal(document.getElementById('modalInscripcionAsignaturas'));
    const formInscripcionAsignaturas = document.getElementById('formInscripcionAsignaturas');
    const inscripcionEstudianteIdInput = document.getElementById('inscripcionEstudianteId');
    const inscripcionAnioAcademicoSelect = document.getElementById('inscripcionAnioAcademico');
    const inscripcionSemestreSelect = document.getElementById('inscripcionSemestre');
    const asignaturasListContainer = document.getElementById('asignaturasList');
    const selectedSubjectsCountSpan = document.getElementById('selectedSubjectsCount');
    const validationMessagesAlert = document.getElementById('validationMessages');
    const validationMessageList = document.getElementById('messageList');
    const btnGuardarInscripciones = document.getElementById('btnGuardarInscripciones');

    // --- Variables para Almacenar Datos ---
    let allAsignaturas = []; // Todas las asignaturas con sus requisitos
    let studentHistorial = []; // Historial académico del estudiante
    let studentCurrentEnrollments = []; // Inscripciones activas/preinscritas del estudiante para el semestre/año actual
    let selectedSubjects = new Set(); // Para mantener un registro de las asignaturas seleccionadas

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

    // --- Lógica de Apertura y Carga del Modal ---
    async function openInscripcionModal(studentId) {
        inscripcionEstudianteIdInput.value = studentId;
        formInscripcionAsignaturas.reset();
        selectedSubjects.clear();
        updateSelectedCount();
        validationMessagesAlert.classList.add('d-none');
        asignaturasListContainer.innerHTML = '<div class="col"><p class="text-center text-muted">Cargando datos...</p></div>';
        btnGuardarInscripciones.disabled = true; // Deshabilitar hasta que se carguen los datos

        try {
            const res = await fetch(`../api/obtener_inscripcion_data.php?id_estudiante=${studentId}`);
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status) {
                // Almacenar datos para el procesamiento del frontend
                allAsignaturas = data.data.asignaturas || [];
                studentHistorial = data.data.historial_academico || [];
                studentCurrentEnrollments = data.data.inscripciones_activas || [];

                // Cargar años académicos
                inscripcionAnioAcademicoSelect.innerHTML = '<option value="">Seleccione Año Académico</option>';
                data.data.anios_academicos.forEach(anio => {
                    const option = document.createElement('option');
                    option.value = anio.id_anio;
                    option.textContent = anio.anio;
                    inscripcionAnioAcademicoSelect.appendChild(option);
                    // Seleccionar el año activo/actual si existe y es el primero
                    if (data.data.anio_activo && anio.id_anio === data.data.anio_activo.id_anio) {
                        inscripcionAnioAcademicoSelect.value = anio.id_anio;
                    }
                });

                // Cargar semestres (asumo que son globales o para el curso del estudiante)
                inscripcionSemestreSelect.innerHTML = '<option value="">Seleccione Semestre</option>';
                // Aquí, podrías filtrar los semestres si la regla "primero-tercero-quinto" es estricta por nombre de semestre
                // Por ahora, cargamos todos los semestres disponibles en la DB.
                data.data.semestres.forEach(semestre => {
                    const option = document.createElement('option');
                    option.value = semestre.id_semestre;
                    option.textContent = semestre.nombre;
                    inscripcionSemestreSelect.appendChild(option);
                });

                // Renderizar asignaturas después de que los selects estén poblados
                renderAsignaturasList();
                btnGuardarInscripciones.disabled = false; // Habilitar el botón una vez que los datos están cargados
            } else {
                mostrarMensajeModal('Error al cargar datos para inscripción: ' + (data.message || 'Datos inválidos.'));
                asignaturasListContainer.innerHTML = '<div class="col"><p class="text-center text-danger">Error al cargar datos.</p></div>';
                console.error("Error al cargar inscripción data:", data.message || data);
            }
        } catch (err) {
            mostrarMensajeModal('Error de conexión al cargar datos para inscripción.');
            asignaturasListContainer.innerHTML = '<div class="col"><p class="text-center text-danger">Error de conexión.</p></div>';
            console.error("Error de conexión al cargar inscripción data:", err);
        } finally {
            modalInscripcionAsignaturas.show();
        }
    }

    /**
     * Comprueba si una asignatura fue aprobada por el estudiante.
     * @param {number} asignaturaId - ID de la asignatura.
     * @returns {boolean} True si fue aprobada.
     */
    function hasPassed(asignaturaId) {
        return studentHistorial.some(item =>
            item.id_asignatura == asignaturaId && item.resultado === 'aprobado'
        );
    }

    /**
     * Comprueba si una asignatura tiene sus prerrequisitos cumplidos.
     * @param {number} asignaturaId - ID de la asignatura.
     * @returns {object} {met: boolean, missing: Array<string>}
     */
    function checkPrerequisites(asignaturaId) {
        const asignatura = allAsignaturas.find(a => a.id_asignatura == asignaturaId);
        if (!asignatura || !asignatura.requisitos || asignatura.requisitos.length === 0) {
            return { met: true, missing: [] }; // No tiene prerrequisitos
        }

        const missingPrereqs = [];
        for (const req of asignatura.requisitos) {
            if (!hasPassed(req.requisito_id)) {
                const reqAsignatura = allAsignaturas.find(a => a.id_asignatura == req.requisito_id);
                missingPrereqs.push(reqAsignatura ? reqAsignatura.nombre : `ID ${req.requisito_id}`);
            }
        }
        return { met: missingPrereqs.length === 0, missing: missingPrereqs };
    }

    /**
     * Comprueba si el estudiante ya está inscrito o se inscribió previamente en esta asignatura
     * para el semestre/año seleccionado con un estado "no finalizado".
     * @param {number} asignaturaId
     * @returns {string|null} "current" si está en la inscripción actual, "reproved" si está reprobada, null si no.
     */
    function getEnrollmentStatus(asignaturaId) {
        // Asignatura reprobada en historial
        const reprovedInHistorial = studentHistorial.some(item =>
            item.id_asignatura == asignaturaId && item.resultado === 'reprobado'
        );
        if (reprovedInHistorial) {
            return 'reproved';
        }

        // Inscripción activa/preinscrita actual
        const currentEnrollment = studentCurrentEnrollments.some(item =>
            item.id_asignatura == asignaturaId &&
            (item.estado === 'preinscrito' || item.estado === 'confirmado') // Considera otros estados si aplica
        );
        if (currentEnrollment) {
            return 'current';
        }

        return null;
    }


    /**
     * Renderiza la lista de asignaturas disponibles con checkboxes y estados.
     */
    function renderAsignaturasList() {
        asignaturasListContainer.innerHTML = '';
        if (allAsignaturas.length === 0) {
            asignaturasListContainer.innerHTML = '<div class="col"><p class="text-center text-muted">No hay asignaturas disponibles para tu curso.</p></div>';
            return;
        }

        allAsignaturas.forEach(asignatura => {
            const passed = hasPassed(asignatura.id_asignatura);
            const prereqCheck = checkPrerequisites(asignatura.id_asignatura);
            const enrollmentStatus = getEnrollmentStatus(asignatura.id_asignatura);

            // Determinar si la asignatura debe estar deshabilitada
            const isDisabled = passed || !prereqCheck.met || enrollmentStatus === 'current'; // No se puede seleccionar si ya está aprobada, no cumple prerrequisitos o ya está inscrita
            const isReproved = enrollmentStatus === 'reproved'; // Es una asignatura que reprobó y puede arrastrar

            const colDiv = document.createElement('div');
            colDiv.classList.add('col');

            const itemDiv = document.createElement('div');
            itemDiv.classList.add('subject-item', 'd-flex', 'align-items-center', 'form-check', 'shadow-sm');
            if (isDisabled) itemDiv.classList.add('disabled');
            if (isReproved) itemDiv.classList.add('reproved-subject'); // Estilo para asignaturas reprobadas

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.classList.add('form-check-input', 'me-3');
            checkbox.id = `subject-${asignatura.id_asignatura}`;
            checkbox.value = asignatura.id_asignatura;
            checkbox.disabled = isDisabled; // Deshabilitar el checkbox
            checkbox.dataset.isReproved = isReproved ? 'true' : 'false'; // Marcar si es de arrastre

            // Marcar como seleccionado si es una asignatura reprobada (para arrastre)
            if (isReproved) {
                checkbox.checked = true;
                selectedSubjects.add(asignatura.id_asignatura);
            }

            itemDiv.addEventListener('click', () => {
                if (!isDisabled) { // Solo permitir clic si no está deshabilitado
                    checkbox.checked = !checkbox.checked;
                    handleSubjectSelection(checkbox, asignatura.id_asignatura);
                }
            });

            checkbox.addEventListener('change', (e) => handleSubjectSelection(e.target, asignatura.id_asignatura));

            let statusBadges = '';
            if (passed) {
                statusBadges += '<span class="badge bg-success me-1">Aprobada</span>';
            }
            if (!prereqCheck.met) {
                statusBadges += `<span class="badge bg-danger me-1">Req. Pendiente</span>`;
            }
            if (isReproved) {
                statusBadges += '<span class="badge bg-warning text-dark me-1">Reprobada (Arrastre)</span>';
            }
             if (enrollmentStatus === 'current' && !isReproved) { // Si ya está inscrito y no es por arrastre
                statusBadges += '<span class="badge bg-info me-1">Ya Inscrita</span>';
            }


            itemDiv.innerHTML = `
                ${checkbox.outerHTML}
                <label class="form-check-label flex-grow-1" for="subject-${asignatura.id_asignatura}">
                    <h6 class="mb-0">${asignatura.nombre} <small class="text-muted">(${asignatura.codigo})</small> ${statusBadges}</h6>
                    <small class="text-muted">Semestre: ${asignatura.semestre_nombre || 'N/A'}</small><br>
                    ${!prereqCheck.met ? `<small class="prerequisite-not-met">Req: ${prereqCheck.missing.join(', ')}</small>` : ''}
                </label>
            `;
            colDiv.appendChild(itemDiv);
            asignaturasListContainer.appendChild(colDiv);
        });
        updateSelectedCount(); // Actualiza el contador inicial
        validateForm(); // Valida el estado inicial del formulario
    }

    /**
     * Maneja la selección/deselección de asignaturas.
     * @param {HTMLInputElement} checkbox - El checkbox de la asignatura.
     * @param {number} asignaturaId - El ID de la asignatura.
     */
    function handleSubjectSelection(checkbox, asignaturaId) {
        if (checkbox.checked) {
            if (selectedSubjects.size >= 6) {
                checkbox.checked = false; // Desselecciona si excede el límite
                mostrarMensajeModal('No puedes seleccionar más de 6 asignaturas.');
                return;
            }
            selectedSubjects.add(asignaturaId);
        } else {
            // No permitir deseleccionar asignaturas reprobadas si es un "arrastre" obligatorio
            if (checkbox.dataset.isReproved === 'true') {
                 checkbox.checked = true; // Forzar que siga seleccionada
                 mostrarMensajeModal('Debes inscribir esta asignatura reprobada. No se puede deseleccionar.');
                 return;
            }
            selectedSubjects.delete(asignaturaId);
        }
        updateSelectedCount();
        validateForm();
    }

    /**
     * Actualiza el contador de asignaturas seleccionadas.
     */
    function updateSelectedCount() {
        selectedSubjectsCountSpan.textContent = `${selectedSubjects.size}/6`;
    }

    /**
     * Realiza validaciones en el formulario antes del envío.
     */
    function validateForm() {
        validationMessageList.innerHTML = '';
        validationMessagesAlert.classList.add('d-none');
        btnGuardarInscripciones.disabled = false; // Habilitar por defecto y deshabilitar si hay errores

        const messages = [];

        // Validación de cantidad de asignaturas
        if (selectedSubjects.size === 0) {
            messages.push('Debes seleccionar al menos una asignatura.');
        }
        if (selectedSubjects.size > 6) {
            messages.push('Has seleccionado más de 6 asignaturas. Por favor, deselecciona algunas.');
        }

        // Validación de prerrequisitos y asignaturas ya aprobadas para las seleccionadas
        selectedSubjects.forEach(id => {
            const asignatura = allAsignaturas.find(a => a.id_asignatura == id);
            if (!asignatura) return; // Esto no debería pasar

            // Check if already passed (should be disabled, but a final check)
            if (hasPassed(id) && !document.getElementById(`subject-${id}`).dataset.isReproved === 'true') {
                messages.push(`"${asignatura.nombre}" ya ha sido aprobada. No puedes volver a inscribirla.`);
            }

            // Check prerequisites
            const prereqCheck = checkPrerequisites(id);
            if (!prereqCheck.met) {
                messages.push(`"${asignatura.nombre}" requiere que hayas aprobado: ${prereqCheck.missing.join(', ')}.`);
            }
        });

        // Mostrar mensajes si existen
        if (messages.length > 0) {
            messages.forEach(msg => {
                const li = document.createElement('li');
                li.textContent = msg;
                validationMessageList.appendChild(li);
            });
            validationMessagesAlert.classList.remove('d-none');
            btnGuardarInscripciones.disabled = true; // Deshabilitar si hay errores de validación
        }
    }


    // --- Lógica de Envío del Formulario ---
    formInscripcionAsignaturas.addEventListener('submit', async e => {
        e.preventDefault();
        validateForm(); // Realizar validación final antes de enviar

        if (btnGuardarInscripciones.disabled) {
            mostrarMensajeModal('Por favor, corrige los errores en la selección de asignaturas.');
            return;
        }

        const id_estudiante = inscripcionEstudianteIdInput.value;
        const id_anio = inscripcionAnioAcademicoSelect.value;
        const id_semestre = inscripcionSemestreSelect.value;
        const selectedAsignaturas = Array.from(selectedSubjects); // Convertir Set a Array

        if (!id_estudiante || !id_anio || !id_semestre || selectedAsignaturas.length === 0) {
            mostrarMensajeModal('Por favor, completa todos los campos y selecciona al menos una asignatura.');
            return;
        }

        // Crear FormData para enviar los datos
        const formData = new FormData();
        formData.append('id_estudiante', id_estudiante);
        formData.append('id_anio', id_anio);
        formData.append('id_semestre', id_semestre);
        selectedAsignaturas.forEach(id => formData.append('asignaturas[]', id)); // Enviar como array

        // Marcar asignaturas como tipo 'arrastre' si es reprobada
        selectedAsignaturas.forEach(id => {
            const checkbox = document.getElementById(`subject-${id}`);
            if (checkbox && checkbox.dataset.isReproved === 'true') {
                formData.append('tipos_inscripcion[]', `${id}:arrastre`);
            } else {
                formData.append('tipos_inscripcion[]', `${id}:regular`);
            }
        });


        btnGuardarInscripciones.disabled = true;
        btnGuardarInscripciones.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Guardando...';

        try {
            const res = await fetch('../api/guardar_inscripciones.php', {
                method: 'POST',
                body: formData
            });
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status) {
                mostrarMensajeModal(data.message, () => {
                    modalInscripcionAsignaturas.hide();
                    // Opcional: recargar el dashboard o la sección de inscripciones del estudiante
                    // location.reload();
                });
            } else {
                mostrarMensajeModal('Error al guardar inscripciones: ' + (data.message || 'Error desconocido.'));
                console.error("Error al guardar inscripciones:", data.message || data);
            }
        } catch (err) {
            mostrarMensajeModal('Error de conexión al guardar inscripciones. Por favor, intente de nuevo.');
            console.error("Error de conexión al guardar inscripciones:", err);
        } finally {
            btnGuardarInscripciones.disabled = false;
            btnGuardarInscripciones.innerHTML = '<i class="bi bi-save me-2"></i> Guardar Inscripción';
        }
    });

    // --- Carga Inicial (simulando que el dashboard ya tiene el LOGGED_IN_STUDENT_ID) ---
    document.addEventListener('DOMContentLoaded', () => {
        // Podrías precargar algunos datos si el modal se abre con frecuencia
        // Por ahora, todos los datos se cargan al abrir el modal.
    });

</script>
<?php include 'footer.php'; ?>