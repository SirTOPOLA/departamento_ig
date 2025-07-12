<?php
require_once '../includes/functions.php';
check_login_and_role('Administrador');
require_once '../config/database.php';

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
    // Solo capturamos un único curso de entrada para el año actual
    $id_curso_entrada_actual = filter_var($_POST['id_curso_entrada_actual'] ?? null, FILTER_VALIDATE_INT);

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

    // --- Obtener el ID del año académico activo actual ---
    $id_anio_actual_activo = null;
    try {
        $stmt_active_year = $pdo->query("SELECT id FROM anios_academicos WHERE estado = 'Activo' LIMIT 1");
        $id_anio_actual_activo = $stmt_active_year->fetchColumn();
    } catch (PDOException $e) {
        set_flash_message('danger', 'Error al obtener el año académico activo: ' . $e->getMessage());
        // Podrías detener la ejecución o manejarlo de otra manera si es crítico
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
                        if (empty($codigo_registro)) {
                            set_flash_message('danger', 'Error: Para estudiantes, el Código de Registro es obligatorio.');
                            $pdo->rollBack();
                        } elseif (!$id_anio_actual_activo) {
                            set_flash_message('danger', 'Error: No se encontró un año académico activo para la inscripción del estudiante.');
                            $pdo->rollBack();
                        } elseif (empty($id_curso_entrada_actual)) {
                            set_flash_message('danger', 'Error: Debes seleccionar un Curso de Entrada para el estudiante.');
                            $pdo->rollBack();
                        } else {
                            $stmt_estudiante = $pdo->prepare("INSERT INTO estudiantes (id_usuario, codigo_registro) VALUES (:id_usuario, :codigo_registro)");
                            $stmt_estudiante->bindParam(':id_usuario', $new_user_id);
                            $stmt_estudiante->bindParam(':codigo_registro', $codigo_registro);
                            $stmt_estudiante->execute();
                            $id_estudiante_reciente = $pdo->lastInsertId();

                            // Insertar el curso de entrada para el año actual activo
                            $stmt_curso_estudiante = $pdo->prepare("INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio) VALUES (:id_estudiante, :id_curso, :id_anio)");
                            $stmt_curso_estudiante->bindParam(':id_estudiante', $id_estudiante_reciente);
                            $stmt_curso_estudiante->bindParam(':id_curso', $id_curso_entrada_actual);
                            $stmt_curso_estudiante->bindParam(':id_anio', $id_anio_actual_activo);
                            $stmt_curso_estudiante->execute();

                            $pdo->commit();
                            set_flash_message('success', 'Usuario Estudiante añadido correctamente con curso de entrada para el año actual.');
                        }
                    } elseif ($nombre_rol_seleccionado === 'Profesor') {
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
                        if (empty($codigo_registro)) {
                            set_flash_message('danger', 'Error: Para estudiantes, el Código de Registro es obligatorio.');
                            $pdo->rollBack();
                        } elseif (!$id_anio_actual_activo) {
                            set_flash_message('danger', 'Error: No se encontró un año académico activo para la inscripción del estudiante.');
                            $pdo->rollBack();
                        } elseif (empty($id_curso_entrada_actual)) {
                            set_flash_message('danger', 'Error: Debes seleccionar un Curso de Entrada para el estudiante.');
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

                            // Eliminar la inscripción del curso de entrada existente para el año actual si hay una
                            $stmt_delete_current_entry_course = $pdo->prepare("DELETE FROM curso_estudiante WHERE id_estudiante = :id_estudiante AND id_anio = :id_anio");
                            $stmt_delete_current_entry_course->bindParam(':id_estudiante', $current_estudiante_id);
                            $stmt_delete_current_entry_course->bindParam(':id_anio', $id_anio_actual_activo);
                            $stmt_delete_current_entry_course->execute();

                            // Insertar el nuevo curso de entrada para el año actual
                            $stmt_insert_entry_course = $pdo->prepare("INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio) VALUES (:id_estudiante, :id_curso, :id_anio)");
                            $stmt_insert_entry_course->bindParam(':id_estudiante', $current_estudiante_id);
                            $stmt_insert_entry_course->bindParam(':id_curso', $id_curso_entrada_actual); // CORREGIDO AQUÍ
                            $stmt_insert_entry_course->bindParam(':id_anio', $id_anio_actual_activo);
                            $stmt_insert_entry_course->execute();

                            // Elimina el registro de profesor si el usuario era antes un profesor y ahora es estudiante
                            $stmt_delete_prof = $pdo->prepare("DELETE FROM profesores WHERE id_usuario = :id_usuario");
                            $stmt_delete_prof->bindParam(':id_usuario', $id);
                            $stmt_delete_prof->execute();
                            $pdo->commit();
                            set_flash_message('success', 'Usuario Estudiante actualizado correctamente con curso de entrada para el año actual.');
                        }
                    } elseif ($nombre_rol_seleccionado === 'Profesor') {
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
header('location: ../admin/usuarios.php');
