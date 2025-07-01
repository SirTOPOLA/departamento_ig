<?php

// --- INICIO DE DEPURACIÓN TEMPORAL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

require_once '../includes/functions.php';
check_login_and_role('Estudiante');

require_once '../config/database.php';

$titulo_pagina = "Mi Horario";
include_once '../includes/header.php';

$mensajes_flash = get_flash_messages();

$id_usuario_actual = $_SESSION['user_id'];

// Obtener el id_estudiante del usuario logueado
$stmt_detalles_estudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
$stmt_detalles_estudiante->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
$stmt_detalles_estudiante->execute();
$detalles_estudiante = $stmt_detalles_estudiante->fetch(PDO::FETCH_ASSOC);

if (!$detalles_estudiante) {
    set_flash_message('danger', 'Error: No se encontró el perfil de estudiante asociado a su usuario.');
    header('Location: ../logout.php');
    exit;
}
$id_estudiante_actual = $detalles_estudiante['id'];

// --- Obtener todos los cursos y semestres a los que ha estado el estudiante ---
// Ordenar por año académico y semestre de forma descendente para mostrar los más recientes primero
$stmt_historial_cursos_estudiante = $pdo->prepare("
    SELECT ce.id_curso, ce.id_anio, sem.id AS id_semestre, sem.numero_semestre, aa.nombre_anio
    FROM curso_estudiante ce
    JOIN anios_academicos aa ON ce.id_anio = aa.id
    JOIN semestres sem ON sem.id_anio_academico = aa.id
    WHERE ce.id_estudiante = :id_estudiante
    ORDER BY aa.nombre_anio DESC, sem.numero_semestre DESC
");
$stmt_historial_cursos_estudiante->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
$stmt_historial_cursos_estudiante->execute();
$historial_cursos_estudiante = $stmt_historial_cursos_estudiante->fetchAll(PDO::FETCH_ASSOC);

$todos_horarios_estudiante = [];
$pares_curso_semestre_procesados = []; // Para evitar duplicados de horarios

foreach ($historial_cursos_estudiante as $entrada_curso) {
    $id_curso_estudiante = $entrada_curso['id_curso'];
    $id_anio_academico = $entrada_curso['id_anio'];
    $id_semestre = $entrada_curso['id_semestre'];

    $clave_par = $id_curso_estudiante . '-' . $id_semestre;
    if (in_array($clave_par, $pares_curso_semestre_procesados)) {
        continue;
    }
    $pares_curso_semestre_procesados[] = $clave_par;

    // Obtener las asignaturas en las que el estudiante estuvo inscrito y confirmadas para este curso y semestre
    $stmt_asignaturas_inscritas = $pdo->prepare("
        SELECT ie.id_asignatura
        FROM inscripciones_estudiantes ie
        WHERE ie.id_estudiante = :id_estudiante
        AND ie.id_semestre = :id_semestre
        AND ie.confirmada = 1
    ");
    $stmt_asignaturas_inscritas->bindParam(':id_estudiante', $id_estudiante_actual, PDO::PARAM_INT);
    $stmt_asignaturas_inscritas->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
    $stmt_asignaturas_inscritas->execute();
    $ids_asignaturas_inscritas = $stmt_asignaturas_inscritas->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($ids_asignaturas_inscritas)) {
        $marcadores_posicion = implode(',', array_fill(0, count($ids_asignaturas_inscritas), '?'));

        // Obtener los horarios para esas asignaturas, este curso y este semestre
        $sql_horarios = "
            SELECT
                h.dia_semana, h.hora_inicio, h.hora_fin, h.turno,
                a.nombre_asignatura,
                p.nombre_completo AS nombre_profesor,
                au.nombre_aula, au.ubicacion,
                c.nombre_curso,
                sem.numero_semestre,
                aa.nombre_anio
            FROM horarios h
            JOIN asignaturas a ON h.id_asignatura = a.id
            JOIN usuarios p ON h.id_profesor = p.id
            JOIN aulas au ON h.id_aula = au.id
            JOIN cursos c ON h.id_curso = c.id
            JOIN semestres sem ON h.id_semestre = sem.id
            JOIN anios_academicos aa ON sem.id_anio_academico = aa.id
            WHERE h.id_semestre = ?
            AND h.id_asignatura IN ($marcadores_posicion)
            AND h.id_curso = ?
            ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'), h.hora_inicio ASC
        ";

        $parametros = [$id_semestre];
        $parametros = array_merge($parametros, $ids_asignaturas_inscritas);
        $parametros[] = $id_curso_estudiante;

        $stmt_horarios = $pdo->prepare($sql_horarios);
        $stmt_horarios->execute($parametros);
        $horarios_obtenidos = $stmt_horarios->fetchAll(PDO::FETCH_ASSOC);

        $todos_horarios_estudiante = array_merge($todos_horarios_estudiante, $horarios_obtenidos);
    }
}

// Organizar el horario para la visualización en tabla de cuadrícula
$horario_organizado = [];
$franjas_horarias_unicas = []; // Para almacenar todas las franjas horarias únicas para el renderizado de la tabla

// Días de la semana en el orden deseado (hasta Sábado)
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

foreach ($todos_horarios_estudiante as $item) {
    $nombre_anio_academico = $item['nombre_anio'];
    $numero_semestre = $item['numero_semestre'];
    $nombre_curso = $item['nombre_curso'];
    $turno = $item['turno'];
    $dia_semana = $item['dia_semana'];
    $hora_inicio = substr($item['hora_inicio'], 0, 5);
    $hora_fin = substr($item['hora_fin'], 0, 5);
    $clave_franja_horaria = $hora_inicio . ' - ' . $hora_fin;

    // Añadir la franja horaria a la lista de únicas para cada grupo (Año/Semestre/Curso/Turno)
    if (!isset($franjas_horarias_unicas[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno])) {
        $franjas_horarias_unicas[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno] = [];
    }
    if (!in_array($clave_franja_horaria, $franjas_horarias_unicas[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno])) {
        $franjas_horarias_unicas[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno][] = $clave_franja_horaria;
    }

    // Inicializar la estructura si no existe
    if (!isset($horario_organizado[$nombre_anio_academico])) {
        $horario_organizado[$nombre_anio_academico] = [];
    }
    if (!isset($horario_organizado[$nombre_anio_academico][$numero_semestre])) {
        $horario_organizado[$nombre_anio_academico][$numero_semestre] = [];
    }
    if (!isset($horario_organizado[$nombre_anio_academico][$numero_semestre][$nombre_curso])) {
        $horario_organizado[$nombre_anio_academico][$numero_semestre][$nombre_curso] = [];
    }
    if (!isset($horario_organizado[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno])) {
        $horario_organizado[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno] = [];
    }
    if (!isset($horario_organizado[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno][$clave_franja_horaria])) {
        $horario_organizado[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno][$clave_franja_horaria] = [];
        // Inicializar todos los días para esta franja horaria para asegurar celdas vacías
        foreach ($dias_semana as $dia) {
            $horario_organizado[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno][$clave_franja_horaria][$dia] = null;
        }
    }

    // Asignar los detalles de la clase
    $horario_organizado[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno][$clave_franja_horaria][$dia_semana] = [
        'asignatura' => $item['nombre_asignatura'],
        'profesor' => $item['nombre_profesor'],
        'aula' => $item['nombre_aula'],
        'ubicacion' => $item['ubicacion']
    ];
}

// Ordenar las franjas horarias de forma ascendente dentro de cada grupo
foreach ($franjas_horarias_unicas as $clave_anio => &$datos_semestres) {
    foreach ($datos_semestres as $clave_semestre => &$datos_cursos) {
        foreach ($datos_cursos as $clave_curso => &$datos_turnos) {
            foreach ($datos_turnos as $clave_turno => &$array_franjas_horarias) {
                usort($array_franjas_horarias, function($a, $b) {
                    $tiempoA = strtotime(explode(' - ', $a)[0]);
                    $tiempoB = strtotime(explode(' - ', $b)[0]);
                    return $tiempoA <=> $tiempoB;
                });
            }
        }
    }
}
unset($datos_semestres);
unset($datos_cursos);
unset($datos_turnos);
unset($array_franjas_horarias);

?>

<h1 class="mt-4">Mi Horario de Clases</h1>
<p class="lead">Consulta tus horarios de clases organizados por año académico y semestre.</p>

<?php if (empty($todos_horarios_estudiante)): ?>
    <div class="alert alert-warning">
        No tienes clases programadas o tu inscripción aún no ha sido confirmada por la administración para ningún curso/semestre.
    </div>
<?php else: ?>
    <?php
    // Obtener el semestre académico actual para el mensaje informativo
    $info_semestre_actual = get_current_semester($pdo);
    if ($info_semestre_actual):
    ?>
        <div class="alert alert-info">
            **Semestre Actual Activo:** <?php echo htmlspecialchars($info_semestre_actual['numero_semestre'] . ' (' . $info_semestre_actual['nombre_anio'] . ')'); ?>
        </div>
    <?php endif; ?>

    <?php foreach ($horario_organizado as $nombre_anio_academico => $semestres): ?>
        <?php foreach ($semestres as $numero_semestre => $cursos): ?>
            <?php foreach ($cursos as $nombre_curso => $turnos): ?>
                <?php foreach ($turnos as $turno => $datos_franjas_horarias): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                Año: <?php echo htmlspecialchars($nombre_anio_academico); ?> |
                                Semestre: <?php echo htmlspecialchars($numero_semestre); ?> |
                                Curso: <?php echo htmlspecialchars($nombre_curso); ?> |
                                Turno: <?php echo htmlspecialchars($turno); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm text-center align-middle">
                                    <thead>
                                        <tr class="table-light">
                                            <th style="width: 10%;">Hora</th>
                                            <?php foreach ($dias_semana as $dia): ?>
                                                <th><?php echo htmlspecialchars($dia); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Asegurarse de que las franjas horarias estén ordenadas para este grupo específico
                                        $franjas_ordenadas_para_este_grupo = $franjas_horarias_unicas[$nombre_anio_academico][$numero_semestre][$nombre_curso][$turno];
                                        foreach ($franjas_ordenadas_para_este_grupo as $franja_horaria):
                                        ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($franja_horaria); ?></td>
                                                <?php foreach ($dias_semana as $dia): ?>
                                                    <td>
                                                        <?php
                                                        $info_clase = $datos_franjas_horarias[$franja_horaria][$dia] ?? null;
                                                        if ($info_clase):
                                                        ?>
                                                            <strong class="d-block text-primary"><?php echo htmlspecialchars($info_clase['asignatura']); ?></strong>
                                                            <small class="d-block text-muted">Profesor: <?php echo htmlspecialchars($info_clase['profesor']); ?></small>
                                                            <small class="d-block text-secondary">Aula: <?php echo htmlspecialchars($info_clase['aula'] . ' (' . $info_clase['ubicacion'] . ')'); ?></small>
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
    <?php endforeach; ?>
<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
</div>

<script>
    const mensajesFlash = <?php echo json_encode($mensajes_flash); ?>;

    function mostrarToast(tipo, mensaje) {
        const contenedorToast = document.querySelector('.toast-container');
        const idToast = 'toast-' + Date.now();

        let colorFondo = '';
        let icono = '';
        switch (tipo) {
            case 'success': colorFondo = 'bg-success'; icono = '<i class="fas fa-check-circle me-2"></i>'; break;
            case 'danger': colorFondo = 'bg-danger'; icono = '<i class="fas fa-exclamation-triangle me-2"></i>'; break;
            case 'warning': colorFondo = 'bg-warning text-dark'; icono = '<i class="fas fa-exclamation-circle me-2"></i>'; break;
            case 'info': colorFondo = 'bg-info'; icono = '<i class="fas fa-info-circle me-2"></i>'; break;
            default: colorFondo = 'bg-secondary'; icono = '<i class="fas fa-bell me-2"></i>'; break;
        }

        const htmlToast = `
            <div id="${idToast}" class="toast align-items-center text-white ${colorFondo} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        ${icono} ${mensaje}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
            </div>
        `;
        contenedorToast.insertAdjacentHTML('beforeend', htmlToast);

        const elementoToast = document.getElementById(idToast);
        const toast = new bootstrap.Toast(elementoToast);
        toast.show();

        elementoToast.addEventListener('hidden.bs.toast', function () {
            elementoToast.remove();
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        mensajesFlash.forEach(msg => {
            mostrarToast(msg.type, msg.message);
        });
    });
</script>