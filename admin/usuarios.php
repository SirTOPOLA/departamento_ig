<?php
require_once '../includes/functions.php';
check_login_and_role('Administrador');
require_once '../config/database.php';

$page_title = "Gestión de Usuarios";
include_once '../includes/header.php';

 
// --- Obtener todos los roles para el select del formulario ---
$stmt_roles = $pdo->query("SELECT id, nombre_rol FROM roles ORDER BY nombre_rol");
$roles = $stmt_roles->fetchAll();

// --- Obtener el año académico activo actual ---
$active_anio_id = null;
$active_anio_nombre = null;
try {
    $stmt_active_year_data = $pdo->query("SELECT id, nombre_anio FROM anios_academicos WHERE estado = 'Activo' LIMIT 1");
    $active_year_data = $stmt_active_year_data->fetch(PDO::FETCH_ASSOC);
    if ($active_year_data) {
        $active_anio_id = $active_year_data['id'];
        $active_anio_nombre = $active_year_data['nombre_anio'];
    }
} catch (PDOException $e) {
    // Handle error if necessary
}

// --- Obtener cursos para el select de estudiantes (solo los del año activo actual) ---
$cursos_entrada_anio_actual = [];
if ($active_anio_id) {
    $stmt_cursos_anio_actual = $pdo->prepare("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
 
    $stmt_cursos_anio_actual->execute();
    $cursos_entrada_anio_actual = $stmt_cursos_anio_actual->fetchAll();
}


// --- Obtener todos los usuarios para la tabla ---
// Modificamos la consulta para recuperar el curso de entrada del estudiante para el año activo actual
$stmt_usuarios = $pdo->query("
    SELECT u.id, u.nombre_usuario, u.nombre_completo, u.email, u.telefono, u.nip, u.estado,
           r.id AS id_rol, r.nombre_rol,
           e.id AS estudiante_id, e.codigo_registro,
           p.especialidad,
           p.grado_academico
    FROM usuarios u
    JOIN roles r ON u.id_rol = r.id
    LEFT JOIN estudiantes e ON u.id = e.id_usuario
    LEFT JOIN profesores p ON u.id = p.id_usuario
    ORDER BY u.id DESC
");
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

// Para cada estudiante, obtener su curso de entrada para el año actual
foreach ($usuarios as &$user) {
    if ($user['nombre_rol'] === 'Estudiante' && $user['estudiante_id'] && $active_anio_id) {
        $stmt_curso_entrada_estudiante = $pdo->prepare("
            SELECT ce.id_curso, c.nombre_curso, ce.id_anio, a.nombre_anio
            FROM curso_estudiante ce
            JOIN cursos c ON ce.id_curso = c.id
            JOIN anios_academicos a ON ce.id_anio = a.id
            WHERE ce.id_estudiante = :estudiante_id AND ce.id_anio = :active_anio_id
        ");
        $stmt_curso_entrada_estudiante->bindParam(':estudiante_id', $user['estudiante_id']);
        $stmt_curso_entrada_estudiante->bindParam(':active_anio_id', $active_anio_id);
        $stmt_curso_entrada_estudiante->execute();
        $user['curso_entrada_actual'] = $stmt_curso_entrada_estudiante->fetch(PDO::FETCH_ASSOC);

        // Para mostrar un resumen en la tabla principal
        $user['curso_entrada_display'] = $user['curso_entrada_actual']['nombre_curso'] ?? '-';
        $user['anio_entrada_display'] = $user['curso_entrada_actual']['nombre_anio'] ?? '-';

    } else {
        $user['curso_entrada_actual'] = null;
        $user['curso_entrada_display'] = '-';
        $user['anio_entrada_display'] = '-';
    }
}
unset($user); // Romper la referencia al último elemento

// NEW: Obtener mensajes flash para JavaScript
$flash_messages = get_flash_messages();
?>

<h1 class="mt-4">Gestión de Usuarios</h1>
<p class="lead">Administra los usuarios (Administradores, Estudiantes, Profesores) del sistema.</p>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" id="addUserBtn">
        <i class="fas fa-plus-circle me-2"></i>Añadir Nuevo Usuario
    </button>
    <div class="col-md-4">
        <input type="search" class="form-control" id="searchInput" placeholder="Buscar usuario...">
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Lista de Usuarios</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="usuariosTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NIP</th>
                        <th>Usuario</th>
                        <th>Nombre Completo</th>
                        <th>Rol</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Cód. Registro</th>
                        <th>Curso Entrada (Año Actual)</th>
                        <th>Año Entrada</th>
                        <th>Especialidad</th>
                        <th>Grado Académico</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($usuarios) > 0): ?>
                        <?php foreach ($usuarios as $user): ?>
                            <tr data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                data-nombre_usuario="<?php echo htmlspecialchars($user['nombre_usuario']); ?>"
                                data-nombre_completo="<?php echo htmlspecialchars($user['nombre_completo']); ?>"
                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                data-telefono="<?php echo htmlspecialchars($user['telefono']); ?>"
                                data-nip="<?php echo htmlspecialchars($user['nip']); ?>"
                                data-id_rol="<?php echo htmlspecialchars($user['id_rol']); ?>" data-nombre_rol="<?php echo htmlspecialchars($user['nombre_rol']); ?>"
                                data-codigo_registro="<?php echo htmlspecialchars($user['codigo_registro'] ?? ''); ?>"
                                data-estudiante_id="<?php echo htmlspecialchars($user['estudiante_id'] ?? ''); ?>"
                                data-curso_entrada_id="<?php echo htmlspecialchars($user['curso_entrada_actual']['id_curso'] ?? ''); ?>"
                                data-anio_entrada_id="<?php echo htmlspecialchars($user['curso_entrada_actual']['id_anio'] ?? ''); ?>"
                                data-especialidad="<?php echo htmlspecialchars($user['especialidad'] ?? ''); ?>" data-grado_academico="<?php echo htmlspecialchars($user['grado_academico'] ?? ''); ?>" data-estado="<?php echo htmlspecialchars($user['estado']); ?>">
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['nip']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_usuario']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Estudiante' ? ($user['codigo_registro'] ?? '-') : '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['curso_entrada_display']); ?></td>
                                <td><?php echo htmlspecialchars($user['anio_entrada_display']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Profesor' ? ($user['especialidad'] ?? '-') : '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Profesor' ? ($user['grado_academico'] ?? '-') : '-'); ?></td>
                                <td>
                                    <?php
                                        $estado_clase = '';
                                        switch ($user['estado']) {
                                            case 'Activo': $estado_clase = 'badge bg-success'; break;
                                            case 'Inactivo': $estado_clase = 'badge bg-warning text-dark'; break;
                                            case 'Bloqueado': $estado_clase = 'badge bg-danger'; break;
                                            default: $estado_clase = 'badge bg-secondary'; break;
                                        }
                                    ?>
                                    <span class="<?php echo $estado_clase; ?>"><?php echo htmlspecialchars($user['estado']); ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#userModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="usuarios.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar a este usuario? Esta acción es irreversible.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14" class="text-center">No hay usuarios registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination justify-content-center" id="pagination">
            </ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="userForm" action="../api/guardar_usuario.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="userModalLabel">Añadir Nuevo Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="userId">
                    <input type="hidden" name="id_anio_inscripcion_activo" id="idAnioInscripcionActivo" value="<?php echo htmlspecialchars($active_anio_id ?? ''); ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nombre_usuario" class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nip" class="form-label">NIP (Número de Identificación Personal) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nip" name="nip" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Contraseña <span class="text-danger" id="passwordRequired">*</span></label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted" id="passwordHelp">Introduce una contraseña (o déjalo vacío para no cambiar si editas).</small>
                        </div>
                        <div class="col-md-6">
                            <label for="nombre_completo" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="id_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_rol" name="id_rol" required onchange="toggleRoleSpecificFields()">
                                <option value="">Selecciona un rol</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?php echo htmlspecialchars($rol['id']); ?>" data-role-name="<?php echo htmlspecialchars($rol['nombre_rol']); ?>">
                                        <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                                <option value="Bloqueado">Bloqueado</option>
                            </select>
                        </div>
                    </div>

                    <div id="studentFields" style="display: none;">
                        <hr>
                        <h5>Datos de Estudiante</h5>
                        <div class="mb-3">
                            <label for="codigo_registro" class="form-label">Código de Registro <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="codigo_registro" name="codigo_registro">
                        </div>
                        <div class="mb-3">
                            <label for="id_curso_entrada_actual" class="form-label">Curso de Entrada (Año Actual: <?php echo htmlspecialchars($active_anio_nombre ?? 'N/A'); ?>) <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_curso_entrada_actual" name="id_curso_entrada_actual">
                                <option value="">Selecciona el curso de entrada</option>
                                <?php foreach ($cursos_entrada_anio_actual as $curso): ?>
                                    <option value="<?php echo htmlspecialchars($curso['id']); ?>">
                                        <?php echo htmlspecialchars($curso['nombre_curso']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$active_anio_id): ?>
                                <small class="form-text text-danger">No hay un año académico activo definido. No se pueden seleccionar cursos de entrada.</small>
                            <?php elseif (empty($cursos_entrada_anio_actual)): ?>
                                <small class="form-text text-warning">No hay cursos disponibles para el año académico activo (<?php echo htmlspecialchars($active_anio_nombre); ?>).</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="professorFields" style="display: none;">
                        <hr>
                        <h5>Datos de Profesor</h5>
                        <div class="mb-3">
                            <label for="especialidad" class="form-label">Especialidad</label>
                            <input type="text" class="form-control" id="especialidad" name="especialidad">
                        </div>
                        <div class="mb-3">
                            <label for="grado_academico" class="form-label">Grado Académico</label>
                            <input type="text" class="form-control" id="grado_academico" name="grado_academico">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="submitUserBtn">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const userModal = document.getElementById('userModal');
        const userForm = document.getElementById('userForm');
        const formAction = document.getElementById('formAction');
        const userId = document.getElementById('userId');
        const userModalLabel = document.getElementById('userModalLabel');
        const passwordField = document.getElementById('password');
        const passwordRequired = document.getElementById('passwordRequired');
        const passwordHelp = document.getElementById('passwordHelp');

        const studentFields = document.getElementById('studentFields');
        const codigoRegistro = document.getElementById('codigo_registro');
        const idCursoEntradaActual = document.getElementById('id_curso_entrada_actual');

        const professorFields = document.getElementById('professorFields');
        const especialidad = document.getElementById('especialidad');
        const gradoAcademico = document.getElementById('grado_academico');

        const idRolSelect = document.getElementById('id_rol');

        // Function to toggle role-specific fields
        window.toggleRoleSpecificFields = function() {
            const selectedRoleOption = idRolSelect.options[idRolSelect.selectedIndex];
            const selectedRoleName = selectedRoleOption ? selectedRoleOption.dataset.roleName : '';

            // Reset and hide all specific fields initially
            studentFields.style.display = 'none';
            codigoRegistro.removeAttribute('required');
            idCursoEntradaActual.removeAttribute('required');
            idCursoEntradaActual.value = ''; // Clear selection on role change

            professorFields.style.display = 'none';
            especialidad.removeAttribute('required');
            gradoAcademico.removeAttribute('required');

            if (selectedRoleName === 'Estudiante') {
                studentFields.style.display = 'block';
                codigoRegistro.setAttribute('required', 'required');
                idCursoEntradaActual.setAttribute('required', 'required');
            } else if (selectedRoleName === 'Profesor') {
                professorFields.style.display = 'block';
                // You can add 'required' for professor fields if needed
            }
        };

        // Event listener for "Add New User" button
        document.getElementById('addUserBtn').addEventListener('click', function() {
            userModalLabel.textContent = 'Añadir Nuevo Usuario';
            formAction.value = 'add';
            userForm.reset(); // Clear form fields
            userId.value = '';
            passwordField.setAttribute('required', 'required');
            passwordRequired.style.display = 'inline';
            passwordHelp.textContent = 'Introduce una contraseña.';
            toggleRoleSpecificFields(); // Ensure fields are hidden for initial state
        });

        // Event listener for "Edit" buttons
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                userModalLabel.textContent = 'Editar Usuario';
                formAction.value = 'edit';
                passwordField.removeAttribute('required');
                passwordRequired.style.display = 'none';
                passwordHelp.textContent = 'Deja vacío para no cambiar la contraseña.';

                const row = this.closest('tr');
                userId.value = row.dataset.id;
                document.getElementById('nombre_usuario').value = row.dataset.nombre_usuario;
                document.getElementById('nombre_completo').value = row.dataset.nombre_completo;
                document.getElementById('email').value = row.dataset.email;
                document.getElementById('telefono').value = row.dataset.telefono;
                document.getElementById('nip').value = row.dataset.nip;
                document.getElementById('id_rol').value = row.dataset.id_rol;
                document.getElementById('estado').value = row.dataset.estado;

                // Populate role-specific fields
                toggleRoleSpecificFields(); // Call to show/hide fields based on selected role

                const selectedRoleOption = idRolSelect.options[idRolSelect.selectedIndex];
                const selectedRoleName = selectedRoleOption ? selectedRoleOption.dataset.roleName : '';

                if (selectedRoleName === 'Estudiante') {
                    codigoRegistro.value = row.dataset.codigo_registro;
                    // Pre-select the current entry course for the active year
                    const cursoEntradaId = row.dataset.curso_entrada_id;
                    if (cursoEntradaId) {
                         idCursoEntradaActual.value = cursoEntradaId;
                    } else {
                        idCursoEntradaActual.value = ''; // No entry course set for current year
                    }
                } else if (selectedRoleName === 'Profesor') {
                    especialidad.value = row.dataset.especialidad;
                    gradoAcademico.value = row.dataset.grado_academico;
                }
            });
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const tableBody = document.querySelector('#usuariosTable tbody');
            const rows = tableBody.querySelectorAll('tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            setupPagination(); // Re-apply pagination after filtering
        });

        // Initial call to hide/show fields based on default selection or previous state
        toggleRoleSpecificFields();

        // Pagination (simplified, assumes all rows are loaded initially)
        const rowsPerPage = 10; // Number of rows per page
        const usersTable = document.getElementById('usuariosTable');
        const tbody = usersTable.querySelector('tbody');
        const paginationContainer = document.getElementById('pagination');

        function setupPagination() {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const visibleRows = rows.filter(row => row.style.display !== 'none');
            const pageCount = Math.ceil(visibleRows.length / rowsPerPage);
            paginationContainer.innerHTML = ''; // Clear existing pagination

            if (pageCount <= 1) {
                return; // No pagination needed for 1 or less pages
            }

            for (let i = 1; i <= pageCount; i++) {
                const li = document.createElement('li');
                li.classList.add('page-item');
                const a = document.createElement('a');
                a.classList.add('page-link');
                a.href = '#';
                a.textContent = i;
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    displayPage(i);
                });
                li.appendChild(a);
                paginationContainer.appendChild(li);
            }
            displayPage(1); // Display the first page initially
        }

        function displayPage(page) {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const visibleRows = rows.filter(row => row.style.display !== 'none');
            const startIndex = (page - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;

            visibleRows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Update active state of pagination links
            paginationContainer.querySelectorAll('.page-item').forEach(item => {
                item.classList.remove('active');
            });
            paginationContainer.children[page - 1]?.classList.add('active');
        }

        // Initial setup of pagination on page load
        setupPagination();

        // Display flash messages (if any)
        const flashMessages = <?php echo json_encode($flash_messages); ?>;
        if (flashMessages) {
            flashMessages.forEach(msg => {
                const alertDiv = document.createElement('div');
                alertDiv.classList.add('alert', `alert-${msg.type}`, 'alert-dismissible', 'fade', 'show', 'mt-3');
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = `
                    ${msg.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.container-fluid').prepend(alertDiv);
            });
        }
    });
</script>