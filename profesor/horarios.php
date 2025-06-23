<?php
session_start();
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'profesor' && $_SESSION['rol'] !== 'administrador')) {
    header("Location: ../login.php");
    exit;
}

require '../includes/conexion.php';

// Obtener horarios
$sql = "SELECT 
    h.id_horario,
    a.nombre AS asignatura,
    CONCAT(u.nombre, ' ', u.apellido) AS profesor,
    au.nombre AS aula,
    au.capacidad,
    au.ubicacion,
    h.dia,
    DATE_FORMAT(h.hora_inicio, '%H:%i') AS hora_inicio,
    DATE_FORMAT(h.hora_fin, '%H:%i') AS hora_fin,
    c.nombre AS curso,
    c.turno,
    c.grupo,
    s.nombre AS semestre
FROM horarios h
INNER JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
INNER JOIN profesores p ON h.id_profesor = p.id_profesor
INNER JOIN usuarios u ON p.id_profesor = u.id_usuario
INNER JOIN aulas au ON h.aula_id = au.id_aula
INNER JOIN cursos c ON a.curso_id = c.id_curso
INNER JOIN semestres s ON a.semestre_id = s.id_semestre
ORDER BY 
    h.hora_inicio,
    FIELD(h.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes')";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener datos del departamento
$dep = $pdo->query("SELECT * FROM departamento LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

$rangos_horarios = [];
foreach ($horarios as $h) {
    $rango = $h['hora_inicio'] . ' - ' . $h['hora_fin'];
    if (!in_array($rango, $rangos_horarios)) {
        $rangos_horarios[] = $rango;
    }
}

$tablaHorarios = [];
foreach ($rangos_horarios as $rango) {
    foreach ($dias as $dia) {
        $tablaHorarios[$rango][$dia] = [];
    }
}

foreach ($horarios as $h) {
    $rango = $h['hora_inicio'] . ' - ' . $h['hora_fin'];
    $tablaHorarios[$rango][$h['dia']][] = $h;
}

$cursosUnicos = [];
$semestresUnicos = [];
foreach ($horarios as $h) {
    $cursoSemestre = $h['curso'] . ' - ' . $h['semestre'];
    if (!in_array($cursoSemestre, $cursosUnicos)) {
        $cursosUnicos[] = $cursoSemestre;
        $semestresUnicos[] = ['curso' => $h['curso'], 'semestre' => $h['semestre']];
    }
}
?>
<?php include 'header.php'; ?>

<div id="main-content" class="main-content">
    <div class="container p-5">

       

        <h2>Horario Semanal</h2>

        <!-- Curso y semestre -->
        <div class="mb-4">
            <?php foreach ($semestresUnicos as $info): ?>
                <div>
                    <strong>Curso:</strong> <?= htmlspecialchars($info['curso']) ?> |
                    <strong>Semestre:</strong> <?= htmlspecialchars($info['semestre']) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($horarios)): ?>
            <div class="alert alert-info">No hay horarios registrados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-primary text-center">
                        <tr>
                            <th>Hora</th>
                            <?php foreach ($dias as $dia): ?>
                                <th><?= htmlspecialchars($dia) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tablaHorarios as $rango => $diasClases): ?>
                            <tr>
                                <td class="fw-bold text-center"><?= htmlspecialchars($rango) ?></td>
                                <?php foreach ($dias as $dia): ?>
                                    <td>
                                        <?php if (!empty($diasClases[$dia])): ?>
                                            <?php foreach ($diasClases[$dia] as $clase): ?>
                                                <div class="border rounded p-2 mb-2 bg-light shadow-sm" style="font-size:0.9em;">
                                                    <strong><?= htmlspecialchars($clase['asignatura']) ?></strong><br>
                                                    Prof.: <?= htmlspecialchars($clase['profesor']) ?><br>
                                                    Aula: <?= htmlspecialchars($clase['aula']) ?> (<?= htmlspecialchars($clase['capacidad']) ?>)<br>
                                                    <?= htmlspecialchars($clase['ubicacion']) ?>
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
        <?php endif; ?>

    </div>
</div>

<!-- Estilos de impresión -->
<style>
@media print {
    .d-print-none {
        display: none !important;
    }

    body {
        background: #fff;
        color: #000;
    }

    .table th, .table td {
        font-size: 0.8em;
    }

    .container {
        padding: 0 !important;
    }

    .border, .shadow-sm {
        border: 1px solid #ccc !important;
        box-shadow: none !important;
    }
}
</style>

<?php include 'footer.php'; ?>
