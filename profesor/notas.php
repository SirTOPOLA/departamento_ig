<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../login.php");
    exit;
}

require '../includes/conexion.php';

$id_profesor = $_SESSION['id_usuario'] ?? null;
if (!$id_profesor) {
    die("Error: Profesor no identificado.");
}


// Calcular año académico actual
$mes_actual = date('n');
$anio_inicio = $mes_actual >= 8 ? date('Y') : date('Y') - 1;
$anio_fin = $anio_inicio + 1;
$anio_actual = "{$anio_inicio}-{$anio_fin}";

// Buscar el año académico en la base de datos
$anio = $pdo->prepare("SELECT * FROM anios_academicos WHERE anio = :anio LIMIT 1");
$anio->execute(['anio' => $anio_actual]);
$anio = $anio->fetch(PDO::FETCH_ASSOC);

$mensaje_anio = null;
if (!$anio) {
    // Mostrar un mensaje bonito si no existe
    $mensaje_anio = "⚠️ El año académico <strong>$anio_actual</strong> no está registrado en el sistema.";
} else {
    $id_anio = $anio['id_anio'];
}



// Obtener asignaturas que imparte el profesor
$stmt = $pdo->prepare("
    SELECT a.id_asignatura, a.nombre 
    FROM asignaturas a
    INNER JOIN asignatura_profesor ap ON ap.id_asignatura = a.id_asignatura
    WHERE ap.id_profesor = :id_profesor
");
$stmt->execute(['id_profesor' => $id_profesor]);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$asignatura_id = $_GET['asignatura_id'] ?? null;
$estudiantes = [];

if ($asignatura_id) {
    // Obtener estudiantes y notas si ya existen
    $stmt = $pdo->prepare("
    SELECT 
        u.id_usuario, u.nombre, u.apellido, es.matricula,
        n.parcial_1, n.parcial_2, n.examen_final, n.promedio, n.observaciones
    FROM usuarios u
    INNER JOIN estudiantes es ON es.id_estudiante = u.id_usuario
    INNER JOIN asignatura_estudiante ae ON ae.id_estudiante = es.id_estudiante
    LEFT JOIN notas n ON n.id_estudiante = es.id_estudiante 
                     AND n.id_asignatura = :asignatura_id
                     AND n.id_anio = :id_anio
    WHERE ae.id_asignatura = :asignatura_id
");

    $stmt->execute([
        'asignatura_id' => $asignatura_id,
        'id_anio' => $id_anio
    ]);

    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include 'header.php'; ?>

<div id="main-content" class="main-content">
    <div class="container p-5">
        <h3>📑 Registro de Notas</h3>

        <!-- Selección de asignatura -->
        <form method="GET" class="row g-3 align-items-end mb-4">
            <div class="col-md-6">
                <label for="asignatura_id" class="form-label">Seleccionar asignatura:</label>
                <select name="asignatura_id" id="asignatura_id" class="form-select" onchange="this.form.submit()"
                    required>
                    <option value="">-- Elija una asignatura --</option>
                    <?php foreach ($asignaturas as $a): ?>
                        <option value="<?= $a['id_asignatura'] ?>" <?= $asignatura_id == $a['id_asignatura'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($asignatura_id): ?>
            <?php if (empty($estudiantes)): ?>
                <div class="alert alert-warning">No hay estudiantes inscritos en esta asignatura.</div>
            <?php else: ?>
                <form action="../api/guardar_notas.php" method="POST">
                    <input type="hidden" name="asignatura_id" value="<?= htmlspecialchars($asignatura_id) ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Estudiante</th>
                                    <th>Matrícula</th>
                                    <th>Parcial 1</th>
                                    <th>Parcial 2</th>
                                    <th>Examen Final</th>
                                    <th>Promedio</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $i => $e): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?></td>
                                        <td><?= htmlspecialchars($e['matricula']) ?></td>
                                        <?php $readonly = ($e['parcial_1'] !== null || $e['parcial_2'] !== null || $e['examen_final'] !== null) ? 'readonly' : ''; ?>

                                        <td><input type="number" step="0.01" name="parcial_1[<?= $e['id_usuario'] ?>]"
                                                class="form-control" value="<?= $e['parcial_1'] ?>" <?= $readonly ?>></td>

                                        <td><input type="number" step="0.01" name="parcial_2[<?= $e['id_usuario'] ?>]"
                                                class="form-control" value="<?= $e['parcial_2'] ?>" <?= $readonly ?>></td>

                                        <td><input type="number" step="0.01" name="examen_final[<?= $e['id_usuario'] ?>]"
                                                class="form-control" value="<?= $e['examen_final'] ?>" <?= $readonly ?>></td>

                                        <td class="text-center">
                                            <strong><?= $e['promedio'] !== null ? number_format($e['promedio'], 2) : '-' ?></strong>
                                        </td>

                                        <td><input type="text" name="observaciones[<?= $e['id_usuario'] ?>]" class="form-control"
                                                value="<?= htmlspecialchars($e['observaciones']) ?>" <?= $readonly ?>></td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar Notas</button>
                    </div>
                    <!-- <div class="mt-3">
            <a href="../reports/listas_admin.php?asignatura_id=<?= $asignatura_id ?>" target="_blank"
                class="btn btn-primary">
                <i class="bi bi-file-earmark-pdf"></i> Generar Reporte PDF
            </a>
        </div> -->
                </form>
            <?php endif; ?>
        <?php endif; ?>
       

    </div>
</div>

<?php include 'footer.php'; ?>