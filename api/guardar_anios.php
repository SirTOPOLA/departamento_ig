<?php
require_once '../includes/functions.php';

check_login_and_role('Administrador');

require_once '../config/database.php';

// --- Lógica para añadir/editar/eliminar años académicos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $nombre_anio = sanitize_input($_POST['nombre_anio'] ?? '');
    $fecha_inicio = sanitize_input($_POST['fecha_inicio'] ?? '');
    $fecha_fin = sanitize_input($_POST['fecha_fin'] ?? '');
    // [CAMBIO] Obtener el nuevo campo estado
    $estado_anio = sanitize_input($_POST['estado'] ?? ''); 

    // Validaciones básicas
    // [CAMBIO] Incluir validación para el campo estado_anio
    if (empty($nombre_anio) || empty($fecha_inicio) || empty($fecha_fin) || empty($estado_anio)) {
        set_flash_message('danger', 'Error: Todos los campos son obligatorios.');
    } elseif (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
        set_flash_message('danger', 'Error: Las fechas no son válidas.');
    } elseif ($fecha_inicio >= $fecha_fin) {
        set_flash_message('danger', 'Error: La fecha de inicio debe ser anterior a la fecha de fin.');
    } elseif (!in_array($estado_anio, ['Activo', 'Inactivo', 'Cerrado', 'Futuro'])) { // [CAMBIO] Validar valores del ENUM
        set_flash_message('danger', 'Error: El estado del año académico no es válido.');
    } else {
        try {
            // Iniciar transacción para asegurar atomicidad
            $pdo->beginTransaction();

            if ($action === 'add') {
                // [CAMBIO] Añadir el campo estado_anio en la inserción
                $stmt = $pdo->prepare("INSERT INTO anios_academicos (nombre_anio, fecha_inicio, fecha_fin, estado) VALUES (:nombre_anio, :fecha_inicio, :fecha_fin, :estado)");
                $stmt->bindParam(':nombre_anio', $nombre_anio);
                $stmt->bindParam(':fecha_inicio', $fecha_inicio);
                $stmt->bindParam(':fecha_fin', $fecha_fin);
                $stmt->bindParam(':estado', $estado_anio); // [CAMBIO] Bind parameter para estado
                $stmt->execute();
                $new_anio_id = $pdo->lastInsertId(); // Obtener el ID del año recién insertado

                // --- Lógica para actualizar los cursos de los estudiantes ---
                // NOTA IMPORTANTE: Esta lógica de progresión de estudiantes (pasar de un curso a otro)
                // es muy compleja y crítica. Ejecutarla automáticamente al "añadir un nuevo año"
                // puede ser problemático si el administrador añade un año futuro pero no quiere
                // que la progresión se dispare inmediatamente.
                // Se recomienda ENCARECIDAMENTE que esta lógica de progresión esté en una
                // FUNCIÓN SEPARADA y se ejecute explícitamente por el administrador
                // (ej. un botón "Cerrar Año Académico Anterior y Promover Estudiantes").
                // Por ahora, la mantengo aquí como estaba en tu código original, pero lo ideal es moverla.

                // 1. Obtener el ID del año académico anterior
                // Se asume que el "año anterior" es el año con el ID más alto antes del recién creado.
                $stmt_prev_anio = $pdo->prepare("SELECT id FROM anios_academicos WHERE id < :new_anio_id ORDER BY id DESC LIMIT 1");
                $stmt_prev_anio->bindParam(':new_anio_id', $new_anio_id, PDO::PARAM_INT);
                $stmt_prev_anio->execute();
                $prev_anio_id = $stmt_prev_anio->fetchColumn();

                if ($prev_anio_id) {
                    // [CAMBIO SUGERIDO]: Idealmente, aquí se debería buscar el año 'Activo' anterior
                    // y luego cambiar su estado a 'Cerrado' ANTES de procesar la progresión.
                    // Esto es lo que el campo `estado` en `anios_academicos` permitiría de forma limpia.

                    // Obtener todos los estudiantes actualmente activos en el año anterior
                    $stmt_students = $pdo->prepare("
                        SELECT ce.id AS curso_estudiante_id, ce.id_estudiante, ce.id_curso,
                                c.nombre_curso
                        FROM curso_estudiante ce
                        JOIN cursos c ON ce.id_curso = c.id
                        WHERE ce.id_anio = :prev_anio_id AND ce.estado = 'activo'
                    ");
                    $stmt_students->bindParam(':prev_anio_id', $prev_anio_id, PDO::PARAM_INT);
                    $stmt_students->execute();
                    $students_to_update = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($students_to_update as $student) {
                        $student_id = $student['id_estudiante'];
                        $current_course_id = $student['id_curso'];
                        $current_curso_estudiante_id = $student['curso_estudiante_id'];
                        $nombre_curso_actual = $student['nombre_curso'];

                        // 2. Verificar si el estudiante ha aprobado AL MENOS UNA asignatura de su curso en el año anterior
                        $stmt_approved_any_asignatura = $pdo->prepare("
                            SELECT COUNT(DISTINCT ha.id_asignatura)
                            FROM historial_academico ha
                            JOIN asignaturas a ON ha.id_asignatura = a.id
                            JOIN semestres s ON ha.id_semestre = s.id
                            WHERE ha.id_estudiante = :student_id
                            AND ha.estado_final = 'APROBADO'
                            AND s.id_anio_academico = :prev_anio_id
                            AND a.id_curso = :current_course_id
                        ");
                        $stmt_approved_any_asignatura->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                        $stmt_approved_any_asignatura->bindParam(':prev_anio_id', $prev_anio_id, PDO::PARAM_INT);
                        $stmt_approved_any_asignatura->bindParam(':current_course_id', $current_course_id, PDO::PARAM_INT);
                        $stmt_approved_any_asignatura->execute();
                        $approved_asignaturas_count = $stmt_approved_any_asignatura->fetchColumn();

                        // Lógica para determinar si avanza de curso
                        $should_advance = ($approved_asignaturas_count > 0);
                        $next_course_id = $current_course_id;
                        $curso_estudiante_estado_anterior = 'reprobado'; // Por defecto, si no cumple la condición de avance
                        $create_new_course_entry = false; // Por defecto, no se crea nueva entrada

                        // Obtener los IDs de los cursos 'Primero', 'Segundo', 'Tercero'
                        static $course_ids = [];
                        if (empty($course_ids)) {
                            $stmt_courses = $pdo->query("SELECT id, nombre_curso FROM cursos");
                            while ($row = $stmt_courses->fetch(PDO::FETCH_ASSOC)) {
                                $course_ids[$row['nombre_curso']] = $row['id'];
                            }
                        }

                        if ($should_advance) {
                            if (isset($course_ids['Primero']) && $current_course_id == $course_ids['Primero']) { // Estaba en Primero
                                $next_course_id = $course_ids['Segundo'] ?? null;
                                $curso_estudiante_estado_anterior = 'finalizado';
                                $create_new_course_entry = true;
                            } elseif (isset($course_ids['Segundo']) && $current_course_id == $course_ids['Segundo']) { // Estaba en Segundo
                                $next_course_id = $course_ids['Tercero'] ?? null;
                                $curso_estudiante_estado_anterior = 'finalizado';
                                $create_new_course_entry = true;
                            } elseif (isset($course_ids['Tercero']) && $current_course_id == $course_ids['Tercero']) { // Estaba en Tercero
                                $next_course_id = $course_ids['Tercero'] ?? null; // Sigue siendo tercero
                                $curso_estudiante_estado_anterior = 'finalizado';
                                $create_new_course_entry = true; // Crea nueva entrada para el nuevo año en Tercero
                            } else {
                                $should_advance = false;
                                $curso_estudiante_estado_anterior = 'reprobado';
                                $create_new_course_entry = false;
                            }
                        }
                        
                        // Si no hay asignaturas aprobadas (y no es el caso de Tercero que no aprobó ninguna en su propio curso)
                        if (!$should_advance && ((isset($course_ids['Primero']) && $current_course_id == $course_ids['Primero']) || (isset($course_ids['Segundo']) && $current_course_id == $course_ids['Segundo']))) {
                               $curso_estudiante_estado_anterior = 'reprobado';
                               $create_new_course_entry = false;
                        } elseif (!$should_advance && (isset($course_ids['Tercero']) && $current_course_id == $course_ids['Tercero'])) {
                            $curso_estudiante_estado_anterior = 'reprobado';
                            $create_new_course_entry = false;
                        }

                        // 3. Actualizar el estado del registro actual de curso_estudiante (del año anterior)
                        // [NOTA]: Asegúrate de que tu tabla `curso_estudiante` tenga la columna `fecha_finalizacion` si la estás usando.
                        // Tu esquema SQL original no la incluye, si no existe, remueve este campo de la consulta UPDATE.
                        $stmt_update_current_ce = $pdo->prepare("
                            UPDATE curso_estudiante
                            SET estado = :estado
                            WHERE id = :curso_estudiante_id
                        ");
                        $stmt_update_current_ce->bindParam(':estado', $curso_estudiante_estado_anterior);
                        $stmt_update_current_ce->bindParam(':curso_estudiante_id', $current_curso_estudiante_id, PDO::PARAM_INT);
                        $stmt_update_current_ce->execute();

                        // 4. Crear un nuevo registro en curso_estudiante para el siguiente año si avanza
                        if ($create_new_course_entry && $next_course_id) {
                            $stmt_insert_next_ce = $pdo->prepare("
                                INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio, estado, fecha_registro)
                                VALUES (:id_estudiante, :id_curso, :id_anio, 'activo', NOW())
                            ");
                            $stmt_insert_next_ce->bindParam(':id_estudiante', $student_id, PDO::PARAM_INT);
                            $stmt_insert_next_ce->bindParam(':id_curso', $next_course_id, PDO::PARAM_INT);
                            $stmt_insert_next_ce->bindParam(':id_anio', $new_anio_id, PDO::PARAM_INT);
                            $stmt_insert_next_ce->execute();
                        }
                    }
                } else {
                    set_flash_message('info', 'No se encontró un año académico anterior para procesar la progresión de estudiantes.');
                }
                // --- Fin de la lógica para actualizar los cursos de los estudiantes ---

                set_flash_message('success', 'Año académico añadido correctamente y cursos de estudiantes actualizados.');

            } elseif ($action === 'edit') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de año académico no válido para edición.');
                } else {
                    // [CAMBIO] Añadir el campo estado_anio en la actualización
                    $stmt = $pdo->prepare("UPDATE anios_academicos SET nombre_anio = :nombre_anio, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, estado = :estado WHERE id = :id");
                    $stmt->bindParam(':nombre_anio', $nombre_anio);
                    $stmt->bindParam(':fecha_inicio', $fecha_inicio);
                    $stmt->bindParam(':fecha_fin', $fecha_fin);
                    $stmt->bindParam(':estado', $estado_anio); // [CAMBIO] Bind parameter para estado
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    set_flash_message('success', 'Año académico actualizado correctamente.');
                }
            } elseif ($action === 'delete') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de año académico no válido para eliminación.');
                } else {
                    // Verificar si existen semestres asociados antes de eliminar
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM semestres WHERE id_anio_academico = :id");
                    $stmt_check->bindParam(':id', $id);
                    $stmt_check->execute();
                    if ($stmt_check->fetchColumn() > 0) {
                        set_flash_message('danger', 'Error: No se puede eliminar el año académico porque tiene semestres asociados.');
                    } else {
                        // [CAMBIO] También verificar si hay inscripciones o historial académico asociado al año a través de semestres o directamente en curso_estudiante
                        $stmt_check_ce = $pdo->prepare("SELECT COUNT(*) FROM curso_estudiante WHERE id_anio = :id");
                        $stmt_check_ce->bindParam(':id', $id);
                        $stmt_check_ce->execute();
                        if ($stmt_check_ce->fetchColumn() > 0) {
                             set_flash_message('danger', 'Error: No se puede eliminar el año académico porque tiene estudiantes inscritos en él.');
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM anios_academicos WHERE id = :id");
                            $stmt->bindParam(':id', $id);
                            $stmt->execute();
                            set_flash_message('success', 'Año académico eliminado correctamente.');
                        }
                    }
                }
            }
            $pdo->commit(); // Confirmar la transacción
        } catch (PDOException $e) {
            $pdo->rollBack(); // Revertir la transacción en caso de error
            // Manejar errores de duplicidad (ej. nombre_anio UNIQUE)
            if ($e->getCode() == '23000') { // Código SQLSTATE para violación de integridad
                set_flash_message('danger', 'Error: El nombre del año académico ya existe o hay un conflicto de datos.');
            } else {
                set_flash_message('danger', 'Error de base de datos al procesar año académico o estudiantes: ' . $e->getMessage());
                error_log("Error al procesar año académico: " . $e->getMessage() . " en " . $e->getFile() . " en la línea " . $e->getLine());
            }
        }
    }
    header('Location: ../admin/anios_academicos.php');
    exit();
}