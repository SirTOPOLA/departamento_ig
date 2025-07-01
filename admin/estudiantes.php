<?php

// --- INICIO DE DEPURACIÓN TEMPORAL ---
// Habilitar la visualización de errores para depuración.
// ¡Desactivar en producción!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

require_once '../includes/functions.php';
// Asegura que solo un administrador pueda acceder a esta página.
check_login_and_role('Administrador');

require_once '../config/database.php'; // Conexión PDO

// --- FUNCIONES AUXILIARES ---

/**
 * Función auxiliar para obtener el semestre académico actual.
 * Debería estar en 'includes/functions.php'.
 * Asegúrate de que esta función devuelva 'id' del semestre y 'id_anio_academico'.
 *
 * Ejemplo de cómo podría ser get_current_semester() en functions.php:
 * function get_current_semester($pdo) {
 * $stmt = $pdo->query("
 * SELECT s.id, s.numero_semestre, s.fecha_inicio, s.fecha_fin,
 * aa.id AS id_anio_academico, aa.nombre_anio
 * FROM semestres s
 * JOIN anios_academicos aa ON s.id_anio_academico = aa.id
 * WHERE CURDATE() BETWEEN s.fecha_inicio AND s.fecha_fin
 * ORDER BY s.fecha_inicio DESC
 * LIMIT 1
 * ");
 * return $stmt->fetch(PDO::FETCH_ASSOC);
 * }
 */

/**
 * Confirma una inscripción individual si el estudiante está en el curso correcto para el año académico actual.
 * @param PDO $pdo La conexión PDO.
 * @param int $id_inscripcion El ID de la inscripción a confirmar.
 * @param int $id_semestre_actual El ID del semestre actual.
 * @param int $id_anio_academico_actual El ID del año académico actual.
 * @return array Un array con 'exito' (bool) y 'mensaje' (string).
 */
function confirmar_inscripcion_individual_logica(PDO $pdo, $id_inscripcion, $id_semestre_actual, $id_anio_academico_actual) {
    // 1. Obtener el ID del estudiante y el ID de la asignatura para la inscripción
    $stmt_obtener_detalles_inscripcion = $pdo->prepare("SELECT id_estudiante, id_asignatura FROM inscripciones_estudiantes WHERE id = :id_inscripcion AND id_semestre = :id_semestre");
    $stmt_obtener_detalles_inscripcion->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
    $stmt_obtener_detalles_inscripcion->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
    $stmt_obtener_detalles_inscripcion->execute();
    $detalles_inscripcion = $stmt_obtener_detalles_inscripcion->fetch(PDO::FETCH_ASSOC);

    if (!$detalles_inscripcion) {
        return ['exito' => false, 'mensaje' => 'Inscripción no encontrada o ya procesada para el semestre actual.'];
    }

    $id_estudiante_db = $detalles_inscripcion['id_estudiante'];
    $id_asignatura_db = $detalles_inscripcion['id_asignatura'];

    // 2. Obtener el ID del curso al que pertenece esta asignatura
    $stmt_obtener_id_curso_asignatura = $pdo->prepare("SELECT id_curso FROM asignaturas WHERE id = :id_asignatura");
    $stmt_obtener_id_curso_asignatura->bindParam(':id_asignatura', $id_asignatura_db, PDO::PARAM_INT);
    $stmt_obtener_id_curso_asignatura->execute();
    $id_curso_asignatura = $stmt_obtener_id_curso_asignatura->fetchColumn();

    if (!$id_curso_asignatura) {
        return ['exito' => false, 'mensaje' => 'No se pudo determinar el curso de la asignatura seleccionada.'];
    }

    // 3. Verificar si el estudiante está actualmente matriculado en este curso para el año académico actual
    // Se busca una entrada activa en curso_estudiante para el estudiante, curso y año académico actuales.
    $stmt_verificar_matricula_curso = $pdo->prepare("SELECT COUNT(*) FROM curso_estudiante WHERE id_estudiante = :id_estudiante AND id_curso = :id_curso AND id_anio = :id_anio_academico AND estado = 'activo'");
    $stmt_verificar_matricula_curso->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);
    $stmt_verificar_matricula_curso->bindParam(':id_curso', $id_curso_asignatura, PDO::PARAM_INT);
    $stmt_verificar_matricula_curso->bindParam(':id_anio_academico', $id_anio_academico_actual, PDO::PARAM_INT);
    $stmt_verificar_matricula_curso->execute();
    $esta_matriculado_en_curso = $stmt_verificar_matricula_curso->fetchColumn();

    if ($esta_matriculado_en_curso > 0) {
        // 4. Si el estudiante está en el curso correcto, proceder a confirmar la asignatura
        $stmt_confirmar_inscripcion = $pdo->prepare("UPDATE inscripciones_estudiantes SET confirmada = 1 WHERE id = :id_inscripcion AND id_semestre = :id_semestre");
        $stmt_confirmar_inscripcion->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
        $stmt_confirmar_inscripcion->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
        $stmt_confirmar_inscripcion->execute();

        if ($stmt_confirmar_inscripcion->rowCount() > 0) {
            // Opcional: Insertar una entrada inicial en historial_academico si la asignatura se confirma.
            // Esto asegura que la asignatura esté en el historial del estudiante con un estado inicial.
            $stmt_insertar_historial = $pdo->prepare("
                INSERT INTO historial_academico (id_estudiante, id_asignatura, id_semestre, nota_final, estado_final)
                VALUES (:id_estudiante, :id_asignatura, :id_semestre, 0.00, 'PENDIENTE')
                ON DUPLICATE KEY UPDATE fecha_actualizacion = NOW() -- Actualiza si ya existe (ej. si se reprobó antes)
            ");
            $stmt_insertar_historial->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);
            $stmt_insertar_historial->bindParam(':id_asignatura', $id_asignatura_db, PDO::PARAM_INT);
            $stmt_insertar_historial->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
            $stmt_insertar_historial->execute();

            return ['exito' => true, 'mensaje' => 'Asignatura confirmada correctamente.'];
        } else {
            return ['exito' => false, 'mensaje' => 'La asignatura ya estaba confirmada o no existe para el semestre actual.'];
        }
    } else {
        return ['exito' => false, 'mensaje' => 'La asignatura no puede ser confirmada. El estudiante no está matriculado en el curso al que pertenece la asignatura para el año académico actual.'];
    }
}


// --- Lógica de Procesamiento POST para Confirmar/Rechazar Inscripciones ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    $semestre_actual = get_current_semester($pdo);
    // Verificar si se obtuvo un semestre actual y su ID de año académico
    if (!$semestre_actual || !isset($semestre_actual['id']) || !isset($semestre_actual['id_anio_academico'])) {
        set_flash_message('danger', 'Error: No hay un semestre académico activo definido o no se pudo obtener su año académico. Por favor, configure el año y semestre actual.');
        header('Location: estudiantes.php');
        exit;
    }
    $id_semestre_actual = $semestre_actual['id'];
    $id_anio_academico_actual = $semestre_actual['id_anio_academico'];

    try {
        $pdo->beginTransaction(); // Iniciar transacción para operaciones atómicas

        if ($accion === 'confirm_single_enrollment') {
            $id_inscripcion = filter_var($_POST['id_inscripcion'] ?? null, FILTER_VALIDATE_INT);
            if ($id_inscripcion === null) {
                set_flash_message('danger', 'Error: ID de inscripción no válido para confirmar.');
            } else {
                $resultado = confirmar_inscripcion_individual_logica($pdo, $id_inscripcion, $id_semestre_actual, $id_anio_academico_actual);
                set_flash_message($resultado['exito'] ? 'success' : 'danger', $resultado['mensaje']);
            }
        } elseif ($accion === 'reject_single_enrollment') {
            $id_inscripcion = filter_var($_POST['id_inscripcion'] ?? null, FILTER_VALIDATE_INT);
            if ($id_inscripcion === null) {
                set_flash_message('danger', 'Error: ID de inscripción no válido para rechazar.');
            } else {
                $stmt_rechazar = $pdo->prepare("DELETE FROM inscripciones_estudiantes WHERE id = :id_inscripcion AND id_semestre = :id_semestre");
                if ($stmt_rechazar === false) { 
                    error_log("Prepare error (reject_single_enrollment): " . json_encode($pdo->errorInfo())); 
                    throw new PDOException("Error al preparar la consulta de rechazo."); 
                }
                $stmt_rechazar->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
                $stmt_rechazar->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
                if (!$stmt_rechazar->execute()) { 
                    error_log("Execute error (reject_single_enrollment): " . json_encode($stmt_rechazar->errorInfo())); 
                    throw new PDOException("Error al ejecutar la consulta de rechazo."); 
                }

                if ($stmt_rechazar->rowCount() > 0) {
                    set_flash_message('info', 'Asignatura rechazada (eliminada) correctamente para el estudiante.');
                } else {
                    set_flash_message('warning', 'La asignatura no pudo ser encontrada o ya ha sido eliminada.');
                }
            }
        } elseif ($accion === 'confirm_student_enrollments') {
            $id_estudiante_usuario = filter_var($_POST['id_estudiante'] ?? null, FILTER_VALIDATE_INT);
            if ($id_estudiante_usuario === null) {
                set_flash_message('danger', 'Error: ID de estudiante no válido para confirmar inscripciones.');
            } else {
                // Obtener el ID de la tabla estudiantes desde el id_usuario
                $stmt_obtener_id_estudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
                $stmt_obtener_id_estudiante->bindParam(':id_usuario', $id_estudiante_usuario, PDO::PARAM_INT);
                $stmt_obtener_id_estudiante->execute();
                $id_estudiante_db = $stmt_obtener_id_estudiante->fetchColumn();

                if ($id_estudiante_db) {
                    // Seleccionar todas las inscripciones pendientes del estudiante para el semestre actual
                    $stmt_inscripciones_pendientes = $pdo->prepare("
                        SELECT ie.id AS id_inscripcion, ie.id_asignatura, a.id_curso
                        FROM inscripciones_estudiantes ie
                        JOIN asignaturas a ON ie.id_asignatura = a.id
                        WHERE ie.id_estudiante = :id_estudiante
                        AND ie.confirmada = 0
                        AND ie.id_semestre = :id_semestre
                    ");
                    $stmt_inscripciones_pendientes->bindParam(':id_estudiante', $id_estudiante_db, PDO::PARAM_INT);
                    $stmt_inscripciones_pendientes->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
                    $stmt_inscripciones_pendientes->execute();
                    $inscripciones_pendientes = $stmt_inscripciones_pendientes->fetchAll(PDO::FETCH_ASSOC);

                    $contador_confirmadas = 0;
                    $contador_omitidas = 0;

                    foreach ($inscripciones_pendientes as $inscripcion) {
                        $resultado = confirmar_inscripcion_individual_logica($pdo, $inscripcion['id_inscripcion'], $id_semestre_actual, $id_anio_academico_actual);
                        if ($resultado['exito']) {
                            $contador_confirmadas++;
                        } else {
                            $contador_omitidas++;
                            // Log opcional del motivo por el que se saltó
                            error_log("Inscripción ID {$inscripcion['id_inscripcion']} omitida para estudiante {$id_estudiante_db}: " . $resultado['mensaje']);
                        }
                    }

                    if ($contador_confirmadas > 0) {
                        $mensaje = "Se confirmaron {$contador_confirmadas} inscripciones para el estudiante.";
                        if ($contador_omitidas > 0) {
                            $mensaje .= " Se omitieron {$contador_omitidas} inscripciones porque el estudiante no está en el curso correspondiente.";
                        }
                        set_flash_message('success', $mensaje);
                    } else {
                        if (count($inscripciones_pendientes) > 0 && $contador_omitidas == count($inscripciones_pendientes)) {
                            set_flash_message('warning', 'No se confirmó ninguna inscripción. Todas las asignaturas pendientes requieren que el estudiante esté matriculado en el curso correspondiente.');
                        } else {
                            set_flash_message('info', 'No hay inscripciones pendientes para este estudiante o ya estaban confirmadas.');
                        }
                    }
                } else {
                    set_flash_message('danger', 'Error: No se encontró el registro del estudiante en la base de datos.');
                }
            }
        } elseif ($accion === 'confirm_all_enrollments') {
            // Se itera sobre todas las inscripciones pendientes para aplicar la lógica de validación por curso.
            $stmt_todas_pendientes = $pdo->prepare("
                SELECT ie.id AS id_inscripcion, ie.id_estudiante, ie.id_asignatura, a.id_curso
                FROM inscripciones_estudiantes ie
                JOIN estudiantes e ON ie.id_estudiante = e.id
                JOIN asignaturas a ON ie.id_asignatura = a.id
                WHERE ie.confirmada = 0 AND ie.id_semestre = :id_semestre
            ");
            $stmt_todas_pendientes->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
            $stmt_todas_pendientes->execute();
            $todas_inscripciones_pendientes = $stmt_todas_pendientes->fetchAll(PDO::FETCH_ASSOC);

            $contador_confirmadas = 0;
            $contador_omitidas = 0;

            foreach ($todas_inscripciones_pendientes as $inscripcion) {
                $resultado = confirmar_inscripcion_individual_logica($pdo, $inscripcion['id_inscripcion'], $id_semestre_actual, $id_anio_academico_actual);
                if ($resultado['exito']) {
                    $contador_confirmadas++;
                } else {
                    $contador_omitidas++;
                    error_log("Inscripción ID {$inscripcion['id_inscripcion']} omitida para estudiante {$inscripcion['id_estudiante']}: " . $resultado['mensaje']);
                }
            }

            if ($contador_confirmadas > 0) {
                $mensaje = "Se confirmaron {$contador_confirmadas} inscripciones en total.";
                if ($contador_omitidas > 0) {
                    $mensaje .= " Se omitieron {$contador_omitidas} inscripciones porque los estudiantes no están en los cursos correspondientes.";
                }
                set_flash_message('success', $mensaje);
            } else {
                if (count($todas_inscripciones_pendientes) > 0 && $contador_omitidas == count($todas_inscripciones_pendientes)) {
                    set_flash_message('warning', 'No se confirmó ninguna inscripción. Todas las asignaturas pendientes requieren que el estudiante esté matriculado en el curso correspondiente.');
                } else {
                    set_flash_message('info', 'No hay inscripciones pendientes para confirmar en este momento.');
                }
            }
        }

        $pdo->commit(); // Confirmar la transacción

    } catch (PDOException $e) {
        $pdo->rollBack(); // Revertir la transacción en caso de error
        // En producción, solo loguear $e->getMessage() y mostrar un mensaje genérico al usuario
        set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
        error_log("Transaction Error in estudiantes.php: " . $e->getMessage()); // Log the error detallado
    }

    header('Location: estudiantes.php');
    exit;
}

// --- El resto del script que genera la página HTML (para la solicitud GET) ---
$titulo_pagina = "Gestión de Estudiantes e Inscripciones";
include_once '../includes/header.php';

// Obtener mensajes flash para JavaScript
$mensajes_flash = get_flash_messages();

// Obtener el semestre académico actual para filtrar inscripciones
$semestre_actual = get_current_semester($pdo);
$id_semestre_actual = $semestre_actual['id'] ?? null;
// Necesitamos el id_anio_academico_actual para la subconsulta de 'curso_actual'
$id_anio_academico_para_mostrar = $semestre_actual['id_anio_academico'] ?? 0; // Usar 0 si no existe para que la subconsulta no encuentre nada
$nombre_semestre_actual = $semestre_actual ? htmlspecialchars($semestre_actual['numero_semestre'] . ' (' . $semestre_actual['nombre_anio'] . ')') : 'N/A';


// --- Obtener estudiantes con inscripciones pendientes (sin duplicados) ---
$estudiantes_con_inscripciones_pendientes = [];
if ($id_semestre_actual) {
    $stmt_estudiantes_pendientes = $pdo->prepare("
        SELECT DISTINCT
            u.id AS id_usuario,
            u.nombre_completo AS nombre_estudiante,
            e.codigo_registro,
            u.email,
            u.telefono
        FROM inscripciones_estudiantes ie
        JOIN estudiantes e ON ie.id_estudiante = e.id
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE ie.confirmada = 0
        AND ie.id_semestre = :id_semestre_actual
        ORDER BY u.nombre_completo ASC
    ");
    // Manejo de errores para prepare y execute
    if ($stmt_estudiantes_pendientes === false) {
        $info_error = $pdo->errorInfo();
        error_log("PDO Prepare Error (estudiantes_pendientes): " . $info_error[2]);
        set_flash_message('danger', 'Error interno al preparar la consulta de inscripciones pendientes.');
    } else {
        if (!$stmt_estudiantes_pendientes->execute([':id_semestre_actual' => $id_semestre_actual])) {
            $info_error = $stmt_estudiantes_pendientes->errorInfo();
            error_log("PDO Execute Error (estudiantes_pendientes): " . $info_error[2]);
            set_flash_message('danger', 'Error interno al ejecutar la consulta de inscripciones pendientes.');
        }
        $estudiantes_con_inscripciones_pendientes = $stmt_estudiantes_pendientes->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Obtener TODOS los estudiantes activos (CORRECCIÓN CLAVE PARA EL ERROR 'id_curso_inicio') ---
$todos_estudiantes_activos = [];
$stmt_todos_estudiantes = $pdo->prepare("
    SELECT
        u.id AS id_usuario,
        u.nombre_completo AS nombre_estudiante,
        e.codigo_registro,
        u.email,
        u.telefono,
        -- Subconsulta para obtener el curso actual del estudiante para el año académico actual
        (
            SELECT c.nombre_curso
            FROM curso_estudiante ce
            JOIN cursos c ON ce.id_curso = c.id
            WHERE ce.id_estudiante = e.id
            AND ce.id_anio = :id_anio_academico_para_mostrar -- Filtra por el año académico actual
            AND ce.estado = 'activo' -- Asume que 'activo' en curso_estudiante significa el curso actual
            ORDER BY ce.fecha_registro DESC -- En caso de múltiples activos para el mismo año, toma el más reciente
            LIMIT 1
        ) AS curso_actual
    FROM usuarios u
    JOIN estudiantes e ON u.id = e.id_usuario
    WHERE u.id_rol = (SELECT id FROM roles WHERE nombre_rol = 'Estudiante')
    AND u.estado = 'Activo'
    ORDER BY u.nombre_completo ASC
");

// Manejo de errores para prepare y execute
if ($stmt_todos_estudiantes === false) {
    $info_error = $pdo->errorInfo();
    error_log("PDO Prepare Error (todos_estudiantes): " . $info_error[2]);
    set_flash_message('danger', 'Error interno al preparar la consulta de todos los estudiantes.');
} else {
    // Asegurarse de tener el id_rol_estudiante antes de ejecutar
    $stmt_rol_estudiante = $pdo->prepare("SELECT id FROM roles WHERE nombre_rol = 'Estudiante'");
    $stmt_rol_estudiante->execute();
    $id_rol_estudiante = $stmt_rol_estudiante->fetchColumn();

    if (!$stmt_todos_estudiantes->execute([':id_anio_academico_para_mostrar' => $id_anio_academico_para_mostrar])) {
        $info_error = $stmt_todos_estudiantes->errorInfo();
        error_log("PDO Execute Error (todos_estudiantes): " . $info_error[2]);
        set_flash_message('danger', 'Error al ejecutar la consulta de todos los estudiantes: ' . $info_error[2]);
    }
    $todos_estudiantes_activos = $stmt_todos_estudiantes->fetchAll(PDO::FETCH_ASSOC);
}

?>

<h1 class="mt-4">Gestión de Estudiantes e Inscripciones</h1>

<ul class="nav nav-tabs mb-4" id="studentTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pending-enrollments-tab" data-bs-toggle="tab" data-bs-target="#pendingEnrollments" type="button" role="tab" aria-controls="pendingEnrollments" aria-selected="true">
            <i class="fas fa-clipboard-list me-2"></i> Inscripciones Pendientes
            <?php if (count($estudiantes_con_inscripciones_pendientes) > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo count($estudiantes_con_inscripciones_pendientes); ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="all-students-tab" data-bs-toggle="tab" data-bs-target="#allStudents" type="button" role="tab" aria-controls="allStudents" aria-selected="false">
            <i class="fas fa-users me-2"></i> Todos los Estudiantes Activos
        </button>
    </li>
</ul>

<div class="tab-content" id="studentTabsContent">
    <div class="tab-pane fade show active" id="pendingEnrollments" role="tabpanel" aria-labelledby="pending-enrollments-tab">
        <div class="d-flex justify-content-between mb-3 align-items-center">
            <div id="pendingEnrollmentButtons" style="display: <?php echo (count($estudiantes_con_inscripciones_pendientes) > 0 && $semestre_actual) ? 'block' : 'none'; ?>;">
                <button type="button" class="btn btn-success" id="confirmAllEnrollmentsBtn">
                    <i class="fas fa-check-double me-2"></i> Confirmar Todas las Inscripciones Pendientes
                </button>
            </div>
            <div class="col-md-4">
                <input type="search" class="form-control" id="searchInputPending" placeholder="Buscar estudiante en pendientes...">
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Estudiantes con Solicitudes de Inscripción Pendientes</h5>
            </div>
            <div class="card-body">
                <?php if (count($estudiantes_con_inscripciones_pendientes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="studentsTablePending">
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th>Cód. Registro</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes_con_inscripciones_pendientes as $estudiante): ?>
                                    <tr data-id_usuario="<?php echo htmlspecialchars($estudiante['id_usuario']); ?>"
                                        data-nombre_estudiante="<?php echo htmlspecialchars($estudiante['nombre_estudiante']); ?>">
                                        <td><?php echo htmlspecialchars($estudiante['nombre_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['codigo_registro']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['telefono'] ?? 'N/A'); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-info btn-sm view-enrollments-btn"
                                                     data-bs-toggle="modal" data-bs-target="#enrollmentDetailModal"
                                                     title="Ver y Gestionar Inscripciones">
                                                <i class="fas fa-eye me-1"></i> Gestionar Inscripciones
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav>
                        <ul class="pagination justify-content-center" id="paginationPending">
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-1"></i> No hay estudiantes con solicitudes de inscripción pendientes para el semestre actual.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="allStudents" role="tabpanel" aria-labelledby="all-students-tab">
        <div class="d-flex justify-content-end mb-3 align-items-center">
            <div class="col-md-4">
                <input type="search" class="form-control" id="searchInputAll" placeholder="Buscar en todos los estudiantes...">
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Listado de Todos los Estudiantes Activos</h5>
            </div>
            <div class="card-body">
                <?php if (count($todos_estudiantes_activos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="studentsTableAll">
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th>Cód. Registro</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Curso</th>
                                    <th class="text-center">Historial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todos_estudiantes_activos as $estudiante): ?>
                                    <tr data-id_usuario="<?php echo htmlspecialchars($estudiante['id_usuario']); ?>"
                                        data-nombre_estudiante="<?php echo htmlspecialchars($estudiante['nombre_estudiante']); ?>">
                                        <td><?php echo htmlspecialchars($estudiante['nombre_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['codigo_registro']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['telefono'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['curso_actual'] ?? 'N/A'); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-primary btn-sm view-history-btn"
                                                     data-bs-toggle="modal" data-bs-target="#academicHistoryModal"
                                                     title="Ver Historial Académico">
                                                <i class="fas fa-history me-1"></i> Ver Historial
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav>
                        <ul class="pagination justify-content-center" id="paginationAll">
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-1"></i> No hay estudiantes activos registrados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="enrollmentDetailModal" tabindex="-1" aria-labelledby="enrollmentDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="enrollmentDetailModalLabel">Inscripciones Pendientes de: <span id="modalStudentName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalStudentUserId">
                <div id="enrollmentsList">
                    <p class="text-center text-muted" id="loadingEnrollments">Cargando inscripciones...</p>
                </div>
                <div class="alert alert-info mt-3" id="noPendingEnrollmentsMessage" style="display: none;">
                    Este estudiante no tiene asignaturas pendientes de confirmación para el semestre actual.
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
                <form action="estudiantes.php" method="POST" class="d-inline-block" id="confirmAllStudentEnrollmentsForm">
                    <input type="hidden" name="action" value="confirm_student_enrollments">
                    <input type="hidden" name="id_estudiante" id="confirmAllStudentId">
                    <button type="submit" class="btn btn-success" id="confirmAllStudentEnrollmentsBtn" style="display: none;"
                            onclick="return confirm('¿Estás seguro de que quieres CONFIRMAR TODAS las asignaturas pendientes para este estudiante?');">
                        <i class="fas fa-check-double me-2"></i> Confirmar Todas las Asignaturas
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="academicHistoryModal" tabindex="-1" aria-labelledby="academicHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="academicHistoryModalLabel">Historial Académico de: <span id="modalHistoryStudentName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalHistoryStudentUserId">
                <div id="academicHistoryContent">
                    <p class="text-center text-muted" id="loadingHistory">Cargando historial académico...</p>
                </div>
                <div class="alert alert-info mt-3" id="noHistoryMessage" style="display: none;">
                    Este estudiante no tiene historial académico registrado.
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
                <a href="#" id="printHistoryPdfBtn" class="btn btn-danger" target="_blank" style="display: none;">
                    <i class="fas fa-file-pdf me-2"></i> Imprimir en PDF
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
</div>

<script>
    const mensajesFlash = <?php echo json_encode($mensajes_flash); ?>;
    // No es necesario inicializar los modales con new bootstrap.Modal aquí si se manejan con data-bs-toggle
    // const enrollmentDetailModal = new bootstrap.Modal(document.getElementById('enrollmentDetailModal'));
    // const academicHistoryModal = new bootstrap.Modal(document.getElementById('academicHistoryModal'));

    // Elementos del modal de detalle de inscripciones
    const modalStudentName = document.getElementById('modalStudentName');
    const modalStudentUserId = document.getElementById('modalStudentUserId');
    const enrollmentsList = document.getElementById('enrollmentsList');
    const confirmAllStudentEnrollmentsBtn = document.getElementById('confirmAllStudentEnrollmentsBtn');
    const confirmAllStudentId = document.getElementById('confirmAllStudentId');
    const loadingEnrollments = document.getElementById('loadingEnrollments');
    const noPendingEnrollmentsMessage = document.getElementById('noPendingEnrollmentsMessage');
    const pendingEnrollmentButtonsContainer = document.getElementById('pendingEnrollmentButtons');

    // Elementos del modal de historial académico
    const modalHistoryStudentName = document.getElementById('modalHistoryStudentName');
    const modalHistoryStudentUserId = document.getElementById('modalHistoryStudentUserId');
    const academicHistoryContent = document.getElementById('academicHistoryContent');
    const loadingHistory = document.getElementById('loadingHistory');
    const noHistoryMessage = document.getElementById('noHistoryMessage');
    const printHistoryPdfBtn = document.getElementById('printHistoryPdfBtn');

    // Obtener los IDs del semestre y año académico actuales del PHP
    const idSemestreActual = <?php echo json_encode($id_semestre_actual); ?>;
    const idAnioAcademicoParaMostrar = <?php echo json_encode($id_anio_academico_para_mostrar); ?>; // Usado para la subconsulta de curso_actual


    // --- Funcionalidad para el botón "Confirmar Todas las Inscripciones Pendientes" (Global) ---
    document.getElementById('confirmAllEnrollmentsBtn')?.addEventListener('click', function() {
        if (confirm('¿Estás absolutamente seguro de que quieres CONFIRMAR TODAS las solicitudes de inscripción pendientes de TODOS los estudiantes? Esto solo confirmará las asignaturas para estudiantes que estén matriculados en el curso correspondiente. Esta acción no se puede deshacer.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'estudiantes.php';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'confirm_all_enrollments';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });

    // --- Funcionalidad para botones "Gestionar Inscripciones" por estudiante (en pestaña de pendientes) ---
    document.querySelectorAll('.view-enrollments-btn').forEach(button => {
        button.addEventListener('click', function() {
            const filaTabla = this.closest('tr'); // Obtener la fila <tr>

            let idUsuarioEstudiante = null;
            let nombreEstudiante = '';

            if (filaTabla) {
                idUsuarioEstudiante = filaTabla.dataset.id_usuario;
                nombreEstudiante = filaTabla.dataset.nombre_estudiante;
            } else {
                console.error("Error: No se pudo encontrar la fila <tr> padre del botón que activó el modal de inscripciones pendientes.");
                mostrarToast('danger', 'Error al cargar inscripciones: No se encontró la información del estudiante.');
                return; // Detener la ejecución si no se encuentra la fila
            }

            modalStudentName.innerText = nombreEstudiante;
            modalStudentUserId.value = idUsuarioEstudiante;
            confirmAllStudentId.value = idUsuarioEstudiante; // Para el formulario de confirmar todas del estudiante

            enrollmentsList.innerHTML = ''; // Limpiar lista anterior
            loadingEnrollments.style.display = 'block'; // Mostrar mensaje de carga
            noPendingEnrollmentsMessage.style.display = 'none'; // Ocultar mensaje de no pendientes

            // Deshabilitar botón de confirmar todas del estudiante hasta que se carguen las asignaturas
            confirmAllStudentEnrollmentsBtn.style.display = 'none';

            // Cargar inscripciones via AJAX
            // Asegúrate de que confirmaciones_pendientes.php devuelva 'success' y 'enrollments'
            fetch(`../api/inscripciones_pendientes.php?id_usuario=${idUsuarioEstudiante}&id_semestre=${idSemestreActual}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('La respuesta de red no fue satisfactoria ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    loadingEnrollments.style.display = 'none'; // Ocultar mensaje de carga
                    if (data.success && data.enrollments.length > 0) {
                        let htmlContent = `<div class="table-responsive"><table class="table table-sm table-bordered table-hover"><thead><tr>
                                                <th>Asignatura</th><th>Créditos</th><th>Curso</th><th>Semestre Rec.</th><th>Fecha Solicitud</th><th>Acciones</th>
                                                </tr></thead><tbody>`;
                        data.enrollments.forEach(enrollment => {
                            htmlContent += `
                                <tr>
                                    <td>${enrollment.nombre_asignatura}</td>
                                    <td class="text-center">${enrollment.creditos}</td>
                                    <td>${enrollment.nombre_curso}</td>
                                    <td class="text-center">${enrollment.semestre_recomendado}</td>
                                    <td>${new Date(enrollment.fecha_inscripcion).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                                    <td>
                                        <form action="estudiantes.php" method="POST" class="d-inline-block me-1">
                                            <input type="hidden" name="action" value="confirm_single_enrollment">
                                            <input type="hidden" name="id_inscripcion" value="${enrollment.id_inscripcion}">
                                            <button type="submit" class="btn btn-success btn-sm" title="Confirmar Asignatura"
                                                    onclick="return confirm('¿Confirmar ${enrollment.nombre_asignatura}?');">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form action="estudiantes.php" method="POST" class="d-inline-block">
                                            <input type="hidden" name="action" value="reject_single_enrollment">
                                            <input type="hidden" name="id_inscripcion" value="${enrollment.id_inscripcion}">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Rechazar Asignatura"
                                                    onclick="return confirm('¿Rechazar ${enrollment.nombre_asignatura}?');">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            `;
                        });
                        htmlContent += `</tbody></table></div>`;
                        enrollmentsList.innerHTML = htmlContent;
                        confirmAllStudentEnrollmentsBtn.style.display = 'inline-block'; // Habilitar si hay asignaturas
                    } else {
                        enrollmentsList.innerHTML = '';
                        noPendingEnrollmentsMessage.style.display = 'block'; // Mostrar mensaje de no pendientes
                        confirmAllStudentEnrollmentsBtn.style.display = 'none'; // Deshabilitar si no hay asignaturas
                    }
                })
                .catch(error => {
                    loadingEnrollments.style.display = 'none';
                    enrollmentsList.innerHTML = `<div class="alert alert-danger">Error al cargar las inscripciones: ${error.message}</div>`;
                    confirmAllStudentEnrollmentsBtn.style.display = 'none';
                    console.error('Error al obtener inscripciones:', error);
                    mostrarToast('danger', 'Error al cargar las inscripciones del estudiante.');
                });
        });
    });


    // --- Funcionalidad para botones "Ver Historial" por estudiante (en pestaña de todos los estudiantes) ---
    document.querySelectorAll('.view-history-btn').forEach(button => {
        button.addEventListener('click', function() {
            const filaTabla = this.closest('tr'); // Obtener la fila <tr>

            let idUsuarioEstudiante = null;
            let nombreEstudiante = '';

            if (filaTabla) {
                idUsuarioEstudiante = filaTabla.dataset.id_usuario;
                nombreEstudiante = filaTabla.dataset.nombre_estudiante;
            } else {
                console.error("Error: No se pudo encontrar la fila <tr> padre del botón que activó el modal de historial.");
                mostrarToast('danger', 'Error al cargar historial: No se encontró la información del estudiante.');
                return; // Detener la ejecución si no se encuentra la fila
            }

            modalHistoryStudentName.innerText = nombreEstudiante;
            modalHistoryStudentUserId.value = idUsuarioEstudiante;
            // Asegúrate de que generate_history_pdf.php espere 'id_usuario'
            printHistoryPdfBtn.href = `generate_history_pdf.php?id_usuario=${idUsuarioEstudiante}`; // Set PDF link

            academicHistoryContent.innerHTML = ''; // Limpiar contenido anterior
            loadingHistory.style.display = 'block'; // Mostrar mensaje de carga
            noHistoryMessage.style.display = 'none'; // Ocultar mensaje de no historial
            printHistoryPdfBtn.style.display = 'none'; // Ocultar botón de imprimir por defecto

            // Cargar historial académico via AJAX
            // Asegúrate de que historial_academico.php devuelva 'success' y 'historial'
            fetch(`../api/historial_academico.php?id_usuario=${idUsuarioEstudiante}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('La respuesta de red no fue satisfactoria ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    loadingHistory.style.display = 'none'; // Ocultar mensaje de carga
                    // Asegúrate de que la API devuelve 'historial' como un array de objetos, no un objeto anidado por semestre
                    if (data.success && data.historial && data.historial.length > 0) {
                        let htmlContent = `<div class="table-responsive"><table class="table table-sm table-bordered table-hover"><thead><tr>
                                                <th>Asignatura</th><th>Semestre</th><th>Año Académico</th><th>Nota Final</th><th>Estado</th>
                                                </tr></thead><tbody>`;
                        data.historial.forEach(item => {
                            const statusClass = item.estado_final === 'APROBADO' ? 'text-success' : 'text-danger';
                            htmlContent += `
                                <tr>
                                    <td>${item.nombre_asignatura}</td>
                                    <td class="text-center">${item.numero_semestre}</td>
                                    <td>${item.nombre_anio}</td>
                                    <td class="text-center">${item.nota_final}</td>
                                    <td class="text-center"><span class="badge bg-${item.estado_final === 'APROBADO' ? 'success' : 'danger'}">${item.estado_final}</span></td>
                                </tr>
                            `;
                        });
                        htmlContent += `</tbody></table></div>`;
                        academicHistoryContent.innerHTML = htmlContent;
                        printHistoryPdfBtn.style.display = 'inline-block'; // Mostrar botón de imprimir
                    } else {
                        academicHistoryContent.innerHTML = '';
                        noHistoryMessage.style.display = 'block'; // Mostrar mensaje de no historial
                        printHistoryPdfBtn.style.display = 'none'; // Ocultar botón de imprimir
                    }
                })
                .catch(error => {
                    loadingHistory.style.display = 'none';
                    academicHistoryContent.innerHTML = `<div class="alert alert-danger">Error al cargar el historial: ${error.message}</div>`;
                    printHistoryPdfBtn.style.display = 'none';
                    console.error('Error al obtener historial académico:', error);
                    mostrarToast('danger', 'Error al cargar el historial académico del estudiante.');
                });
        });
    });

    // --- Búsqueda y Paginación ---
    function configurarBusquedaYPaginacionTabla(idTabla, idInputBusqueda, idContenedorPaginacion, filasPorPagina = 10) {
        const tabla = document.getElementById(idTabla);
        const inputBusqueda = document.getElementById(idInputBusqueda);
        const contenedorPaginacion = document.getElementById(idContenedorPaginacion);
        if (!tabla || !inputBusqueda || !contenedorPaginacion) return;

        const tbody = tabla.querySelector('tbody');
        // Crear una copia de las filas iniciales para no mutar el DOM en cada render
        const filasOriginales = Array.from(tbody.querySelectorAll('tr'));
        let paginaActual = 1;

        function renderizarTabla() {
            const filtro = inputBusqueda.value.toLowerCase();
            const filasFiltradas = filasOriginales.filter(fila => {
                return fila.textContent.toLowerCase().includes(filtro);
            });

            const totalPaginas = Math.ceil(filasFiltradas.length / filasPorPagina);
            
            // Actualizar visibilidad del botón "Confirmar Todas las Inscripciones"
            if (idTabla === 'studentsTablePending') {
                const botonesInscripcionesPendientes = document.getElementById('pendingEnrollmentButtons');
                if (botonesInscripcionesPendientes) {
                    // Solo mostrar si hay filas filtradas Y un semestre actual definido
                    botonesInscripcionesPendientes.style.display = (filasFiltradas.length > 0 && idSemestreActual) ? 'block' : 'none';
                }
            }

            tbody.innerHTML = ''; // Limpiar filas actuales

            // Asegurar que la página actual no exceda el total de páginas
            if (paginaActual > totalPaginas && totalPaginas > 0) {
                paginaActual = totalPaginas;
            } else if (totalPaginas === 0) {
                paginaActual = 1;
            }

            const inicio = (paginaActual - 1) * filasPorPagina;
            const fin = inicio + filasPorPagina;
            const filasPaginadas = filasFiltradas.slice(inicio, fin);

            if (filasPaginadas.length === 0 && filasFiltradas.length > 0) {
                // Esto maneja el caso donde al filtrar, la página actual se queda sin elementos.
                // En este escenario, si aún hay elementos filtrados pero no en la página actual,
                // intenta ir a la primera página.
                paginaActual = 1;
                renderizarTabla(); 
                return;
            } else if (filasPaginadas.length === 0 && filasFiltradas.length === 0 && filtro !== '') {
                // No hay resultados para la búsqueda actual, mostrar mensaje
                tbody.innerHTML = `<tr><td colspan="${tabla.querySelectorAll('th').length}" class="text-center text-muted">No se encontraron resultados.</td></tr>`;
            } else if (filasFiltradas.length === 0 && filtro === '') {
                // No hay filas en absoluto (tabla vacía de origen), no hacer nada especial
                // El mensaje de "No hay estudiantes..." ya debería ser manejado por PHP
                return;
            }
            
            filasPaginadas.forEach(fila => tbody.appendChild(fila));
            renderizarPaginacion(totalPaginas, contenedorPaginacion);
        }

        function renderizarPaginacion(totalPaginas, contenedor) {
            contenedor.innerHTML = '';
            if (totalPaginas <= 1) return;

            // Botón Anterior
            let li = document.createElement('li');
            li.className = `page-item ${paginaActual === 1 ? 'disabled' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" data-page="${paginaActual - 1}">Anterior</a>`;
            li.addEventListener('click', (e) => {
                e.preventDefault();
                if (paginaActual > 1) {
                    paginaActual--;
                    renderizarTabla();
                }
            });
            contenedor.appendChild(li);

            // Números de página
            // Lógica para mostrar un rango limitado de páginas
            const rangoPaginas = 2; // Mostrar 2 páginas antes y 2 después de la actual
            let inicioRango = Math.max(1, paginaActual - rangoPaginas);
            let finRango = Math.min(totalPaginas, paginaActual + rangoPaginas);

            // Ajustar el rango si estamos cerca del principio o del final
            if (finRango - inicioRango + 1 < (rangoPaginas * 2 + 1)) {
                inicioRango = Math.max(1, finRango - (rangoPaginas * 2));
            }
            if (finRango - inicioRango + 1 < (rangoPaginas * 2 + 1)) {
                finRango = Math.min(totalPaginas, inicioRango + (rangoPaginas * 2));
            }

            if (inicioRango > 1) {
                li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = `<a class="page-link" href="#" data-page="1">1</a>`;
                li.addEventListener('click', (e) => { e.preventDefault(); paginaActual = 1; renderizarTabla(); });
                contenedor.appendChild(li);
                if (inicioRango > 2) {
                    li = document.createElement('li');
                    li.className = 'page-item disabled';
                    li.innerHTML = `<span class="page-link">...</span>`;
                    contenedor.appendChild(li);
                }
            }

            for (let i = inicioRango; i <= finRango; i++) {
                li = document.createElement('li');
                li.className = `page-item ${paginaActual === i ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                li.addEventListener('click', (e) => {
                    e.preventDefault();
                    paginaActual = i;
                    renderizarTabla();
                });
                contenedor.appendChild(li);
            }

            if (finRango < totalPaginas) {
                if (finRango < totalPaginas - 1) {
                    li = document.createElement('li');
                    li.className = 'page-item disabled';
                    li.innerHTML = `<span class="page-link">...</span>`;
                    contenedor.appendChild(li);
                }
                li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = `<a class="page-link" href="#" data-page="${totalPaginas}">${totalPaginas}</a>`;
                li.addEventListener('click', (e) => { e.preventDefault(); paginaActual = totalPaginas; renderizarTabla(); });
                contenedor.appendChild(li);
            }

            // Botón Siguiente
            li = document.createElement('li');
            li.className = `page-item ${paginaActual === totalPaginas ? 'disabled' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" data-page="${paginaActual + 1}">Siguiente</a>`;
            li.addEventListener('click', (e) => {
                e.preventDefault();
                if (paginaActual < totalPaginas) {
                    paginaActual++;
                    renderizarTabla();
                }
            });
            contenedor.appendChild(li);
        }

        inputBusqueda.addEventListener('keyup', () => {
            paginaActual = 1; // Reiniciar a la primera página en la búsqueda
            renderizarTabla();
        });

        // Renderizado inicial
        renderizarTabla();
    }

    configurarBusquedaYPaginacionTabla('studentsTablePending', 'searchInputPending', 'paginationPending');
    configurarBusquedaYPaginacionTabla('studentsTableAll', 'searchInputAll', 'paginationAll');

    // Función para mostrar Toast (asegúrate de tener Bootstrap 5 o similar para esto)
    function mostrarToast(tipo, mensaje) {
        const contenedorToast = document.querySelector('.toast-container') || document.createElement('div');
        if (!contenedorToast.classList.contains('toast-container')) {
            contenedorToast.classList.add('toast-container', 'position-fixed', 'bottom-0', 'end-0', 'p-3');
            document.body.appendChild(contenedorToast);
        }

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${tipo} border-0 fade show`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        `;
        contenedorToast.appendChild(toast);

        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        // Opcional: Eliminar toast después de que se oculte
        toast.addEventListener('hidden.bs.toast', function () {
            toast.remove();
        });
    }

</script>