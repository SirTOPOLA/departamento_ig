<?php include_once('header.php'); ?>
<style>
        body {
            font-family: "Inter", sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
        }
        .form-control.rounded-pill,
        .form-select.rounded-pill,
        .btn.rounded-pill {
            border-radius: 50rem !important;
        }
        .modal-header.bg-primary {
            background-color: #007bff !important;
        }
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .table thead {
            background-color: #007bff;
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2f0ff;
        }
        .text-break-word {
            word-break: break-word;
        }
        .subject-row.confirmed {
            background-color: #d4edda; /* Light green for confirmed */
        }
        .subject-row.rejected {
            background-color: #f8d7da; /* Light red for rejected */
        }
        .subject-row.pending {
            background-color: #e2f0ff; /* Light blue for pending */
        }
        .subject-row.convalidated {
            background-color: #fff3cd; /* Light yellow for convalidated */
        }
        .action-cell {
            min-width: 200px; /* Asegura espacio para los botones */
        }
        .note-input {
            width: 80px; /* Tamaño más pequeño para la nota */
        }
    </style>
 

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">🎓 Gestión de Estudiantes - Administrador</h3>
      <!-- Este botón podría llevar a un modal de registro de usuario si quieres crear estudiantes aquí,
           o simplemente puedes omitirlo si los estudiantes se registran en otro lugar -->
      <!-- <button class="btn btn-success rounded-pill px-4" onclick="abrirModalRegistroEstudiante()">
        <i class="bi bi-plus-circle me-2"></i> Nuevo Estudiante
      </button> -->
    </div>

    <!-- Formulario buscador dinámico -->
    <div class="mb-3" style="max-width: 400px;">
      <div class="input-group shadow-sm rounded-pill overflow-hidden">
        <input type="search" id="busquedaEstudiante" class="form-control border-0 ps-3" placeholder="Buscar por nombre, email, DNI o matrícula" />
        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
      </div>
    </div>

    <div class="table-responsive shadow-sm rounded-3">
      <table id="tablaEstudiantes" class="table table-hover table-striped align-middle" style="width:100%">
        <thead class="table-primary">
          <tr>
            <th>#</th>
            <th>Nombre Completo</th>
            <th>Email / DNI</th>
            <th>Matrícula</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="listaEstudiantes">
          <!-- Aquí se cargarán los estudiantes dinámicamente -->
          <tr>
            <td colspan="6" class="text-center py-4">Cargando estudiantes...</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación (si el backend la soporta, de lo contrario, se haría en JS si se carga todo) -->
    <nav aria-label="Paginación de estudiantes" class="mt-4" style="display: none;">
      <ul class="pagination justify-content-center" id="paginacionEstudiantes">
        <!-- Los elementos de paginación se generarían aquí si fuera necesario -->
      </ul>
    </nav>
  </div>
</div>

<!-- Modal para Validar Inscripciones -->
<div class="modal fade" id="modalValidarInscripcion" tabindex="-1" aria-labelledby="modalValidarInscripcionLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form id="formValidarInscripcion" class="modal-content rounded-4 shadow-lg">
            <div class="modal-header bg-primary text-white rounded-top-3 p-4">
                <h5 class="modal-title fs-5" id="modalValidarInscripcionLabel">✅ Validar Inscripciones de <span id="validarInscripcionEstudianteNombre"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id_estudiante" id="validarInscripcionEstudianteId">

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="validarInscripcionAnio" class="form-label">Año Académico</label>
                        <select id="validarInscripcionAnio" name="id_anio" class="form-select rounded-pill" required>
                            <option value="">Seleccione un año...</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="validarInscripcionSemestre" class="form-label">Semestre</label>
                        <select id="validarInscripcionSemestre" name="id_semestre" class="form-select rounded-pill" required>
                            <option value="">Seleccione un semestre...</option>
                        </select>
                    </div>
                </div>

                <div id="preinscripcionesList" class="mt-4">
                    <p class="text-center text-muted">Seleccione Año y Semestre para ver las preinscripciones.</p>
                </div>

            </div>
            <div class="modal-footer d-flex justify-content-center border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i> Cancelar</button>
                <button type="submit" class="btn btn-success rounded-pill px-4" id="btnGuardarValidacion"><i class="bi bi-save me-2"></i> Guardar Cambios</button>
            </div>
        </form>
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
    // --- Referencias DOM del Listado de Estudiantes ---
    const listaEstudiantes = document.getElementById('listaEstudiantes');
    const busquedaEstudianteInput = document.getElementById('busquedaEstudiante');
    let allEstudiantesData = []; // Para almacenar todos los estudiantes y permitir búsqueda en tiempo real

    // --- Referencias DOM del Modal de Validación de Inscripciones ---
    const modalValidarInscripcion = new bootstrap.Modal(document.getElementById('modalValidarInscripcion'));
    const formValidarInscripcion = document.getElementById('formValidarInscripcion');
    const validarInscripcionEstudianteIdInput = document.getElementById('validarInscripcionEstudianteId');
    const validarInscripcionEstudianteNombreSpan = document.getElementById('validarInscripcionEstudianteNombre');
    const validarInscripcionAnioSelect = document.getElementById('validarInscripcionAnio');
    const validarInscripcionSemestreSelect = document.getElementById('validarInscripcionSemestre');
    const preinscripcionesListContainer = document.getElementById('preinscripcionesList');
    const btnGuardarValidacion = document.getElementById('btnGuardarValidacion');

    // --- Variables de Datos Globales para el Modal de Validación ---
    let currentStudentIdToValidate = null;
    let allInscripcionesData = []; // Todas las preinscripciones para el estudiante, año y semestre seleccionados
    let allAniosAcademicos = [];
    let allSemestres = [];
    let studentHistorialForValidation = []; // Historial para validación de resultados

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

    // --- Lógica para Cargar Estudiantes (Admin View) ---
    async function cargarEstudiantes(searchTerm = '') {
        listaEstudiantes.innerHTML = '<tr><td colspan="6" class="text-center py-4">Cargando estudiantes...</td></tr>';

        if (allEstudiantesData.length === 0 && !searchTerm) {
            try {
                const res = await fetch('../api/obtener_estudiantes_para_admin.php'); // Nueva API para obtener todos los estudiantes del admin
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const data = await res.json();

                if (data.status && Array.isArray(data.data)) {
                    allEstudiantesData = data.data;
                } else {
                    listaEstudiantes.innerHTML = '<tr><td colspan="6" class="text-center py-4">Error al cargar estudiantes: ' + (data.message || 'Datos inválidos') + '</td></tr>';
                    console.error("Error o formato de datos incorrecto para estudiantes:", data.message || data);
                    return;
                }
            } catch (err) {
                listaEstudiantes.innerHTML = '<tr><td colspan="6" class="text-center py-4">Error de conexión al cargar estudiantes.</td></tr>';
                console.error("Error de conexión al cargar estudiantes:", err);
                return;
            }
        }

        const filteredEstudiantes = allEstudiantesData.filter(estudiante => {
            const searchLower = searchTerm.toLowerCase();
            return (estudiante.nombre + ' ' + (estudiante.apellido || '')).toLowerCase().includes(searchLower) ||
                   (estudiante.email || '').toLowerCase().includes(searchLower) ||
                   (estudiante.dni || '').toLowerCase().includes(searchLower) ||
                   (estudiante.matricula || '').toLowerCase().includes(searchLower);
        });

        renderEstudiantesTable(filteredEstudiantes);
    }

    function renderEstudiantesTable(estudiantes) {
        listaEstudiantes.innerHTML = '';
        if (estudiantes.length === 0) {
            listaEstudiantes.innerHTML = '<tr><td colspan="6" class="text-center py-4">No se encontraron estudiantes.</td></tr>';
            return;
        }

        estudiantes.forEach(estudiante => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${estudiante.id_usuario || 'N/A'}</td>
                <td>${estudiante.nombre || ''} ${estudiante.apellido || ''}</td>
                <td class="text-break-word">${estudiante.email || 'N/A'}<br><small class="text-muted">${estudiante.dni || 'N/A'}</small></td>
                <td><span class="badge bg-info text-dark rounded-pill">${estudiante.matricula || 'N/A'}</span></td>
                <td>
                  <span class="badge rounded-pill ${estudiante.estado ? 'bg-success' : 'bg-danger'}">
                    ${estudiante.estado ? 'Activo' : 'Inactivo'}
                  </span>
                </td>
                <td class="action-cell">
                  <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick="openValidarInscripcionModal(${estudiante.id_usuario}, '${estudiante.nombre} ${estudiante.apellido}')" title="Validar Inscripciones">
                    <i class="bi bi-check2-square me-1"></i> Validar Insc.
                  </button>
                  <!-- Otros botones de acción si los tienes (editar, cambiar estado) -->
                </td>
            `;
            listaEstudiantes.appendChild(row);
        });
    }

    let searchTimeoutEstudiante;
    busquedaEstudianteInput.addEventListener('input', () => {
        clearTimeout(searchTimeoutEstudiante);
        searchTimeoutEstudiante = setTimeout(() => {
            cargarEstudiantes(busquedaEstudianteInput.value);
        }, 300);
    });

    // --- Lógica del Modal de Validación de Inscripciones ---

    async function openValidarInscripcionModal(studentId, studentName) {
        currentStudentIdToValidate = studentId;
        validarInscripcionEstudianteIdInput.value = studentId;
        validarInscripcionEstudianteNombreSpan.textContent = studentName;
        formValidarInscripcion.reset(); // Limpiar el formulario
        preinscripcionesListContainer.innerHTML = '<p class="text-center text-muted">Cargando datos...</p>';
        btnGuardarValidacion.disabled = true; // Deshabilitar hasta que se carguen los datos

        try {
            const res = await fetch(`../api/obtener_preinscripciones_para_admin.php?id_estudiante=${studentId}`);
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status) {
                allAniosAcademicos = data.data.anios_academicos || [];
                allSemestres = data.data.semestres || [];
                studentHistorialForValidation = data.data.historial_academico || []; // Historial del estudiante para el frontend
                allInscripcionesData = []; // Limpiar antes de poblar con preinscripciones filtradas por select

                // Poblar dropdowns de Año y Semestre
                populateDropdown(validarInscripcionAnioSelect, allAniosAcademicos, 'id_anio', 'anio', 'Seleccione un año...');
                populateDropdown(validarInscripcionSemestreSelect, allSemestres, 'id_semestre', 'nombre', 'Seleccione un semestre...');

                // Preseleccionar el año académico actual del estudiante si está disponible
                if (data.data.current_academic_year_id) {
                    validarInscripcionAnioSelect.value = data.data.current_academic_year_id;
                }

                // Cargar las preinscripciones por primera vez (si hay año y semestre preseleccionados o el primero de cada)
                // Usar un evento 'change' simulado para cargar las inscripciones iniciales
                if (validarInscripcionAnioSelect.value && validarInscripcionSemestreSelect.value) {
                     loadPreinscripcionesForPeriod(validarInscripcionAnioSelect.value, validarInscripcionSemestreSelect.value, data.data.preinscripciones);
                } else if (allAniosAcademicos.length > 0 && allSemestres.length > 0) {
                    // Seleccionar el primer año y semestre por defecto si no hay uno actual
                    validarInscripcionAnioSelect.value = allAniosAcademicos[0].id_anio;
                    validarInscripcionSemestreSelect.value = allSemestres[0].id_semestre;
                    loadPreinscripcionesForPeriod(allAniosAcademicos[0].id_anio, allSemestres[0].id_semestre, data.data.preinscripciones);
                } else {
                    preinscripcionesListContainer.innerHTML = '<p class="text-center text-muted">No hay años o semestres disponibles para cargar inscripciones.</p>';
                }

                btnGuardarValidacion.disabled = false; // Habilitar el botón
            } else {
                mostrarMensajeModal('Error al cargar datos del modal de validación: ' + (data.message || 'Datos inválidos.'));
                console.error("Error al cargar obtener_preinscripciones_para_admin:", data.message || data);
            }
        } catch (err) {
            mostrarMensajeModal('Error de conexión al abrir el modal de validación.');
            console.error("Error de conexión al obtener_preinscripciones_para_admin:", err);
        } finally {
            modalValidarInscripcion.show();
        }
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

    /**
     * Carga y renderiza las preinscripciones para el año y semestre seleccionados.
     * @param {number} anioId - ID del año académico.
     * @param {number} semestreId - ID del semestre.
     * @param {Array} initialPreinscripciones - Opcional, datos de preinscripciones iniciales para no hacer otra llamada.
     */
    async function loadPreinscripcionesForPeriod(anioId, semestreId, initialPreinscripciones = null) {
        preinscripcionesListContainer.innerHTML = '<p class="text-center text-muted">Cargando preinscripciones...</p>';
        allInscripcionesData = []; // Resetear

        try {
            let dataToProcess = initialPreinscripciones;
            if (!dataToProcess) { // Si no se pasaron datos iniciales, hacer una nueva llamada
                const res = await fetch(`../api/obtener_preinscripciones_para_admin.php?id_estudiante=${currentStudentIdToValidate}&id_anio=${anioId}&id_semestre=${semestreId}`);
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const data = await res.json();
                if (data.status && Array.isArray(data.data.preinscripciones)) {
                    dataToProcess = data.data.preinscripciones;
                    studentHistorialForValidation = data.data.historial_academico || []; // Actualizar historial si se hace nueva llamada
                } else {
                    preinscripcionesListContainer.innerHTML = '<p class="text-center text-danger">Error al cargar preinscripciones: ' + (data.message || 'Datos inválidos.') + '</p>';
                    console.error("Error o formato de datos incorrecto al cargar preinscripciones:", data.message || data);
                    return;
                }
            } else {
                // Filtrar las inscripciones iniciales para el periodo seleccionado
                dataToProcess = dataToProcess.filter(insc => insc.id_anio == anioId && insc.id_semestre == semestreId);
            }

            allInscripcionesData = dataToProcess; // Cachear para el envío del formulario
            renderPreinscripcionesTable(allInscripcionesData);

        } catch (err) {
            preinscripcionesListContainer.innerHTML = '<p class="text-center text-danger">Error de conexión al cargar preinscripciones.</p>';
            console.error("Error de conexión al cargar preinscripciones para el periodo:", err);
        }
    }

    /**
     * Renderiza la tabla de preinscripciones en el modal.
     */
    function renderPreinscripcionesTable(inscripciones) {
        preinscripcionesListContainer.innerHTML = ''; // Limpiar

        if (inscripciones.length === 0) {
            preinscripcionesListContainer.innerHTML = '<p class="text-center text-muted">No hay preinscripciones para el año y semestre seleccionados.</p>';
            return;
        }

        let tableHtml = `
            <table class="table table-bordered table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Asignatura</th>
                        <th>Tipo</th>
                        <th>Estado Inscripción</th>
                        <th>Resultado Historial</th>
                        <th>Nota Final (Opcional)</th>
                        <th>Observación (Opcional)</th>
                    </tr>
                </thead>
                <tbody>
        `;

        inscripciones.forEach(insc => {
            const isAlreadyConvalidated = studentHistorialForValidation.some(h =>
                h.id_asignatura == insc.id_asignatura && h.resultado === 'convalidado' && h.id_anio == insc.id_anio
            );
            const isAlreadyApproved = studentHistorialForValidation.some(h =>
                h.id_asignatura == insc.id_asignatura && h.resultado === 'aprobado'
            );

            // Determinar la clase de la fila basada en el estado de la inscripción
            let rowClass = '';
            if (insc.estado === 'confirmado') {
                rowClass = 'confirmed';
            } else if (insc.estado === 'rechazado') {
                rowClass = 'rejected';
            } else if (insc.estado === 'preinscrito') {
                rowClass = 'pending';
            }
            if (isAlreadyConvalidated) {
                rowClass = 'convalidated';
            }


            tableHtml += `
                <tr class="subject-row ${rowClass}">
                    <td>${insc.asignatura_nombre || 'N/A'} <br><small class="text-muted">${insc.semestre_nombre || ''}</small></td>
                    <td><span class="badge bg-secondary">${insc.tipo || 'regular'}</span></td>
                    <td>
                        <select name="inscripciones[${insc.id_inscripcion}][estado]" class="form-select form-select-sm" data-inscripcion-id="${insc.id_inscripcion}" required>
                            <option value="preinscrito" ${insc.estado === 'preinscrito' ? 'selected' : ''}>Preinscrito</option>
                            <option value="confirmado" ${insc.estado === 'confirmado' ? 'selected' : ''}>Confirmado</option>
                            <option value="rechazado" ${insc.estado === 'rechazado' ? 'selected' : ''}>Rechazado</option>
                        </select>
                    </td>
                    <td>
                        <select name="inscripciones[${insc.id_inscripcion}][resultado_historial]" class="form-select form-select-sm resultado-historial-select" data-inscripcion-id="${insc.id_inscripcion}">
                            <option value="">No aplica</option>
                            <option value="aprobado" ${isAlreadyApproved && !isAlreadyConvalidated ? 'selected' : ''} ${isAlreadyApproved && !isAlreadyConvalidated ? 'disabled' : ''}>Aprobado</option>
                            <option value="regular">Regular</option>
                            <option value="reprobado">Reprobado</option>
                            <option value="abandono">Abandono</option>
                            <option value="convalidado" ${isAlreadyConvalidated ? 'selected' : ''}>Convalidado</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" max="100" name="inscripciones[${insc.id_inscripcion}][nota_final]" class="form-control form-control-sm note-input" placeholder="Nota" ${isAlreadyConvalidated ? '' : 'disabled'}>
                    </td>
                    <td>
                        <textarea name="inscripciones[${insc.id_inscripcion}][observacion]" class="form-control form-control-sm" rows="1" placeholder="Observaciones"></textarea>
                    </td>
                </tr>
            `;
        });
        tableHtml += `</tbody></table>`;
        preinscripcionesListContainer.innerHTML = tableHtml;

        // Adjuntar event listeners para los selects de resultado_historial
        document.querySelectorAll('.resultado-historial-select').forEach(select => {
            select.addEventListener('change', (event) => {
                const row = event.target.closest('tr');
                const notaInput = row.querySelector('input[name*="[nota_final]"]');
                const observacionTextarea = row.querySelector('textarea[name*="[observacion]"]');
                const selectedResult = event.target.value;

                if (selectedResult === 'convalidado') {
                    notaInput.disabled = false;
                    observacionTextarea.disabled = false;
                    // Opcional: prellenar nota si es convalidado (ej. 100)
                    if (notaInput.value === '') notaInput.value = '100.00';
                    if (observacionTextarea.value === '') observacionTextarea.value = 'Convalidado por equivalencia.';
                } else if (selectedResult === 'aprobado' || selectedResult === 'reprobado') {
                    notaInput.disabled = false;
                    observacionTextarea.disabled = false;
                }
                else { // No aplica, Abandono, etc.
                    notaInput.disabled = true;
                    observacionTextarea.disabled = false; // Observación puede seguir siendo útil
                    notaInput.value = ''; // Limpiar nota si no aplica
                }
            });
            // Disparar el evento change al renderizar para aplicar el estado inicial
            select.dispatchEvent(new Event('change'));
        });
    }

    // Event listeners para los selects de Año y Semestre en el modal de validación
    validarInscripcionAnioSelect.addEventListener('change', () => {
        const anioId = validarInscripcionAnioSelect.value;
        const semestreId = validarInscripcionSemestreSelect.value;
        if (anioId && semestreId) {
            loadPreinscripcionesForPeriod(anioId, semestreId);
        } else {
            preinscripcionesListContainer.innerHTML = '<p class="text-center text-muted">Seleccione Año y Semestre para ver las preinscripciones.</p>';
        }
    });

    validarInscripcionSemestreSelect.addEventListener('change', () => {
        const anioId = validarInscripcionAnioSelect.value;
        const semestreId = validarInscripcionSemestreSelect.value;
        if (anioId && semestreId) {
            loadPreinscripcionesForPeriod(anioId, semestreId);
        } else {
            preinscripcionesListContainer.innerHTML = '<p class="text-center text-muted">Seleccione Año y Semestre para ver las preinscripciones.</p>';
        }
    });

    // --- Lógica de Envío del Formulario de Validación ---
    formValidarInscripcion.addEventListener('submit', async e => {
        e.preventDefault();

        mostrarConfirmacionModal('¿Estás seguro de que deseas guardar los cambios en las inscripciones?', async () => {
            btnGuardarValidacion.disabled = true;
            btnGuardarValidacion.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Guardando...';

            const formData = new FormData(formValidarInscripcion);
            // Incluir el ID del estudiante manualmente
            formData.append('id_estudiante', currentStudentIdToValidate);

            try {
                const res = await fetch('../api/procesar_validaciones_inscripcion.php', {
                    method: 'POST',
                    body: formData
                });
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const data = await res.json();

                if (data.status) {
                    mostrarMensajeModal(data.message, () => {
                        modalValidarInscripcion.hide();
                        cargarEstudiantes(); // Recargar la tabla principal de estudiantes
                    });
                } else {
                    mostrarMensajeModal('Error al guardar validaciones: ' + (data.message || 'Error desconocido.'));
                    console.error("Error al guardar validaciones:", data.message || data);
                }
            } catch (err) {
                mostrarMensajeModal('Error de conexión al guardar validaciones. Por favor, intente de nuevo.');
                console.error("Error de conexión al guardar validaciones:", err);
            } finally {
                btnGuardarValidacion.disabled = false;
                btnGuardarValidacion.innerHTML = '<i class="bi bi-save me-2"></i> Guardar Cambios';
            }
        });
    });

    // Carga inicial de estudiantes al cargar la página
    document.addEventListener('DOMContentLoaded', () => {
        cargarEstudiantes();
    });

</script>
<?php include_once('footer.php'); ?>