<?php

// --- INICIO DE DEPURACIÓN TEMPORAL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
check_login_and_role('Estudiante');
require_once '../config/database.php';

$titulo_pagina = "Mis Asignaturas Inscritas";
include_once '../includes/header.php';

$mensajes_flash = get_flash_messages();
$id_usuario_actual = $_SESSION['user_id'];

$stmt_estudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
$stmt_estudiante->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
$stmt_estudiante->execute();
$datos_estudiante = $stmt_estudiante->fetch(PDO::FETCH_ASSOC);
$id_estudiante = $datos_estudiante['id'] ?? null;

if (!$id_estudiante) {
    echo "<div class='alert alert-danger'>Error: No se pudo encontrar el ID del estudiante asociado al usuario.</div>";
    include_once '../includes/footer.php';
    exit;
}

// --- CONSULTA SQL MODIFICADA ---
$stmt_inscripciones = $pdo->prepare("
SELECT
    ie.id AS id_inscripcion,
    ie.confirmada,
    ie.fecha_inscripcion,

    -- Asignatura y créditos
    a.nombre_asignatura,
    a.creditos,

    -- Curso desde grupos_asignaturas (o asignaturas, si es más directo)
    -- Si el curso de la asignatura es siempre el mismo que el del grupo,
    -- podrías obtenerlo directamente de `a.id_curso` y `JOIN cursos c ON a.id_curso = c.id`
    -- Pero si el grupo puede tener un curso diferente, déjalo así.
    c.nombre_curso,
    
    -- Semestre
    s.numero_semestre,
    s.id AS id_semestre,

    -- Profesor del grupo asignado
    u.nombre_completo AS nombre_profesor,

    -- Información de Grupo (Grupo y Turno)
    ga.grupo,
    ga.turno AS grupo_turno,

    -- Horarios CONCATENADOS
    GROUP_CONCAT(
        DISTINCT CONCAT(
            h.dia_semana, ' (', SUBSTRING(h.hora_inicio, 1, 5), '-', SUBSTRING(h.hora_fin, 1, 5), ') @ ', au.nombre_aula,
            ' (Turno: ', h.turno, ')' -- Incluir el turno del horario si es diferente al del grupo
        )
        ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'), h.hora_inicio
        SEPARATOR '<br>' -- Usar <br> para saltos de línea en HTML
    ) AS horarios_detallados,

    -- Aulas (se concatenarán junto con los horarios)
    -- Ya se incluyen en horarios_detallados, no necesitamos una columna separada aquí

    -- Calificación final desde tabla notas
    n.nota AS nota_final,
    n.estado AS estado_final

FROM inscripciones_estudiantes ie

-- Asignatura
INNER JOIN asignaturas a ON ie.id_asignatura = a.id

-- Semestre
INNER JOIN semestres s ON ie.id_semestre = s.id

-- Grupo (para profesor, curso y turno)
LEFT JOIN grupos_asignaturas ga ON ie.id_grupo_asignatura = ga.id
LEFT JOIN cursos c ON ga.id_curso = c.id
LEFT JOIN profesores p ON ga.id_profesor = p.id
LEFT JOIN usuarios u ON p.id_usuario = u.id

-- Horario (asociado al grupo)
-- Importante: Esta unión *no* debería estar en LEFT JOIN si solo quieres horarios
-- asociados a un grupo ya asignado a la inscripción.
-- Pero para mostrar  si no hay grupo asignado, LEFT JOIN está bien.
LEFT JOIN horarios h ON h.id_grupo_asignatura = ga.id AND h.id_semestre = ie.id_semestre
LEFT JOIN aulas au ON h.id_aula = au.id

-- Nota final
LEFT JOIN notas n ON n.id_inscripcion = ie.id

WHERE ie.id_estudiante = :id_estudiante

GROUP BY ie.id -- *** ESTO ES CRUCIAL PARA EVITAR DUPLICADOS ***

ORDER BY
    c.nombre_curso ASC,
    s.numero_semestre ASC,
    a.nombre_asignatura ASC
");


$stmt_inscripciones->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
$stmt_inscripciones->execute();
$inscripciones = $stmt_inscripciones->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary-emphasis"><i class="fas fa-book-reader me-2"></i>Mis Asignaturas Inscritas</h2>
    </div>

    <?php if (!empty($mensajes_flash)): ?>
        <?php foreach ($mensajes_flash as $mensaje): ?>
            <div class="alert alert-<?= htmlspecialchars($mensaje['type']) ?> alert-dismissible fade show shadow-sm"
                role="alert">
                <?= htmlspecialchars($mensaje['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($inscripciones)): ?>
        <div class="alert alert-info text-center shadow-sm" role="alert">
            <i class="fas fa-info-circle me-2"></i> No tienes asignaturas inscritas registradas.
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead class="table-primary">
                    <tr class="fw-semibold text-secondary">
                        <th>Asignatura</th>
                        <th>Curso</th>
                        <th>Semestre</th>
                        <th>Inscripción</th>
                        <th>Profesor</th>
                        <th>Grupo/Turno</th> <th>Horario y Aula</th> <th>Calificación Final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscripciones as $ins): ?>
                        <tr>
                            <td class="text-start">
                                <strong><?= htmlspecialchars($ins['nombre_asignatura']) ?></strong><br>
                                <small class="text-muted"><?= $ins['creditos'] ?> créditos</small>
                            </td>
                            <td><?= htmlspecialchars($ins['nombre_curso'] ?? 'N/A') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ins['numero_semestre']) ?>°
                                    Semestre</span></td>
                            <td>
                                <span
                                    class="badge rounded-pill <?= $ins['confirmada'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <i class="fas <?= $ins['confirmada'] ? 'fa-check-circle' : 'fa-hourglass-half' ?>"></i>
                                    <?= $ins['confirmada'] ? 'Confirmada' : 'Pendiente' ?>
                                </span>
                            </td>
                            <td>
                                <span
                                    class="badge bg-info-subtle text-dark"><?= htmlspecialchars($ins['nombre_profesor'] ?? 'Sin asignar') ?></span>
                            </td>
                            <td>
                                <?php if (!empty($ins['grupo'])): ?>
                                    <span class="badge bg-primary-subtle text-dark">Grupo <?= htmlspecialchars($ins['grupo']) ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($ins['grupo_turno'] ?? '') ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Sin grupo asignado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($ins['horarios_detallados'])): ?>
                                    <div class="small text-muted text-start">
                                        <?= $ins['horarios_detallados'] ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Sin horario asignado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!is_null($ins['nota_final'])): ?>
                                    <span
                                        class="badge bg-<?= $ins['estado_final'] === 'APROBADO' ? 'success' : 'danger' ?> fw-semibold">
                                        <i class="fas <?= $ins['estado_final'] === 'APROBADO' ? 'fa-check' : 'fa-times' ?>"></i>
                                        <?= htmlspecialchars($ins['nota_final']) ?> <span
                                            class="ms-1">(<?= $ins['estado_final'] ?>)</span>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>