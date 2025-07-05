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

$stmt_inscripciones = $pdo->prepare("  
   SELECT
    ie.id AS id_inscripcion,
    ie.confirmada,
    ie.fecha_inscripcion,
    a.nombre_asignatura,
    a.creditos,
    c.nombre_curso,
    s.numero_semestre,
    s.id AS id_semestre,
    h.dia_semana,
    h.hora_inicio,
    h.hora_fin,
    h.turno,
    u_profesor.nombre_completo AS nombre_profesor,
    au.nombre_aula,
    h.id AS id_horario_elegido,

    -- Datos del historial académico
    ha.nota_final,
    ha.estado_final

FROM inscripciones_estudiantes ie
INNER JOIN asignaturas a ON ie.id_asignatura = a.id
INNER JOIN semestres s ON ie.id_semestre = s.id
LEFT JOIN cursos c ON a.id_curso = c.id
LEFT JOIN horarios h ON ie.id_horario = h.id
LEFT JOIN profesores p ON h.id_profesor = p.id
LEFT JOIN usuarios u_profesor ON p.id_usuario = u_profesor.id
LEFT JOIN aulas au ON h.id_aula = au.id

-- JOIN con historial académico
LEFT JOIN historial_academico ha ON 
    ha.id_estudiante = ie.id_estudiante AND 
    ha.id_asignatura = ie.id_asignatura AND 
    ha.id_semestre = ie.id_semestre

WHERE ie.id_estudiante = :id_estudiante

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
            <div class="alert alert-<?= htmlspecialchars($mensaje['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
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
                        <th>Horario</th>
                        <th>Profesor</th>
                        <th>Aula</th>
                        <th>Calificación Final</th>
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
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ins['numero_semestre']) ?>° Semestre</span></td>
                            <td>
                                <span class="badge rounded-pill <?= $ins['confirmada'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <i class="fas <?= $ins['confirmada'] ? 'fa-check-circle' : 'fa-hourglass-half' ?>"></i>
                                    <?= $ins['confirmada'] ? 'Confirmada' : 'Pendiente' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($ins['id_horario_elegido'])): ?>
                                    <div class="small text-muted">
                                        <i class="fas fa-calendar-day me-1"></i> <?= htmlspecialchars($ins['dia_semana']) ?><br>
                                        <i class="fas fa-clock me-1"></i> <?= substr($ins['hora_inicio'], 0, 5) ?> - <?= substr($ins['hora_fin'], 0, 5) ?><br>
                                        <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($ins['turno']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Sin horario</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-dark"><?= htmlspecialchars($ins['nombre_profesor'] ?? 'Sin asignar') ?></span>
                            </td>
                            <td>
                                <span class="text-muted"><?= htmlspecialchars($ins['nombre_aula'] ?? 'Sin asignar') ?></span>
                            </td>
                            <td>
                                <?php if (!is_null($ins['nota_final'])): ?>
                                    <span class="badge bg-<?= $ins['estado_final'] === 'APROBADO' ? 'success' : 'danger' ?> fw-semibold">
                                        <i class="fas <?= $ins['estado_final'] === 'APROBADO' ? 'fa-check' : 'fa-times' ?>"></i>
                                        <?= htmlspecialchars($ins['nota_final']) ?> <span class="ms-1">(<?= $ins['estado_final'] ?>)</span>
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
