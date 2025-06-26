<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../login.php");
    exit;
}
require '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

// Obtener id del profesor desde sesión
$id_profesor = $_SESSION['id_usuario'];

// Día actual en español para filtrar horarios (ej: "Lunes")
$diasSemana = [
    'Sunday' => 'Domingo',
    'Monday' => 'Lunes',
    'Tuesday' => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sábado'
];
$diaActual = $diasSemana[date('l')];

// Consulta: asignaturas asignadas al profesor con info necesaria
// FIX: La subconsulta para total_estudiantes ahora usa la tabla 'inscripciones'
// y filtra por estado 'confirmado' para obtener los estudiantes activos en esa asignatura.
$sql = "SELECT 
    a.id_asignatura, 
    a.nombre AS asignatura_nombre, 
    a.descripcion,
    c.nombre AS curso_nombre, 
    c.turno, 
    c.grupo,
    s.nombre AS semestre_nombre,
    (
        SELECT COUNT(DISTINCT i.id_estudiante) 
        FROM inscripciones i 
        WHERE i.id_asignatura = a.id_asignatura AND i.estado = 'confirmado'
    ) AS total_estudiantes_inscritos, -- Renombrado para mayor claridad
    -- Horario solo para día actual, puede ser NULL si no hay horario hoy
    h.hora_inicio, 
    h.hora_fin,
    au.nombre AS aula_nombre
FROM 
    asignaturas a
INNER JOIN 
    asignatura_profesor ap ON a.id_asignatura = ap.id_asignatura
INNER JOIN 
    cursos c ON a.curso_id = c.id_curso
INNER JOIN 
    semestres s ON a.semestre_id = s.id_semestre
LEFT JOIN 
    horarios h ON h.id_asignatura = a.id_asignatura AND h.id_profesor = ap.id_profesor AND h.dia = :diaActual
LEFT JOIN 
    aulas au ON h.aula_id = au.id_aula
WHERE 
    ap.id_profesor = :id_profesor
ORDER BY 
    c.nombre, s.nombre, a.nombre";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_profesor' => $id_profesor, 'diaActual' => $diaActual]);
    $asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejo de errores en caso de problemas con la base de datos
    error_log("Error al cargar asignaturas para profesor: " . $e->getMessage());
    $asignaturas = []; // Asegúrate de que $asignaturas sea un array vacío para evitar errores en la vista
    $error_message = "Hubo un problema al cargar tus asignaturas. Por favor, inténtalo de nuevo más tarde.";
}

?>

<?php include 'header.php'; ?>

<style>
    body {
        font-family: "Inter", sans-serif;
        background-color: #f0f2f5;
        /* Fondo más suave */
    }

    .container {
        max-width: 1000px;
    }

    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        /* Sombra para la tabla */
    }

    .table thead th {
        background-color: #007bff;
        color: white;
        border-bottom: none;
    }

    .table-striped>tbody>tr:nth-of-type(odd)>* {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .table-hover tbody tr:hover {
        background-color: #e2f0ff;
    }

    .badge {
        font-size: 0.85em;
        padding: 0.4em 0.7em;
        border-radius: 0.5rem;
    }

    .text-secondary {
        color: #6c757d !important;
    }

    .main-content {
        min-height: calc(100vh - 120px);
        /* Ajusta según la altura de tu header y footer */
    }
</style>

<div class="container-fluid py-5 px-5">


    <h2 class="mb-4 text-primary"><i class="bi bi-journal-bookmark-fill me-3"></i> Mis Asignaturas - Hoy:
        <?= htmlspecialchars($diaActual) ?></h2>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php elseif (empty($asignaturas)): ?>
        <div class="alert alert-info" role="alert">
            No tienes asignaturas asignadas o no hay clases programadas para hoy.
        </div>
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
                            <td>
                                <?= htmlspecialchars($asig['curso_nombre']) ?>
                                (<span class="badge bg-info text-dark"><?= ucfirst($asig['turno']) ?></span>, Grupo
                                <span class="badge bg-secondary"><?= $asig['grupo'] ?></span>)
                            </td>
                            <td><?= htmlspecialchars($asig['semestre_nombre']) ?></td>
                            <td><span class="badge bg-success"><?= intval($asig['total_estudiantes_inscritos']) ?></span></td>
                            <td>
                                <?php if ($asig['hora_inicio'] && $asig['hora_fin'] && $asig['aula_nombre']): ?>
                                    <?= date('H:i', strtotime($asig['hora_inicio'])) ?> -
                                    <?= date('H:i', strtotime($asig['hora_fin'])) ?><br>
                                    <small class="text-muted"><i
                                            class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($asig['aula_nombre']) ?></small>
                                <?php else: ?>
                                    <span class="text-secondary"><i class="bi bi-clock-history me-1"></i>Sin clase hoy</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>