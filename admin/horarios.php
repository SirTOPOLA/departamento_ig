<?php
require_once '../includes/functions.php'; 
check_login_and_role('Administrador');

require_once '../config/database.php';

// --- Lógica para añadir/editar/eliminar horarios (PROCESAMIENTO POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $id_horario = filter_var($_POST['id_horario'] ?? null, FILTER_VALIDATE_INT);
    $id_semestre = filter_var($_POST['id_semestre'] ?? null, FILTER_VALIDATE_INT);
    $id_asignatura = filter_var($_POST['id_asignatura'] ?? null, FILTER_VALIDATE_INT);
    $id_curso = filter_var($_POST['id_curso'] ?? null, FILTER_VALIDATE_INT);
    // CAMBIO CLAVE: id_profesor ahora se refiere al ID de la tabla 'profesores'
    $id_profesor = filter_var($_POST['id_profesor'] ?? null, FILTER_VALIDATE_INT); 
    $id_aula = filter_var($_POST['id_aula'] ?? null, FILTER_VALIDATE_INT);
    $dia_semana = sanitize_input($_POST['dia_semana'] ?? '');
    $hora_inicio = sanitize_input($_POST['hora_inicio'] ?? '');
    $hora_fin = sanitize_input($_POST['hora_fin'] ?? '');
    $turno = sanitize_input($_POST['turno'] ?? '');

    // Validaciones básicas
    if (empty($id_semestre) || empty($id_asignatura) || empty($id_curso) || empty($id_profesor) || empty($id_aula) || empty($dia_semana) || empty($hora_inicio) || empty($hora_fin) || empty($turno)) {
        set_flash_message('danger', 'Error: Todos los campos son obligatorios.');
    } elseif (strtotime($hora_inicio) >= strtotime($hora_fin)) {
        set_flash_message('danger', 'Error: La hora de inicio debe ser anterior a la hora de fin.');
    } else {
        try {
            // --- Lógica de Detección de Conflictos ---
            $conflict_found = false;
            $conflict_message = '';

            // 1. Conflicto de Profesor: El profesor no puede estar en dos lugares al mismo tiempo.
            $stmt_profesor_conflict = $pdo->prepare("
                SELECT COUNT(*) FROM horarios
                WHERE id_profesor = :id_profesor
                AND id_semestre = :id_semestre
                AND dia_semana = :dia_semana
                AND (
                    (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                )
                " . ($action === 'edit' ? " AND id != :id_horario" : "")
            );
            $stmt_profesor_conflict->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
            $stmt_profesor_conflict->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
            $stmt_profesor_conflict->bindParam(':dia_semana', $dia_semana);
            $stmt_profesor_conflict->bindParam(':hora_inicio', $hora_inicio);
            $stmt_profesor_conflict->bindParam(':hora_fin', $hora_fin);
            if ($action === 'edit') {
                $stmt_profesor_conflict->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
            }
            $stmt_profesor_conflict->execute();
            if ($stmt_profesor_conflict->fetchColumn() > 0) {
                $conflict_found = true;
                $conflict_message = 'Conflicto: El profesor ya tiene otra clase programada en ese horario para este semestre.';
            }

            // 2. Conflicto de Aula: El aula no puede usarse para dos clases al mismo tiempo.
            if (!$conflict_found) {
                $stmt_aula_conflict = $pdo->prepare("
                    SELECT COUNT(*) FROM horarios
                    WHERE id_aula = :id_aula
                    AND id_semestre = :id_semestre
                    AND dia_semana = :dia_semana
                    AND (
                        (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                    )
                    " . ($action === 'edit' ? " AND id != :id_horario" : "")
                );
                $stmt_aula_conflict->bindParam(':id_aula', $id_aula, PDO::PARAM_INT);
                $stmt_aula_conflict->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                $stmt_aula_conflict->bindParam(':dia_semana', $dia_semana);
                $stmt_aula_conflict->bindParam(':hora_inicio', $hora_inicio);
                $stmt_aula_conflict->bindParam(':hora_fin', $hora_fin);
                if ($action === 'edit') {
                    $stmt_aula_conflict->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
                }
                $stmt_aula_conflict->execute();
                if ($stmt_aula_conflict->fetchColumn() > 0) {
                    $conflict_found = true;
                    $conflict_message = 'Conflicto: El aula ya está ocupada en ese horario para este semestre.';
                }
            }

            // 3. Conflicto de Asignatura/Curso/Semestre: Evitar duplicados exactos para la misma clase.
            // Esto es más un control de duplicidad que un conflicto de recursos
            if (!$conflict_found) {
                $stmt_duplicate_horarios = $pdo->prepare("
                    SELECT COUNT(*) FROM horarios
                    WHERE id_semestre = :id_semestre
                    AND id_asignatura = :id_asignatura
                    AND id_curso = :id_curso
                    AND dia_semana = :dia_semana
                    AND hora_inicio = :hora_inicio
                    AND hora_fin = :hora_fin
                    AND turno = :turno
                    " . ($action === 'edit' ? " AND id != :id_horario" : "")
                );
                $stmt_duplicate_horarios->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                $stmt_duplicate_horarios->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                $stmt_duplicate_horarios->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                $stmt_duplicate_horarios->bindParam(':dia_semana', $dia_semana);
                $stmt_duplicate_horarios->bindParam(':hora_inicio', $hora_inicio);
                $stmt_duplicate_horarios->bindParam(':hora_fin', $hora_fin);
                $stmt_duplicate_horarios->bindParam(':turno', $turno);
                if ($action === 'edit') {
                    $stmt_duplicate_horarios->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
                }
                $stmt_duplicate_horarios->execute();
                if ($stmt_duplicate_horarios->fetchColumn() > 0) {
                    $conflict_found = true;
                    $conflict_message = 'Conflicto: Ya existe un horario idéntico programado para esta asignatura, curso y semestre.';
                }
            }

            if ($conflict_found) {
                set_flash_message('danger', $conflict_message);
            } else {
                // Si no hay conflictos, proceder con la operación CRUD
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO horarios (id_semestre, id_asignatura, id_curso, id_profesor, id_aula, dia_semana, hora_inicio, hora_fin, turno) VALUES (:id_semestre, :id_asignatura, :id_curso, :id_profesor, :id_aula, :dia_semana, :hora_inicio, :hora_fin, :turno)");
                    $stmt->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                    $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                    $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                    $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                    $stmt->bindParam(':id_aula', $id_aula, PDO::PARAM_INT);
                    $stmt->bindParam(':dia_semana', $dia_semana);
                    $stmt->bindParam(':hora_inicio', $hora_inicio);
                    $stmt->bindParam(':hora_fin', $hora_fin);
                    $stmt->bindParam(':turno', $turno);
                    
                    if ($stmt->execute()) { // Verifica si la ejecución fue exitosa
                        set_flash_message('success', 'Horario añadido correctamente.');
                    } else {
                        set_flash_message('danger', 'Error al añadir el horario.');
                    }
                } elseif ($action === 'edit') {
                    if ($id_horario === null) {
                        set_flash_message('danger', 'Error: ID de horario no válido para edición.');
                    } else {
                        $stmt = $pdo->prepare("UPDATE horarios SET id_semestre = :id_semestre, id_asignatura = :id_asignatura, id_curso = :id_curso, id_profesor = :id_profesor, id_aula = :id_aula, dia_semana = :dia_semana, hora_inicio = :hora_inicio, hora_fin = :hora_fin, turno = :turno WHERE id = :id_horario");
                        $stmt->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                        $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                        $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                        $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                        $stmt->bindParam(':id_aula', $id_aula, PDO::PARAM_INT);
                        $stmt->bindParam(':dia_semana', $dia_semana);
                        $stmt->bindParam(':hora_inicio', $hora_inicio);
                        $stmt->bindParam(':hora_fin', $hora_fin);
                        $stmt->bindParam(':turno', $turno);
                        $stmt->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
                        
                        if ($stmt->execute()) { // Verifica si la ejecución fue exitosa
                            set_flash_message('success', 'Horario actualizado correctamente.');
                        } else {
                            set_flash_message('danger', 'Error al actualizar el horario.');
                        }
                    }
                } elseif ($action === 'delete') {
                    if ($id_horario === null) {
                        set_flash_message('danger', 'Error: ID de horario no válido para eliminación.');
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM horarios WHERE id = :id_horario");
                        $stmt->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
                        
                        if ($stmt->execute()) { // Verifica si la ejecución fue exitosa
                            set_flash_message('success', 'Horario eliminado correctamente.');
                        } else {
                            set_flash_message('danger', 'Error al eliminar el horario.');
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
        }
    }
    // REDIRECCIÓN POST-GET: CRUCIAL para que los mensajes flash se muestren
    header('Location: horarios.php'); // Redirige a la misma página (GET request)
    exit; // Termina la ejecución del script
}

// --- El resto del script que genera la página HTML (para la solicitud GET) ---
$page_title = "Gestión de Horarios";
include_once '../includes/header.php'; // Incluye el header DESPUÉS del procesamiento POST

// Obtener mensajes flash para JavaScript (ahora sí se recuperarán de la sesión)
$flash_messages = get_flash_messages();

// --- Obtener datos para los selects del formulario y la tabla ---

// Horarios para la tabla principal
$stmt_horarios = $pdo->query("
    SELECT 
        h.id, h.dia_semana, h.hora_inicio, h.hora_fin, h.turno,
        s.numero_semestre, aa.nombre_anio,
        a.nombre_asignatura, c.nombre_curso,
        p.nombre_completo AS nombre_profesor, -- Mostramos el nombre del usuario, pero el ID es de la tabla profesores
        au.nombre_aula, au.ubicacion,
        h.id_semestre, h.id_asignatura, h.id_curso, h.id_profesor, h.id_aula
    FROM horarios h
    JOIN semestres s ON h.id_semestre = s.id
    JOIN anios_academicos aa ON s.id_anio_academico = aa.id
    JOIN asignaturas a ON h.id_asignatura = a.id
    JOIN cursos c ON h.id_curso = c.id
    JOIN profesores prof ON h.id_profesor = prof.id -- CAMBIO CLAVE: Unimos con la tabla 'profesores'
    JOIN usuarios p ON prof.id_usuario = p.id -- CAMBIO CLAVE: Luego unimos 'profesores' con 'usuarios' para obtener el nombre
    JOIN aulas au ON h.id_aula = au.id
    ORDER BY h.dia_semana, h.hora_inicio ASC
");
$horarios = $stmt_horarios->fetchAll(PDO::FETCH_ASSOC);

// Datos para los selects del modal
$semestres_list = $pdo->query("SELECT s.id, s.numero_semestre, aa.nombre_anio FROM semestres s JOIN anios_academicos aa ON s.id_anio_academico = aa.id ORDER BY aa.nombre_anio DESC, s.numero_semestre ASC")->fetchAll(PDO::FETCH_ASSOC);
$asignaturas_list = $pdo->query("SELECT id, nombre_asignatura, id_curso, semestre_recomendado FROM asignaturas ORDER BY nombre_asignatura ASC")->fetchAll(PDO::FETCH_ASSOC);
$cursos_list = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC ")->fetchAll(PDO::FETCH_ASSOC);
// CAMBIO CLAVE: Obtener profesores de la tabla 'profesores', uniendo con 'usuarios' para el nombre completo
$stmt_profesores = $pdo->prepare("SELECT prof.id, u.nombre_completo FROM profesores prof JOIN usuarios u ON prof.id_usuario = u.id ORDER BY u.nombre_completo ASC");
$stmt_profesores->execute();
$profesores_list = $stmt_profesores->fetchAll(PDO::FETCH_ASSOC);
$aulas_list = $pdo->query("SELECT id, nombre_aula, capacidad FROM aulas ORDER BY nombre_aula ASC")->fetchAll(PDO::FETCH_ASSOC);

// Días de la semana para el select
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

// Turnos
$turnos = ['Mañana', 'Tarde', 'Noche']; // Asumo que también puedes tener turno de mañana. Si no, ajústalo.

?>
 
            <h1 class="mt-4">Gestión de Horarios</h1>
            <p class="lead">Programa las clases, asignando asignaturas, profesores, aulas y horarios.</p>

            <div class="d-flex justify-content-between mb-3 align-items-center">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#horariosModal" id="addNewhorariosBtn">
                    <i class="fas fa-plus me-2"></i> Nuevo Horario
                </button>
                <div class="col-md-4">
                    <input type="search" class="form-control" id="searchInput" placeholder="Buscar horario...">
                </div>
            </div>

            <?php if (!empty($flash_messages)): ?>
                <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
                    <?php foreach ($flash_messages as $msg_data): ?>
                        <div class="toast align-items-center text-white bg-<?php echo $msg_data['type']; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <?php echo $msg_data['message']; ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Lista de Horarios Programados</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="horariosTable"> <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Semestre Académico</th>
                                    <th>Asignatura</th>
                                    <th>Curso</th>
                                    <th>Profesor</th>
                                    <th>Aula</th>
                                    <th>Día</th>
                                    <th>Hora Inicio</th>
                                    <th>Hora Fin</th>
                                    <th>Turno</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($horarios) > 0): ?>
                                    <?php foreach ($horarios as $horario): ?>
                                        <tr data-id="<?php echo htmlspecialchars($horario['id']); ?>"
                                            data-id_semestre="<?php echo htmlspecialchars($horario['id_semestre']); ?>"
                                            data-id_asignatura="<?php echo htmlspecialchars($horario['id_asignatura']); ?>"
                                            data-id_curso="<?php echo htmlspecialchars($horario['id_curso']); ?>"
                                            data-id_profesor="<?php echo htmlspecialchars($horario['id_profesor']); ?>"
                                            data-id_aula="<?php echo htmlspecialchars($horario['id_aula']); ?>"
                                            data-dia_semana="<?php echo htmlspecialchars($horario['dia_semana']); ?>"
                                            data-hora_inicio="<?php echo htmlspecialchars($horario['hora_inicio']); ?>"
                                            data-hora_fin="<?php echo htmlspecialchars($horario['hora_fin']); ?>"
                                            data-turno="<?php echo htmlspecialchars($horario['turno']); ?>">
                                            <td><?php echo htmlspecialchars($horario['id']); ?></td>
                                            <td><?php echo htmlspecialchars($horario['numero_semestre'] . ' (' . $horario['nombre_anio'] . ')'); ?></td>
                                            <td><?php echo htmlspecialchars($horario['nombre_asignatura']); ?></td>
                                            <td><?php echo htmlspecialchars($horario['nombre_curso']); ?></td>
                                            <td><?php echo htmlspecialchars($horario['nombre_profesor']); ?></td>
                                            <td><?php echo htmlspecialchars($horario['nombre_aula'] . ' (' . $horario['ubicacion'] . ')'); ?></td>
                                            <td><?php echo htmlspecialchars($horario['dia_semana']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($horario['hora_inicio'], 0, 5)); ?></td>
                                            <td><?php echo htmlspecialchars(substr($horario['hora_fin'], 0, 5)); ?></td>
                                            <td><?php echo htmlspecialchars($horario['turno']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-sm edit-btn me-1" title="Editar"
                                                        data-bs-toggle="modal" data-bs-target="#horariosModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="horarios.php?action=delete&id_horario=<?php echo htmlspecialchars($horario['id']); ?>"
                                                   class="btn btn-danger btn-sm delete-btn" title="Eliminar"
                                                   onclick="return confirm('¿Estás seguro de que quieres eliminar este horario? Esta acción es irreversible.');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center">No hay horarios registrados.</td>
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
     

<div class="modal fade" id="horariosModal" tabindex="-1" aria-labelledby="horariosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="horarios.php" method="POST" id="horariosForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="horariosModalLabel">Añadir Nuevo Horario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_horario" id="modal_id_horario">
                    <input type="hidden" name="action" id="modal_action" value="add"> <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_id_semestre" class="form-label">Semestre Académico <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_id_semestre" name="id_semestre" required>
                                <option value="">Seleccione un semestre...</option>
                                <?php foreach ($semestres_list as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem['id']); ?>">
                                        <?php echo htmlspecialchars($sem['numero_semestre'] . ' (' . $sem['nombre_anio'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_id_curso" class="form-label">Curso <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_id_curso" name="id_curso" required>
                                <option value="">Seleccione un curso...</option>
                                <?php foreach ($cursos_list as $cur): ?>
                                    <option value="<?php echo htmlspecialchars($cur['id']); ?>">
                                        <?php echo htmlspecialchars($cur['nombre_curso']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_id_asignatura" class="form-label">Asignatura <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_id_asignatura" name="id_asignatura" required>
                                <option value="">Seleccione una asignatura...</option>
                                <?php foreach ($asignaturas_list as $asig): ?>
                                    <option value="<?php echo htmlspecialchars($asig['id']); ?>"
                                            data-id-curso="<?php echo htmlspecialchars($asig['id_curso']); ?>"
                                            data-semestre-recomendado="<?php echo htmlspecialchars($asig['semestre_recomendado']); ?>">
                                        <?php echo htmlspecialchars($asig['nombre_asignatura']); ?>
                                        <?php echo (!empty($asig['id_curso']) ? ' (Curso: ' . htmlspecialchars($asig['id_curso']) . ' Sem: ' . htmlspecialchars($asig['semestre_recomendado']) . ')' : ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted" id="asignaturaInfo"></small>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_id_profesor" class="form-label">Profesor <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_id_profesor" name="id_profesor" required>
                                <option value="">Seleccione un profesor...</option>
                                <?php foreach ($profesores_list as $prof): ?>
                                    <option value="<?php echo htmlspecialchars($prof['id']); ?>">
                                        <?php echo htmlspecialchars($prof['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_id_aula" class="form-label">Aula <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_id_aula" name="id_aula" required>
                                <option value="">Seleccione un aula...</option>
                                <?php foreach ($aulas_list as $aula): ?>
                                    <option value="<?php echo htmlspecialchars($aula['id']); ?>">
                                        <?php echo htmlspecialchars($aula['nombre_aula'] . ' (Cap: ' . $aula['capacidad'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_dia_semana" class="form-label">Día de la Semana <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_dia_semana" name="dia_semana" required>
                                <option value="">Seleccione un día...</option>
                                <?php foreach ($dias_semana as $dia): ?>
                                    <option value="<?php echo htmlspecialchars($dia); ?>">
                                        <?php echo htmlspecialchars($dia); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="modal_hora_inicio" class="form-label">Hora Inicio <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="modal_hora_inicio" name="hora_inicio" required>
                        </div>
                        <div class="col-md-4">
                            <label for="modal_hora_fin" class="form-label">Hora Fin <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="modal_hora_fin" name="hora_fin" required>
                        </div>
                        <div class="col-md-4">
                            <label for="modal_turno" class="form-label">Turno <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_turno" name="turno" required>
                                <option value="">Seleccione un turno...</option>
                                <?php foreach ($turnos as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>">
                                        <?php echo htmlspecialchars($t); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="modalSaveBtn"><i class="fas fa-save me-2"></i> Guardar Horario</button>
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
    const horariosModal = new bootstrap.Modal(document.getElementById('horariosModal'));

    // Cache elements for better performance
    const modalIdHorario = document.getElementById('modal_id_horario');
    const modalIdSemestre = document.getElementById('modal_id_semestre');
    const modalIdAsignatura = document.getElementById('modal_id_asignatura');
    const modalIdCurso = document.getElementById('modal_id_curso');
    const modalIdProfesor = document.getElementById('modal_id_profesor');
    const modalIdAula = document.getElementById('modal_id_aula');
    const modalDiaSemana = document.getElementById('modal_dia_semana');
    const modalHoraInicio = document.getElementById('modal_hora_inicio');
    const modalHoraFin = document.getElementById('modal_hora_fin');
    const modalTurno = document.getElementById('modal_turno');
    const horariosModalLabel = document.getElementById('horariosModalLabel');
    const modalSaveBtn = document.getElementById('modalSaveBtn');
    const asignaturaInfo = document.getElementById('asignaturaInfo');
    const modalAction = document.getElementById('modal_action'); // Nuevo elemento para la acción

    // Función para mostrar toasts
    function showToast(message, type) {
        const toastContainer = document.querySelector('.toast-container');
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastEl = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }

    // Mostrar mensajes flash al cargar la página
    flashMessages.forEach(msg => {
        showToast(msg.message, msg.type);
    });

    // --- Funcionalidad para botón "Nuevo Horario" ---
    document.getElementById('addNewhorariosBtn').addEventListener('click', function() {
        document.getElementById('horariosForm').reset();
        modalIdHorario.value = '';
        modalAction.value = 'add'; // Establecer acción a 'add'
        horariosModalLabel.innerText = 'Añadir Nuevo Horario';
        modalSaveBtn.innerText = 'Guardar Horario';
        modalSaveBtn.classList.remove('btn-warning');
        modalSaveBtn.classList.add('btn-primary');
        asignaturaInfo.innerText = ''; // Limpiar info de asignatura
    });

    // --- Funcionalidad para botón "Editar" ---
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            modalIdHorario.value = row.dataset.id;
            modalAction.value = 'edit'; // Establecer acción a 'edit'
            modalIdSemestre.value = row.dataset.id_semestre;
            modalIdAsignatura.value = row.dataset.id_asignatura;
            modalIdCurso.value = row.dataset.id_curso;
            modalIdProfesor.value = row.dataset.id_profesor;
            modalIdAula.value = row.dataset.id_aula;
            modalDiaSemana.value = row.dataset.dia_semana;
            modalHoraInicio.value = row.dataset.hora_inicio.substring(0, 5); // Formato HH:MM
            modalHoraFin.value = row.dataset.hora_fin.substring(0, 5);     // Formato HH:MM
            modalTurno.value = row.dataset.turno;

            // Actualizar info de asignatura al editar
            // Necesitamos encontrar la opción correcta para cargar su dataset
            const asignaturaOptions = Array.from(modalIdAsignatura.options);
            const selectedAsignaturaOption = asignaturaOptions.find(option => option.value === row.dataset.id_asignatura);
            if (selectedAsignaturaOption) {
                const idCursoAsignatura = selectedAsignaturaOption.dataset.idCurso;
                const semestreRecomendadoAsignatura = selectedAsignaturaOption.dataset.semestreRecomendado;
                asignaturaInfo.innerText = `Asignatura del Curso: ${idCursoAsignatura}, Semestre Recomendado: ${semestreRecomendadoAsignatura}`;
            } else {
                asignaturaInfo.innerText = '';
            }

            horariosModalLabel.innerText = 'Editar Horario';
            modalSaveBtn.innerText = 'Actualizar Horario';
            modalSaveBtn.classList.remove('btn-primary');
            modalSaveBtn.classList.add('btn-warning');
        });
    });

    // --- Actualizar información de asignatura al cambiar la selección ---
    modalIdAsignatura.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const idCursoAsignatura = selectedOption.dataset.idCurso;
            const semestreRecomendadoAsignatura = selectedOption.dataset.semestreRecomendado;
            asignaturaInfo.innerText = `Asignatura del Curso: ${idCursoAsignatura}, Semestre Recomendado: ${semestreRecomendadoAsignatura}`;
        } else {
            asignaturaInfo.innerText = '';
        }
    });

    // --- Búsqueda dinámica ---
    document.getElementById('searchInput').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("horariosTable");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) { // Empieza en 1 para saltar el encabezado
            tr[i].style.display = "none"; // Oculta la fila por defecto
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length - 1; j++) { // Itera sobre todas las celdas excepto la última (acciones)
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = ""; // Muestra la fila si encuentra coincidencia
                        break; // Sale del bucle de celdas para esta fila
                    }
                }
            }
        }
        updatePagination(); // Actualiza la paginación después de la búsqueda
    });

    // --- Paginación y Mostrar Entradas ---
    const rowsPerPage = 10;
    const tableBody = document.querySelector('#horariosTable tbody');
    const paginationElement = document.getElementById('pagination');

    function displayRows(page) {
        const rows = Array.from(tableBody.querySelectorAll('tr:not([style*="display: none"])')); // Solo filas visibles
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        rows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function setupPagination(totalRows) {
        paginationElement.innerHTML = ''; // Limpia paginación existente
        const pageCount = Math.ceil(totalRows / rowsPerPage);

        for (let i = 1; i <= pageCount; i++) {
            const li = document.createElement('li');
            li.classList.add('page-item');
            const link = document.createElement('a');
            link.classList.add('page-link');
            link.href = '#';
            link.textContent = i;
            link.addEventListener('click', function(e) {
                e.preventDefault();
                displayRows(i);
                document.querySelectorAll('.page-item').forEach(item => item.classList.remove('active'));
                li.classList.add('active');
            });
            li.appendChild(link);
            paginationElement.appendChild(li);
        }

        if (pageCount > 0) {
            paginationElement.firstElementChild.classList.add('active');
            displayRows(1);
        }
    }

    function updatePagination() {
        const visibleRows = Array.from(tableBody.querySelectorAll('tr:not([style*="display: none"])')).filter(row => row.style.display !== 'none');
        setupPagination(visibleRows.length);
    }

    // Inicializar paginación al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        updatePagination();
    });

    // Asegurarse de que el script del footer se incluya después de esta parte para evitar errores de referencia
</script>