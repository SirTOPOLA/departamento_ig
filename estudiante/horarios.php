<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../index.php");
    exit;
}
require '../includes/conexion.php';

// Días de clase
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Consulta general de horarios con información completa
$sql = "
SELECT 
    h.id_horario,
    h.dia,
    TIME_FORMAT(h.hora_inicio, '%H:%i') AS hora_inicio,
    TIME_FORMAT(h.hora_fin, '%H:%i') AS hora_fin,
    a.nombre AS asignatura,
    CONCAT(u.nombre, ' ', u.apellido) AS profesor,
    au.nombre AS aula,
    au.capacidad,
    au.ubicacion,
    c.id_curso,
    c.nombre AS curso,
    c.turno,
    c.grupo,
    s.id_semestre,
    s.nombre AS semestre
FROM horarios h
JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
JOIN profesores p ON h.id_profesor = p.id_profesor
JOIN usuarios u ON p.id_profesor = u.id_usuario
JOIN aulas au ON h.aula_id = au.id_aula
JOIN cursos c ON a.curso_id = c.id_curso
JOIN semestres s ON a.semestre_id = s.id_semestre
ORDER BY c.nombre, s.nombre, h.hora_inicio, FIELD(h.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado')";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar por curso-semestre
$datos = [];
$rangosUnicos = [];

foreach ($horarios as $h) {
    $clave = $h['curso'] . ' - Turno: ' . ucfirst($h['turno']) . ' - Grupo: ' . $h['grupo'] . ' | ' . $h['semestre'];
    $rango = $h['hora_inicio'] . ' - ' . $h['hora_fin'];

    if (!isset($datos[$clave])) {
        $datos[$clave] = [];
    }

    if (!in_array($rango, $rangosUnicos)) {
        $rangosUnicos[] = $rango;
    }

    $datos[$clave][$rango][$h['dia']][] = $h;
}
?>

<?php include 'header.php'; ?>

<section class="container py-5">

<?php if (empty($horarios)): ?>
    <div class="d-flex flex-column align-items-center justify-content-center text-center bg-light border shadow-sm rounded"
         style="min-height: 80vh;">
        <i class="bi bi-calendar-x fs-1 text-secondary mb-3"></i>
        <h4 class="text-muted">No se han registrado horarios</h4>
        <p class="text-muted">Por el momento, no hay información disponible sobre los horarios académicos.</p>
    </div>
<?php else: ?>

        <h3><i class="bi bi-calendar3-week"></i> Horarios Académicos por Semestre</h3>
        <p class="text-muted">Se muestran los horarios organizados por semestre y curso.</p>

        <?php foreach ($datos as $semestreNombre => $tabla): ?>
            <div class="card my-5 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= htmlspecialchars($semestreNombre) ?></h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>Hora</th>
                                <?php foreach ($dias as $dia): ?>
                                    <th><?= $dia ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rangosUnicos as $rango): ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $rango ?></td>
                                    <?php foreach ($dias as $dia): ?>
                                        <td>
                                            <?php if (!empty($tabla[$rango][$dia])): ?>
                                                <?php foreach ($tabla[$rango][$dia] as $h): ?>
                                                    <div class="p-2 mb-2 border rounded bg-light shadow-sm small">
                                                        <strong><?= $h['asignatura'] ?></strong><br>
                                                        Prof.: <?= $h['profesor'] ?><br>
                                                        Aula: <?= $h['aula'] ?> (<?= $h['capacidad'] ?>)<br>
                                                        <span class="text-muted"><?= $h['ubicacion'] ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>


<?php include 'footer.php'; ?>