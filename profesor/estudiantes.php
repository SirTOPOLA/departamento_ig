<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../login.php");
    exit;
}

require '../includes/conexion.php';

// Validar sesión y conexión
$id_profesor = $_SESSION['id_usuario'] ?? 0;
if ($id_profesor <= 0) {
    echo "ID de profesor inválido.";
    exit;
}

try {
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
        e.id_estudiante,
        u.nombre AS nombre_estudiante,
        u.apellido AS apellido_estudiante,
        u.dni AS dni_estudiante,
        e.matricula
    FROM asignaturas a
    INNER JOIN asignatura_profesor ap ON ap.id_asignatura = a.id_asignatura
    INNER JOIN cursos c ON a.curso_id = c.id_curso
    INNER JOIN semestres s ON a.semestre_id = s.id_semestre
    INNER JOIN asignatura_estudiante ae ON ae.id_asignatura = a.id_asignatura
    INNER JOIN estudiantes e ON ae.id_estudiante = e.id_estudiante
    INNER JOIN usuarios u ON e.id_estudiante = u.id_usuario
    WHERE ap.id_profesor = :id_profesor
    ORDER BY a.nombre, c.nombre, s.nombre, u.apellido, u.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_profesor' => $id_profesor]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}
?>

<?php include 'header.php'; ?>

<div id="main-content" class="main-content">
    <div class="container p-5">
        <h2 class="mb-4">Lista de Estudiantes por Asignatura</h2>

        <?php if (empty($estudiantes)): ?>
            <p>No tienes estudiantes asignados en ninguna asignatura.</p>
        <?php else: ?>
            <?php
            $grupos = [];
            foreach ($estudiantes as $est) {
                $key = $est['id_asignatura'] . '_' . $est['nombre_curso'] . '_' . $est['nombre_semestre'];

                $grupos[$key]['asignatura'] = $est['nombre_asignatura'] ?? 'Sin nombre';
                $grupos[$key]['curso'] = $est['nombre_curso'] ?? 'Curso desconocido';
                $grupos[$key]['turno'] = $est['turno'] ?? 'N/D';
                $grupos[$key]['grupo'] = $est['grupo'] ?? 'N/D';
                $grupos[$key]['semestre'] = $est['nombre_semestre'] ?? 'Semestre desconocido';
                $grupos[$key]['estudiantes'][] = $est;
            }
            ?>

            <?php foreach ($grupos as $grupo): ?>
                <div class="card mb-4 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars($grupo['asignatura']) ?></h5>
                            <small class="text-muted">
                                <?= htmlspecialchars($grupo['curso']) ?> -
                                <?= htmlspecialchars($grupo['semestre']) ?> - Grupo <?= htmlspecialchars($grupo['grupo']) ?> - Turno
                                <?= htmlspecialchars($grupo['turno']) ?>
                            </small>
                        </div>
                        <a href="../reports/listas.php?asignatura=<?= urlencode($grupo['asignatura']) ?>"
                           class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="bi bi-file-earmark-pdf"></i> Generar PDF
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
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
                                        <td><?= htmlspecialchars($est['matricula'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($est['dni_estudiante'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
