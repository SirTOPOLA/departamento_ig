 

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

?>