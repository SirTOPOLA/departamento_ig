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
    // Eliminamos id_anio_inicio y id_curso_inicio directos de 'estudiantes' si la nueva tabla 'curso_estudiante' maneja esto.
    // En su lugar, capturamos los nuevos campos para la relación curso_estudiante
    $id_anio_inscripcion = filter_var($_POST['id_anio_inscripcion'] ?? null, FILTER_VALIDATE_INT);
    $cursos_seleccionados = $_POST['cursos_seleccionados'] ?? []; // Array de IDs de cursos

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
                        // Aquí, el código de registro es obligatorio para un estudiante.
                        // La inscripción a cursos se maneja en curso_estudiante.
                        if (empty($codigo_registro)) { // Quitamos la validación de id_anio_inicio e id_curso_inicio aquí
                             set_flash_message('danger', 'Error: Para estudiantes, el Código de Registro es obligatorio.');
                             $pdo->rollBack();
                        } else {
                            $stmt_estudiante = $pdo->prepare("INSERT INTO estudiantes (id_usuario, codigo_registro) VALUES (:id_usuario, :codigo_registro)");
                            $stmt_estudiante->bindParam(':id_usuario', $new_user_id);
                            $stmt_estudiante->bindParam(':codigo_registro', $codigo_registro);
                            $stmt_estudiante->execute();
                            $id_estudiante_reciente = $pdo->lastInsertId(); // Obtener el ID del estudiante recién creado

                            // Insertar en la tabla curso_estudiante
                            if (!empty($cursos_seleccionados) && $id_anio_inscripcion) {
                                $stmt_curso_estudiante = $pdo->prepare("INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio) VALUES (:id_estudiante, :id_curso, :id_anio)");
                                foreach ($cursos_seleccionados as $curso_id) {
                                    $stmt_curso_estudiante->bindParam(':id_estudiante', $id_estudiante_reciente);
                                    $stmt_curso_estudiante->bindParam(':id_curso', $curso_id);
                                    $stmt_curso_estudiante->bindParam(':id_anio', $id_anio_inscripcion);
                                    $stmt_curso_estudiante->execute();
                                }
                            }

                            $pdo->commit();
                            set_flash_message('success', 'Usuario Estudiante añadido correctamente.');
                        }
                    } elseif ($nombre_rol_seleccionado === 'Profesor') { // LÓGICA PARA PROFESOR
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
                        if (empty($codigo_registro)) { // Solo validamos el código de registro aquí
                            set_flash_message('danger', 'Error: Para estudiantes, el Código de Registro es obligatorio.');
                            $pdo->rollBack();
                        } else {
                            // Obtener el ID del estudiante asociado al usuario
                            $stmt_get_estudiante_id = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                            $stmt_get_estudiante_id->bindParam(':id_usuario', $id);
                            $stmt_get_estudiante_id->execute();
                            $current_estudiante_id = $stmt_get_estudiante_id->fetchColumn();

                            if ($current_estudiante_id) {
                                $stmt_estudiante = $pdo->prepare("UPDATE estudiantes SET codigo_registro = :codigo_registro WHERE id = :id_estudiante");
                                $stmt_estudiante->bindParam(':codigo_registro', $codigo_registro);
                                $stmt_estudiante->bindParam(':id_estudiante', $current_estudiante_id);
                                $stmt_estudiante->execute();
                            } else {
                                // Si no existe un registro de estudiante, crearlo
                                $stmt_estudiante = $pdo->prepare("INSERT INTO estudiantes (id_usuario, codigo_registro) VALUES (:id_usuario, :codigo_registro)");
                                $stmt_estudiante->bindParam(':id_usuario', $id);
                                $stmt_estudiante->bindParam(':codigo_registro', $codigo_registro);
                                $stmt_estudiante->execute();
                                $current_estudiante_id = $pdo->lastInsertId();
                            }

                            // Eliminar todas las inscripciones de cursos existentes para este estudiante
                            $stmt_delete_cursos_est = $pdo->prepare("DELETE FROM curso_estudiante WHERE id_estudiante = :id_estudiante");
                            $stmt_delete_cursos_est->bindParam(':id_estudiante', $current_estudiante_id);
                            $stmt_delete_cursos_est->execute();

                            // Insertar nuevas inscripciones de cursos si se han seleccionado
                            if (!empty($cursos_seleccionados) && $id_anio_inscripcion) {
                                $stmt_insert_curso_est = $pdo->prepare("INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio) VALUES (:id_estudiante, :id_curso, :id_anio)");
                                foreach ($cursos_seleccionados as $curso_id) {
                                    $stmt_insert_curso_est->bindParam(':id_estudiante', $current_estudiante_id);
                                    $stmt_insert_curso_est->bindParam(':id_curso', $curso_id);
                                    $stmt_insert_curso_est->bindParam(':id_anio', $id_anio_inscripcion);
                                    $stmt_insert_curso_est->execute();
                                }
                            }

                            // Elimina el registro de profesor si el usuario era antes un profesor y ahora es estudiante
                            $stmt_delete_prof = $pdo->prepare("DELETE FROM profesores WHERE id_usuario = :id_usuario");
                            $stmt_delete_prof->bindParam(':id_usuario', $id);
                            $stmt_delete_prof->execute();
                            $pdo->commit();
                            set_flash_message('success', 'Usuario Estudiante actualizado correctamente.');
                        }
                    } elseif ($nombre_rol_seleccionado === 'Profesor') { // LÓGICA PARA PROFESORES
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
                        // Y elimina sus cursos asociados en curso_estudiante
                        $stmt_get_estudiante_id_to_delete = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                        $stmt_get_estudiante_id_to_delete->bindParam(':id_usuario', $id);
                        $estudiante_id_to_delete = $stmt_get_estudiante_id_to_delete->fetchColumn();
                        if ($estudiante_id_to_delete) {
                            $stmt_delete_cursos_est = $pdo->prepare("DELETE FROM curso_estudiante WHERE id_estudiante = :id_estudiante");
                            $stmt_delete_cursos_est->bindParam(':id_estudiante', $estudiante_id_to_delete);
                            $stmt_delete_cursos_est->execute();
                        }

                        $pdo->commit();
                        set_flash_message('success', 'Usuario Profesor actualizado correctamente.');
                    } else {
                        // Si el rol no es Estudiante ni Profesor, elimina registros de ambas tablas (si existen)
                        $stmt_delete_est = $pdo->prepare("DELETE FROM estudiantes WHERE id_usuario = :id_usuario");
                        $stmt_delete_est->bindParam(':id_usuario', $id);
                        $stmt_delete_est->execute();

                        // También elimina sus cursos asociados en curso_estudiante
                        $stmt_get_estudiante_id_to_delete = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                        $stmt_get_estudiante_id_to_delete->bindParam(':id_usuario', $id);
                        $estudiante_id_to_delete = $stmt_get_estudiante_id_to_delete->fetchColumn();
                        if ($estudiante_id_to_delete) {
                            $stmt_delete_cursos_est = $pdo->prepare("DELETE FROM curso_estudiante WHERE id_estudiante = :id_estudiante");
                            $stmt_delete_cursos_est->bindParam(':id_estudiante', $estudiante_id_to_delete);
                            $stmt_delete_cursos_est->execute();
                        }

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

                    // Obtener el ID del estudiante para eliminar sus cursos asociados
                    $stmt_get_estudiante_id_to_delete = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                    $stmt_get_estudiante_id_to_delete->bindParam(':id_usuario', $id);
                    $estudiante_id_to_delete = $stmt_get_estudiante_id_to_delete->fetchColumn();

                    // Eliminar de curso_estudiante si el usuario es un estudiante
                    if ($estudiante_id_to_delete) {
                        $stmt_delete_cursos_est = $pdo->prepare("DELETE FROM curso_estudiante WHERE id_estudiante = :id_estudiante");
                        $stmt_delete_cursos_est->bindParam(':id_estudiante', $estudiante_id_to_delete);
                        $stmt_delete_cursos_est->execute();
                    }

                    // Eliminar de tablas relacionadas (estudiantes, profesores) primero debido a claves foráneas
                    $stmt_delete_est = $pdo->prepare("DELETE FROM estudiantes WHERE id_usuario = :id");
                    $stmt_delete_est->bindParam(':id', $id);
                    $stmt_delete_est->execute();

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

$stmt_cursos_all = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
$cursos_all = $stmt_cursos_all->fetchAll();

// --- Obtener todos los usuarios para la tabla ---
// Modificamos la consulta para recuperar los cursos del estudiante
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

// Para cada estudiante, obtener sus cursos asociados
foreach ($usuarios as &$user) {
    if ($user['nombre_rol'] === 'Estudiante' && $user['estudiante_id']) {
        $stmt_cursos_estudiante = $pdo->prepare("
            SELECT ce.id_curso, c.nombre_curso, ce.id_anio, a.nombre_anio
            FROM curso_estudiante ce
            JOIN cursos c ON ce.id_curso = c.id
            JOIN anios_academicos a ON ce.id_anio = a.id
            WHERE ce.id_estudiante = :estudiante_id
        ");
        $stmt_cursos_estudiante->bindParam(':estudiante_id', $user['estudiante_id']);
        $stmt_cursos_estudiante->execute();
        $user['cursos_inscritos'] = $stmt_cursos_estudiante->fetchAll(PDO::FETCH_ASSOC);

        // Para mostrar un resumen en la tabla principal (puedes ajustar esto)
        $user['cursos_nombres_display'] = [];
        $user['anios_inscripcion_display'] = [];
        foreach ($user['cursos_inscritos'] as $curso_inscrito) {
            $user['cursos_nombres_display'][] = $curso_inscrito['nombre_curso'];
            // Asumo que el "año de inicio" para la visualización es el año de inscripción del curso
            if (!in_array($curso_inscrito['nombre_anio'], $user['anios_inscripcion_display'])) {
                $user['anios_inscripcion_display'][] = $curso_inscrito['nombre_anio'];
            }
        }
        $user['cursos_nombres_display'] = implode(', ', $user['cursos_nombres_display']);
        $user['anios_inscripcion_display'] = implode(', ', $user['anios_inscripcion_display']);

    } else {
        $user['cursos_inscritos'] = [];
        $user['cursos_nombres_display'] = '-';
        $user['anios_inscripcion_display'] = '-';
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
                        <th>Cursos Inscritos</th> <th>Años de Inscripción</th> <th>Especialidad</th>
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
                                data-cursos_inscritos_json='<?php echo json_encode($user['cursos_inscritos']); ?>'
                                data-especialidad="<?php echo htmlspecialchars($user['especialidad'] ?? ''); ?>" data-grado_academico="<?php echo htmlspecialchars($user['grado_academico'] ?? ''); ?>" data-estado="<?php echo htmlspecialchars($user['estado']); ?>">
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['nip']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_usuario']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Estudiante' ? ($user['codigo_registro'] ?? '-') : '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['cursos_nombres_display']); ?></td> <td><?php echo htmlspecialchars($user['anios_inscripcion_display']); ?></td> <td><?php echo htmlspecialchars($user['nombre_rol'] === 'Profesor' ? ($user['especialidad'] ?? '-') : '-'); ?></td>
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

                    <div id="student-fields" style="display: none;">
                        <hr>
                        <h6 class="mb-3 text-primary">Detalles de Estudiante</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="codigo_registro" class="form-label">Código de Registro <span class="text-danger student-required-label">*</span></label>
                                <input type="text" class="form-control" id="codigo_registro" name="codigo_registro">
                            </div>
                             <div class="col-md-6">
                                <label for="id_anio_inscripcion" class="form-label">Año Académico de Inscripción <span class="text-danger student-required-label">*</span></label>
                                <select class="form-select" id="id_anio_inscripcion" name="id_anio_inscripcion">
                                    <option value="">Selecciona un año</option>
                                    <?php foreach ($anios_academicos as $anio): ?>
                                        <option value="<?php echo htmlspecialchars($anio['id']); ?>">
                                            <?php echo htmlspecialchars($anio['nombre_anio']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="cursos_seleccionados" class="form-label">Cursos a Inscribir <span class="text-danger student-required-label">*</span></label>
                                <select class="form-select" id="cursos_seleccionados" name="cursos_seleccionados[]" multiple size="5">
                                    <?php foreach ($cursos_all as $curso): ?>
                                        <option value="<?php echo htmlspecialchars($curso['id']); ?>">
                                            <?php echo htmlspecialchars($curso['nombre_curso']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Mantén 'Ctrl' (Windows/Linux) o 'Cmd' (Mac) para seleccionar múltiples cursos.</small>
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
    document.querySelectorAll('#id_rol option').forEach(option => {
        if (option.value) { // Ignorar la opción vacía
            roleNames[option.value] = option.dataset.roleName;
        }
    });

    // Función para mostrar/ocultar campos específicos de cada rol
    function toggleRoleSpecificFields() {
        var rolSelect = document.getElementById('id_rol');
        var selectedRoleName = roleNames[rolSelect.value]; // Obtener el nombre del rol

        var studentFields = document.getElementById('student-fields');
        var studentRequiredInputs = studentFields.querySelectorAll('input, select');
        var professorFields = document.getElementById('professor-fields');
        var professorInputs = professorFields.querySelectorAll('input, select');

        // Ocultar todos los campos específicos primero y remover 'required'
        studentFields.style.display = 'none';
        studentRequiredInputs.forEach(function(input) {
            input.removeAttribute('required');
            if (input.tagName === 'SELECT' && input.multiple) {
                // Deseleccionar todas las opciones para selects múltiples
                Array.from(input.options).forEach(option => option.selected = false);
            } else {
                input.value = ''; // Limpiar campos al ocultar
            }
        });

        professorFields.style.display = 'none';
        professorInputs.forEach(function(input) {
            input.removeAttribute('required'); // Los campos de profesor no son obligatorios por defecto
            input.value = ''; // Limpiar campos al ocultar
        });

        // Aplicar 'required' y mostrar campos según el rol seleccionado
        if (selectedRoleName === 'Estudiante') {
            studentFields.style.display = 'block';
            document.getElementById('codigo_registro').setAttribute('required', 'required');
            document.getElementById('id_anio_inscripcion').setAttribute('required', 'required');
            document.getElementById('cursos_seleccionados').setAttribute('required', 'required'); // Hacer el select múltiple requerido
        } else if (selectedRoleName === 'Profesor') {
            professorFields.style.display = 'block';
            // Los campos de profesor no son requeridos por defecto, así que no se añaden 'required'
        }
    }

    // Lógica para abrir modal de "Añadir Nuevo Usuario"
    document.getElementById('addUserBtn').addEventListener('click', function() {
        document.getElementById('userForm').reset();
        document.getElementById('userModalLabel').textContent = 'Añadir Nuevo Usuario';
        document.getElementById('formAction').value = 'add';
        document.getElementById('submitBtn').textContent = 'Añadir Usuario';
        document.getElementById('password').setAttribute('required', 'required');
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('passwordHelp').style.display = 'inline';
        toggleRoleSpecificFields(); // Asegúrate de que los campos específicos estén ocultos al añadir
    });

    // Lógica para abrir modal de "Editar Usuario"
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('usuariosTable').addEventListener('click', function(event) {
            const editBtn = event.target.closest('.edit-btn');
            if (editBtn) {
                const row = editBtn.closest('tr');
                const id = row.dataset.id;
                const nombre_usuario = row.dataset.nombre_usuario;
                const nombre_completo = row.dataset.nombre_completo;
                const email = row.dataset.email;
                const telefono = row.dataset.telefono;
                const nip = row.dataset.nip;
                const id_rol = row.dataset.id_rol;
                const nombre_rol = row.dataset.nombre_rol;
                const estado = row.dataset.estado;
                const codigo_registro = row.dataset.codigo_registro;
                const especialidad = row.dataset.especialidad;
                const grado_academico = row.dataset.grado_academico;
                const cursos_inscritos_json = row.dataset.cursos_inscritos_json;
                let cursos_inscritos = [];
                try {
                    cursos_inscritos = JSON.parse(cursos_inscritos_json);
                } catch (e) {
                    console.error('Error parsing cursos_inscritos_json:', e);
                }

                document.getElementById('userModalLabel').textContent = 'Editar Usuario';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('submitBtn').textContent = 'Actualizar Usuario';
                document.getElementById('password').removeAttribute('required');
                document.getElementById('password').value = ''; // Limpiar la contraseña al editar
                document.getElementById('passwordRequired').style.display = 'none';
                document.getElementById('passwordHelp').style.display = 'inline';

                document.getElementById('userId').value = id;
                document.getElementById('nombre_usuario').value = nombre_usuario;
                document.getElementById('nombre_completo').value = nombre_completo;
                document.getElementById('email').value = email;
                document.getElementById('telefono').value = telefono;
                document.getElementById('nip').value = nip;
                document.getElementById('id_rol').value = id_rol;
                document.getElementById('estado').value = estado;

                // Restablecer y luego establecer campos específicos de rol
                toggleRoleSpecificFields(); // Oculta/limpia todos primero

                if (nombre_rol === 'Estudiante') {
                    document.getElementById('codigo_registro').value = codigo_registro;
                    // Pre-seleccionar el año de inscripción del primer curso (o el que se considere principal)
                    if (cursos_inscritos.length > 0 && cursos_inscritos[0].id_anio) {
                         document.getElementById('id_anio_inscripcion').value = cursos_inscritos[0].id_anio;
                    } else {
                        document.getElementById('id_anio_inscripcion').value = ''; // Ningún año si no hay cursos
                    }

                    // Pre-seleccionar múltiples cursos
                    const cursosSelect = document.getElementById('cursos_seleccionados');
                    Array.from(cursosSelect.options).forEach(option => {
                        option.selected = cursos_inscritos.some(ci => String(ci.id_curso) === option.value);
                    });

                } else if (nombre_rol === 'Profesor') {
                    document.getElementById('especialidad').value = especialidad;
                    document.getElementById('grado_academico').value = grado_academico;
                }

                // Asegurarse de que los campos específicos se muestren después de cargar los datos
                toggleRoleSpecificFields();
            }
        });

        // Mostrar mensajes flash al cargar la página
        flashMessages.forEach(msg => {
            showToast(msg.message, msg.type);
        });

        // Initial call to hide/show fields based on default role selection (if any)
        toggleRoleSpecificFields();

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#usuariosTable tbody tr');
            rows.forEach(row => {
                let textContent = row.textContent || row.innerText;
                if (textContent.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    // Toast functionality (assuming you have Bootstrap toasts)
    function showToast(message, type) {
        const toastContainer = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }
</script>