<?php

// --- INICIO DE DEPURACIÓN TEMPORAL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

require_once '../includes/functions.php';
check_login_and_role('Estudiante');

require_once '../config/database.php';

$page_title = "Mi Horario";
include_once '../includes/header.php';

$flash_messages = get_flash_messages();

$current_user_id = $_SESSION['user_id'];

// Obtener el id_estudiante y su id_curso_inicio del usuario logueado
$stmt_estudiantes_details = $pdo->prepare("SELECT id, id_curso_inicio FROM estudiantes WHERE id_usuario = :id_usuario");
$stmt_estudiantes_details->bindParam(':id_usuario', $current_user_id, PDO::PARAM_INT);
$stmt_estudiantes_details->execute();
$estudiantes_details = $stmt_estudiantes_details->fetch(PDO::FETCH_ASSOC);

if (!$estudiantes_details) {
    set_flash_message('danger', 'Error: No se encontró el perfil de estudiante asociado a su usuario.');
    header('Location: ../logout.php');
    exit;
}
$id_estudiante_actual = $estudiantes_details['id'];
$id_curso_inicio_estudiante = $estudiantes_details['id_curso_inicio'];

// Obtener el semestre académico actual
// Asegúrate de que esta función esté definida en includes/functions.php y devuelva el ID, numero_semestre y nombre_anio.
// Y que tu tabla 'semestres' tenga una columna 'activo' (TINYINT(1)) para identificar el semestre actual.
$current_semester = get_current_semester($pdo);

$estudiantes_horarios = [];
if ($current_semester) {
    // Obtener las asignaturas en las que el estudiante está inscrito y confirmadas para el semestre actual
    $stmt_enrolled_asignaturas = $pdo->prepare("
        SELECT ie.id_asignatura
        FROM inscripciones_estudiantes ie
        WHERE ie.id_estudiante = :id_estudiante
        AND ie.id_semestre = :id_semestre
        AND ie.confirmada = 1
    ");
    $stmt_enrolled_asignaturas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_enrolled_asignaturas->bindParam(':id_semestre', $current_semester['id'], PDO::PARAM_INT);
    $stmt_enrolled_asignaturas->execute();
    $enrolled_asignatura_ids = $stmt_enrolled_asignaturas->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($enrolled_asignatura_ids)) {
        // Construir la lista de placeholders para la cláusula IN
        $placeholders = implode(',', array_fill(0, count($enrolled_asignatura_ids), '?'));

        // Preparar el array de parámetros para execute
        // El primer parámetro es para h.id_semestre
        $params = [$current_semester['id']];
        // Añadir todos los id de asignaturas inscritas
        $params = array_merge($params, $enrolled_asignatura_ids);

        // Obtener los horarios para esas asignaturas en el semestre actual
        $stmt_horarios = $pdo->prepare("
            SELECT
                h.dia_semana, h.hora_inicio, h.hora_fin, h.turno,
                a.nombre_asignatura,
                p.nombre_completo AS nombre_profesor,
                au.nombre_aula, au.ubicacion,
                c.nombre_curso,
                a.semestre_recomendado AS numero_semestre_asignatura
            FROM horarios h
            JOIN asignaturas a ON h.id_asignatura = a.id
            JOIN usuarios p ON h.id_profesor = p.id
            JOIN aulas au ON h.id_aula = au.id
            JOIN cursos c ON h.id_curso = c.id
            WHERE h.id_semestre = ?
            AND h.id_asignatura IN ($placeholders)
            ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'), h.hora_inicio ASC
        ");

        $stmt_horarios->execute($params);
        $estudiantes_horarios = $stmt_horarios->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Organizar el horario para la visualización en tabla de cuadrícula ---
$organized_schedule = [];
$unique_time_slots = []; // Para almacenar todas las franjas horarias únicas

// Días de la semana en el orden deseado (hasta Sábado)
$days_of_week = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

foreach ($estudiantes_horarios as $item) {
    $curso_nombre = $item['nombre_curso'];
    $semestre_asignatura = $item['numero_semestre_asignatura']; // Semestre recomendado de la asignatura
    $turno = $item['turno'];
    $dia_semana = $item['dia_semana'];
    $hora_inicio = substr($item['hora_inicio'], 0, 5);
    $hora_fin = substr($item['hora_fin'], 0, 5);
    $time_slot_key = $hora_inicio . ' - ' . $hora_fin;

    // Añadir la franja horaria a la lista de únicas
    if (!in_array($time_slot_key, $unique_time_slots)) {
        $unique_time_slots[] = $time_slot_key;
    }

    // Inicializar la estructura si no existe
    if (!isset($organized_schedule[$curso_nombre])) {
        $organized_schedule[$curso_nombre] = [];
    }
    if (!isset($organized_schedule[$curso_nombre][$semestre_asignatura])) {
        $organized_schedule[$curso_nombre][$semestre_asignatura] = [];
    }
    if (!isset($organized_schedule[$curso_nombre][$semestre_asignatura][$turno])) {
        $organized_schedule[$curso_nombre][$semestre_asignatura][$turno] = [];
    }
    if (!isset($organized_schedule[$curso_nombre][$semestre_asignatura][$turno][$time_slot_key])) {
        $organized_schedule[$curso_nombre][$semestre_asignatura][$turno][$time_slot_key] = [];
        // Inicializar todos los días para esta franja horaria para asegurar celdas vacías
        foreach ($days_of_week as $day) {
            $organized_schedule[$curso_nombre][$semestre_asignatura][$turno][$time_slot_key][$day] = null;
        }
    }

    // Asignar los detalles de la clase
    $organized_schedule[$curso_nombre][$semestre_asignatura][$turno][$time_slot_key][$dia_semana] = [
        'asignatura' => $item['nombre_asignatura'],
        'profesor' => $item['nombre_profesor'],
        'aula' => $item['nombre_aula'],
        'ubicacion' => $item['ubicacion']
    ];
}

// Ordenar las franjas horarias de forma ascendente
usort($unique_time_slots, function($a, $b) {
    $timeA = strtotime(explode(' - ', $a)[0]);
    $timeB = strtotime(explode(' - ', $b)[0]);
    return $timeA <=> $timeB;
});

// Ordenar los cursos, semestres y turnos para una visualización consistente
ksort($organized_schedule); // Ordenar cursos alfabéticamente
foreach ($organized_schedule as $curso_nombre => &$semestres) {
    ksort($semestres); // Ordenar semestres numéricamente
    foreach ($semestres as $semestre_asignatura => &$turnos) {
        ksort($turnos); // Ordenar turnos alfabéticamente
    }
}
unset($semestres); // Romper la referencia al último elemento
unset($turnos); // Romper la referencia al último elemento

?>

<h1 class="mt-4">Mi Horario de Clases</h1>
<p class="lead">Consulta tu horario para el semestre actual.</p>

<?php if (!$current_semester): ?>
    <div class="alert alert-info">
        Actualmente no hay un semestre académico activo para mostrar un horario.
    </div>
<?php elseif (empty($estudiantes_horarios)): ?>
    <div class="alert alert-warning">
        No tienes clases programadas para el semestre actual o tu inscripción aún no ha sido confirmada por la administración.
    </div>
<?php else: ?>
    <div class="alert alert-info">
        **Semestre Actual de Inscripción:** <?php echo htmlspecialchars($current_semester['numero_semestre'] . ' (' . $current_semester['nombre_anio'] . ')'); ?>
    </div>

    <?php if (empty($organized_schedule)): ?>
        <p class="text-center">No hay clases programadas para tus asignaturas confirmadas en este semestre.</p>
    <?php else: ?>
        <?php foreach ($organized_schedule as $curso_nombre => $semestres): ?>
            <?php foreach ($semestres as $semestre_asignatura => $turnos): ?>
                <?php foreach ($turnos as $turno => $time_slots_data): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                Curso: <?php echo htmlspecialchars($curso_nombre); ?> |
                                Semestre: <?php echo htmlspecialchars($semestre_asignatura); ?> |
                                Turno: <?php echo htmlspecialchars($turno); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm text-center align-middle">
                                    <thead>
                                        <tr class="table-light">
                                            <th style="width: 10%;">Hora</th>
                                            <?php foreach ($days_of_week as $day): ?>
                                                <th><?php echo htmlspecialchars($day); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($unique_time_slots as $time_slot): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($time_slot); ?></td>
                                                <?php foreach ($days_of_week as $day): ?>
                                                    <td>
                                                        <?php
                                                        $class_info = $time_slots_data[$time_slot][$day] ?? null;
                                                        if ($class_info):
                                                        ?>
                                                            <strong class="d-block"><?php echo htmlspecialchars($class_info['asignatura']); ?></strong>
                                                            <small class="d-block text-muted">Profesor: <?php echo htmlspecialchars($class_info['profesor']); ?></small>
                                                            <small class="d-block text-muted">Aula: <?php echo htmlspecialchars($class_info['aula'] . ' (' . $class_info['ubicacion'] . ')'); ?></small>
                                                        <?php else: ?>
                                                            —
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
</div>

<script>
    const flashMessages = <?php echo json_encode($flash_messages); ?>;

    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now();

        let bgColor = '';
        let icon = ''; // Added icon variable
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
    });
</script>