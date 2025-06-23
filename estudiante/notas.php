<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit;
}

require '../includes/conexion.php';

$id_estudiante = $_SESSION['id_usuario'] ?? null;
if (!$id_estudiante) {
    exit("Error: Estudiante no identificado.");
}

// Obtener año académico activo
$stmt = $pdo->query("SELECT id_anio, anio FROM anios_academicos WHERE activo = 1 LIMIT 1");
$anioActivo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$anioActivo) {
    exit("No hay un año académico activo definido.");
}
$id_anio = $anioActivo['id_anio'];

// Obtener datos del estudiante
$stmt = $pdo->prepare("
    SELECT u.nombre,
     u.apellido, e.matricula, 
    c.nombre AS curso_actual
    FROM estudiantes e
    LEFT JOIN cursos c ON c.id_curso = e.id_curso
    LEFT JOIN usuarios u ON e.id_estudiante = u.id_usuario
    WHERE e.id_estudiante = ?");
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$estudiante) {
    exit("Estudiante no encontrado.");
}

// Obtener cursos a los que pertenece el estudiante
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id_curso, c.nombre AS nombre_curso
    FROM asignatura_estudiante ae
    JOIN asignaturas a ON ae.id_asignatura = a.id_asignatura
    JOIN cursos c ON a.curso_id = c.id_curso
    WHERE ae.id_estudiante = ?
    ORDER BY c.nombre
");
$stmt->execute([$id_estudiante]);
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$boletin = [];

foreach ($cursos as $curso) {
    $curso_id = $curso['id_curso'];

    // Obtener semestres del curso
    $stmt = $pdo->prepare("
        SELECT s.id_semestre, s.nombre AS nombre_semestre
        FROM semestres s
        WHERE s.curso_id = ?
        ORDER BY s.id_semestre ASC
    ");
    $stmt->execute([$curso_id]);
    $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $boletin[$curso['nombre_curso']] = [];

    foreach ($semestres as $semestre) {
        $semestre_id = $semestre['id_semestre'];

        // Obtener asignaturas inscritas en este semestre y curso
        $stmt = $pdo->prepare("
            SELECT a.id_asignatura, a.nombre AS asignatura
            FROM asignatura_estudiante ae
            JOIN asignaturas a ON ae.id_asignatura = a.id_asignatura
            WHERE ae.id_estudiante = ?
              AND a.curso_id = ?
              AND a.semestre_id = ?
            ORDER BY a.nombre
        ");
        $stmt->execute([$id_estudiante, $curso_id, $semestre_id]);
        $asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notas_semestre = [];

        foreach ($asignaturas as $asignatura) {
            // Obtener notas filtrando por año académico
            $stmt = $pdo->prepare("
                SELECT parcial_1, parcial_2, examen_final, promedio, observaciones
                FROM notas
                WHERE id_estudiante = ? AND id_asignatura = ? AND id_anio = ?
            ");
            $stmt->execute([$id_estudiante, $asignatura['id_asignatura'], $id_anio]);
            $nota = $stmt->fetch(PDO::FETCH_ASSOC);

            $notas_semestre[] = [
                'asignatura'    => $asignatura['asignatura'],
                'parcial_1'     => $nota['parcial_1'] ?? '-',
                'parcial_2'     => $nota['parcial_2'] ?? '-',
                'examen_final'  => $nota['examen_final'] ?? '-',
                'promedio'      => $nota['promedio'] ?? '-',
                'observaciones' => $nota['observaciones'] ?? '',
            ];
        }

        $boletin[$curso['nombre_curso']][$semestre['nombre_semestre']] = $notas_semestre;
    }
}
?>

<?php include 'header.php'; ?>

<div class="container py-4">
  <h2 class="mb-4">Resumen Académico - Año <?= htmlspecialchars($anioActivo['anio']) ?></h2>
  <p><strong>Estudiante:</strong> <?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']) ?></p>
  <p><strong>Matrícula:</strong> <?= htmlspecialchars($estudiante['matricula']) ?></p>
  <p><strong>Curso actual:</strong> <?= htmlspecialchars($estudiante['curso_actual']) ?></p>

  <?php if (empty($boletin)): ?>
    <p>No hay notas registradas para mostrar.</p>
  <?php else: ?>
    <?php foreach ($boletin as $nombreCurso => $semestres): ?>
      <section class="mb-5">
        <h3 class="text-primary"><?= htmlspecialchars($nombreCurso) ?></h3>

        <?php foreach ($semestres as $nombreSemestre => $notas): ?>
          <h4 class="mt-3"><?= htmlspecialchars($nombreSemestre) ?></h4>
          <?php if (empty($notas)): ?>
            <p><em>No hay asignaturas registradas en este semestre.</em></p>
          <?php else: ?>
            <table class="table table-bordered table-striped">
              <thead class="table-secondary text-center">
                <tr>
                  <th>Asignatura</th>
                  <th>Parcial 1</th>
                  <th>Parcial 2</th>
                  <th>Examen Final</th>
                  <th>Promedio</th>
                  <th>Observaciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($notas as $nota): ?>
                  <tr>
                    <td><?= htmlspecialchars($nota['asignatura']) ?></td>
                    <td class="text-center"><?= is_numeric($nota['parcial_1']) ? number_format($nota['parcial_1'], 2) : $nota['parcial_1'] ?></td>
                    <td class="text-center"><?= is_numeric($nota['parcial_2']) ? number_format($nota['parcial_2'], 2) : $nota['parcial_2'] ?></td>
                    <td class="text-center"><?= is_numeric($nota['examen_final']) ? number_format($nota['examen_final'], 2) : $nota['examen_final'] ?></td>
                    <td class="text-center"><strong><?= is_numeric($nota['promedio']) ? number_format($nota['promedio'], 2) : $nota['promedio'] ?></strong></td>
                    <td><?= nl2br(htmlspecialchars($nota['observaciones'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
