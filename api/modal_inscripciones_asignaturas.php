<?php
// get_modal_subjects.php

session_start(); // ¡Importante! Asegúrate de iniciar la sesión para acceder a $_SESSION['user_id']
ini_set('display_errors', 1); // Solo para depuración, desactivar en producción
ini_set('display_startup_errors', 1); // Solo para depuración, desactivar en producción
error_reporting(E_ALL); // Solo para depuración, desactivar en producción

require_once '../includes/functions.php'; // Ajusta la ruta si es necesario
require_once '../config/database.php';   // Ajusta la ruta si es necesario

// 1. Verificación de Autenticación y Rol
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    http_response_code(403); // Prohibido
    echo '<div class="alert alert-danger">Acceso no autorizado. Por favor, inicia sesión como estudiante.</div>';
    exit;
}

// 2. Obtener Detalles del Estudiante
$stmt_student_details = $pdo->prepare("SELECT id, id_curso_inicio FROM estudiantes WHERE id_usuario = :id_usuario");
$stmt_student_details->bindParam(':id_usuario', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt_student_details->execute();
$student_details = $stmt_student_details->fetch(PDO::FETCH_ASSOC);

if (!$student_details) {
    http_response_code(500); // Error interno del servidor
    echo '<div class="alert alert-danger">Error: No se encontró el perfil de estudiante asociado.</div>';
    exit;
}
$id_estudiante_actual = $student_details['id'];
$id_curso_inicio_estudiante = $student_details['id_curso_inicio'];

// 3. Obtener el Semestre Académico Actual
$current_semester = get_current_semester($pdo);

if (!$current_semester) {
    echo '<div class="alert alert-warning text-center">No hay un semestre académico activo para la inscripción en este momento.</div>';
    exit; // No hay semestre activo, no hay asignaturas que mostrar
}

// 4. Obtener Asignaturas Reprobadas (Obligatorias)
$reproved_asignaturas = [];
$reproved_asignaturas_ids = [];
$stmt_reproved_asignaturas = $pdo->prepare("
    SELECT
        ha.id_asignatura AS id,
        a.nombre_asignatura,
        a.creditos,
        c.nombre_curso,
        a.semestre_recomendado AS numero_semestre_asignatura,
        s.numero_semestre AS semestre_historial_numero,
        aa.nombre_anio
    FROM historial_academico ha
    JOIN asignaturas a ON ha.id_asignatura = a.id
    LEFT JOIN cursos c ON a.id_curso = c.id
    JOIN semestres s ON ha.id_semestre = s.id
    JOIN anios_academicos aa ON s.id_anio_academico = aa.id
    WHERE ha.id_estudiante = :id_estudiante
    AND ha.estado_final = 'REPROBADO'
    AND a.id NOT IN (
        SELECT id_asignatura FROM inscripciones_estudiantes WHERE id_estudiante = :id_estudiante_current AND id_semestre = :id_semestre_current AND confirmada = 1
    )
    ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
");
$stmt_reproved_asignaturas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_reproved_asignaturas->bindParam(':id_estudiante_current', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_reproved_asignaturas->bindParam(':id_semestre_current', $current_semester['id'], PDO::PARAM_INT);
$stmt_reproved_asignaturas->execute();
$reproved_asignaturas = $stmt_reproved_asignaturas->fetchAll(PDO::FETCH_ASSOC);
$reproved_asignaturas_ids = array_column($reproved_asignaturas, 'id');

// 5. Obtener Asignaturas Aprobadas (para verificación de prerrequisitos)
$approved_asignaturas_ids = [];
$stmt_approved_asignaturas = $pdo->prepare("
    SELECT id_asignatura FROM historial_academico
    WHERE id_estudiante = :id_estudiante AND estado_final = 'APROBADO'
");
$stmt_approved_asignaturas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_approved_asignaturas->execute();
$approved_asignaturas_ids = $stmt_approved_asignaturas->fetchAll(PDO::FETCH_COLUMN);

// 6. Obtener Asignaturas ya Inscritas en el Semestre Actual (para deshabilitar)
$current_enrollments_ids = array_column($pdo->query("SELECT id_asignatura FROM inscripciones_estudiantes WHERE id_estudiante = {$id_estudiante_actual} AND id_semestre = {$current_semester['id']}")->fetchAll(PDO::FETCH_ASSOC), 'id_asignatura');

// 7. Determinar Paridad del Semestre Actual
$current_semester_number = $current_semester['numero_semestre'];
$is_current_semester_odd = ($current_semester_number % 2 != 0);

// 8. Obtener Asignaturas Disponibles (base para el modal)
$stmt_available_asignaturas = $pdo->prepare("
    SELECT
        a.id,
        a.nombre_asignatura,
        a.creditos,
        a.id_prerequisito,
        pa.nombre_asignatura AS prerequisito_nombre,
        c.nombre_curso,
        a.semestre_recomendado
    FROM asignaturas a
    LEFT JOIN asignaturas pa ON a.id_prerequisito = pa.id
    JOIN cursos c ON a.id_curso = c.id
    WHERE a.id_curso = :id_curso_estudiante
    AND a.id NOT IN (
        SELECT id_asignatura FROM historial_academico WHERE id_estudiante = :id_estudiante_historial_aprobado AND estado_final = 'APROBADO'
    )
    AND a.id NOT IN (
        SELECT id_asignatura FROM inscripciones_estudiantes WHERE id_estudiante = :id_estudiante_enrolled AND id_semestre = :id_semestre_enrolled
    )
    AND (
        (:is_current_semester_odd AND (a.semestre_recomendado % 2 != 0))
        OR
        (:is_current_semester_even AND (a.semestre_recomendado % 2 = 0))
    )
    ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
");

$stmt_available_asignaturas->bindParam(':id_curso_estudiante', $id_curso_inicio_estudiante, PDO::PARAM_INT);
$stmt_available_asignaturas->bindParam(':id_estudiante_historial_aprobado', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_available_asignaturas->bindParam(':id_estudiante_enrolled', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_available_asignaturas->bindParam(':id_semestre_enrolled', $current_semester['id'], PDO::PARAM_INT);
$stmt_available_asignaturas->bindValue(':is_current_semester_odd', $is_current_semester_odd ? 1 : 0, PDO::PARAM_INT);
$stmt_available_asignaturas->bindValue(':is_current_semester_even', !$is_current_semester_odd ? 1 : 0, PDO::PARAM_INT);

$stmt_available_asignaturas->execute();
$available_asignaturas_current_course = $stmt_available_asignaturas->fetchAll(PDO::FETCH_ASSOC);

// 9. Obtener todos los semestres recomendados distintos para el filtro (dinámico)
$stmt_all_semesters = $pdo->prepare("SELECT DISTINCT semestre_recomendado FROM asignaturas WHERE id_curso = :id_curso ORDER BY semestre_recomendado ASC");
$stmt_all_semesters->bindParam(':id_curso', $id_curso_inicio_estudiante, PDO::PARAM_INT);
$stmt_all_semesters->execute();
$all_semesters_for_filter = $stmt_all_semesters->fetchAll(PDO::FETCH_COLUMN);

// --- 10. Generación del HTML para la respuesta AJAX ---
?>
<div class="mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <select id="filterCourse" class="form-select">
                <option value="">Filtrar por Curso</option>
                <?php
                // Obtener y mostrar los cursos asociados a las asignaturas del estudiante
                $stmt_courses = $pdo->prepare("SELECT DISTINCT c.id, c.nombre_curso FROM cursos c JOIN asignaturas a ON c.id = a.id_curso WHERE a.id_curso = :id_curso_estudiante ORDER BY c.nombre_curso");
                $stmt_courses->bindParam(':id_curso_estudiante', $id_curso_inicio_estudiante, PDO::PARAM_INT);
                $stmt_courses->execute();
                while ($course = $stmt_courses->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="' . htmlspecialchars($course['nombre_curso']) . '">' . htmlspecialchars($course['nombre_curso']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col-md-4">
            <select id="filterSemester" class="form-select">
                <option value="">Filtrar por Semestre</option>
                <?php
                // Llenar dinámicamente con los semestres recomendados existentes en la base de datos para el curso del estudiante
                foreach ($all_semesters_for_filter as $sem_num) {
                    echo '<option value="' . htmlspecialchars($sem_num) . '">Semestre ' . htmlspecialchars($sem_num) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" id="filterSearch" class="form-control" placeholder="Buscar asignatura...">
        </div>
    </div>
</div>
<p><span id="modalSelectedCount"></span></p>


<h4>Asignaturas Reprobadas (Obligatorias)</h4>
<?php if (!empty($reproved_asignaturas)): ?>
    <p class="text-danger">Debes volver a cursar las siguientes asignaturas:</p>
    <div class="list-group mb-3">
        <?php foreach ($reproved_asignaturas as $asig_reproved): ?>
            <label class="list-group-item d-flex justify-content-between align-items-center bg-warning-subtle reprobada-obligatoria">
                <input type="hidden" name="selected_asignaturas[]" value="<?php echo htmlspecialchars($asig_reproved['id']); ?>">
                <input class="form-check-input me-1" type="checkbox" value="<?php echo htmlspecialchars($asig_reproved['id']); ?>" checked disabled>
                <?php echo htmlspecialchars($asig_reproved['nombre_asignatura']); ?> (<?php echo htmlspecialchars($asig_reproved['creditos']); ?> Créditos)
                <span class="badge bg-danger rounded-pill">Reprobada</span>
            </label>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="text-success">¡Enhorabuena! No tienes asignaturas reprobadas pendientes de cursar.</p>
<?php endif; ?>

<h4>Asignaturas Disponibles para este Semestre</h4>
<div class="list-group" id="availableAsignaturasList">
    <?php if (!empty($available_asignaturas_current_course)): ?>
        <?php foreach ($available_asignaturas_current_course as $asig): ?>
            <?php
            $is_approved = in_array($asig['id'], $approved_asignaturas_ids);
            $is_enrolled_this_semester = in_array($asig['id'], $current_enrollments_ids);
            $is_reprobated_mandatory = in_array($asig['id'], $reproved_asignaturas_ids);

            // Una asignatura está deshabilitada si ya está aprobada, inscrita en este semestre o es reprobada obligatoria (ya marcada)
            $is_disabled = $is_approved || $is_enrolled_this_semester || $is_reprobated_mandatory;
            $checked_status = $is_reprobated_mandatory ? 'checked' : ''; // Solo marcar si es reprobada obligatoria

            $bg_class = '';
            if ($is_approved) {
                $bg_class = 'bg-success-subtle';
            } elseif ($is_enrolled_this_semester) {
                $bg_class = 'bg-info-subtle';
            } elseif ($is_reprobated_mandatory) {
                $bg_class = 'bg-warning-subtle';
            }
            ?>
            <label class="list-group-item d-flex justify-content-between align-items-center asignatura-item <?php echo $bg_class; ?>"
                   data-course="<?php echo htmlspecialchars($asig['nombre_curso']); ?>"
                   data-semester="<?php echo htmlspecialchars($asig['semestre_recomendado']); ?>"
                   data-name="<?php echo htmlspecialchars($asig['nombre_asignatura']); ?>">
                <input class="form-check-input me-1 <?php echo $is_reprobated_mandatory ? 'reprobada-obligatoria' : 'asig-normal'; ?>"
                       type="checkbox"
                       name="selected_asignaturas[]"
                       value="<?php echo htmlspecialchars($asig['id']); ?>"
                       <?php echo $is_disabled ? 'disabled' : ''; ?>
                       <?php echo $checked_status; ?>>
                <?php echo htmlspecialchars($asig['nombre_asignatura']); ?> (<?php echo htmlspecialchars($asig['creditos']); ?> Créditos)
                <div class="ms-auto">
                    <?php if ($asig['id_prerequisito']): ?>
                        <span class="badge bg-secondary me-1" data-bs-toggle="tooltip" title="Prerrequisito: <?php echo htmlspecialchars($asig['prerequisito_nombre']); ?>">PREREQ</span>
                    <?php endif; ?>
                    <?php if ($is_approved): ?>
                        <span class="badge bg-success">APROBADA</span>
                    <?php elseif ($is_enrolled_this_semester): ?>
                        <span class="badge bg-info">INSCRITA</span>
                    <?php elseif ($is_reprobated_mandatory): ?>
                        <span class="badge bg-danger">REPROBADA (OBLIGATORIA)</span>
                    <?php else: ?>
                        <span class="badge bg-primary">Semestre <?php echo htmlspecialchars($asig['semestre_recomendado']); ?></span>
                    <?php endif; ?>
                </div>
            </label>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="alert alert-info">No hay asignaturas disponibles para inscribirse en este momento que cumplan con los requisitos del semestre y los prerrequisitos.</p>
    <?php endif; ?>
</div>