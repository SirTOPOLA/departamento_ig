<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../login.php");
    exit;
}

require '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible aquí

// Validar sesión y conexión
$id_profesor = $_SESSION['id_usuario'] ?? 0;
if ($id_profesor <= 0) {
    // Si el ID del profesor es inválido, redirige o muestra un error.
    // En un entorno real, es posible que quieras un manejo más elegante que un 'die()'.
    header("Location: ../login.php?error=invalid_professor_id");
    exit;
}

$estudiantes = []; // Inicializar para asegurar que siempre sea un array
$error_message = '';

try {
    // Consulta para obtener todas las asignaturas asignadas al profesor y los estudiantes
    // inscritos en ellas con estado 'confirmado', agrupados por año académico.
    $sql = "SELECT
        a.id_asignatura,
        a.nombre AS nombre_asignatura,
        a.descripcion AS descripcion_asignatura,
        c.id_curso,
        c.nombre AS nombre_curso,
        c.turno,
        c.grupo,
        s.id_semestre,
        s.nombre AS nombre_semestre,
        an.id_anio,
        an.anio AS anio_academico,
        u.id_usuario AS id_estudiante,
        u.nombre AS nombre_estudiante,
        u.apellido AS apellido_estudiante,
        u.dni AS dni_estudiante,
        e.matricula,
        i.id_inscripcion -- Incluir id_inscripcion si es necesario para futuras acciones (ej. notas)
    FROM
        asignaturas a
    INNER JOIN
        asignatura_profesor ap ON ap.id_asignatura = a.id_asignatura
    INNER JOIN
        cursos c ON a.curso_id = c.id_curso
    INNER JOIN
        semestres s ON a.semestre_id = s.id_semestre
    INNER JOIN
        inscripciones i ON i.id_asignatura = a.id_asignatura AND i.id_estudiante = i.id_estudiante -- Link through inscriptions
    INNER JOIN
        usuarios u ON i.id_estudiante = u.id_usuario AND u.rol = 'estudiante' -- Ensure it's a student user
    INNER JOIN
        estudiantes e ON u.id_usuario = e.id_estudiante
    INNER JOIN
        anios_academicos an ON i.id_anio = an.id_anio -- Get academic year info
    WHERE
        ap.id_profesor = :id_profesor
        AND i.estado = 'confirmado' -- Solo inscripciones confirmadas
    ORDER BY
        an.anio DESC, -- Ordenar por año más reciente primero
        c.nombre,
        s.nombre,
        a.nombre,
        u.apellido,
        u.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_profesor' => $id_profesor]);
    $estudiantes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar los estudiantes por Asignatura, Curso, Semestre y Año Académico
    $grupos = [];
    foreach ($estudiantes_raw as $est) {
        // Clave de agrupación más precisa, incluyendo el año académico
        $key = $est['id_asignatura'] . '_' . $est['id_curso'] . '_' . $est['id_semestre'] . '_' . $est['id_anio'];

        // Inicializar el grupo si no existe
        if (!isset($grupos[$key])) {
            $grupos[$key] = [
                'id_asignatura' => $est['id_asignatura'],
                'nombre_asignatura' => $est['nombre_asignatura'] ?? 'Sin nombre',
                'id_curso' => $est['id_curso'],
                'nombre_curso' => $est['nombre_curso'] ?? 'Curso desconocido',
                'turno' => $est['turno'] ?? 'N/D',
                'grupo_curso' => $est['grupo'] ?? 'N/D', // Cambiado a grupo_curso para evitar conflicto
                'id_semestre' => $est['id_semestre'],
                'nombre_semestre' => $est['nombre_semestre'] ?? 'Semestre desconocido',
                'id_anio' => $est['id_anio'],
                'anio_academico' => $est['anio_academico'] ?? 'Año desconocido',
                'estudiantes' => []
            ];
        }
        $grupos[$key]['estudiantes'][] = $est;
    }

} catch (PDOException $e) {
    $error_message = "Error en la base de datos al cargar estudiantes: " . $e->getMessage();
    error_log("Error en lista_estudiantes_por_asignatura.php: " . $e->getMessage());
    $grupos = []; // Asegúrate de que $grupos sea un array vacío para evitar errores en la vista
}

?>

<?php include 'header.php'; // Incluye tu archivo de cabecera ?>

<style>
    body {
        font-family: "Inter", sans-serif;
        background-color: #f0f2f5;
    }
    .container {
        max-width: 1200px;
        padding: 30px;
    }
    .card {
        border-radius: 0.75rem;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
    }
    .card-header-custom {
        background-color: #007bff;
        color: white;
        padding: 1.25rem 1.5rem;
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .card-header-custom h5 {
        margin-bottom: 0.25rem;
    }
    .card-header-custom small {
        color: rgba(255, 255, 255, 0.8);
    }
    .table thead th {
        background-color: #6c757d; /* Gris oscuro para las cabeceras de tabla internas */
        color: white;
        border-bottom: none;
    }
    .table-hover tbody tr:hover {
        background-color: #e2f0ff;
    }
    .badge {
        font-size: 0.85em;
        padding: 0.4em 0.7em;
        border-radius: 0.5rem;
        vertical-align: middle;
    }
    .bg-info-light {
        background-color: #e0f7fa; /* Light cyan */
        color: #007bff;
    }
</style>

<div id="main-content" class="main-content">
    <div class="container">
        <h2 class="mb-4 text-primary"><i class="bi bi-people-fill me-3"></i> Gestión de Estudiantes por Asignatura</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php elseif (empty($grupos)): ?>
            <div class="alert alert-info" role="alert">
                No tienes estudiantes confirmados en ninguna de tus asignaturas.
            </div>
        <?php else: ?>
            <?php foreach ($grupos as $grupo): ?>
                <div class="card mb-4">
                    <div class="card-header-custom">
                        <div>
                            <h5 class="mb-0">
                                <i class="bi bi-journal-bookmark me-2"></i><?= htmlspecialchars($grupo['nombre_asignatura']) ?>
                            </h5>
                            <small>
                                <span class="badge bg-info-light me-1"><i class="bi bi-calendar-check me-1"></i><?= htmlspecialchars($grupo['anio_academico']) ?></span>
                                <span class="badge bg-light text-dark me-1"><i class="bi bi-book-half me-1"></i><?= htmlspecialchars($grupo['nombre_curso']) ?></span>
                                <span class="badge bg-light text-dark me-1"><i class="bi bi-list-ol me-1"></i><?= htmlspecialchars($grupo['nombre_semestre']) ?></span>
                                <span class="badge bg-light text-dark me-1"><i class="bi bi-clock me-1"></i><?= htmlspecialchars(ucfirst($grupo['turno'])) ?></span>
                                <span class="badge bg-light text-dark"><i class="bi bi-people me-1"></i>Grupo <?= htmlspecialchars($grupo['grupo_curso']) ?></span>
                            </small>
                        </div>
                        <a href="../reports/listas.php?id_asignatura=<?= urlencode($grupo['id_asignatura']) ?>&id_curso=<?= urlencode($grupo['id_curso']) ?>&id_semestre=<?= urlencode($grupo['id_semestre']) ?>&id_anio=<?= urlencode($grupo['id_anio']) ?>"
                           class="btn btn-outline-light rounded-pill px-3" target="_blank" title="Generar lista de estudiantes para esta clase">
                            <i class="bi bi-file-earmark-pdf me-2"></i> Generar PDF
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 align-middle">
                                <thead class="text-white">
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Apellido</th>
                                        <th>Matrícula</th>
                                        <th>DNI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grupo['estudiantes'] as $idx => $est): ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td><?= htmlspecialchars($est['nombre_estudiante'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($est['apellido_estudiante'] ?? '') ?></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($est['matricula'] ?? '') ?></span></td>
                                            <td><?= htmlspecialchars($est['dni_estudiante'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; // Incluye tu archivo de pie de página ?>
