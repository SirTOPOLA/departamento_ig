 

<?php
// includes/functions.php

/**
 * Redirige al usuario a su dashboard correspondiente según el rol.
 * @param string $role El rol del usuario ('Administrador', 'Estudiante', 'Profesor').
 */
function redirect_to_dashboard($role) {
    switch ($role) {
        case 'Administrador':
            header("Location: ../admin/index.php");
            break;
        case 'Estudiante':
            header("Location: ../estudiante/index.php");
            break;
        case 'Profesor':
            header("Location: ../profesor/index.php");
            break;
        default:
            // Si el rol no es reconocido, redirigir a login o a una página de error
            header("Location: ../index.php?error=error");
            break;
    }
    exit(); // Es crucial llamar a exit() después de una redirección
}



function getHorarioPorGrupoYSemestre(PDO $pdo, int $id_grupo_asignatura, int $id_semestre): ?array {
    $stmt = $pdo->prepare("
        SELECT dia_semana, hora_inicio, hora_fin, turno
        FROM horarios
        WHERE id_grupo_asignatura = :grupo AND id_semestre = :semestre
        LIMIT 1
    ");
    $stmt->execute([
        ':grupo' => $id_grupo_asignatura,
        ':semestre' => $id_semestre
    ]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


/**
 * Verifica si el usuario está logueado y tiene el rol requerido.
 * Si no está logueado o no tiene el rol, redirige.
 * @param string $required_role El rol que se requiere ('Administrador', 'Estudiante', 'Profesor').
 */
function check_login_and_role($required_role) {
    session_start(); // Asegura que la sesión esté iniciada

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== $required_role) {
        // Si no está logueado o el rol no coincide, redirigir al login
        header("Location: ../index.php");
        exit();
    }
}

/**
 * Sanea la entrada del usuario para prevenir ataques XSS.
 *
 * @param string $data La cadena de entrada a sanear.
 * @return string La cadena saneada.
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// NEW: Funciones para mensajes flash
/**
 * Establece un mensaje flash en la sesión.
 *
 * @param string $type Tipo de mensaje (ej: 'success', 'danger', 'warning', 'info').
 * @param string $message El contenido del mensaje.
 */
function set_flash_message($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/**
 * Obtiene y limpia todos los mensajes flash de la sesión.
 *
 * @return array Un array de mensajes flash, o un array vacío si no hay ninguno.
 */
function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']); // Limpiar los mensajes después de obtenerlos
    return $messages;
}
 

function get_current_academic_year($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre_anio, fecha_inicio, fecha_fin FROM anios_academicos WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // En un entorno de producción, es mejor registrar el error en lugar de mostrarlo directamente.
        error_log("Error al obtener el año académico actual: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene los detalles del semestre actual dentro de un año académico específico.
 *
 * @param PDO $pdo Objeto PDO de conexión a la base de datos.
 * @param int $id_anio_academico_param El ID del año académico actual.
 * @return array|false Retorna un array asociativo con los detalles del semestre actual (id, numero_semestre, nombre_semestre, fecha_inicio, fecha_fin) o false si no se encuentra ninguno.
 */
function get_current_semester_details(PDO $pdo, int $id_anio_academico_param) {
    try {
        $stmt = $pdo->prepare("SELECT id, numero_semestre, nombre_semestre, fecha_inicio, fecha_fin FROM semestres WHERE id_anio_academico = :id_anio_academico AND CURDATE() BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
        $stmt->bindParam(':id_anio_academico', $id_anio_academico_param, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener los detalles del semestre actual: " . $e->getMessage());
        return false;
    }
}

 
// ... otras funciones

function get_current_semester($pdo) {
    // Obtenemos la fecha actual en formato 'YYYY-MM-DD'
    $current_date = date('Y-m-d');

    // Preparamos la consulta para encontrar el semestre donde la fecha actual
    // esté entre la fecha de inicio y la fecha de fin del semestre.
    // También unimos con anios_academicos para obtener su nombre si es necesario.
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.id_anio_academico,
            s.numero_semestre,
            s.fecha_inicio,
            s.fecha_fin,
            s.id_curso_asociado_al_semestre,  
            aa.nombre_anio,
            aa.fecha_inicio AS anio_fecha_inicio,
            aa.fecha_fin AS anio_fecha_fin
        FROM semestres s
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        WHERE :current_date BETWEEN s.fecha_inicio AND s.fecha_fin
        LIMIT 1
    ");

    // Enlazamos el parámetro de la fecha actual
    $stmt->bindParam(':current_date', $current_date);

    // Ejecutamos la consulta
    $stmt->execute();

    // Devolvemos el resultado como un array asociativo.
    // Si no se encuentra ningún semestre, devolverá false.
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


if (!function_exists('format_date')) {
    function format_date($date_string) {
        if (empty($date_string) || $date_string == '0000-00-00') {
            return '-';
        }
        // Assuming your dates are in YYYY-MM-DD format from the database
        $date = new DateTime($date_string);
        return $date->format('d/m/Y'); // Or any other format you prefer
    }
}


 


function confirmar_inscripcion_individual_logica(PDO $pdo, $id_inscripcion, $id_semestre_actual, $id_anio_academico_actual) {
    try {
        // 1. Obtener detalles de la inscripción
        $stmt_inscripcion = $pdo->prepare("SELECT ie.id_estudiante, ie.id_asignatura, ie.id_semestre FROM inscripciones_estudiantes ie WHERE ie.id = :id_inscripcion AND ie.confirmada = 0");
        $stmt_inscripcion->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
        $stmt_inscripcion->execute();
        $inscripcion_data = $stmt_inscripcion->fetch(PDO::FETCH_ASSOC);

        if (!$inscripcion_data) {
            return ['exito' => false, 'mensaje' => 'La inscripción no existe o ya ha sido confirmada/rechazada.'];
        }

        if ($inscripcion_data['id_semestre'] != $id_semestre_actual) {
            return ['exito' => false, 'mensaje' => 'La inscripción no corresponde al semestre actual.'];
        }

        $id_estudiante = $inscripcion_data['id_estudiante'];
        $id_asignatura = $inscripcion_data['id_asignatura'];

        // 2. Obtener el curso de la asignatura
        $stmt_asignatura_curso = $pdo->prepare("SELECT id_curso FROM asignaturas WHERE id = :id_asignatura");
        $stmt_asignatura_curso->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
        $stmt_asignatura_curso->execute();
        $id_curso_asignatura = $stmt_asignatura_curso->fetchColumn();

        if (!$id_curso_asignatura) {
            return ['exito' => false, 'mensaje' => 'Asignatura no encontrada o sin curso asociado.'];
        }

        // 3. Verificar si el estudiante está matriculado como "activo" en el curso de la asignatura para el año académico actual
        $stmt_matricula_curso = $pdo->prepare("
            SELECT COUNT(*)
            FROM curso_estudiante ce
            WHERE ce.id_estudiante = :id_estudiante
            AND ce.id_curso = :id_curso
            AND ce.id_anio = :id_anio_academico_actual
            AND ce.estado = 'activo'
        ");
        $stmt_matricula_curso->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmt_matricula_curso->bindParam(':id_curso', $id_curso_asignatura, PDO::PARAM_INT);
        $stmt_matricula_curso->bindParam(':id_anio_academico_actual', $id_anio_academico_actual, PDO::PARAM_INT);
        $stmt_matricula_curso->execute();
        $esta_matriculado_en_curso = $stmt_matricula_curso->fetchColumn() > 0;

        if (!$esta_matriculado_en_curso) {
            return ['exito' => false, 'mensaje' => 'El estudiante no está matriculado activamente en el curso de esta asignatura para el año académico actual.'];
        }

        // 4. Confirmar la inscripción
        $stmt_confirmar = $pdo->prepare("UPDATE inscripciones_estudiantes SET confirmada = 1 WHERE id = :id_inscripcion");
        $stmt_confirmar->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
        $stmt_confirmar->execute();

        if ($stmt_confirmar->rowCount() === 0) {
            return ['exito' => false, 'mensaje' => 'No se pudo confirmar la inscripción.'];
        }

        // 5. Insertar/Actualizar en historial_academico
        // Verificar si ya existe una entrada para esta asignatura y semestre para evitar duplicados
        $stmt_check_historial = $pdo->prepare("SELECT id FROM historial_academico WHERE id_estudiante = :id_estudiante AND id_asignatura = :id_asignatura AND id_semestre = :id_semestre");
        $stmt_check_historial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
        $stmt_check_historial->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
        $stmt_check_historial->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
        $stmt_check_historial->execute();
        $historial_existente_id = $stmt_check_historial->fetchColumn();

        if ($historial_existente_id) {
            // Si ya existe, actualizamos su estado a PENDIENTE (o lo que corresponda al momento de inscripción)
            $stmt_update_historial = $pdo->prepare("UPDATE historial_academico SET estado_final = 'PENDIENTE' WHERE id = :id_historial");
            $stmt_update_historial->bindParam(':id_historial', $historial_existente_id, PDO::PARAM_INT);
            $stmt_update_historial->execute();
        } else {
            // Si no existe, la insertamos
            $stmt_insert_historial = $pdo->prepare("INSERT INTO historial_academico (id_estudiante, id_asignatura, id_semestre, nota_final, estado_final) VALUES (:id_estudiante, :id_asignatura, :id_semestre, NULL, 'PENDIENTE')");
            $stmt_insert_historial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
            $stmt_insert_historial->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
            $stmt_insert_historial->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
            $stmt_insert_historial->execute();
        }

        return ['exito' => true, 'mensaje' => 'Asignatura confirmada correctamente e historial actualizado.'];

    } catch (PDOException $e) {
        error_log("Error en confirmar_inscripcion_individual_logica: " . $e->getMessage());
        return ['exito' => false, 'mensaje' => 'Error al procesar la confirmación: ' . $e->getMessage()];
    }
}


/**
 * Maneja las operaciones de agregar, editar y eliminar entradas de historial académico.
 * @param PDO $pdo Objeto PDO de la base de datos.
 * @param array $data Datos de la operación (id_estudiante, id_asignatura, id_semestre, nota_final, estado_final, id_historial_entry para editar/eliminar).
 * @param string $action Tipo de acción: 'add', 'edit', 'delete'.
 * @return array Un array asociativo con 'success' (boolean) y 'message' (string).
 */

 

function manejar_historial_academico_logica($pdo, $data, $operacion) {
    $response = ['success' => false, 'message' => ''];

    // Asegurarse de que el id_usuario_estudiante se reciba y sea válido
    $id_usuario_estudiante = filter_var($data['id_usuario_estudiante'] ?? null, FILTER_VALIDATE_INT);
    if (!$id_usuario_estudiante) {
        $response['message'] = 'ID de usuario de estudiante no válido.';
        return $response;
    }

    // Obtener el id real del estudiante de la tabla 'estudiantes'
    $stmt_estudiante_id = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
    $stmt_estudiante_id->bindParam(':id_usuario', $id_usuario_estudiante, PDO::PARAM_INT);
    $stmt_estudiante_id->execute();
    $id_estudiante_db = $stmt_estudiante_id->fetchColumn();

    if (!$id_estudiante_db) {
        $response['message'] = 'No se encontró el registro del estudiante en la base de datos.';
        return $response;
    }

    $id_asignatura = filter_var($data['id_asignatura'] ?? null, FILTER_VALIDATE_INT);
    $id_semestre = filter_var($data['id_semestre'] ?? null, FILTER_VALIDATE_INT); // AHORA USAMOS ESTE
    $nota_final = filter_var($data['nota_final'] ?? null, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 10]]);
    $estado_final = trim($data['estado_final'] ?? '');

    if (!$id_asignatura || !$id_semestre || $nota_final === null || $estado_final === '') {
        $response['message'] = 'Datos incompletos o inválidos para la operación de historial académico.';
        return $response;
    }

    try {
        if ($operacion === 'add') {
            // Verificar si ya existe una entrada para esta asignatura en este semestre para este estudiante
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM historial_academico WHERE id_estudiante = :id_estudiante AND id_asignatura = :id_asignatura AND id_semestre = :id_semestre");
            $stmt_check->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);
            $stmt_check->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
            $stmt_check->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                $response['message'] = 'Ya existe una entrada para esta asignatura en el semestre seleccionado. Utiliza la opción de editar si deseas modificarla.';
                return $response;
            }

            $stmt = $pdo->prepare("INSERT INTO historial_academico (id_estudiante, id_asignatura, id_semestre, nota_final, estado_final) VALUES (:id_estudiante, :id_asignatura, :id_semestre, :nota_final, :estado_final)");
            $stmt->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);
            $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
            $stmt->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT); // Usar el semestre seleccionado
            $stmt->bindParam(':nota_final', $nota_final, PDO::PARAM_STR); // Usar STR para FLOAT para evitar problemas de precisión
            $stmt->bindParam(':estado_final', $estado_final, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Entrada de historial académico agregada con éxito.';
            } else {
                error_log("Error al insertar historial: " . json_encode($stmt->errorInfo()));
                $response['message'] = 'Error al agregar la entrada de historial académico.';
            }

        } elseif ($operacion === 'edit') {
            $id_historial_entry = filter_var($data['id_historial_entry'] ?? null, FILTER_VALIDATE_INT);
            if (!$id_historial_entry) {
                $response['message'] = 'ID de entrada de historial no válido para editar.';
                return $response;
            }

            $stmt = $pdo->prepare("UPDATE historial_academico SET id_asignatura = :id_asignatura, id_semestre = :id_semestre, nota_final = :nota_final, estado_final = :estado_final WHERE id = :id_historial AND id_estudiante = :id_estudiante");
            $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
            $stmt->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT); // Usar el semestre seleccionado
            $stmt->bindParam(':nota_final', $nota_final, PDO::PARAM_STR);
            $stmt->bindParam(':estado_final', $estado_final, PDO::PARAM_STR);
            $stmt->bindParam(':id_historial', $id_historial_entry, PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Entrada de historial académico actualizada con éxito.';
                } else {
                    $response['message'] = 'No se encontró la entrada de historial para actualizar o no se realizaron cambios.';
                }
            } else {
                error_log("Error al actualizar historial: " . json_encode($stmt->errorInfo()));
                $response['message'] = 'Error al actualizar la entrada de historial académico.';
            }

        } elseif ($operacion === 'delete') {
            $id_historial_entry = filter_var($data['id_historial_entry'] ?? null, FILTER_VALIDATE_INT);
            if (!$id_historial_entry) {
                $response['message'] = 'ID de entrada de historial no válido para eliminar.';
                return $response;
            }

            $stmt = $pdo->prepare("DELETE FROM historial_academico WHERE id = :id_historial AND id_estudiante = :id_estudiante");
            $stmt->bindParam(':id_historial', $id_historial_entry, PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Entrada de historial académico eliminada con éxito.';
                } else {
                    $response['message'] = 'No se encontró la entrada de historial para eliminar.';
                }
            } else {
                error_log("Error al eliminar historial: " . json_encode($stmt->errorInfo()));
                $response['message'] = 'Error al eliminar la entrada de historial académico.';
            }
        } else {
            $response['message'] = 'Operación de historial académico no reconocida.';
        }
    } catch (PDOException $e) {
        error_log("PDO Exception in manejar_historial_academico_logica: " . $e->getMessage());
        $response['message'] = 'Error de base de datos al gestionar historial: ' . $e->getMessage();
    } catch (Exception $e) {
        error_log("General Exception in manejar_historial_academico_logica: " . $e->getMessage());
        $response['message'] = 'Error inesperado al gestionar historial: ' . $e->getMessage();
    }

    return $response;
}
?>