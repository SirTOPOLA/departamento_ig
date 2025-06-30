<?php

require_once '../includes/functions.php';
check_login_and_role('Administrador'); // Solo administradores pueden gestionar inscripciones

require_once '../config/database.php'; // Conexión PDO

 

// --- Lógica de Procesamiento POST para Confirmar/Rechazar Inscripciones ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $current_semester = get_current_semester($pdo);
    if (!$current_semester) {
        set_flash_message('danger', 'Error: No hay un semestre académico activo definido para gestionar inscripciones.');
        header('Location: estudiantes.php');
        exit;
    }
    $id_semestre_actual = $current_semester['id'];

    try {
        $pdo->beginTransaction(); // Iniciar transacción para operaciones atómicas

        if ($action === 'confirm_single_enrollment') {
            $id_inscripcion = filter_var($_POST['id_inscripcion'] ?? null, FILTER_VALIDATE_INT);
            if ($id_inscripcion === null) {
                set_flash_message('danger', 'Error: ID de inscripción no válido para confirmar.');
            } else {
                $stmt = $pdo->prepare("UPDATE inscripciones_estudiantes SET confirmada = 1 WHERE id = :id_inscripcion AND id_semestre = :id_semestre");
                $stmt->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
                $stmt->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    set_flash_message('success', 'Asignatura confirmada correctamente para el estudiante.');
                } else {
                    set_flash_message('warning', 'La asignatura ya estaba confirmada o no existe para el semestre actual.');
                }
            }
        } elseif ($action === 'reject_single_enrollment') {
            $id_inscripcion = filter_var($_POST['id_inscripcion'] ?? null, FILTER_VALIDATE_INT);
            if ($id_inscripcion === null) {
                set_flash_message('danger', 'Error: ID de inscripción no válido para rechazar.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM inscripciones_estudiantes WHERE id = :id_inscripcion AND id_semestre = :id_semestre");
                $stmt->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
                $stmt->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    set_flash_message('info', 'Asignatura rechazada (eliminada) correctamente para el estudiante.');
                } else {
                    set_flash_message('warning', 'La asignatura no pudo ser encontrada o ya ha sido eliminada.');
                }
            }
        } elseif ($action === 'confirm_student_enrollments') {
            $id_estudiante_usuario = filter_var($_POST['id_estudiante'] ?? null, FILTER_VALIDATE_INT);
            if ($id_estudiante_usuario === null) {
                set_flash_message('danger', 'Error: ID de estudiante no válido para confirmar inscripciones.');
            } else {
                $stmt = $pdo->prepare("
                    UPDATE inscripciones_estudiantes ie
                    JOIN estudiantes e ON ie.id_estudiante = e.id
                    SET ie.confirmada = 1
                    WHERE e.id_usuario = :id_estudiante_usuario AND ie.confirmada = 0 AND ie.id_semestre = :id_semestre
                ");
                $stmt->bindParam(':id_estudiante_usuario', $id_estudiante_usuario, PDO::PARAM_INT);
                $stmt->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    set_flash_message('success', 'Todas las inscripciones pendientes para el estudiante han sido confirmadas.');
                } else {
                    set_flash_message('info', 'No hay inscripciones pendientes para este estudiante o ya estaban confirmadas.');
                }
            }
        } elseif ($action === 'confirm_all_enrollments') {
            $stmt = $pdo->prepare("UPDATE inscripciones_estudiantes SET confirmada = 1 WHERE confirmada = 0 AND id_semestre = :id_semestre");
            $stmt->bindParam(':id_semestre', $id_semestre_actual, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                set_flash_message('success', 'Todas las inscripciones pendientes de todos los estudiantes han sido confirmadas.');
            } else {
                set_flash_message('info', 'No hay inscripciones pendientes para confirmar en este momento.');
            }
        }

        $pdo->commit(); // Confirmar la transacción

    } catch (PDOException $e) {
        $pdo->rollBack(); // Revertir la transacción en caso de error
        set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
    }

    header('Location: estudiantes.php');
    exit;
}

// --- El resto del script que genera la página HTML (para la solicitud GET) ---
$page_title = "Gestión de Estudiantes e Inscripciones";
include_once '../includes/header.php';

// Obtener mensajes flash para JavaScript
$flash_messages = get_flash_messages();

// Obtener el semestre académico actual para filtrar inscripciones
$current_semester = get_current_semester($pdo);
$id_semestre_actual = $current_semester['id'] ?? null;
$nombre_semestre_actual = $current_semester ? htmlspecialchars($current_semester['numero_semestre'] . ' (' . $current_semester['nombre_anio'] . ')') : 'N/A';


// --- Obtener estudiantes con inscripciones pendientes (sin duplicados) ---
$students_with_pending_enrollments = [];
if ($id_semestre_actual) {
    $stmt_students_pending = $pdo->prepare("
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
    $stmt_students_pending->bindParam(':id_semestre_actual', $id_semestre_actual, PDO::PARAM_INT);
    $stmt_students_pending->execute();
    $students_with_pending_enrollments = $stmt_students_pending->fetchAll(PDO::FETCH_ASSOC);
}

// --- Obtener TODOS los estudiantes activos ---
$all_active_students = [];
$stmt_all_students = $pdo->prepare("
    SELECT
        u.id AS id_usuario,
        u.nombre_completo AS nombre_estudiante,
        e.codigo_registro,
        u.email,
        u.telefono,
        c.nombre_curso AS curso_actual
    FROM usuarios u
    JOIN estudiantes e ON u.id = e.id_usuario
    LEFT JOIN semestres s ON e.id_curso_inicio = s.id_curso_asociado_al_semestre -- Asumiendo que id_curso_asociado_al_semestre en semestres indica el curso para ese semestre
    LEFT JOIN cursos c ON s.id_curso_asociado_al_semestre = c.id -- O si tienes un campo de curso actual en estudiantes
    WHERE u.id_rol = (SELECT id FROM roles WHERE nombre_rol = 'Estudiante')
    AND u.estado = 'Activo'
    ORDER BY u.nombre_completo ASC
");
// Nota: La lógica para obtener el "curso actual" del estudiante puede necesitar ajuste
// dependiendo de cómo se gestiona la progresión del estudiante en tu DB.
// He asumido una relación con `semestres` y `cursos` o un campo en `estudiantes`.
$stmt_all_students->execute();
$all_active_students = $stmt_all_students->fetchAll(PDO::FETCH_ASSOC);

?>

<h1 class="mt-4">Gestión de Estudiantes e Inscripciones</h1>
 
 
<ul class="nav nav-tabs mb-4" id="studentTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pending-enrollments-tab" data-bs-toggle="tab" data-bs-target="#pendingEnrollments" type="button" role="tab" aria-controls="pendingEnrollments" aria-selected="true">
            <i class="fas fa-clipboard-list me-2"></i> Inscripciones Pendientes
            <?php if (count($students_with_pending_enrollments) > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo count($students_with_pending_enrollments); ?></span>
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
            <div id="pendingEnrollmentButtons" style="display: <?php echo (count($students_with_pending_enrollments) > 0 && $current_semester) ? 'block' : 'none'; ?>;">
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
                <?php if (count($students_with_pending_enrollments) > 0): ?>
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
                                <?php foreach ($students_with_pending_enrollments as $student): ?>
                                    <tr data-id_usuario="<?php echo htmlspecialchars($student['id_usuario']); ?>"
                                        data-nombre_estudiante="<?php echo htmlspecialchars($student['nombre_estudiante']); ?>">
                                        <td><?php echo htmlspecialchars($student['nombre_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars($student['codigo_registro']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['telefono'] ?? 'N/A'); ?></td>
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
                <?php if (count($all_active_students) > 0): ?>
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
                                <?php foreach ($all_active_students as $student): ?>
                                    <tr data-id_usuario="<?php echo htmlspecialchars($student['id_usuario']); ?>"
                                        data-nombre_estudiante="<?php echo htmlspecialchars($student['nombre_estudiante']); ?>">
                                        <td><?php echo htmlspecialchars($student['nombre_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars($student['codigo_registro']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['telefono'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['curso_actual'] ?? 'N/A'); ?></td>
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
    const flashMessages = <?php echo json_encode($flash_messages); ?>;
    const enrollmentDetailModal = new bootstrap.Modal(document.getElementById('enrollmentDetailModal'));
    const academicHistoryModal = new bootstrap.Modal(document.getElementById('academicHistoryModal'));

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


    // --- Funcionalidad para el botón "Confirmar Todas las Inscripciones Pendientes" (Global) ---
    document.getElementById('confirmAllEnrollmentsBtn')?.addEventListener('click', function() {
        if (confirm('¿Estás absolutamente seguro de que quieres CONFIRMAR TODAS las solicitudes de inscripción pendientes de TODOS los estudiantes? Esta acción no se puede deshacer.')) {
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
            const row = this.closest('tr');
            const id_usuario = row.dataset.id_usuario;
            const nombre_estudiante = row.dataset.nombre_estudiante;

            modalStudentName.innerText = nombre_estudiante;
            modalStudentUserId.value = id_usuario;
            confirmAllStudentId.value = id_usuario; // Para el formulario de confirmar todas del estudiante

            enrollmentsList.innerHTML = ''; // Limpiar lista anterior
            loadingEnrollments.style.display = 'block'; // Mostrar mensaje de carga
            noPendingEnrollmentsMessage.style.display = 'none'; // Ocultar mensaje de no pendientes

            // Deshabilitar botón de confirmar todas del estudiante hasta que se carguen las asignaturas
            confirmAllStudentEnrollmentsBtn.style.display = 'none';

            // Cargar inscripciones via AJAX
            fetch(`../api/confirmaciones_pendientes.php?id_usuario=${id_usuario}&id_semestre=<?php echo $id_semestre_actual; ?>`)
                .then(response => response.json())
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
                });
        });
    });


    // --- Funcionalidad para botones "Ver Historial" por estudiante (en pestaña de todos los estudiantes) ---
    document.querySelectorAll('.view-history-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const id_usuario = row.dataset.id_usuario;
            const nombre_estudiante = row.dataset.nombre_estudiante;

            modalHistoryStudentName.innerText = nombre_estudiante;
            modalHistoryStudentUserId.value = id_usuario;
            printHistoryPdfBtn.href = `generate_history_pdf.php?id_usuario=${id_usuario}`; // Set PDF link

            academicHistoryContent.innerHTML = ''; // Limpiar contenido anterior
            loadingHistory.style.display = 'block'; // Mostrar mensaje de carga
            noHistoryMessage.style.display = 'none'; // Ocultar mensaje de no historial
            printHistoryPdfBtn.style.display = 'none'; // Ocultar botón de imprimir por defecto

            // Cargar historial académico via AJAX
            fetch(`../api/historial_academico.php?id_usuario=${id_usuario}`)
                .then(response => response.json())
                .then(data => {
                    loadingHistory.style.display = 'none'; // Ocultar mensaje de carga
                    if (data.success && data.history && Object.keys(data.history).length > 0) {
                        let htmlContent = '';
                        for (const semester in data.history) {
                            htmlContent += `
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Semestre: ${semester}</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-borderless mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Asignatura</th>
                                                        <th class="text-center">Créditos</th>
                                                        <th class="text-center">Nota</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                            `;
                            data.history[semester].forEach(entry => {
                                const statusClass = entry.estado_final === 'APROBADO' ? 'text-success' : 'text-danger';
                                htmlContent += `
                                    <tr>
                                        <td>${entry.nombre_asignatura}</td>
                                        <td class="text-center">${entry.creditos}</td>
                                        <td class="text-center">${entry.nota_final}</td>
                                        <td class="text-center ${statusClass}">${entry.estado_final}</td>
                                    </tr>
                                `;
                            });
                            htmlContent += `
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
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
                });
        });
    });

    // --- Búsqueda dinámica y Paginación (separadas por pestaña) ---

    // Paginación para "Inscripciones Pendientes"
    const rowsPerPagePending = 10;
    let currentPagePending = 1;
    let totalPagesPending = 0;

    function setupPaginationPending() {
        const table = document.getElementById('studentsTablePending');
        const tbodyRows = table.querySelectorAll('tbody tr');
        totalPagesPending = Math.ceil(tbodyRows.length / rowsPerPagePending);

        const paginationUl = document.getElementById('paginationPending');
        paginationUl.innerHTML = '';

        if (tbodyRows.length <= rowsPerPagePending && document.getElementById('searchInputPending').value === "") {
            paginationUl.style.display = 'none';
            tbodyRows.forEach(row => row.style.display = '');
            return;
        } else {
            paginationUl.style.display = 'flex';
        }

        for (let i = 1; i <= totalPagesPending; i++) {
            const li = document.createElement('li');
            li.classList.add('page-item');
            if (i === currentPagePending) {
                li.classList.add('active');
            }
            const a = document.createElement('a');
            a.classList.add('page-link');
            a.href = '#';
            a.innerText = i;
            a.addEventListener('click', function(e) {
                e.preventDefault();
                currentPagePending = i;
                showPagePending(currentPagePending);
            });
            li.appendChild(a);
            paginationUl.appendChild(li);
        }
    }

    function showPagePending(page) {
        const table = document.getElementById('studentsTablePending');
        const tbodyRows = table.querySelectorAll('tbody tr');

        const startIndex = (page - 1) * rowsPerPagePending;
        const endIndex = startIndex + rowsPerPagePending;

        tbodyRows.forEach((row, index) => {
            if (index >= startIndex && index < endIndex) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        document.querySelectorAll('#paginationPending .page-item').forEach(li => {
            li.classList.remove('active');
        });
        const activePageLink = document.querySelector(`#paginationPending .page-item:nth-child(${page})`);
        if (activePageLink) {
            activePageLink.classList.add('active');
        }
    }

    document.getElementById('searchInputPending').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInputPending");
        filter = input.value.toUpperCase();
        table = document.getElementById("studentsTablePending");
        tr = table.getElementsByTagName("tr");

        document.getElementById('paginationPending').style.display = 'none';

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none";
            td = tr[i].getElementsByTagName("td");
            if (td[0] || td[1] || td[2] || td[3]) {
                txtValue0 = td[0].textContent || td[0].innerText;
                txtValue1 = td[1].textContent || td[1].innerText;
                txtValue2 = td[2].textContent || td[2].innerText;
                txtValue3 = td[3].textContent || td[3].innerText;

                if (txtValue0.toUpperCase().indexOf(filter) > -1 ||
                    txtValue1.toUpperCase().indexOf(filter) > -1 ||
                    txtValue2.toUpperCase().indexOf(filter) > -1 ||
                    txtValue3.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                }
            }
        }
        if (filter === "") {
            document.getElementById('paginationPending').style.display = 'flex';
            showPagePending(currentPagePending);
        }
    });

    // Paginación para "Todos los Estudiantes Activos"
    const rowsPerPageAll = 10;
    let currentPageAll = 1;
    let totalPagesAll = 0;

    function setupPaginationAll() {
        const table = document.getElementById('studentsTableAll');
        const tbodyRows = table.querySelectorAll('tbody tr');
        totalPagesAll = Math.ceil(tbodyRows.length / rowsPerPageAll);

        const paginationUl = document.getElementById('paginationAll');
        paginationUl.innerHTML = '';

        if (tbodyRows.length <= rowsPerPageAll && document.getElementById('searchInputAll').value === "") {
            paginationUl.style.display = 'none';
            tbodyRows.forEach(row => row.style.display = '');
            return;
        } else {
            paginationUl.style.display = 'flex';
        }

        for (let i = 1; i <= totalPagesAll; i++) {
            const li = document.createElement('li');
            li.classList.add('page-item');
            if (i === currentPageAll) {
                li.classList.add('active');
            }
            const a = document.createElement('a');
            a.classList.add('page-link');
            a.href = '#';
            a.innerText = i;
            a.addEventListener('click', function(e) {
                e.preventDefault();
                currentPageAll = i;
                showPageAll(currentPageAll);
            });
            li.appendChild(a);
            paginationUl.appendChild(li);
        }
    }

    function showPageAll(page) {
        const table = document.getElementById('studentsTableAll');
        const tbodyRows = table.querySelectorAll('tbody tr');

        const startIndex = (page - 1) * rowsPerPageAll;
        const endIndex = startIndex + rowsPerPageAll;

        tbodyRows.forEach((row, index) => {
            if (index >= startIndex && index < endIndex) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        document.querySelectorAll('#paginationAll .page-item').forEach(li => {
            li.classList.remove('active');
        });
        const activePageLink = document.querySelector(`#paginationAll .page-item:nth-child(${page})`);
        if (activePageLink) {
            activePageLink.classList.add('active');
        }
    }

    document.getElementById('searchInputAll').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInputAll");
        filter = input.value.toUpperCase();
        table = document.getElementById("studentsTableAll");
        tr = table.getElementsByTagName("tr");

        document.getElementById('paginationAll').style.display = 'none';

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none";
            td = tr[i].getElementsByTagName("td");
            // Buscar en las columnas de Estudiante, Cód. Registro, Email, Teléfono, Curso
            if (td[0] || td[1] || td[2] || td[3] || td[4]) {
                txtValue0 = td[0].textContent || td[0].innerText;
                txtValue1 = td[1].textContent || td[1].innerText;
                txtValue2 = td[2].textContent || td[2].innerText;
                txtValue3 = td[3].textContent || td[3].innerText;
                txtValue4 = td[4].textContent || td[4].innerText;

                if (txtValue0.toUpperCase().indexOf(filter) > -1 ||
                    txtValue1.toUpperCase().indexOf(filter) > -1 ||
                    txtValue2.toUpperCase().indexOf(filter) > -1 ||
                    txtValue3.toUpperCase().indexOf(filter) > -1 ||
                    txtValue4.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                }
            }
        }
        if (filter === "") {
            document.getElementById('paginationAll').style.display = 'flex';
            showPageAll(currentPageAll);
        }
    });

    // Función para mostrar un Toast de Bootstrap
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
    }

    // Mostrar todos los mensajes flash al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });

        // Ocultar el botón global de confirmación si no hay pendientes
        if (<?php echo count($students_with_pending_enrollments); ?> === 0 || !<?php echo json_encode($current_semester); ?>) {
            pendingEnrollmentButtonsContainer.style.display = 'none';
        }

        // Inicializar paginación para ambas tablas
        setupPaginationPending();
        showPagePending(currentPagePending);

        setupPaginationAll();
        showPageAll(currentPageAll);
    });
</script>