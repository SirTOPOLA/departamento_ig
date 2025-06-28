<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Obtener todos los periodos académicos
$stmtPeriodos = $pdo->query("SELECT * FROM periodos_academicos ORDER BY fecha_inicio DESC");
$periodos = $stmtPeriodos->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las configuraciones de horarios
$stmtConfig = $pdo->query("SELECT * FROM configuracion_horarios ORDER BY FIELD(dia_semana, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Default')");
$configuraciones = $stmtConfig->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
    <div class="container py-5">
        <h3><i class="bi bi-gear-fill"></i> Configuración del Sistema de Horarios</h3>

        <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="periodos-tab" data-bs-toggle="tab" data-bs-target="#periodos" type="button" role="tab" aria-controls="periodos" aria-selected="true">
                    Períodos Académicos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reglas-tab" data-bs-toggle="tab" data-bs-target="#reglas" type="button" role="tab" aria-controls="reglas" aria-selected="false">
                    Reglas por Día
                </button>
            </li>
        </ul>

        <div class="tab-content" id="configTabsContent">
            <div class="tab-pane fade show active" id="periodos" role="tabpanel" aria-labelledby="periodos-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Gestión de Períodos Académicos</h4>
                    <button class="btn btn-success" onclick="abrirModalPeriodo()"><i class="bi bi-plus-circle"></i> Nuevo Período</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-info">
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Activo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periodos as $p): ?>
                                <tr>
                                    <td><?= $p['id_periodo'] ?></td>
                                    <td><?= htmlspecialchars($p['nombre_periodo']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['fecha_fin'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $p['activo'] ? 'success' : 'secondary' ?>">
                                            <?= $p['activo'] ? 'Sí' : 'No' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editarPeriodo(<?= $p['id_periodo'] ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarPeriodo(<?= $p['id_periodo'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php if (!$p['activo']): ?>
                                            <button class="btn btn-primary btn-sm" onclick="activarPeriodo(<?= $p['id_periodo'] ?>)">
                                                <i class="bi bi-check-circle"></i> Activar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($periodos) === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No hay períodos académicos registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="reglas" role="tabpanel" aria-labelledby="reglas-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Reglas de Horarios por Día</h4>
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

<div class="modal fade" id="modalPeriodo" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="formPeriodo">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Nuevo / Editar Período Académico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_periodo" id="periodo_id">
                <div class="mb-2">
                    <label class="form-label">Nombre del Período</label>
                    <input type="text" name="nombre_periodo" id="periodo_nombre" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" id="periodo_fecha_inicio" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Fecha de Fin</label>
                    <input type="date" name="fecha_fin" id="periodo_fecha_fin" class="form-control" required>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="activo" id="periodo_activo">
                    <label class="form-check-label" for="periodo_activo">
                        Activo (solo un período puede estar activo a la vez)
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

<div class="modal fade" id="customAlertModal" tabindex="-1" aria-labelledby="customAlertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customAlertModalLabel">Mensaje del Sistema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customAlertModalBody">
                </div>
            <div class="modal-footer" id="customAlertModalFooter">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modalPeriodo = new bootstrap.Modal(document.getElementById('modalPeriodo'));
    const formPeriodo = document.getElementById('formPeriodo');
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

    // --- Funciones para Períodos Académicos ---
    function abrirModalPeriodo() {
        formPeriodo.reset();
        formPeriodo.periodo_id.value = '';
        document.getElementById('periodo_activo').checked = false; // Desmarcar por defecto
        modalPeriodo.show();
    }

    async function editarPeriodo(id) {
        try {
            const response = await fetch(`../api/periodos_academicos_crud.php?id=${id}`);
            const d = await response.json();
            if (d.status && d.periodo) {
                const p = d.periodo;
                formPeriodo.periodo_id.value = p.id_periodo;
                document.getElementById('periodo_nombre').value = p.nombre_periodo;
                document.getElementById('periodo_fecha_inicio').value = p.fecha_inicio;
                document.getElementById('periodo_fecha_fin').value = p.fecha_fin;
                document.getElementById('periodo_activo').checked = (p.activo == 1);
                modalPeriodo.show();
            } else {
                showCustomMessage("Error al obtener datos del período: " + (d.message || "Desconocido"));
            }
        } catch (error) {
            console.error("Error de red al editar período:", error);
            showCustomMessage("Error de red al editar el período.");
        }
    }

    async function eliminarPeriodo(id) {
        const confirmed = await showCustomMessage("¿Está seguro de que desea eliminar este período? Esto podría afectar horarios asociados.", true);
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/periodos_academicos_crud.php?action=delete&id=${id}`);
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al eliminar período:", error);
            showCustomMessage("Error de red al eliminar el período.");
        }
    }

    async function activarPeriodo(id) {
        const confirmed = await showCustomMessage("Al activar este período, cualquier otro período activo será desactivado. ¿Desea continuar?", true);
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/periodos_academicos_crud.php?action=activate&id=${id}`);
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al activar período:", error);
            showCustomMessage("Error de red al activar el período.");
        }
    }

    formPeriodo.addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(formPeriodo);
        formData.append('activo', document.getElementById('periodo_activo').checked ? 1 : 0);

        try {
            const response = await fetch('../api/periodos_academicos_crud.php', {
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
            console.error("Error de red al guardar período:", error);
            showCustomMessage("Error de red al guardar el período.");
        }
    });

    // --- Funciones para Configuración de Horarios por Día ---
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
