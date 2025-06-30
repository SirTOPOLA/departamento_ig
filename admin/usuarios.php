<?php
require_once '../includes/functions.php';
check_login_and_role('Administrador');
require_once '../config/database.php';

$page_title = "Gestión de Usuarios";
include_once '../includes/header.php';

// --- Lógica para añadir/editar/eliminar usuarios ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $nombre_usuario = sanitize_input($_POST['nombre_usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $nombre_completo = sanitize_input($_POST['nombre_completo'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $telefono = sanitize_input($_POST['telefono'] ?? '');
    $nip = sanitize_input($_POST['nip'] ?? '');
    $id_rol = filter_var($_POST['id_rol'] ?? null, FILTER_VALIDATE_INT);
    $estado = sanitize_input($_POST['estado'] ?? 'Activo');

    // Campos específicos de Estudiante
    $codigo_registro = sanitize_input($_POST['codigo_registro'] ?? '');
    $id_anio_inicio = filter_var($_POST['id_anio_inicio'] ?? null, FILTER_VALIDATE_INT);
    $id_curso_inicio = filter_var($_POST['id_curso_inicio'] ?? null, FILTER_VALIDATE_INT);

    // NUEVOS Campos específicos de Profesor
    $especialidad = sanitize_input($_POST['especialidad'] ?? '');
    $grado_academico = sanitize_input($_POST['grado_academico'] ?? '');

    $nombre_rol_seleccionado = '';
    if ($id_rol) {
        $stmt_rol = $pdo->prepare("SELECT nombre_rol FROM roles WHERE id = :id_rol");
        $stmt_rol->bindParam(':id_rol', $id_rol);
        $stmt_rol->execute();
        $nombre_rol_seleccionado = $stmt_rol->fetchColumn();
    }

    if (empty($nombre_usuario) || empty($nombre_completo) || empty($id_rol) || empty($nip)) {
        set_flash_message('danger', 'Error: Todos los campos obligatorios (Usuario, Nombre Completo, Rol, NIP) deben ser rellenados.');
    } else {
        try {
            if ($action === 'add') {
                if (empty($password)) {
                    set_flash_message('danger', 'Error: La contraseña no puede estar vacía al crear un nuevo usuario.');
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, password_hash, id_rol, nombre_completo, email, telefono, nip, estado)
                                                VALUES (:nombre_usuario, :password_hash, :id_rol, :nombre_completo, :email, :telefono, :nip, :estado)");
                    $stmt->bindParam(':nombre_usuario', $nombre_usuario);
                    $stmt->bindParam(':password_hash', $password_hash);
                    $stmt->bindParam(':id_rol', $id_rol);
                    $stmt->bindParam(':nombre_completo', $nombre_completo);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':telefono', $telefono);
                    $stmt->bindParam(':nip', $nip);
                    $stmt->bindParam(':estado', $estado);
                    $stmt->execute();
                    $new_user_id = $pdo->lastInsertId();

                    if ($nombre_rol_seleccionado === 'Estudiante') {
                        if (empty($codigo_registro) || empty($id_anio_inicio) || empty($id_curso_inicio)) {
                             set_flash_message('danger', 'Error: Para estudiantes, el Código de Registro, Año de Inicio y Curso de Inicio son obligatorios.');
                             $pdo->rollBack();
                        } else {
                            $stmt_estudiante = $pdo->prepare("INSERT INTO estudiantes (id_usuario, codigo_registro, id_anio_inicio, id_curso_inicio) VALUES (:id_usuario, :codigo_registro, :id_anio_inicio, :id_curso_inicio)");
                            $stmt_estudiante->bindParam(':id_usuario', $new_user_id);
                            $stmt_estudiante->bindParam(':codigo_registro', $codigo_registro);
                            $stmt_estudiante->bindParam(':id_anio_inicio', $id_anio_inicio);
                            $stmt_estudiante->bindParam(':id_curso_inicio', $id_curso_inicio);
                            $stmt_estudiante->execute();
                            $pdo->commit();
                            set_flash_message('success', 'Usuario Estudiante añadido correctamente.');
                        }
                    } elseif ($nombre_rol_seleccionado === 'Profesor') { // NUEVA LÓGICA PARA PROFESOR
                        // Aquí no hacemos campos obligatorios por defecto, pero podrías añadirlos si lo necesitas
                        $stmt_profesor = $pdo->prepare("INSERT INTO profesores (id_usuario, especialidad, grado_academico) VALUES (:id_usuario, :especialidad, :grado_academico)");
                        $stmt_profesor->bindParam(':id_usuario', $new_user_id);
                        $stmt_profesor->bindParam(':especialidad', $especialidad);
                        $stmt_profesor->bindParam(':grado_academico', $grado_academico);
                        $stmt_profesor->execute();
                        $pdo->commit();
                        set_flash_message('success', 'Usuario Profesor añadido correctamente.');
                    } else {
                        $pdo->commit();
                        set_flash_message('success', 'Usuario añadido correctamente.');
                    }
                }
            } elseif ($action === 'edit') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de usuario no válido para edición.');
                } else {
                    $update_password_sql = '';
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $update_password_sql = ', password_hash = :password_hash';
                    }

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("UPDATE usuarios SET nombre_usuario = :nombre_usuario, id_rol = :id_rol, nombre_completo = :nombre_completo, email = :email, telefono = :telefono, nip = :nip, estado = :estado {$update_password_sql} WHERE id = :id");
                    $stmt->bindParam(':nombre_usuario', $nombre_usuario);
                    $stmt->bindParam(':id_rol', $id_rol);
                    $stmt->bindParam(':nombre_completo', $nombre_completo);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':telefono', $telefono);
                    $stmt->bindParam(':nip', $nip);
                    $stmt->bindParam(':estado', $estado);
                    if (!empty($password)) {
                        $stmt->bindParam(':password_hash', $password_hash);
                    }
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();

                    // Lógica para ESTUDIANTES
                    if ($nombre_rol_seleccionado === 'Estudiante') {
                         if (empty($codigo_registro) || empty($id_anio_inicio) || empty($id_curso_inicio)) {
                             set_flash_message('danger', 'Error: Para estudiantes, el Código de Registro, Año de Inicio y Curso de Inicio son obligatorios.');
                             $pdo->rollBack();
                         } else {
                            $stmt_check_est = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                            $stmt_check_est->bindParam(':id_usuario', $id);
                            $stmt_check_est->execute();
                            if ($stmt_check_est->fetch()) {
                                $stmt_estudiante = $pdo->prepare("UPDATE estudiantes SET codigo_registro = :codigo_registro, id_anio_inicio = :id_anio_inicio, id_curso_inicio = :id_curso_inicio WHERE id_usuario = :id_usuario");
                            } else {
                                $stmt_estudiante = $pdo->prepare("INSERT INTO estudiantes (id_usuario, codigo_registro, id_anio_inicio, id_curso_inicio) VALUES (:id_usuario, :codigo_registro, :id_anio_inicio, :id_curso_inicio)");
                            }
                            $stmt_estudiante->bindParam(':id_usuario', $id);
                            $stmt_estudiante->bindParam(':codigo_registro', $codigo_registro);
                            $stmt_estudiante->bindParam(':id_anio_inicio', $id_anio_inicio);
                            $stmt_estudiante->bindParam(':id_curso_inicio', $id_curso_inicio);
                            $stmt_estudiante->execute();
                            // También elimina el registro de profesor si el usuario era antes un profesor
                            $stmt_delete_prof = $pdo->prepare("DELETE FROM profesores WHERE id_usuario = :id_usuario");
                            $stmt_delete_prof->bindParam(':id_usuario', $id);
                            $stmt_delete_prof->execute();
                            $pdo->commit();
                            set_flash_message('success', 'Usuario Estudiante actualizado correctamente.');
                        }
                    } elseif ($nombre_rol_seleccionado === 'Profesor') { // NUEVA LÓGICA PARA PROFESORES
                        $stmt_check_prof = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
                        $stmt_check_prof->bindParam(':id_usuario', $id);
                        $stmt_check_prof->execute();
                        if ($stmt_check_prof->fetch()) {
                            $stmt_profesor = $pdo->prepare("UPDATE profesores SET especialidad = :especialidad, grado_academico = :grado_academico WHERE id_usuario = :id_usuario");
                        } else {
                            $stmt_profesor = $pdo->prepare("INSERT INTO profesores (id_usuario, especialidad, grado_academico) VALUES (:id_usuario, :especialidad, :grado_academico)");
                        }
                        $stmt_profesor->bindParam(':id_usuario', $id);
                        $stmt_profesor->bindParam(':especialidad', $especialidad);
                        $stmt_profesor->bindParam(':grado_academico', $grado_academico);
                        $stmt_profesor->execute();
                        // También elimina el registro de estudiante si el usuario era antes un estudiante
                        $stmt_delete_est = $pdo->prepare("DELETE FROM estudiantes WHERE id_usuario = :id_usuario");
                        $stmt_delete_est->bindParam(':id_usuario', $id);
                        $stmt_delete_est->execute();
                        $pdo->commit();
                        set_flash_message('success', 'Usuario Profesor actualizado correctamente.');
                    } else {
                        // Si el rol no es Estudiante ni Profesor, elimina registros de ambas tablas (si existen)
                        $stmt_delete_est = $pdo->prepare("DELETE FROM estudiantes WHERE id_usuario = :id_usuario");
                        $stmt_delete_est->bindParam(':id_usuario', $id);
                        $stmt_delete_est->execute();

                        $stmt_delete_prof = $pdo->prepare("DELETE FROM profesores WHERE id_usuario = :id_usuario");
                        $stmt_delete_prof->bindParam(':id_usuario', $id);
                        $stmt_delete_prof->execute();

                        $pdo->commit();
                        set_flash_message('success', 'Usuario actualizado correctamente.');
                    }
                }
            } elseif ($action === 'delete') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de usuario no válido para eliminación.');
                } else {
                    $pdo->beginTransaction();
                    // Eliminar de tablas relacionadas primero debido a claves foráneas
                    $stmt_delete_est = $pdo->prepare("DELETE FROM estudiantes WHERE id_usuario = :id");
                    $stmt_delete_est->bindParam(':id', $id);
                    $stmt_delete_est->execute();

                    // NUEVA LÓGICA: Eliminar de profesores si existe
                    $stmt_delete_prof = $pdo->prepare("DELETE FROM profesores WHERE id_usuario = :id");
                    $stmt_delete_prof->bindParam(':id', $id);
                    $stmt_delete_prof->execute();

                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    $pdo->commit();
                    set_flash_message('success', 'Usuario eliminado correctamente.');
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
        }
    }
}

// --- Obtener todos los roles para el select del formulario ---
$stmt_roles = $pdo->query("SELECT id, nombre_rol FROM roles ORDER BY nombre_rol");
$roles = $stmt_roles->fetchAll();

// --- Obtener años académicos y cursos para el select de estudiantes ---
$stmt_anios = $pdo->query("SELECT id, nombre_anio FROM anios_academicos ORDER BY nombre_anio DESC");
$anios_academicos = $stmt_anios->fetchAll();

$stmt_cursos = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
$cursos = $stmt_cursos->fetchAll();

// --- Obtener todos los usuarios para la tabla ---
$stmt_usuarios = $pdo->query("
    SELECT u.id, u.nombre_usuario, u.nombre_completo, u.email, u.telefono, u.nip, u.estado,
           r.id AS id_rol, r.nombre_rol, -- Aseguramos que el id_rol se recupere correctamente
           e.codigo_registro,
           e.id_anio_inicio, -- Recuperar el ID del año para el select
           (SELECT nombre_anio FROM anios_academicos WHERE id = e.id_anio_inicio) AS anio_inicio_nombre,
           e.id_curso_inicio, -- Recuperar el ID del curso para el select
           (SELECT nombre_curso FROM cursos WHERE id = e.id_curso_inicio) AS curso_inicio_nombre,
           p.especialidad,   -- NUEVO: Campo de profesores
           p.grado_academico -- NUEVO: Campo de profesores
    FROM usuarios u
    JOIN roles r ON u.id_rol = r.id
    LEFT JOIN estudiantes e ON u.id = e.id_usuario
    LEFT JOIN profesores p ON u.id = p.id_usuario -- NUEVO: Unir con la tabla profesores
    ORDER BY u.id DESC
");
$usuarios = $stmt_usuarios->fetchAll();

// NEW: Obtener mensajes flash para JavaScript
$flash_messages = get_flash_messages();

?>

<h1 class="mt-4">Gestión de Usuarios</h1>
<p class="lead">Administra los usuarios (Administradores, Estudiantes, Profesores) del sistema.</p>

<?php // echo $message; // <-- ¡ELIMINA ESTA LÍNEA! Los mensajes se mostrarán con JS ahora ?>

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
                        <th>Año Inicio</th>
                        <th>Curso Inicio</th>
                        <th>Especialidad</th> <th>Grado Académico</th> <th>Estado</th>
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
                                data-id_anio_inicio="<?php echo htmlspecialchars($user['id_anio_inicio'] ?? ''); ?>"
                                data-id_curso_inicio="<?php echo htmlspecialchars($user['id_curso_inicio'] ?? ''); ?>"
                                data-especialidad="<?php echo htmlspecialchars($user['especialidad'] ?? ''); ?>" data-grado_academico="<?php echo htmlspecialchars($user['grado_academico'] ?? ''); ?>" data-estado="<?php echo htmlspecialchars($user['estado']); ?>">
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['nip']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_usuario']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Estudiante' ? ($user['codigo_registro'] ?? '-') : '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Estudiante' ? ($user['anio_inicio_nombre'] ?? '-') : '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Estudiante' ? ($user['curso_inicio_nombre'] ?? '-') : '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Profesor' ? ($user['especialidad'] ?? '-') : '-'); ?></td> <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Profesor' ? ($user['grado_academico'] ?? '-') : '-'); ?></td> <td>
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
                            <td colspan="14" class="text-center">No hay usuarios registrados.</td> </tr>
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
            <form id="userForm" action="usuarios.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="userModalLabel">Añadir Nuevo Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="userId">

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
                                    <option value="<?php echo htmlspecialchars($rol['id']); ?>">
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

                    <div id="student-fields" style="display: none;">
                        <hr>
                        <h6 class="mb-3 text-primary">Detalles de Estudiante</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="codigo_registro" class="form-label">Código de Registro <span class="text-danger student-required-label">*</span></label>
                                <input type="text" class="form-control" id="codigo_registro" name="codigo_registro">
                            </div>
                            <div class="col-md-4">
                                <label for="id_anio_inicio" class="form-label">Año de Inicio <span class="text-danger student-required-label">*</span></label>
                                <select class="form-select" id="id_anio_inicio" name="id_anio_inicio">
                                    <option value="">Selecciona un año</option>
                                    <?php foreach ($anios_academicos as $anio): ?>
                                        <option value="<?php echo htmlspecialchars($anio['id']); ?>">
                                            <?php echo htmlspecialchars($anio['nombre_anio']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="id_curso_inicio" class="form-label">Curso de Inicio <span class="text-danger student-required-label">*</span></label>
                                <select class="form-select" id="id_curso_inicio" name="id_curso_inicio">
                                    <option value="">Selecciona un curso</option>
                                    <?php foreach ($cursos as $curso): ?>
                                        <option value="<?php echo htmlspecialchars($curso['id']); ?>">
                                            <?php echo htmlspecialchars($curso['nombre_curso']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="professor-fields" style="display: none;">
                        <hr>
                        <h6 class="mb-3 text-success">Detalles de Profesor</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="especialidad" class="form-label">Especialidad</label>
                                <input type="text" class="form-control" id="especialidad" name="especialidad">
                            </div>
                            <div class="col-md-6">
                                <label for="grado_academico" class="form-label">Grado Académico</label>
                                <input type="text" class="form-control" id="grado_academico" name="grado_academico">
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cerrar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save me-1"></i> Añadir Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
</div>

<script>
    const flashMessages = <?php echo json_encode($flash_messages); ?>;

    // Obtener el mapeo de ID de rol a nombre de rol desde PHP
    const roleNames = {};
    <?php foreach ($roles as $rol): ?>
        roleNames[<?php echo htmlspecialchars($rol['id']); ?>] = '<?php echo htmlspecialchars($rol['nombre_rol']); ?>';
    <?php endforeach; ?>

    // Función para mostrar/ocultar campos específicos de cada rol
    function toggleRoleSpecificFields() {
        var rolSelect = document.getElementById('id_rol');
        var selectedRoleName = roleNames[rolSelect.value]; // Obtener el nombre del rol

        var studentFields = document.getElementById('student-fields');
        var studentRequiredInputs = studentFields.querySelectorAll('input, select');
        var professorFields = document.getElementById('professor-fields');
        var professorInputs = professorFields.querySelectorAll('input, select'); // Los campos de profesor no son obligatorios por defecto

        // Ocultar todos los campos específicos primero
        studentFields.style.display = 'none';
        studentRequiredInputs.forEach(function(input) {
            input.removeAttribute('required');
            input.value = ''; // Limpiar campos al ocultar
        });

        professorFields.style.display = 'none';
        professorInputs.forEach(function(input) {
            // input.removeAttribute('required'); // Los de profesor no suelen ser required
            input.value = ''; // Limpiar campos al ocultar
        });


        if (selectedRoleName === 'Estudiante') {
            studentFields.style.display = 'block';
            studentRequiredInputs.forEach(function(input) {
                input.setAttribute('required', 'required');
            });
        } else if (selectedRoleName === 'Profesor') {
            professorFields.style.display = 'block';
            // Los campos de profesor no son requeridos por defecto, así que no se añaden 'required'
        }
    }


    // Lógica para abrir modal de "Añadir Nuevo Usuario"
    document.getElementById('addUserBtn').addEventListener('click', function() {
        document.getElementById('userModalLabel').innerText = 'Añadir Nuevo Usuario';
        document.getElementById('formAction').value = 'add';
        document.getElementById('userId').value = '';
        document.getElementById('userForm').reset(); // Limpia el formulario
        document.getElementById('password').setAttribute('required', 'required'); // Contraseña es requerida para añadir
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('passwordHelp').innerText = 'Introduce una contraseña.';
        document.getElementById('submitBtn').innerText = 'Añadir Usuario';
        document.getElementById('estado').value = 'Activo'; // Default para añadir
        toggleRoleSpecificFields(); // Asegura que los campos específicos estén ocultos por defecto
    });

    // Lógica para abrir modal de "Editar Usuario"
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('userModalLabel').innerText = 'Editar Usuario';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('password').removeAttribute('required'); // Contraseña no es requerida para editar
            document.getElementById('passwordRequired').style.display = 'none';
            document.getElementById('passwordHelp').innerText = 'Deja este campo vacío para mantener la contraseña actual.';
            document.getElementById('submitBtn').innerText = 'Guardar Cambios';

            const row = this.closest('tr');
            document.getElementById('userId').value = row.dataset.id;
            document.getElementById('nombre_usuario').value = row.dataset.nombre_usuario;
            document.getElementById('nip').value = row.dataset.nip;
            document.getElementById('nombre_completo').value = row.dataset.nombre_completo;
            document.getElementById('email').value = row.dataset.email;
            document.getElementById('telefono').value = row.dataset.telefono;
            document.getElementById('id_rol').value = row.dataset.id_rol; // Asegúrate de que este es el ID del rol
            document.getElementById('estado').value = row.dataset.estado;

            // Campos específicos de estudiante
            document.getElementById('codigo_registro').value = row.dataset.codigo_registro;
            document.getElementById('id_anio_inicio').value = row.dataset.id_anio_inicio;
            document.getElementById('id_curso_inicio').value = row.dataset.id_curso_inicio;

            // NUEVO: Campos específicos de profesor
            document.getElementById('especialidad').value = row.dataset.especialidad;
            document.getElementById('grado_academico').value = row.dataset.grado_academico;

            toggleRoleSpecificFields(); // Ajusta visibilidad y requeridos de campos según el rol
        });
    });

    // --- Búsqueda dinámica ---
    document.getElementById('searchInput').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("usuariosTable");
        tr = table.getElementsByTagName("tr");

        document.getElementById('pagination').style.display = 'none'; // Ocultar paginación durante la búsqueda

        for (i = 1; i < tr.length; i++) { // Empieza en 1 para saltar el thead
            tr[i].style.display = "none"; // Oculta todas las filas por defecto
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = ""; // Muestra la fila si hay coincidencia
                        break;
                    }
                }
            }
        }
        if (filter === "") {
            document.getElementById('pagination').style.display = 'flex';
            showPage(currentPage); // Vuelve a mostrar la paginación al borrar el filtro
        }
    });

    // --- Paginación ---
    const rowsPerPage = 10;
    let currentPage = 1;
    let totalPages = 0;

    function setupPagination() {
        const table = document.getElementById('usuariosTable');
        const tbodyRows = table.querySelectorAll('tbody tr');
        totalPages = Math.ceil(tbodyRows.length / rowsPerPage);

        const paginationUl = document.getElementById('pagination');
        paginationUl.innerHTML = '';

        if (tbodyRows.length <= rowsPerPage && document.getElementById('searchInput').value === "") { // Solo ocultar si no hay búsqueda
             paginationUl.style.display = 'none';
             tbodyRows.forEach(row => row.style.display = ''); // Mostrar todas las filas si no hay paginación y búsqueda
             return;
        } else {
             paginationUl.style.display = 'flex';
        }


        // Botón "Anterior"
        let prevLi = document.createElement('li');
        prevLi.classList.add('page-item');
        if (currentPage === 1) prevLi.classList.add('disabled');
        prevLi.innerHTML = `<a class="page-link" href="#" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a>`;
        prevLi.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentPage > 1) {
                currentPage--;
                showPage(currentPage);
                setupPagination();
            }
        });
        paginationUl.appendChild(prevLi);

        // Números de página
        for (let i = 1; i <= totalPages; i++) {
            let li = document.createElement('li');
            li.classList.add('page-item');
            if (i === currentPage) li.classList.add('active');
            li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            li.addEventListener('click', (e) => {
                e.preventDefault();
                currentPage = i;
                showPage(currentPage);
                setupPagination();
            });
            paginationUl.appendChild(li);
        }

        // Botón "Siguiente"
        let nextLi = document.createElement('li');
        nextLi.classList.add('page-item');
        if (currentPage === totalPages) nextLi.classList.add('disabled');
        nextLi.innerHTML = `<a class="page-link" href="#" aria-label="Next"><span aria-hidden="true">&raquo;</span></a>`;
        nextLi.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentPage < totalPages) {
                currentPage++;
                showPage(currentPage);
                setupPagination();
            }
        });
        paginationUl.appendChild(nextLi);
    }

    function showPage(page) {
        const table = document.getElementById('usuariosTable');
        const tbodyRows = table.querySelectorAll('tbody tr');
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        tbodyRows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Ejecutar al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        showPage(currentPage);
        setupPagination();
        toggleRoleSpecificFields(); // Asegurar que los campos correctos estén visibles/ocultos al cargar la página

        // Mostrar mensajes flash al cargar la página
        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });

        // Event listener para el cierre del modal para recargar la página si se realizó una acción exitosa
        var userModalElement = document.getElementById('userModal');
        userModalElement.addEventListener('hidden.bs.modal', function (event) {
            // Comprobar si hay mensajes de éxito. Si los hay, significa que se realizó una acción y es buena idea recargar.
            const hasSuccessMessage = flashMessages.some(msg => msg.type === 'success');
            if (hasSuccessMessage) {
                 window.location.reload(); // Recargar la página para ver los cambios
            }
        });
    });

</script>