<?php
 
require_once '../includes/functions.php'; 
check_login_and_role('Estudiante'); // Solo estudiantes pueden acceder

require_once '../config/database.php';

$page_title = "Mi Progreso Académico"; // Nuevo título más descriptivo
include_once '../includes/header.php';

$flash_messages = get_flash_messages();

$current_user_id = $_SESSION['user_id'];

// Obtener el id_estudiante del usuario logueado
$stmt_student_id = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
$stmt_student_id->bindParam(':id_usuario', $current_user_id, PDO::PARAM_INT);
$stmt_student_id->execute();
$id_estudiante_actual = $stmt_student_id->fetchColumn();

if (!$id_estudiante_actual) {
    set_flash_message('danger', 'Error: No se encontró el perfil de estudiante asociado a su usuario.');
    header('Location: ../logout.php'); // Redirigir al logout o a una página de error
    exit;
}

// Obtener el semestre académico actual
$current_semester = get_current_semester($pdo);

$current_subjects_status = []; // Para las del semestre actual (confirmadas y pendientes)
$approved_subjects = []; // Para las aprobadas del historial
$reproved_subjects = []; // Para las reprobadas del historial

// --- 1. Asignaturas del Semestre Actual (Inscritas y Pendientes/Confirmadas) ---
if ($current_semester) {
    $stmt_current_enrollments = $pdo->prepare("
        SELECT
            ie.id_asignatura,
            a.nombre_asignatura,
            a.creditos,
            ie.confirmada,
            h.dia_semana,
            h.hora_inicio,
            h.hora_fin,
            au.nombre_aula,
            u_prof.nombre_completo AS nombre_profesor
        FROM inscripciones_estudiantes ie
        JOIN asignaturas a ON ie.id_asignatura = a.id
        LEFT JOIN horarios h ON h.id_asignatura = a.id AND h.id_semestre = ie.id_semestre
        LEFT JOIN aulas au ON h.id_aula = au.id
        LEFT JOIN usuarios u_prof ON h.id_profesor = u_prof.id
        WHERE ie.id_estudiante = :id_estudiante
        AND ie.id_semestre = :id_semestre_actual
        ORDER BY a.nombre_asignatura ASC, h.dia_semana ASC, h.hora_inicio ASC
    ");
    $stmt_current_enrollments->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_current_enrollments->bindParam(':id_semestre_actual', $current_semester['id'], PDO::PARAM_INT);
    $stmt_current_enrollments->execute();

    $temp_current_subjects = [];
    foreach ($stmt_current_enrollments->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $asignatura_id = $row['id_asignatura'];
        if (!isset($temp_current_subjects[$asignatura_id])) {
            $temp_current_subjects[$asignatura_id] = [
                'nombre_asignatura' => $row['nombre_asignatura'],
                'creditos' => $row['creditos'],
                'confirmada' => $row['confirmada'],
                'horarios' => []
            ];
        }
        if (!empty($row['dia_semana'])) {
            $temp_current_subjects[$asignatura_id]['horarios'][] = [
                'dia_semana' => $row['dia_semana'],
                'hora_inicio' => $row['hora_inicio'],
                'hora_fin' => $row['hora_fin'],
                'nombre_aula' => $row['nombre_aula'],
                'nombre_profesor' => $row['nombre_profesor']
            ];
        }
    }
    $current_subjects_status = array_values($temp_current_subjects); // Reset keys
}


// --- 2. Asignaturas Aprobadas (del historial) ---
$stmt_approved = $pdo->prepare("
    SELECT
        ha.id_asignatura,
        a.nombre_asignatura,
        a.creditos,
        ha.nota_final,
        ha.estado_final,
        s.numero_semestre,
        aa.nombre_anio
    FROM historial_academico ha
    JOIN asignaturas a ON ha.id_asignatura = a.id
    JOIN semestres s ON ha.id_semestre = s.id
    JOIN anios_academicos aa ON s.id_anio_academico = aa.id
    WHERE ha.id_estudiante = :id_estudiante_aprobadas
    AND ha.estado_final = 'APROBADO'
    ORDER BY aa.nombre_anio DESC, s.numero_semestre DESC, a.nombre_asignatura ASC
");
$stmt_approved->bindParam(':id_estudiante_aprobadas', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_approved->execute();
$approved_subjects = $stmt_approved->fetchAll(PDO::FETCH_ASSOC);


// --- 3. Asignaturas Reprobadas (del historial) ---
$stmt_reproved = $pdo->prepare("
    SELECT
        ha.id_asignatura,
        a.nombre_asignatura,
        a.creditos,
        ha.nota_final,
        ha.estado_final,
        s.numero_semestre,
        aa.nombre_anio
    FROM historial_academico ha
    JOIN asignaturas a ON ha.id_asignatura = a.id
    JOIN semestres s ON ha.id_semestre = s.id
    JOIN anios_academicos aa ON s.id_anio_academico = aa.id
    WHERE ha.id_estudiante = :id_estudiante_reprobadas
    AND ha.estado_final = 'REPROBADO'
    ORDER BY aa.nombre_anio DESC, s.numero_semestre DESC, a.nombre_asignatura ASC
");
$stmt_reproved->bindParam(':id_estudiante_reprobadas', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_reproved->execute();
$reproved_subjects = $stmt_reproved->fetchAll(PDO::FETCH_ASSOC);

?>

<h1 class="mt-4">Mi Progreso Académico</h1>
<p class="lead">Consulta un resumen completo de tus asignaturas inscritas y tu historial de calificaciones.</p>

<?php if (!$current_semester): ?>
    <div class="alert alert-info">
        Actualmente no hay un semestre académico activo para la inscripción.
    </div>
<?php else: ?>
    <div class="alert alert-info">
        **Semestre Actual:** <?php echo htmlspecialchars($current_semester['numero_semestre'] . ' (' . $current_semester['nombre_anio'] . ')'); ?>
    </div>
<?php endif; ?>

 
<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Tus Inscripciones para el Semestre Actual</h5>
    </div>
    <div class="card-body">
        <?php if (empty($current_subjects_status)): ?>
            <p>No tienes asignaturas inscritas para el semestre actual. Dirígete a <a href="inscripciones.php">Inscripción Semestral</a> para registrarte.</p>
        <?php else: ?>
            <div class="accordion" id="currentSubjectsAccordion">
                <?php foreach ($current_subjects_status as $index => $subject): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingCurrent<?php echo $index; ?>">
                            <button class="accordion-button <?php echo ($index == 0) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCurrent<?php echo $index; ?>" aria-expanded="<?php echo ($index == 0) ? 'true' : 'false'; ?>" aria-controls="collapseCurrent<?php echo $index; ?>">
                                <?php echo htmlspecialchars($subject['nombre_asignatura']); ?> (Créditos: <?php echo htmlspecialchars($subject['creditos']); ?>)
                                <?php if ($subject['confirmada']): ?>
                                    <span class="badge bg-success ms-2">Confirmada</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark ms-2">Pendiente</span>
                                <?php endif; ?>
                            </button>
                        </h2>
                        <div id="collapseCurrent<?php echo $index; ?>" class="accordion-collapse collapse <?php echo ($index == 0) ? 'show' : ''; ?>" aria-labelledby="headingCurrent<?php echo $index; ?>" data-bs-parent="#currentSubjectsAccordion">
                            <div class="accordion-body">
                                <?php if (!empty($subject['horarios'])): ?>
                                    <h6>Horario(s):</h6>
                                    <ul class="list-group list-group-flush mb-3">
                                        <?php foreach ($subject['horarios'] as $hora): ?>
                                            <li class="list-group-item">
                                                **Día:** <?php echo htmlspecialchars($hora['dia_semana']); ?> |
                                                **Hora:** <?php echo htmlspecialchars(substr($hora['hora_inicio'], 0, 5) . ' - ' . substr($hora['hora_fin'], 0, 5)); ?> |
                                                **Aula:** <?php echo htmlspecialchars($hora['nombre_aula'] ?? 'N/A'); ?> |
                                                **Profesor:** <?php echo htmlspecialchars($hora['nombre_profesor'] ?? 'N/A'); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">No hay horario asignado aún para esta asignatura.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

 
<div class="card shadow-sm mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">Historial de Asignaturas Aprobadas</h5>
    </div>
    <div class="card-body">
        <?php if (empty($approved_subjects)): ?>
            <p>Aún no has aprobado ninguna asignatura.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Asignatura</th>
                            <th>Créditos</th>
                            <th>Nota Final</th>
                            <th>Semestre</th>
                            <th>Año Académico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approved_subjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['nombre_asignatura']); ?></td>
                                <td><?php echo htmlspecialchars($subject['creditos']); ?></td>
                                <td><span class="badge bg-success"><?php echo htmlspecialchars(number_format($subject['nota_final'], 2)); ?></span></td>
                                <td><?php echo htmlspecialchars($subject['numero_semestre']); ?></td>
                                <td><?php echo htmlspecialchars($subject['nombre_anio']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

 
<div class="card shadow-sm mb-4">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">Asignaturas Reprobadas</h5>
    </div>
    <div class="card-body">
        <?php if (empty($reproved_subjects)): ?>
            <p>¡Felicidades! No tienes asignaturas reprobadas registradas.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Asignatura</th>
                            <th>Créditos</th>
                            <th>Nota Final</th>
                            <th>Semestre</th>
                            <th>Año Académico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reproved_subjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['nombre_asignatura']); ?></td>
                                <td><?php echo htmlspecialchars($subject['creditos']); ?></td>
                                <td><span class="badge bg-danger"><?php echo htmlspecialchars(number_format($subject['nota_final'], 2)); ?></span></td>
                                <td><?php echo htmlspecialchars($subject['numero_semestre']); ?></td>
                                <td><?php echo htmlspecialchars($subject['nombre_anio']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
    </div>

<script>
    const flashMessages = <?php echo json_encode($flash_messages); ?>;

    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now();

        let bgColor = '';
        switch (type) {
            case 'success': bgColor = 'bg-success'; break;
            case 'danger': bgColor = 'bg-danger'; break;
            case 'warning': bgColor = 'bg-warning text-dark'; break;
            case 'info': bgColor = 'bg-info'; break;
            default: bgColor = 'bg-secondary'; break;
        }

        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
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
    });
</script>