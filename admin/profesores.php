<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Obtener todos los profesores con sus datos de usuario y especialidad
$stmt = $pdo->query("
    SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.dni, u.estado, p.especialidad, u.telefono, u.direccion
    FROM usuarios u
    INNER JOIN profesores p ON u.id_usuario = p.id_profesor
    WHERE u.rol = 'profesor' -- Asegurarse de que solo se muestren usuarios con rol de profesor
    ORDER BY u.nombre
");

$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="content" id="content" tabindex="-1">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>👨‍🏫 Gestión de Profesores</h3>
            <button class="btn btn-success" onclick="abrirModalProfesor()"><i class="bi bi-plus-circle"></i> Nuevo Profesor</button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Email / DNI</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($profesores) > 0): ?>
                        <?php foreach ($profesores as $prof): ?>
                            <tr>
                                <td><?= $prof['id_usuario'] ?></td>
                                <td><?= htmlspecialchars($prof['nombre'] . ' ' . $prof['apellido']) ?></td>
                                <td><?= htmlspecialchars($prof['email']) ?><br><small class="text-muted"><?= $prof['dni'] ?></small></td>
                                <td><?= htmlspecialchars($prof['especialidad']) ?></td>
                                <td>
                                    <span class="badge <?= $prof['estado'] ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $prof['estado'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary mb-1" onclick="asignarAsignaturas(<?= $prof['id_usuario'] ?>)">
                                        <i class="bi bi-journal-plus"></i> Asignar Asignaturas
                                    </button>
                                    <button class="btn btn-warning btn-sm mb-1" onclick="editarProfesor(<?= $prof['id_usuario'] ?>)">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </button>
                                    <button class="btn btn-info btn-sm mb-1" onclick="verDetallesProfesor(<?= $prof['id_usuario'] ?>)">
                                        <i class="bi bi-eye"></i> Detalles
                                    </button>
                                    <button class="btn btn-<?= $prof['estado'] ? 'danger' : 'success' ?> btn-sm mb-1" onclick="cambiarEstadoProfesor(<?= $prof['id_usuario'] ?>, <?= $prof['estado'] ? '0' : '1' ?>)">
                                        <i class="bi bi-toggle-<?= $prof['estado'] ? 'on' : 'off' ?>"></i> <?= $prof['estado'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay profesores registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Nuevo / Editar Profesor -->
<div class="modal fade" id="modalProfesor" tabindex="-1" aria-labelledby="modalProfesorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="formProfesor">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalProfesorLabel">Nuevo Profesor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_usuario" id="profesor_id_usuario">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="profesor_nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="profesor_nombre" name="nombre" required>
                    </div>
                    <div class="col-md-6">
                        <label for="profesor_apellido" class="form-label">Apellido</label>
                        <input type="text" class="form-control" id="profesor_apellido" name="apellido" required>
                    </div>
                    <div class="col-md-6">
                        <label for="profesor_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="profesor_email" name="email" required>
                    </div>
                    <div class="col-md-6">
                        <label for="profesor_dni" class="form-label">DNI</label>
                        <input type="text" class="form-control" id="profesor_dni" name="dni" required>
                    </div>
                    <div class="col-md-6">
                        <label for="profesor_telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="profesor_telefono" name="telefono">
                    </div>
                    <div class="col-md-6">
                        <label for="profesor_especialidad" class="form-label">Especialidad</label>
                        <input type="text" class="form-control" id="profesor_especialidad" name="especialidad">
                    </div>
                    <div class="col-12">
                        <label for="profesor_direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="profesor_direccion" name="direccion">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="profesor_estado" name="estado" value="1" checked>
                            <label class="form-check-label" for="profesor_estado">
                                Activo
                            </label>
                        </div>
                    </div>
                    <!-- Contraseña solo para nuevo profesor o si se desea cambiar -->
                    <div class="col-md-6" id="password_group">
                        <label for="profesor_contrasena" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="profesor_contrasena" name="contrasena" required>
                    </div>
                    <div class="col-md-6" id="confirm_password_group">
                        <label for="profesor_confirm_contrasena" class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="profesor_confirm_contrasena" name="confirm_contrasena" required>
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

<!-- Modal Asignar Asignaturas -->
<div class="modal fade" id="modalAsignar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="formAsignarAsignaturas">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Asignar Asignaturas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_profesor" id="id_profesor_asignar">
                
                <div class="mb-3">
                    <h6>Asignaturas Asignadas Actualmente:</h6>
                    <div id="contenedorAsignaturasAsignadas" class="row g-3 border p-3 rounded bg-light">
                        <p class="text-muted">No hay asignaturas asignadas.</p>
                    </div>
                </div>

                <div class="mb-3">
                    <h6>Asignaturas Disponibles para Asignar:</h6>
                    <div id="contenedorAsignaturasDisponibles" class="row g-3 border p-3 rounded bg-light">
                        <p class="text-muted">No hay asignaturas disponibles.</p>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn btn-success" type="submit">Guardar Asignaciones</button>
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detalles del Profesor -->
<div class="modal fade" id="modalDetallesProfesor" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Detalles del Profesor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetalles">
                <p>Cargando datos...</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Alert/Message Box -->
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
    // --- Variables de Modales y Formularios ---
    const modalProfesor = new bootstrap.Modal(document.getElementById('modalProfesor'));
    const formProfesor = document.getElementById('formProfesor');
    const modalProfesorLabel = document.getElementById('modalProfesorLabel');
    const inputProfesorContrasena = document.getElementById('profesor_contrasena');
    const inputProfesorConfirmContrasena = document.getElementById('profesor_confirm_contrasena');
    const passwordGroup = document.getElementById('password_group');
    const confirmPasswordGroup = document.getElementById('confirm_password_group');


    const modalDetalles = new bootstrap.Modal(document.getElementById('modalDetallesProfesor'));
    const contenidoDetalles = document.getElementById('contenidoDetalles');

    const modalAsignar = new bootstrap.Modal(document.getElementById('modalAsignar'));
    const formAsignar = document.getElementById('formAsignarAsignaturas');
    const contenedorAsignaturasAsignadas = document.getElementById('contenedorAsignaturasAsignadas');
    const contenedorAsignaturasDisponibles = document.getElementById('contenedorAsignaturasDisponibles');


    // --- Variables para Custom Alert ---
    const customAlertModal = new bootstrap.Modal(document.getElementById('customAlertModal'));
    const customAlertModalBody = document.getElementById('customAlertModalBody');
    const customAlertModalFooter = document.getElementById('customAlertModalFooter');

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

    // --- Funciones para Gestión de Profesores (Nuevo/Editar) ---
    function abrirModalProfesor() {
        formProfesor.reset();
        document.getElementById('profesor_id_usuario').value = '';
        modalProfesorLabel.textContent = 'Nuevo Profesor';
        document.getElementById('profesor_estado').checked = true; // Por defecto activo
        passwordGroup.style.display = 'block'; // Mostrar campos de contraseña
        confirmPasswordGroup.style.display = 'block';
        inputProfesorContrasena.setAttribute('required', 'required');
        inputProfesorConfirmContrasena.setAttribute('required', 'required');
        modalProfesor.show();
    }

    async function editarProfesor(idProfesor) {
        formProfesor.reset();
        document.getElementById('profesor_id_usuario').value = idProfesor;
        modalProfesorLabel.textContent = 'Editar Profesor';
        passwordGroup.style.display = 'none'; // Ocultar campos de contraseña por defecto en edición
        confirmPasswordGroup.style.display = 'none';
        inputProfesorContrasena.removeAttribute('required');
        inputProfesorConfirmContrasena.removeAttribute('required');

        try {
            const response = await fetch(`../api/profesores_crud.php?id_profesor=${idProfesor}`);
            const data = await response.json();

            if (data.status && data.profesor) {
                const p = data.profesor;
                document.getElementById('profesor_nombre').value = p.nombre;
                document.getElementById('profesor_apellido').value = p.apellido;
                document.getElementById('profesor_email').value = p.email;
                document.getElementById('profesor_dni').value = p.dni;
                document.getElementById('profesor_telefono').value = p.telefono;
                document.getElementById('profesor_especialidad').value = p.especialidad;
                document.getElementById('profesor_direccion').value = p.direccion;
                document.getElementById('profesor_estado').checked = (p.estado == 1);
                modalProfesor.show();
            } else {
                showCustomMessage("Error al cargar datos del profesor: " + (data.message || "Desconocido"));
            }
        } catch (error) {
            console.error("Error de red al editar profesor:", error);
            showCustomMessage("Error de red al cargar los datos del profesor.");
        }
    }

    formProfesor.addEventListener('submit', async e => {
        e.preventDefault();

        const idProfesor = document.getElementById('profesor_id_usuario').value;
        const contrasena = inputProfesorContrasena.value;
        const confirmContrasena = inputProfesorConfirmContrasena.value;

        if (!idProfesor && contrasena !== confirmContrasena) {
            showCustomMessage("Las contraseñas no coinciden.");
            return;
        }
        if (!idProfesor && contrasena.length < 6) { // Mínimo de 6 caracteres para la contraseña
            showCustomMessage("La contraseña debe tener al menos 6 caracteres.");
            return;
        }

        const formData = new FormData(formProfesor);
        formData.append('rol', 'profesor'); // Asegurar que el rol sea 'profesor'
        formData.append('estado', document.getElementById('profesor_estado').checked ? 1 : 0);

        try {
            const response = await fetch('../api/profesores_crud.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status) {
                showCustomMessage(data.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + data.message);
            }
        } catch (error) {
            console.error("Error de red al guardar profesor:", error);
            showCustomMessage("Error de red al guardar los datos del profesor.");
        }
    });

    async function cambiarEstadoProfesor(idProfesor, nuevoEstado) {
        const estadoTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';
        const confirmed = await showCustomMessage(`¿Está seguro de que desea ${estadoTexto} este profesor?`, true);
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/profesores_crud.php?action=toggle_status&id_profesor=${idProfesor}&estado=${nuevoEstado}`);
            const data = await response.json();

            if (data.status) {
                showCustomMessage(data.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + data.message);
            }
        } catch (error) {
            console.error("Error de red al cambiar estado:", error);
            showCustomMessage("Error de red al cambiar el estado del profesor.");
        }
    }

    // --- Funciones para Asignar Asignaturas ---
    function asignarAsignaturas(idProfesor) {
        document.getElementById('id_profesor_asignar').value = idProfesor;
        contenedorAsignaturasAsignadas.innerHTML = '<p class="text-muted">Cargando asignaturas...</p>';
        contenedorAsignaturasDisponibles.innerHTML = '<p class="text-muted">Cargando asignaturas...</p>';

        fetch('../api/asignaturas_disponibles.php?id_profesor=' + idProfesor)
            .then(res => res.json())
            .then(data => {
                if (!data.status) {
                    showCustomMessage("Error al cargar asignaturas: " + data.message);
                    contenedorAsignaturasAsignadas.innerHTML = '<p class="text-danger">Error al cargar asignaturas asignadas.</p>';
                    contenedorAsignaturasDisponibles.innerHTML = '<p class="text-danger">Error al cargar asignaturas disponibles.</p>';
                    return;
                }

                // Limpiar contenedores
                contenedorAsignaturasAsignadas.innerHTML = '';
                contenedorAsignaturasDisponibles.innerHTML = '';

                // Renderizar asignaturas asignadas
                if (data.assigned_subjects.length === 0) {
                    contenedorAsignaturasAsignadas.innerHTML = '<p class="text-muted">No hay asignaturas asignadas actualmente.</p>';
                } else {
                    data.assigned_subjects.forEach(asig => {
                        const div = document.createElement('div');
                        div.className = 'col-md-6';
                        div.innerHTML = `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="asignaturas[]" value="${asig.id_asignatura}" id="asig_assigned_${asig.id_asignatura}" checked>
                                <label class="form-check-label" for="asig_assigned_${asig.id_asignatura}">
                                    ${asig.nombre} <span class="text-success">(Asignada)</span>
                                </label>
                            </div>
                        `;
                        contenedorAsignaturasAsignadas.appendChild(div);
                    });
                }

                // Renderizar asignaturas disponibles
                if (data.unassigned_subjects.length === 0) {
                    contenedorAsignaturasDisponibles.innerHTML = '<p class="text-muted">No hay más asignaturas disponibles para asignar.</p>';
                } else {
                    data.unassigned_subjects.forEach(asig => {
                        const div = document.createElement('div');
                        div.className = 'col-md-6';
                        div.innerHTML = `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="asignaturas[]" value="${asig.id_asignatura}" id="asig_unassigned_${asig.id_asignatura}">
                                <label class="form-check-label" for="asig_unassigned_${asig.id_asignatura}">
                                    ${asig.nombre}
                                </label>
                            </div>
                        `;
                        contenedorAsignaturasDisponibles.appendChild(div);
                    });
                }

                modalAsignar.show();
            })
            .catch(error => {
                console.error("Error al cargar asignaturas:", error);
                showCustomMessage('Error de red al cargar las asignaturas disponibles.');
                contenedorAsignaturasAsignadas.innerHTML = '<p class="text-danger">Error de red.</p>';
                contenedorAsignaturasDisponibles.innerHTML = '<p class="text-danger">Error de red.</p>';
            });
    }

    formAsignar.addEventListener('submit', async e => {
        e.preventDefault();

        const datos = new FormData(formAsignar);

        try {
            const response = await fetch('../api/guardar_asignaturas_profesor.php', {
                method: 'POST',
                body: datos
            });
            const resp = await response.json();
            if (resp.status) {
                showCustomMessage(resp.message).then(() => modalAsignar.hide());
            } else {
                showCustomMessage('Error: ' + resp.message);
            }
        } catch (error) {
            console.error("Error de red al guardar asignaciones:", error);
            showCustomMessage('Error de red al guardar las asignaciones.');
        }
    });

    // --- Funciones para Ver Detalles del Profesor ---
    function verDetallesProfesor(idProfesor) {
        contenidoDetalles.innerHTML = '<p>Cargando...</p>';

        fetch(`../api/detalles_profesor.php?id_profesor=${idProfesor}`)
            .then(res => res.json())
            .then(data => {
                if (!data.profesor) {
                    contenidoDetalles.innerHTML = '<p>No se encontraron datos del profesor.</p>';
                    return;
                }

                const p = data.profesor;

                let html = `
                <h5 class="text-primary">Información Personal</h5>
                <ul class="list-group mb-3">
                    <li class="list-group-item"><strong>Nombre:</strong> ${p.nombre} ${p.apellido}</li>
                    <li class="list-group-item"><strong>Email:</strong> ${p.email}</li>
                    <li class="list-group-item"><strong>DNI:</strong> ${p.dni}</li>
                    <li class="list-group-item"><strong>Especialidad:</strong> ${p.especialidad || 'No especificada'}</li>
                    <li class="list-group-item"><strong>Teléfono:</strong> ${p.telefono || 'No disponible'}</li>
                    <li class="list-group-item"><strong>Dirección:</strong> ${p.direccion || 'No disponible'}</li>
                    <li class="list-group-item"><strong>Estado:</strong> <span class="badge ${p.estado ? 'bg-success' : 'bg-danger'}">${p.estado ? 'Activo' : 'Inactivo'}</span></li>
                </ul>
            `;

                html += `<h5 class="text-success">Asignaturas Asignadas</h5>`;
                if (data.asignaturas.length === 0) {
                    html += `<p class="text-muted">Este profesor aún no tiene asignaturas asignadas.</p>`;
                } else {
                    html += '<ul class="list-group">';
                    data.asignaturas.forEach(a => {
                        // MODIFICACIÓN CLAVE: Se añade '|| "N/A"' para manejar valores nulos/indefinidos
                        html += `<li class="list-group-item">${a.nombre} (Curso: ${a.curso_nombre || 'N/A'}, Turno: ${(a.curso_turno || 'N/A').toUpperCase()})</li>`;
                    });
                    html += '</ul>';
                }

                contenidoDetalles.innerHTML = html;
                modalDetalles.show();
            })
            .catch(error => {
                console.error("Error al obtener detalles del profesor:", error);
                contenidoDetalles.innerHTML = '<p>Error al obtener los detalles del profesor.</p>';
            });
    }
</script>
<?php include_once('footer.php'); ?>
