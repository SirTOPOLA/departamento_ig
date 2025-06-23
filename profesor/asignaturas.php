<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../login.php");
    exit;
}
require '../includes/conexion.php';

// Obtener id del profesor desde sesión
$id_profesor = $_SESSION['id_usuario'];

// Día actual en español para filtrar horarios (ej: "Lunes")
$diasSemana = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
$diaActual = $diasSemana[date('l')];

// Consulta: asignaturas asignadas al profesor con info necesaria
$sql = "SELECT 
    a.id_asignatura, a.nombre AS asignatura_nombre, a.descripcion,
    c.nombre AS curso_nombre, c.turno, c.grupo,
    s.nombre AS semestre_nombre,
    (SELECT COUNT(*) FROM asignatura_estudiante ae WHERE ae.id_asignatura = a.id_asignatura) AS total_estudiantes,
    -- Horario solo para día actual, puede ser NULL si no hay horario hoy
    h.hora_inicio, h.hora_fin,
    au.nombre AS aula_nombre
FROM asignaturas a
INNER JOIN asignatura_profesor ap ON a.id_asignatura = ap.id_asignatura
INNER JOIN cursos c ON a.curso_id = c.id_curso
INNER JOIN semestres s ON a.semestre_id = s.id_semestre
LEFT JOIN horarios h ON h.id_asignatura = a.id_asignatura AND h.id_profesor = ap.id_profesor AND h.dia = :diaActual
LEFT JOIN aulas au ON h.aula_id = au.id_aula
WHERE ap.id_profesor = :id_profesor
ORDER BY c.nombre, s.nombre, a.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id_profesor' => $id_profesor, 'diaActual' => $diaActual]);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>

<div id="main-content" class="main-content">
    <div class="container p-5">

        <h2 class="mb-4">Mis Asignaturas - Hoy: <?= htmlspecialchars($diaActual) ?></h2>
    

    <?php if (!$asignaturas): ?>
        <div class="alert alert-info">No tienes asignaturas asignadas.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Asignatura</th>
                        <th>Descripción</th>
                        <th>Curso</th>
                        <th>Semestre</th>
                        <th>Estudiantes Inscritos</th>
                        <th>Horario (Hoy)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asignaturas as $asig): ?>
                        <tr>
                            <td><?= htmlspecialchars($asig['asignatura_nombre']) ?></td>
                            <td><?= nl2br(htmlspecialchars($asig['descripcion'])) ?></td>
                            <td><?= htmlspecialchars($asig['curso_nombre']) ?> (<?= ucfirst($asig['turno']) ?>, Grupo
                                <?= $asig['grupo'] ?>)
                            </td>
                            <td><?= htmlspecialchars($asig['semestre_nombre']) ?></td>
                            <td><?= intval($asig['total_estudiantes']) ?></td>
                            <td>
                                <?php if ($asig['hora_inicio'] && $asig['hora_fin'] && $asig['aula_nombre']): ?>
                                    <?= date('H:i', strtotime($asig['hora_inicio'])) ?> -
                                    <?= date('H:i', strtotime($asig['hora_fin'])) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($asig['aula_nombre']) ?></small>
                                <?php else: ?>
                                    <span class="text-secondary">Sin clase hoy</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</div>
 

<?php include 'footer.php'; ?>