<?php
// --- INICIO DE DEPURACIÓN TEMPORAL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

require_once '../includes/functions.php';
// Asegúrate de que esta función maneje el rol 'Profesor'
check_login_and_role('Profesor');

require_once '../config/database.php';

$page_title = "Panel del Profesor";
include_once '../includes/header.php'; // Asegúrate de que incluye Bootstrap y Font Awesome

$flash_messages = get_flash_messages();

$current_user_id = $_SESSION['user_id'];

// Obtener el id_profesor del usuario logueado desde la tabla `profesores`
$stmt_profesor_id = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
$stmt_profesor_id->bindParam(':id_usuario', $current_user_id, PDO::PARAM_INT);
$stmt_profesor_id->execute();
$id_profesor_actual = $stmt_profesor_id->fetchColumn();

if (!$id_profesor_actual) {
    set_flash_message('danger', 'Error: No se encontró el perfil de profesor asociado a su usuario.');
    header('Location: ../logout.php');
    exit;
}

// --- Lógica de Procesamiento POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        // 1. Subir CV
        if ($action === 'upload_cv') {
            if (isset($_FILES['profesor_cv']) && $_FILES['profesor_cv']['error'] === UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['profesor_cv']['tmp_name'];
                $file_name = basename($_FILES['profesor_cv']['name']);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['pdf', 'doc', 'docx']; // Extensiones permitidas

                if (!in_array($file_ext, $allowed_ext)) {
                    set_flash_message('danger', 'Error: Solo se permiten archivos PDF, DOC y DOCX para el CV.');
                } else {
                    $upload_dir = 'uploads/cvs/'; // Directorio donde se guardarán los CVs
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true); // Crear directorio si no existe
                    }
                    $unique_file_name = $id_profesor_actual . '_cv_' . time() . '.' . $file_ext;
                    $file_path = $upload_dir . $unique_file_name;

                    if (move_uploaded_file($file_tmp_name, $file_path)) {
                        // Guardar/actualizar información del CV en la base de datos
                        // La tabla es `cvs_profesores`, columnas `id_profesor`, `nombre_archivo`, `ruta_archivo`, `fecha_subida`
                        $stmt_check_cv = $pdo->prepare("SELECT id FROM cvs_profesores WHERE id_profesor = :id_profesor");
                        $stmt_check_cv->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                        $stmt_check_cv->execute();
                        $existing_cv = $stmt_check_cv->fetch(PDO::FETCH_ASSOC);

                        if ($existing_cv) {
                            // Opcional: Eliminar CV anterior del servidor si solo se permite uno
                            $stmt_old_cv_path = $pdo->prepare("SELECT ruta_archivo FROM cvs_profesores WHERE id = :id_cv");
                            $stmt_old_cv_path->bindParam(':id_cv', $existing_cv['id'], PDO::PARAM_INT);
                            $stmt_old_cv_path->execute();
                            $old_path = $stmt_old_cv_path->fetchColumn();
                            if ($old_path && file_exists($old_path)) {
                                unlink($old_path); // Eliminar archivo físico anterior
                            }

                            $stmt_update_cv = $pdo->prepare("UPDATE cvs_profesores SET nombre_archivo = :nombre_archivo, ruta_archivo = :ruta_archivo, fecha_subida = NOW() WHERE id_profesor = :id_profesor");
                            $stmt_update_cv->bindParam(':nombre_archivo', $file_name);
                            $stmt_update_cv->bindParam(':ruta_archivo', $file_path);
                            $stmt_update_cv->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                            $stmt_update_cv->execute();
                        } else {
                            $stmt_insert_cv = $pdo->prepare("INSERT INTO cvs_profesores (id_profesor, nombre_archivo, ruta_archivo, fecha_subida) VALUES (:id_profesor, :nombre_archivo, :ruta_archivo, NOW())");
                            $stmt_insert_cv->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                            $stmt_insert_cv->bindParam(':nombre_archivo', $file_name);
                            $stmt_insert_cv->bindParam(':ruta_archivo', $file_path);
                            $stmt_insert_cv->execute();
                        }
                        set_flash_message('success', 'CV subido y actualizado correctamente.');
                    } else {
                        set_flash_message('danger', 'Error al mover el archivo subido.');
                    }
                }
            } else {
                set_flash_message('danger', 'Error al subir el archivo CV. Código de error: ' . ($_FILES['profesor_cv']['error'] ?? 'N/A'));
            }
        }
        // 2. Sugerir Asignatura
        elseif ($action === 'suggest_subject') {
            $id_asignatura = filter_var($_POST['id_asignatura'] ?? null, FILTER_VALIDATE_INT);
            if ($id_asignatura === null) {
                set_flash_message('danger', 'Error: Asignatura no válida.');
            } else {
                // Verificar si ya ha sugerido esta asignatura en `profesores_asignaturas_sugeridas`
                $stmt_check_suggestion = $pdo->prepare("SELECT COUNT(*) FROM profesores_asignaturas_sugeridas WHERE id_profesor = :id_profesor AND id_asignatura = :id_asignatura");
                $stmt_check_suggestion->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                $stmt_check_suggestion->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                $stmt_check_suggestion->execute();

                if ($stmt_check_suggestion->fetchColumn() > 0) {
                    set_flash_message('warning', 'Ya has sugerido esta asignatura anteriormente.');
                } else {
                    $stmt_suggest = $pdo->prepare("INSERT INTO profesores_asignaturas_sugeridas (id_profesor, id_asignatura, fecha_sugerencia) VALUES (:id_profesor, :id_asignatura, NOW())");
                    $stmt_suggest->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                    $stmt_suggest->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                    $stmt_suggest->execute();
                    set_flash_message('success', 'Asignatura sugerida correctamente.');
                }
            }
        }
        // 3. Eliminar Sugerencia de Asignatura
        elseif ($action === 'remove_suggestion') {
            $id_sugerencia = filter_var($_POST['id_sugerencia'] ?? null, FILTER_VALIDATE_INT);
            if ($id_sugerencia === null) {
                set_flash_message('danger', 'Error: Sugerencia no válida.');
            } else {
                // Eliminar de `profesores_asignaturas_sugeridas`
                $stmt_remove = $pdo->prepare("DELETE FROM profesores_asignaturas_sugeridas WHERE id = :id_sugerencia AND id_profesor = :id_profesor");
                $stmt_remove->bindParam(':id_sugerencia', $id_sugerencia, PDO::PARAM_INT);
                $stmt_remove->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                $stmt_remove->execute();
                if ($stmt_remove->rowCount() > 0) {
                    set_flash_message('success', 'Sugerencia de asignatura eliminada.');
                } else {
                    set_flash_message('warning', 'La sugerencia no pudo ser eliminada o no te pertenece.');
                }
            }
        }

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
    }
   /*  header('Location: index.php'); // Redirección POST-GET
    exit; */
}

// --- Obtener datos para la vista del profesor ---

// Obtener información del CV del profesor de `cvs_profesores`
$profesor_cv = null;
$stmt_cv = $pdo->prepare("SELECT nombre_archivo, ruta_archivo FROM cvs_profesores WHERE id_profesor = :id_profesor");
$stmt_cv->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
$stmt_cv->execute();
$profesor_cv = $stmt_cv->fetch(PDO::FETCH_ASSOC);

// Obtener todas las asignaturas disponibles de `asignaturas`
$available_subjects = $pdo->query("SELECT id, nombre_asignatura, creditos FROM asignaturas ORDER BY nombre_asignatura ASC")->fetchAll(PDO::FETCH_ASSOC);

// Obtener asignaturas sugeridas por el profesor de `profesores_asignaturas_sugeridas`
$suggested_subjects = [];
$stmt_suggested = $pdo->prepare("
    SELECT pas.id, a.nombre_asignatura, a.creditos
    FROM profesores_asignaturas_sugeridas pas
    JOIN asignaturas a ON pas.id_asignatura = a.id
    WHERE pas.id_profesor = :id_profesor
    ORDER BY a.nombre_asignatura ASC
");
$stmt_suggested->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
$stmt_suggested->execute();
$suggested_subjects = $stmt_suggested->fetchAll(PDO::FETCH_ASSOC);

// Obtener el semestre actual para filtrar los horarios
// Asumiendo que `semestres` tiene una columna `activo` o que `get_current_semester` ya implementa la lógica
// Si no existe `activo` o `get_current_semester`, podrías buscar el semestre cuya fecha actual esté entre `fecha_inicio` y `fecha_fin`.
 
$current_semester = get_current_semester($pdo);

// Obtener los horarios asignados a este profesor para el semestre actual de `horarios`
$assigned_schedules = [];
if ($current_semester) {
    $stmt_assigned_schedules = $pdo->prepare("
        SELECT
            h.id AS id_horario,
            h.dia_semana, h.hora_inicio, h.hora_fin, h.turno,
            a.id AS id_asignatura, a.nombre_asignatura,
            c.id AS id_curso, c.nombre_curso,
            au.nombre_aula
        FROM horarios h
        JOIN asignaturas a ON h.id_asignatura = a.id
        JOIN cursos c ON h.id_curso = c.id
        JOIN aulas au ON h.id_aula = au.id
        WHERE h.id_profesor = :id_profesor
        AND h.id_semestre = :id_semestre
        ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'), h.hora_inicio ASC
    ");
    $stmt_assigned_schedules->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
    $stmt_assigned_schedules->bindParam(':id_semestre', $current_semester['id'], PDO::PARAM_INT);
    $stmt_assigned_schedules->execute();
    $assigned_schedules = $stmt_assigned_schedules->fetchAll(PDO::FETCH_ASSOC);
}

?>

<h1 class="mt-4 text-center text-primary mb-4"><i class="fas fa-chalkboard-teacher me-2"></i> Panel del Profesor</h1>
<p class="lead text-center text-muted">Bienvenido a tu panel de gestión académica.</p>

<hr>

<div class="row mb-5 justify-content-center">
    <div class="col-md-4 col-sm-6 mb-3">
        <div class="card border-primary shadow-lg h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                <div class="icon-circle bg-primary text-white mb-3">
                    <i class="fas fa-book fa-2x"></i>
                </div>
                <h5 class="card-title text-primary">Asignaturas Asignadas</h5>
                <p class="card-text fs-1 fw-bold text-primary"><?php echo count($assigned_schedules); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6 mb-3">
        <div class="card border-info shadow-lg h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                <div class="icon-circle bg-info text-white mb-3">
                    <i class="fas fa-file-alt fa-2x"></i>
                </div>
                <h5 class="card-title text-info">Estado del CV</h5>
                <p class="card-text fs-1 fw-bold text-info">
                    <?php echo $profesor_cv ? '<i class="fas fa-check-circle text-success"></i> Subido' : '<i class="fas fa-times-circle text-danger"></i> Pendiente'; ?>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6 mb-3">
        <div class="card border-success shadow-lg h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                <div class="icon-circle bg-success text-white mb-3">
                    <i class="fas fa-lightbulb fa-2x"></i>
                </div>
                <h5 class="card-title text-success">Asignaturas Sugeridas</h5>
                <p class="card-text fs-1 fw-bold text-success"><?php echo count($suggested_subjects); ?></p>
            </div>
        </div>
    </div>
</div>

<hr>

<ul class="nav nav-tabs mb-4" id="professorTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="cv-tab" data-bs-toggle="tab" data-bs-target="#cvSection" type="button" role="tab" aria-controls="cvSection" aria-selected="true">
            <i class="fas fa-upload me-2"></i> Subir CV
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjectsSection" type="button" role="tab" aria-controls="subjectsSection" aria-selected="false">
            <i class="fas fa-book-reader me-2"></i> Sugerir Asignaturas
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="assigned-subjects-tab" data-bs-toggle="tab" data-bs-target="#assignedSubjectsSection" type="button" role="tab" aria-controls="assignedSubjectsSection" aria-selected="false">
            <i class="fas fa-chalkboard me-2"></i> Mis Asignaturas Asignadas
        </button>
    </li>
</ul>

<div class="tab-content" id="professorTabsContent">
    <div class="tab-pane fade show active" id="cvSection" role="tabpanel" aria-labelledby="cv-tab">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Gestión de Currículum Vitae (CV)</h5>
            </div>
            <div class="card-body">
                <?php if ($profesor_cv): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <div>Tu CV actual: <a href="<?php echo htmlspecialchars($profesor_cv['ruta_archivo']); ?>" target="_blank" class="alert-link"><?php echo htmlspecialchars($profesor_cv['nombre_archivo']); ?></a></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>Aún no has subido tu CV. Por favor, súbelo para completar tu perfil.</div>
                    </div>
                <?php endif; ?>

                <form action="index.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_cv">
                    <div class="mb-3">
                        <label for="profesor_cv" class="form-label">Seleccionar archivo CV (PDF, DOC, DOCX)</label>
                        <input class="form-control" type="file" id="profesor_cv" name="profesor_cv" accept=".pdf,.doc,.docx" required>
                        <div class="form-text">Tamaño máximo de archivo: 5MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i> Subir CV</button>
                </form>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="subjectsSection" role="tabpanel" aria-labelledby="subjects-tab">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Asignaturas Disponibles para Sugerir</h5>
            </div>
            <div class="card-body">
                <?php if (count($available_subjects) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="availableSubjectsTable">
                            <thead>
                                <tr>
                                    <th>Asignatura</th>
                                    <th class="text-center">Créditos</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($available_subjects as $subject): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['nombre_asignatura']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($subject['creditos']); ?></td>
                                        <td class="text-center">
                                            <form action="index.php" method="POST" class="d-inline-block">
                                                <input type="hidden" name="action" value="suggest_subject">
                                                <input type="hidden" name="id_asignatura" value="<?php echo htmlspecialchars($subject['id']); ?>">
                                                <button type="submit" class="btn btn-info btn-sm" title="Sugerir Asignatura"
                                                        onclick="return confirm('¿Estás seguro de que quieres sugerir impartir la asignatura: <?php echo htmlspecialchars($subject['nombre_asignatura']); ?>?');">
                                                    <i class="fas fa-plus-circle"></i> Sugerir
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">No hay asignaturas disponibles para sugerir en este momento.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Mis Asignaturas Sugeridas</h5>
            </div>
            <div class="card-body">
                <?php if (count($suggested_subjects) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Asignatura</th>
                                    <th class="text-center">Créditos</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suggested_subjects as $suggestion): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($suggestion['nombre_asignatura']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($suggestion['creditos']); ?></td>
                                        <td class="text-center">
                                            <form action="index.php" method="POST" class="d-inline-block">
                                                <input type="hidden" name="action" value="remove_suggestion">
                                                <input type="hidden" name="id_sugerencia" value="<?php echo htmlspecialchars($suggestion['id']); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar Sugerencia"
                                                        onclick="return confirm('¿Estás seguro de que quieres eliminar la sugerencia para: <?php echo htmlspecialchars($suggestion['nombre_asignatura']); ?>?');">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">Aún no has sugerido ninguna asignatura.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="assignedSubjectsSection" role="tabpanel" aria-labelledby="assigned-subjects-tab">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Mis Asignaturas Asignadas para el Semestre Actual</h5>
            </div>
            <div class="card-body">
                <?php if (!$current_semester): ?>
                    <div class="alert alert-info">No hay un semestre académico activo para mostrar asignaturas asignadas.</div>
                <?php elseif (empty($assigned_schedules)): ?>
                    <div class="alert alert-info">No tienes asignaturas asignadas para el semestre actual.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="assignedSubjectsListTable">
                            <thead>
                                <tr>
                                    <th>Asignatura</th>
                                    <th>Curso</th>
                                    <th>Día</th>
                                    <th>Hora</th>
                                    <th>Aula</th>
                                    <th>Turno</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_schedules as $schedule): ?>
                                    <tr data-id_horario="<?php echo htmlspecialchars($schedule['id_horario']); ?>"
                                        data-id_asignatura="<?php echo htmlspecialchars($schedule['id_asignatura']); ?>"
                                        data-id_curso="<?php echo htmlspecialchars($schedule['id_curso']); ?>"
                                        data-turno="<?php echo htmlspecialchars($schedule['turno']); ?>"
                                        data-nombre_asignatura="<?php echo htmlspecialchars($schedule['nombre_asignatura']); ?>"
                                        data-nombre_curso="<?php echo htmlspecialchars($schedule['nombre_curso']); ?>">
                                        <td><?php echo htmlspecialchars($schedule['nombre_asignatura']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['nombre_curso']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['dia_semana']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($schedule['hora_inicio'], 0, 5) . ' - ' . substr($schedule['hora_fin'], 0, 5)); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['nombre_aula']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['turno']); ?></td>
                                        <td class="text-center text-nowrap">
                                            <button type="button" class="btn btn-primary btn-sm view-students-btn me-1"
                                                    data-bs-toggle="modal" data-bs-target="#studentListModal"
                                                    title="Ver Lista de Estudiantes">
                                                <i class="fas fa-users"></i> Lista Estudiantes
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm manage-grades-btn me-1"
                                                    data-bs-toggle="modal" data-bs-target="#gradeManagementModal"
                                                    title="Gestionar Notas">
                                                <i class="fas fa-edit"></i> Gestionar Notas
                                            </button>
                                            <a href="#" class="btn btn-danger btn-sm download-student-list-pdf-btn"
                                               data-id_horario="<?php echo htmlspecialchars($schedule['id_horario']); ?>"
                                               title="Descargar Lista de Estudiantes (PDF)" target="_blank">
                                                <i class="fas fa-file-pdf"></i> Lista PDF
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="studentListModal" tabindex="-1" aria-labelledby="studentListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="studentListModalLabel">Lista de Estudiantes para: <span id="modalSubjectCourseTurn"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="studentListContent">
                    <p class="text-center text-muted" id="loadingStudentList">Cargando lista de estudiantes...</p>
                </div>
                <div class="alert alert-info mt-3" id="noStudentsMessage" style="display: none;">
                    No hay estudiantes inscritos y confirmados para esta asignatura en el semestre actual.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="gradeManagementModal" tabindex="-1" aria-labelledby="gradeManagementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="gradeManagementModalLabel">Gestionar Notas para: <span id="modalGradeSubjectCourseTurn"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="gradeSubmissionForm" method="POST" action="submit_grades.php">
                    <input type="hidden" name="id_horario" id="gradeFormIdHorario">
                    <input type="hidden" name="id_semestre" value="<?php echo htmlspecialchars($current_semester['id'] ?? ''); ?>">
                    <div id="gradesInputList">
                        <p class="text-center text-muted" id="loadingGrades">Cargando notas y estudiantes...</p>
                    </div>
                    <div class="alert alert-info mt-3" id="noGradesStudentsMessage" style="display: none;">
                        No hay estudiantes para gestionar notas en esta asignatura.
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
                <button type="submit" form="gradeSubmissionForm" class="btn btn-success" id="saveGradesBtn" style="display: none;">
                    <i class="fas fa-save me-2"></i> Guardar Notas
                </button>
                <a href="#" id="downloadGradeReportPdfBtn" class="btn btn-danger" target="_blank" style="display: none;">
                    <i class="fas fa-file-pdf me-2"></i> Descargar Acta PDF
                </a>
            </div>
        </div>
    </div>
</div>


<?php include_once '../includes/footer.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;"></div>

<script>
    const flashMessages = <?php echo json_encode($flash_messages); ?>;

    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now();

        let bgColor = '';
        let icon = '';
        switch (type) {
            case 'success': bgColor = 'bg-success'; icon = '<i class="fas fa-check-circle me-2"></i>'; break;
            case 'danger': bgColor = 'bg-danger'; icon = '<i class="fas fa-exclamation-triangle me-2"></i>'; break;
            case 'warning': bgColor = 'bg-warning text-dark'; icon = '<i class="fas fa-exclamation-circle me-2"></i>'; break;
            case 'info': bgColor = 'bg-info'; icon = '<i class="fas fa-info-circle me-2"></i>'; break;
            default: bgColor = 'bg-secondary'; icon = '<i class="fas fa-bell me-2"></i>'; break;
        }

        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        ${icon} ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });

        // --- Lógica para el Modal de Lista de Estudiantes ---
        document.querySelectorAll('.view-students-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const id_horario = row.dataset.id_horario;
                const nombre_asignatura = row.dataset.nombre_asignatura;
                const nombre_curso = row.dataset.nombre_curso;
                const turno = row.dataset.turno;

                document.getElementById('modalSubjectCourseTurn').innerText = `${nombre_asignatura} (${nombre_curso} - ${turno})`;
                document.getElementById('loadingStudentList').style.display = 'block';
                document.getElementById('studentListContent').innerHTML = '';
                document.getElementById('noStudentsMessage').style.display = 'none';

                fetch(`../api/profesores_estudiantes.php?id_horario=${id_horario}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('loadingStudentList').style.display = 'none';
                        if (data.success && data.students.length > 0) {
                            let htmlContent = `<div class="table-responsive"><table class="table table-sm table-bordered table-hover"><thead><tr>
                                                <th>Cód. Registro</th><th>Nombre Estudiante</th><th>Email</th><th>Estado Inscripción</th>
                                                </tr></thead><tbody>`;
                            data.students.forEach(student => {
                                const statusBadge = student.confirmada == 1 ? '<span class="badge bg-success">Confirmada</span>' : '<span class="badge bg-warning text-dark">Pendiente</span>';
                                htmlContent += `
                                    <tr>
                                        <td>${student.codigo_registro}</td>
                                        <td>${student.nombre_completo}</td>
                                        <td>${student.email || 'N/A'}</td>
                                        <td>${statusBadge}</td>
                                    </tr>
                                `;
                            });
                            htmlContent += `</tbody></table></div>`;
                            document.getElementById('studentListContent').innerHTML = htmlContent;
                        } else {
                            document.getElementById('noStudentsMessage').style.display = 'block';
                        }
                    })
                    .catch(error => {
                        document.getElementById('loadingStudentList').style.display = 'none';
                        document.getElementById('studentListContent').innerHTML = `<div class="alert alert-danger">Error al cargar la lista de estudiantes: ${error.message}</div>`;
                    });
            });
        });

        // --- Lógica para el Modal de Gestión de Notas ---
        document.querySelectorAll('.manage-grades-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const id_horario = row.dataset.id_horario;
                const id_asignatura = row.dataset.id_asignatura; // No usada directamente aquí, pero útil para contexto
                const id_curso = row.dataset.id_curso; // No usada directamente aquí, pero útil para contexto
                const turno = row.dataset.turno;
                const nombre_asignatura = row.dataset.nombre_asignatura;
                const nombre_curso = row.dataset.nombre_curso;

                document.getElementById('modalGradeSubjectCourseTurn').innerText = `${nombre_asignatura} (${nombre_curso} - ${turno})`;
                document.getElementById('gradeFormIdHorario').value = id_horario;
                document.getElementById('loadingGrades').style.display = 'block';
                document.getElementById('gradesInputList').innerHTML = '';
                document.getElementById('noGradesStudentsMessage').style.display = 'none';
                document.getElementById('saveGradesBtn').style.display = 'none';
                document.getElementById('downloadGradeReportPdfBtn').style.display = 'none'; // Ocultar hasta que se carguen los datos

                // Set PDF download link for grade report
                document.getElementById('downloadGradeReportPdfBtn').href = `generate_grade_report_pdf.php?id_horario=${id_horario}`;


                // Cargar estudiantes y sus notas actuales via AJAX
                fetch(`../api/listas_estudiantes.php?id_horario=${id_horario}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('loadingGrades').style.display = 'none';
                        if (data.success && data.students.length > 0) {
                            let htmlContent = `<div class="table-responsive"><table class="table table-sm table-bordered table-hover"><thead><tr>
                                                <th>Cód. Registro</th><th>Estudiante</th><th>Nota Actual</th><th>Estado</th>
                                                </tr></thead><tbody>`;
                            data.students.forEach(student => {
                                const currentGrade = student.nota || '';
                                const currentState = student.estado || 'PENDIENTE';
                                const isActaConfirmed = student.acta_final_confirmada == 1;

                                htmlContent += `
                                    <tr>
                                        <td>${student.codigo_registro}</td>
                                        <td>${student.nombre_completo}</td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm"
                                                name="grades[${student.id_inscripcion}][nota]" value="${currentGrade}"
                                                ${isActaConfirmed ? 'disabled' : ''}>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="grades[${student.id_inscripcion}][estado]" ${isActaConfirmed ? 'disabled' : ''}>
                                                <option value="PENDIENTE" ${currentState === 'PENDIENTE' ? 'selected' : ''}>PENDIENTE</option>
                                                <option value="APROBADO" ${currentState === 'APROBADO' ? 'selected' : ''}>APROBADO</option>
                                                <option value="REPROBADO" ${currentState === 'REPROBADO' ? 'selected' : ''}>REPROBADO</option>
                                            </select>
                                            ${isActaConfirmed ? '<small class="text-muted d-block mt-1"><i class="fas fa-lock me-1"></i>Acta Final Confirmada</small>' : ''}
                                        </td>
                                    </tr>
                                `;
                            });
                            htmlContent += `</tbody></table></div>`;
                            document.getElementById('gradesInputList').innerHTML = htmlContent;
                            // Mostrar botón de guardar solo si hay estudiantes y el acta no está confirmada para al menos uno
                            const anyNotConfirmed = data.students.some(s => s.acta_final_confirmada == 0);
                            if (anyNotConfirmed) {
                                document.getElementById('saveGradesBtn').style.display = 'inline-block';
                            } else {
                                document.getElementById('saveGradesBtn').style.display = 'none';
                            }
                            document.getElementById('downloadGradeReportPdfBtn').style.display = 'inline-block'; // Siempre mostrar PDF si hay estudiantes
                        } else {
                            document.getElementById('noGradesStudentsMessage').style.display = 'block';
                            document.getElementById('saveGradesBtn').style.display = 'none';
                            document.getElementById('downloadGradeReportPdfBtn').style.display = 'none';
                        }
                    })
                    .catch(error => {
                        document.getElementById('loadingGrades').style.display = 'none';
                        document.getElementById('gradesInputList').innerHTML = `<div class="alert alert-danger">Error al cargar las notas: ${error.message}</div>`;
                        document.getElementById('saveGradesBtn').style.display = 'none';
                        document.getElementById('downloadGradeReportPdfBtn').style.display = 'none';
                    });
            });
        });

        // --- Lógica para Descargar Lista de Estudiantes (PDF) ---
        document.querySelectorAll('.download-student-list-pdf-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const id_horario = this.dataset.id_horario;
                this.href = `generate_student_list_pdf.php?id_horario=${id_horario}`;
            });
        });
    });
</script>

<style>
    /* Estilo para los círculos de iconos en las tarjetas de resumen */
    .icon-circle {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px; /* Centra el círculo */
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .card-body.text-center {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
    }
    .card.h-100 {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .card.h-100:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.2) !important;
    }

    /* Degradado para el encabezado de la tabla */
    .bg-gradient-secondary {
        background: linear-gradient(45deg, #6c757d, #495057); /* Gris oscuro a medio */
    }

    /* Mejorar el padding de las celdas para mayor espacio */
    .table th, .table td {
        padding: 0.75rem; /* Aumenta el padding para mayor aire */
    }

    /* Ajustar tamaño de fuente para el promedio/créditos en las tarjetas */
    .card-text.fs-1 {
        font-size: 3rem !important; /* Más grande */
    }
    .card-title {
        font-weight: 600;
    }
</style>