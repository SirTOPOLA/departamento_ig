<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Obtener todos los años académicos
$stmtAnios = $pdo->query("SELECT * FROM anios_academicos ORDER BY anio DESC");
$anios = $stmtAnios->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las configuraciones de horarios
$stmtConfig = $pdo->query("SELECT * FROM configuracion_horarios ORDER BY FIELD(dia_semana, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Default')");
$configuraciones = $stmtConfig->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
    <div class="container py-5">
        <h3><i class="bi bi-gear-fill"></i> Configuración del Sistema de Horarios</h3>

        <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="anios-tab" data-bs-toggle="tab" data-bs-target="#anios" type="button" role="tab" aria-controls="anios" aria-selected="true">
                    Años Académicos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reglas-tab" data-bs-toggle="tab" data-bs-target="#reglas" type="button" role="tab" aria-controls="reglas" aria-selected="false">
                    Reglas por Día
                </button>
            </li>
        </ul>

        <div class="tab-content" id="configTabsContent">
            <!-- Pestaña de Años Académicos -->
            <div class="tab-pane fade show active" id="anios" role="tabpanel" aria-labelledby="anios-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Gestión de Años Académicos</h4>
                    <button class="btn btn-success" onclick="abrirModalAnio()"><i class="bi bi-plus-circle"></i> Nuevo Año</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-info">
                            <tr>
                                <th>#</th>
                                <th>Año</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Activo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($anios as $a): ?>
                                <tr>
                                    <td><?= $a['id_anio'] ?></td>
                                    <td><?= htmlspecialchars($a['anio']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($a['fecha_inicio'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($a['fecha_fin'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $a['activo'] ? 'success' : 'secondary' ?>">
                                            <?= $a['activo'] ? 'Sí' : 'No' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editarAnio(<?= $a['id_anio'] ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarAnio(<?= $a['id_anio'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php if (!$a['activo']): ?>
                                            <button class="btn btn-primary btn-sm" onclick="activarAnio(<?= $a['id_anio'] ?>)">
                                                <i class="bi bi-check-circle"></i> Activar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($anios) === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No hay años académicos registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pestaña de Reglas por Día -->
            <div class="tab-pane fade" id="reglas" role="tabpanel" aria-labelledby="reglas-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Reglas de Horarios por Día</h4>
                    <!-- No se añade 'Nuevo' aquí, se editan los existentes -->
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-info">
                            <tr>
                                <th>Día</th>
                                <th>Inicio Perm.</th>
                                <th>Fin Perm.</th>
                                <th>Máx Horas Prof.</th>
                                <th>Min/Max Clase (min)</th>
                                <th>Requiere Mixto</th>
                                <th>Min 1h/2h</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configuraciones as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['dia_semana']) ?></td>
                                    <td><?= substr($c['hora_inicio_permitida'], 0, 5) ?></td>
                                    <td><?= substr($c['hora_fin_permitida'], 0, 5) ?></td>
                                    <td><?= $c['max_horas_dia_profesor'] ?></td>
                                    <td><?= $c['min_duracion_clase_min'] ?> / <?= $c['max_duracion_clase_min'] ?></td>
                                    <td><?= $c['requiere_mixto_horas'] ? 'Sí' : 'No' ?></td>
                                    <td><?= $c['min_clases_1h_mixto'] ?> / <?= $c['min_clases_2h_mixto'] ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editarConfiguracion(<?= $c['id_config'] ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php if ($c['dia_semana'] !== 'Default'): ?>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarConfiguracion(<?= $c['id_config'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Nuevo / Editar Año Académico -->
<div class="modal fade" id="modalAnio" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="formAnio">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Nuevo / Editar Año Académico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_anio" id="anio_id">
                <div class="mb-2">
                    <label class="form-label">Año</label>
                    <input type="text" name="anio" id="anio_nombre" class="form-control" required placeholder="Ej: 2024-2025">
                </div>
                <div class="mb-2">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" id="anio_fecha_inicio" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Fecha de Fin</label>
                    <input type="date" name="fecha_fin" id="anio_fecha_fin" class="form-control" required>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="activo" id="anio_activo">
                    <label class="form-check-label" for="anio_activo">
                        Activo (solo un año puede estar activo a la vez)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Guardar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Editar Configuración de Horario por Día -->
<div class="modal fade" id="modalConfiguracion" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="formConfiguracion">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Editar Reglas de Horario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_config" id="config_id">
                <div class="mb-2">
                    <label class="form-label">Día de la Semana</label>
                    <input type="text" name="dia_semana" id="config_dia_semana" class="form-control" readonly>
                </div>
                <div class="mb-2 row">
                    <div class="col">
                        <label class="form-label">Hora Inicio Permitida</label>
                        <input type="time" name="hora_inicio_permitida" id="config_hora_inicio_permitida" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Hora Fin Permitida</label>
                        <input type="time" name="hora_fin_permitida" id="config_hora_fin_permitida" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Máx. Horas por Profesor al Día</label>
                    <input type="number" name="max_horas_dia_profesor" id="config_max_horas_dia_profesor" class="form-control" min="1" required>
                </div>
                <div class="mb-2 row">
                    <div class="col">
                        <label class="form-label">Min. Duración Clase (min)</label>
                        <input type="number" name="min_duracion_clase_min" id="config_min_duracion_clase_min" class="form-control" min="10" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Máx. Duración Clase (min)</label>
                        <input type="number" name="max_duracion_clase_min" id="config_max_duracion_clase_min" class="form-control" min="10" required>
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="requiere_mixto_horas" id="config_requiere_mixto_horas">
                    <label class="form-check-label" for="config_requiere_mixto_horas">
                        Requiere Combinación Mixta de Clases (1h/2h)
                    </label>
                </div>
                <div class="row" id="mixto_options_div">
                    <div class="col-6 mb-2">
                        <label class="form-label">Mín. Clases de 1h (Mixto)</label>
                        <input type="number" name="min_clases_1h_mixto" id="config_min_clases_1h_mixto" class="form-control" min="0">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Mín. Clases de 2h (Mixto)</label>
                        <input type="number" name="min_clases_2h_mixto" id="config_min_clases_2h_mixto" class="form-control" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Guardar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Alert/Message Box (Duplicado para este archivo, considera un archivo JS común) -->
<div class="modal fade" id="customAlertModal" tabindex="-1" aria-labelledby="customAlertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customAlertModalLabel">Mensaje del Sistema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customAlertModalBody">
                <!-- Message will be inserted here -->
            </div>
            <div class="modal-footer" id="customAlertModalFooter">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modalAnio = new bootstrap.Modal(document.getElementById('modalAnio')); // Cambio: modalAnio
    const formAnio = document.getElementById('formAnio'); // Cambio: formAnio
    const modalConfiguracion = new bootstrap.Modal(document.getElementById('modalConfiguracion'));
    const formConfiguracion = document.getElementById('formConfiguracion');
    const customAlertModal = new bootstrap.Modal(document.getElementById('customAlertModal'));
    const customAlertModalBody = document.getElementById('customAlertModalBody');
    const customAlertModalFooter = document.getElementById('customAlertModalFooter');

    // Manejar la visibilidad de las opciones de mixto
    const checkboxMixto = document.getElementById('config_requiere_mixto_horas');
    const mixtoOptionsDiv = document.getElementById('mixto_options_div');

    function toggleMixtoOptions() {
        if (checkboxMixto.checked) {
            mixtoOptionsDiv.style.display = 'flex'; // Mostrar como flex para las columnas
            document.getElementById('config_min_clases_1h_mixto').setAttribute('required', 'required');
            document.getElementById('config_min_clases_2h_mixto').setAttribute('required', 'required');
        } else {
            mixtoOptionsDiv.style.display = 'none';
            document.getElementById('config_min_clases_1h_mixto').removeAttribute('required');
            document.getElementById('config_min_clases_2h_mixto').removeAttribute('required');
        }
    }
    checkboxMixto.addEventListener('change', toggleMixtoOptions);
    document.addEventListener('DOMContentLoaded', toggleMixtoOptions); // Asegurar estado inicial

    /**
     * Muestra un mensaje personalizado en lugar de alert().
     * @param {string} message El mensaje a mostrar.
     * @param {boolean} isConfirm Si es una confirmación, añade botones Sí/No.
     * @returns {Promise<boolean>} Resuelve a true si se confirma, false si se cancela.
     */
    function showCustomMessage(message, isConfirm = false) {
        return new Promise((resolve) => {
            customAlertModalBody.innerHTML = message;
            customAlertModalFooter.innerHTML = ''; // Limpiar botones anteriores

            if (isConfirm) {
                const btnYes = document.createElement('button');
                btnYes.type = 'button';
                btnYes.className = 'btn btn-danger me-2';
                btnYes.textContent = 'Sí';
                btnYes.onclick = () => {
                    customAlertModal.hide();
                    resolve(true);
                };

                const btnNo = document.createElement('button');
                btnNo.type = 'button';
                btnNo.className = 'btn btn-secondary';
                btnNo.textContent = 'No';
                btnNo.onclick = () => {
                    customAlertModal.hide();
                    resolve(false);
                };
                customAlertModalFooter.appendChild(btnYes);
                customAlertModalFooter.appendChild(btnNo);
            } else {
                const btnOk = document.createElement('button');
                btnOk.type = 'button';
                btnOk.className = 'btn btn-primary';
                btnOk.textContent = 'Aceptar';
                btnOk.onclick = () => {
                    customAlertModal.hide();
                    resolve(true);
                };
                customAlertModalFooter.appendChild(btnOk);
            }
            customAlertModal.show();
        });
    }

    // --- Funciones para Años Académicos --- // Cambio: Años Académicos
    function abrirModalAnio() { // Cambio: abrirModalAnio
        formAnio.reset(); // Cambio: formAnio
        formAnio.anio_id.value = ''; // Cambio: anio_id
        document.getElementById('anio_activo').checked = false; // Cambio: anio_activo
        modalAnio.show(); // Cambio: modalAnio
    }

    async function editarAnio(id) { // Cambio: editarAnio
        try {
            const response = await fetch(`../api/anios_academicos_crud.php?id=${id}`); // Cambio: anios_academicos_crud.php
            const d = await response.json();
            if (d.status && d.anio) { // Cambio: d.anio
                const a = d.anio; // Cambio: a = d.anio
                formAnio.anio_id.value = a.id_anio; // Cambio: anio_id, a.id_anio
                document.getElementById('anio_nombre').value = a.anio; // Cambio: anio_nombre, a.anio
                document.getElementById('anio_fecha_inicio').value = a.fecha_inicio; // Cambio: anio_fecha_inicio
                document.getElementById('anio_fecha_fin').value = a.fecha_fin; // Cambio: anio_fecha_fin
                document.getElementById('anio_activo').checked = (a.activo == 1); // Cambio: anio_activo, a.activo
                modalAnio.show(); // Cambio: modalAnio
            } else {
                showCustomMessage("Error al obtener datos del año académico: " + (d.message || "Desconocido")); // Cambio: año académico
            }
        } catch (error) {
            console.error("Error de red al editar año académico:", error); // Cambio: año académico
            showCustomMessage("Error de red al editar el año académico."); // Cambio: año académico
        }
    }

    async function eliminarAnio(id) { // Cambio: eliminarAnio
        const confirmed = await showCustomMessage("¿Está seguro de que desea eliminar este año académico? Esto podría afectar horarios asociados.", true); // Cambio: año académico
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/anios_academicos_crud.php?action=delete&id=${id}`); // Cambio: anios_academicos_crud.php
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al eliminar año académico:", error); // Cambio: año académico
            showCustomMessage("Error de red al eliminar el año académico."); // Cambio: año académico
        }
    }

    async function activarAnio(id) { // Cambio: activarAnio
        const confirmed = await showCustomMessage("Al activar este año académico, cualquier otro año activo será desactivado. ¿Desea continuar?", true); // Cambio: año académico
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/anios_academicos_crud.php?action=activate&id=${id}`); // Cambio: anios_academicos_crud.php
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al activar año académico:", error); // Cambio: año académico
            showCustomMessage("Error de red al activar el año académico."); // Cambio: año académico
        }
    }

    formAnio.addEventListener('submit', async e => { // Cambio: formAnio
        e.preventDefault();
        const formData = new FormData(formAnio); // Cambio: formAnio
        formData.append('activo', document.getElementById('anio_activo').checked ? 1 : 0); // Cambio: anio_activo

        try {
            const response = await fetch('../api/anios_academicos_crud.php', { // Cambio: anios_academicos_crud.php
                method: 'POST',
                body: formData
            });
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al guardar año académico:", error); // Cambio: año académico
            showCustomMessage("Error de red al guardar el año académico."); // Cambio: año académico
        }
    });

    // --- Funciones para Configuración de Horarios por Día (no cambian, ya que no dependen de anios/periodos) ---
    async function editarConfiguracion(id) {
        try {
            const response = await fetch(`../api/configuracion_horarios_crud.php?id=${id}`);
            const d = await response.json();
            if (d.status && d.configuracion) {
                const c = d.configuracion;
                formConfiguracion.config_id.value = c.id_config;
                document.getElementById('config_dia_semana').value = c.dia_semana;
                document.getElementById('config_hora_inicio_permitida').value = c.hora_inicio_permitida.substring(0,5);
                document.getElementById('config_hora_fin_permitida').value = c.hora_fin_permitida.substring(0,5);
                document.getElementById('config_max_horas_dia_profesor').value = c.max_horas_dia_profesor;
                document.getElementById('config_min_duracion_clase_min').value = c.min_duracion_clase_min;
                document.getElementById('config_max_duracion_clase_min').value = c.max_duracion_clase_min;
                document.getElementById('config_requiere_mixto_horas').checked = (c.requiere_mixto_horas == 1);
                document.getElementById('config_min_clases_1h_mixto').value = c.min_clases_1h_mixto;
                document.getElementById('config_min_clases_2h_mixto').value = c.min_clases_2h_mixto;
                toggleMixtoOptions(); // Actualizar visibilidad
                modalConfiguracion.show();
            } else {
                showCustomMessage("Error al obtener datos de configuración: " + (d.message || "Desconocido"));
            }
        } catch (error) {
            console.error("Error de red al editar configuración:", error);
            showCustomMessage("Error de red al editar la configuración.");
        }
    }

    async function eliminarConfiguracion(id) {
        const confirmed = await showCustomMessage("¿Está seguro de que desea eliminar esta configuración específica del día? Se usará la configuración por defecto.", true);
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/configuracion_horarios_crud.php?action=delete&id=${id}`);
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al eliminar configuración:", error);
            showCustomMessage("Error de red al eliminar la configuración.");
        }
    }

    formConfiguracion.addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(formConfiguracion);
        formData.append('requiere_mixto_horas', document.getElementById('config_requiere_mixto_horas').checked ? 1 : 0);

        try {
            const response = await fetch('../api/configuracion_horarios_crud.php', {
                method: 'POST',
                body: formData
            });
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al guardar configuración:", error);
            showCustomMessage("Error de red al guardar la configuración.");
        }
    });
</script>
<?php include_once('footer.php'); ?>
