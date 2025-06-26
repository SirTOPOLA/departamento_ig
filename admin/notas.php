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
        color: white;
        /* Asegura el texto blanco para el fondo azul */
    }

    .modal-header.bg-warning {
        background-color: #ffc107 !important;
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

    .list-group {
        border-radius: 0.5rem;
    }

    /* Estilos para el modal de previsualización */
    #logPreviewTable tbody td {
        font-size: 0.85rem;
    }
</style>


<div class="content" id="content" tabindex="-1">
    <div class="container py-5">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-journal-check me-2"></i> Gestión de Notas Académicas</h3>

            <div class="d-flex align-items-center">
                <button id="btnVerLogPreview" class="btn btn-info rounded-pill px-4 me-2">
                    <i class="bi bi-eye me-2"></i> Ver Log de Notas
                    <span id="logPreviewCount" class="badge bg-light text-dark ms-2">0</span>
                </button>
                <button id="btnProcesarLog" class="btn btn-success rounded-pill px-4">
                    <i class="bi bi-arrow-repeat me-2"></i> Procesar Notas Pendientes
                    <span id="contadorLog" class="badge bg-danger ms-2">0</span>
                </button>
            </div>
        </div>

        <!-- Sección: Notas pendientes en Log (vista simplificada) -->
        <div class="mb-4 p-3 bg-white rounded-3 shadow-sm">
            <h5>Registros en Log (Total: <span id="countPendientes">0</span>)</h5>
            <div id="alertNotasPendientes" class="alert alert-info d-none">No hay notas pendientes en el log.</div>
            <ul id="listaNotasPendientes" class="list-group" style="max-height: 250px; overflow-y: auto;">
                <!-- Las líneas del log se cargarán aquí (vista simplificada) -->
                <li class="list-group-item text-center text-muted">Cargando registros...</li>
            </ul>
        </div>

        <!-- Buscador para notas registradas -->
        <div class="mb-3" style="max-width:400px;">
            <div class="input-group shadow-sm rounded-pill overflow-hidden">
                <input type="search" id="busquedaNotas" class="form-control border-0 ps-3"
                    placeholder="Buscar por estudiante, asignatura...">
                <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
            </div>
        </div>

        <!-- Tabla de notas ya registradas -->
        <div class="table-responsive shadow-sm rounded-3">
            <table id="tablaNotasRegistradas" class="table table-hover table-striped align-middle" style="width:100%">
                <thead class="table-primary">
                    <tr>
                        <th>#</th>
                        <th>Estudiante</th>
                        <th>Asignatura</th>
                        <th>Parcial 1</th>
                        <th>Parcial 2</th>
                        <th>Final</th>
                        <th>Promedio</th>
                        <th>Observaciones</th>
                        <!-- <th>Acción</th> -->
                    </tr>
                </thead>
                <tbody id="listaNotasRegistradas">
                    <!-- Las notas registradas se cargarán aquí -->
                    <tr>
                        <td colspan="8" class="text-center py-4">Cargando notas registradas...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Previsualizar Log de Notas -->
<div class="modal fade" id="modalPreviewLog" tabindex="-1" aria-labelledby="modalPreviewLogLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header bg-primary text-white rounded-top-3 p-4">
                <h5 class="modal-title fs-5" id="modalPreviewLogLabel"><i class="bi bi-file-earmark-text me-2"></i>
                    Contenido del Log de Notas (<span id="previewModalCount">0</span> Registros)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div id="logPreviewMessage" class="alert alert-info d-none"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>ID Inscripción</th>
                                <th>Parcial 1</th>
                                <th>Parcial 2</th>
                                <th>Examen Final</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="logPreviewTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted">Cargando contenido del log...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-center border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal"><i
                        class="bi bi-x-circle me-2"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modales de Mensaje/Confirmación (reutilizados) -->
<div class="modal fade" id="customMessageModal" tabindex="-1" aria-labelledby="customMessageModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header bg-primary text-white rounded-top-3">
                <h5 class="modal-title">Mensaje</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Cerrar"></button>
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

<div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmModalLabel"
    aria-hidden="true">
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
                <button type="button" class="btn btn-secondary rounded-pill px-4"
                    data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-pill px-4"
                    id="confirmActionButton">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Referencias a elementos del DOM
    const btnVerLogPreview = document.getElementById('btnVerLogPreview');
    const logPreviewCount = document.getElementById('logPreviewCount');
    const modalPreviewLog = new bootstrap.Modal(document.getElementById('modalPreviewLog'));
    const previewModalCountSpan = document.getElementById('previewModalCount');
    const logPreviewTableBody = document.getElementById('logPreviewTableBody');
    const logPreviewMessage = document.getElementById('logPreviewMessage');

    const btnProcesarLog = document.getElementById('btnProcesarLog');
    const contadorLog = document.getElementById('contadorLog');
    const countPendientes = document.getElementById('countPendientes');
    const alertNotasPendientes = document.getElementById('alertNotasPendientes');
    const listaNotasPendientes = document.getElementById('listaNotasPendientes');
    const busquedaNotasInput = document.getElementById('busquedaNotas');
    const listaNotasRegistradas = document.getElementById('listaNotasRegistradas');

    let allNotasRegistradasData = []; // Cache para la búsqueda dinámica

    // --- Funciones de Utilidad (Modales de Mensaje/Confirmación) ---
    function mostrarMensajeModal(message, callback = () => { }) {
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

    // --- Funciones de Carga y Renderización del Log de Notas ---

    /**
     * Carga el conteo de notas pendientes en el log y actualiza los contadores en la vista principal.
     */
    async function loadLogNotesCount() {
        try {
            const res = await fetch('../api/obtener_notas_log_count.php'); // API para obtener solo el conteo
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status) {
                logPreviewCount.textContent = data.count; // Contador en el botón "Ver Log"
                contadorLog.textContent = data.count;     // Contador en el botón "Procesar"
                countPendientes.textContent = data.count; // Contador en la sección "Actas Pendientes"

                // Actualizar la lista simplificada de actas pendientes
                if (data.raw_lines && data.raw_lines.length > 0) {
                    listaNotasPendientes.innerHTML = '';
                    data.raw_lines.forEach(line => {
                        const li = document.createElement('li');
                        li.classList.add('list-group-item', 'small', 'text-break-word');
                        li.textContent = line.substring(0, 100) + (line.length > 100 ? '...' : ''); // Mostrar solo un fragmento
                        listaNotasPendientes.appendChild(li);
                    });
                    alertNotasPendientes.classList.add('d-none');
                } else {
                    listaNotasPendientes.innerHTML = '';
                    alertNotasPendientes.classList.remove('d-none');
                }

                // Habilitar/deshabilitar el botón de procesar
                btnProcesarLog.disabled = (data.count === 0);
                btnVerLogPreview.disabled = (data.count === 0);

            } else {
                console.error("Error al obtener el conteo y líneas del log: ", data.message || data);
                logPreviewCount.textContent = 'Error';
                contadorLog.textContent = 'Error';
                countPendientes.textContent = 'Error';
                alertNotasPendientes.textContent = 'Error al cargar el log.';
                alertNotasPendientes.classList.remove('d-none');
                btnProcesarLog.disabled = true;
                btnVerLogPreview.disabled = true;
            }
        } catch (err) {
            console.error("Error de conexión al obtener conteo y líneas del log: ", err);
            logPreviewCount.textContent = 'Error';
            contadorLog.textContent = 'Error';
            countPendientes.textContent = 'Error';
            alertNotasPendientes.textContent = 'Error de conexión al cargar el log.';
            alertNotasPendientes.classList.remove('d-none');
            btnProcesarLog.disabled = true;
            btnVerLogPreview.disabled = true;
        }
    }

    /**
     * Abre el modal de previsualización del log y carga su contenido detallado.
     */
    btnVerLogPreview.addEventListener('click', async () => {
        logPreviewTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Cargando contenido del log...</td></tr>';
        logPreviewMessage.classList.add('d-none');
        previewModalCountSpan.textContent = '0';

        try {
            const res = await fetch('../api/obtener_notas_guardadas_log.php'); // API para obtener el contenido JSON parseado
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status && Array.isArray(data.data)) {
                if (data.data.length === 0) {
                    logPreviewTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">El log de notas está vacío.</td></tr>';
                    logPreviewMessage.classList.remove('d-none');
                    logPreviewMessage.classList.remove('alert-danger');
                    logPreviewMessage.classList.add('alert-info');
                    logPreviewMessage.textContent = 'No hay entradas en el log para previsualizar.';
                } else {
                    logPreviewTableBody.innerHTML = ''; // Limpiar antes de añadir
                    let malformedCount = 0;
                    data.data.forEach(entry => {
                        if (entry.parsed) { // Si la entrada JSON fue parseada correctamente
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${entry.timestamp || 'N/A'}</td>
                                <td>${entry.id_inscripcion || 'N/A'}</td>
                                <td>${entry.parcial_1 !== null ? entry.parcial_1 : '-'}</td>
                                <td>${entry.parcial_2 !== null ? entry.parcial_2 : '-'}</td>
                                <td>${entry.examen_final !== null ? entry.examen_final : '-'}</td>
                                <td>${entry.observaciones || 'Ninguna'}</td>
                            `;
                            logPreviewTableBody.appendChild(row);
                        } else {
                            malformedCount++;
                            const row = document.createElement('tr');
                            row.classList.add('table-danger');
                            row.innerHTML = `<td colspan="6"><strong>Error en línea:</strong> ${entry.raw_line.substring(0, 150) + (entry.raw_line.length > 150 ? '...' : '')} (Formato JSON inválido)</td>`;
                            logPreviewTableBody.appendChild(row);
                        }
                    });

                    previewModalCountSpan.textContent = data.data.length;

                    if (malformedCount > 0) {
                        logPreviewMessage.classList.remove('d-none');
                        logPreviewMessage.classList.remove('alert-info');
                        logPreviewMessage.classList.add('alert-danger');
                        logPreviewMessage.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i> Advertencia: Se encontraron <strong>${malformedCount}</strong> líneas con formato JSON inválido. Estas líneas serán ignoradas durante el procesamiento.`;
                    } else {
                        logPreviewMessage.classList.add('d-none');
                    }
                }
            } else {
                logPreviewTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar el contenido del log: ' + (data.message || 'Datos inválidos.') + '</td></tr>';
                logPreviewMessage.classList.remove('d-none');
                logPreviewMessage.classList.remove('alert-info');
                logPreviewMessage.classList.add('alert-danger');
                logPreviewMessage.textContent = 'Error al cargar el contenido del log.';
                console.error("Error o formato de datos incorrecto para log de notas:", data.message || data);
            }
        } catch (err) {
            logPreviewTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error de conexión al cargar el log de notas.</td></tr>';
            logPreviewMessage.classList.remove('d-none');
            logPreviewMessage.classList.remove('alert-info');
            logPreviewMessage.classList.add('alert-danger');
            logPreviewMessage.textContent = 'Error de conexión al cargar el log de notas.';
            console.error("Error de conexión al cargar log de notas:", err);
        } finally {
            modalPreviewLog.show();
        }
    });

    // --- Funcionalidad del botón "Procesar Notas Pendientes" (existente) ---
    btnProcesarLog.addEventListener('click', async () => {
        mostrarConfirmacionModal('¿Estás seguro de que deseas procesar todas las notas pendientes? Esto registrará las notas válidas en la base de datos y actualizará el historial académico.', async () => {
            btnProcesarLog.disabled = true;
            btnProcesarLog.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';

            try {
                // Llama al script de procesamiento que inserta en la BD y vacía el log
                const res = await fetch('../api/procesar_nota.php', { method: 'POST' });
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const data = await res.json();

                if (data.status) {
                    let successMessage = data.message;
                    if (data.errors && data.errors.length > 0) {
                        successMessage += '<br><br>Sin embargo, se encontraron errores en algunas líneas. Estas no fueron procesadas. Revise los detalles en la consola del navegador.';
                    }
                    mostrarMensajeModal(successMessage, () => {
                        // Recargar contadores y tablas después de procesar
                        loadLogNotesCount();
                        cargarNotasRegistradas();
                    });
                } else {
                    let errorMessage = 'Error al procesar notas: ' + (data.message || 'Ocurrió un error desconocido.');
                    if (data.errors && data.errors.length > 0) {
                        errorMessage += '<br><br>Detalles:<ul>';
                        data.errors.forEach(err => {
                            errorMessage += `<li>${err}</li>`;
                        });
                        errorMessage += '</ul>';
                    }
                    mostrarMensajeModal(errorMessage, () => {
                        // Recargar solo contadores si hubo un error crítico que no vació el log
                        loadLogNotesCount();
                    });
                    console.error("Error al procesar notas:", data.message || data);
                }
            } catch (err) {
                mostrarMensajeModal('Error de conexión al procesar notas. Intente de nuevo.');
                console.error("Error de conexión al procesar notas:", err);
            } finally {
                btnProcesarLog.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i> Procesar Notas Pendientes <span id="contadorLog" class="badge bg-danger ms-2">0</span>';
                // El estado de disabled se actualizará con loadLogNotesCount()
            }
        });
    });

    // --- Funcionalidad del buscador de notas registradas (existente) ---
    let searchTimeoutNotas;
    busquedaNotasInput.addEventListener('input', () => {
        clearTimeout(searchTimeoutNotas);
        searchTimeoutNotas = setTimeout(() => {
            cargarNotasRegistradas(busquedaNotasInput.value);
        }, 300);
    });

    /**
     * Carga las notas ya registradas desde la base de datos.
     * @param {string} searchTerm - Término de búsqueda para filtrar la lista.
     */
    async function cargarNotasRegistradas(searchTerm = '') {
        listaNotasRegistradas.innerHTML = '<tr><td colspan="8" class="text-center py-4">Cargando notas registradas...</td></tr>';

        // Solo cargar de la API si no tenemos datos y no estamos buscando específicamente
        // O si ya tenemos datos pero el término de búsqueda ha cambiado
        if (allNotasRegistradasData.length === 0 || searchTerm) { // Simplificado para recargar si hay búsqueda
            try {
                const res = await fetch(`../api/obtener_notas.php${searchTerm ? '?busqueda=' + encodeURIComponent(searchTerm) : ''}`);
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const data = await res.json();

                if (data.status && Array.isArray(data.data)) {
                    allNotasRegistradasData = data.data; // Cachea todos los datos
                    renderNotasTable(allNotasRegistradasData); // Siempre renderiza con los datos completos
                } else {
                    listaNotasRegistradas.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al cargar notas: ' + (data.message || 'Datos inválidos') + '</td></tr>';
                    console.error("Error o formato de datos incorrecto para notas registradas:", data.message || data);
                    return;
                }
            } catch (err) {
                listaNotasRegistradas.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error de conexión al cargar notas registradas.</td></tr>';
                console.error("Error de conexión al cargar notas registradas:", err);
                return;
            }
        } else {
            // Si no hay búsqueda y ya tenemos datos, simplemente renderiza lo que está en cache
            renderNotasTable(allNotasRegistradasData);
        }
    }


    /**
     * Renderiza la tabla de notas con los datos proporcionados.
     * @param {Array} notas - Array de objetos de nota.
     */
    function renderNotasTable(notas) {
        listaNotasRegistradas.innerHTML = ''; // Limpia la tabla

        if (notas.length === 0) {
            listaNotasRegistradas.innerHTML = '<tr><td colspan="8" class="text-center py-4">No hay notas registradas que coincidan con la búsqueda.</td></tr>';
            return;
        }

        notas.forEach(n => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${n.id_nota || 'N/A'}</td>
                <td>${n.nombre_estudiante || ''} ${n.apellido_estudiante || ''}</td>
                <td>${n.asignatura_nombre || 'N/A'}</td>
                <td>${n.parcial_1 !== null ? n.parcial_1 : 'N/A'}</td>
                <td>${n.parcial_2 !== null ? n.parcial_2 : 'N/A'}</td>
                <td>${n.examen_final !== null ? n.examen_final : 'N/A'}</td>
                <td><strong>${n.promedio !== null ? n.promedio : 'N/A'}</strong></td>
                <td><p class="text-break-word mb-0">${n.observaciones ? n.observaciones.replace(/\n/g, '<br>') : 'Ninguna'}</p></td>
            `;
            listaNotasRegistradas.appendChild(row);
        });
    }

    // Carga inicial al cargar la página
    document.addEventListener('DOMContentLoaded', () => {
        loadLogNotesCount(); // Primero el conteo y la lista simplificada
        cargarNotasRegistradas(); // Luego las notas registradas de la BD
    });

</script>

<?php include_once('footer.php'); ?>