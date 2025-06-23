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

// Obtener info básica del estudiante
$stmt = $pdo->prepare("
    SELECT u.nombre, u.apellido, e.matricula, c.nombre AS curso_actual
    FROM estudiantes e
    LEFT JOIN cursos c ON c.id_curso = e.id_curso 
    JOIN usuarios u ON e.id_estudiante = u.id_usuario
    WHERE e.id_estudiante = ?");
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$estudiante) {
    exit("Estudiante no encontrado.");
}

// Obtener asignaturas inscritas del estudiante
$stmt = $pdo->prepare("
    SELECT a.id_asignatura, a.nombre AS asignatura, s.nombre AS semestre, c.nombre AS curso
    FROM asignatura_estudiante ae
    JOIN asignaturas a ON ae.id_asignatura = a.id_asignatura
    LEFT JOIN semestres s ON a.semestre_id = s.id_semestre
    LEFT JOIN cursos c ON a.curso_id = c.id_curso
    WHERE ae.id_estudiante = ?");
$stmt->execute([$id_estudiante]);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener horarios para esas asignaturas
$idsAsignaturas = array_column($asignaturas, 'id_asignatura');
$horarios = [];
if (count($idsAsignaturas) > 0) {
    $in  = str_repeat('?,', count($idsAsignaturas) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT h.*, a.nombre AS asignatura, u.nombre AS profesor_nombre, u.apellido AS profesor_apellido,
               au.nombre AS aula, au.capacidad, au.ubicacion
        FROM horarios h
        JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
        JOIN profesores p ON h.id_profesor = p.id_profesor
        JOIN usuarios u ON p.id_profesor = u.id_usuario
        JOIN aulas au ON h.aula_id = au.id_aula
        WHERE h.id_asignatura IN ($in)
        ORDER BY FIELD(h.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'),
                 h.hora_inicio
    ");
    $stmt->execute($idsAsignaturas);
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener notas del estudiante
$stmt = $pdo->prepare("
    SELECT n.*, a.nombre AS asignatura
    FROM notas n
    JOIN asignaturas a ON n.id_asignatura = a.id_asignatura
    WHERE n.id_estudiante = ?");
$stmt->execute([$id_estudiante]);
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener publicaciones visibles
$stmt = $pdo->query("
    SELECT * FROM publicaciones
    WHERE visible = TRUE
    ORDER BY creado_en DESC
    LIMIT 5
");
$publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include 'header.php'; ?>

<div class="container py-4">

  <h2>Bienvenido, <?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']) ?></h2>
  <p><strong>Matrícula:</strong> <?= htmlspecialchars($estudiante['matricula']) ?></p>
  <p><strong>Curso actual:</strong> <?= htmlspecialchars($estudiante['curso_actual']) ?></p>

  <hr>

  <h3>Asignaturas Inscritas</h3>
  <?php if (empty($asignaturas)): ?>
    <p>No tienes asignaturas inscritas.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($asignaturas as $a): ?>
        <li>
          <?= htmlspecialchars($a['asignatura']) ?> 
          (Curso: <?= htmlspecialchars($a['curso']) ?>, Semestre: <?= htmlspecialchars($a['semestre']) ?>)
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <hr>

  <h3>Horario de Clases</h3>
  <?php if (empty($horarios)): ?>
    <p>No hay horarios asignados para tus asignaturas.</p>
  <?php else: ?>
    <table class="table table-striped table-bordered">
      <thead class="table-primary">
        <tr>
          <th>Día</th>
          <th>Hora</th>
          <th>Asignatura</th>
          <th>Profesor</th>
          <th>Aula</th>
          <th>Capacidad</th>
          <th>Ubicación</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($horarios as $h): ?>
          <tr>
            <td><?= htmlspecialchars($h['dia']) ?></td>
            <td><?= htmlspecialchars(substr($h['hora_inicio'], 0, 5)) ?> - <?= htmlspecialchars(substr($h['hora_fin'], 0, 5)) ?></td>
            <td><?= htmlspecialchars($h['asignatura']) ?></td>
            <td><?= htmlspecialchars($h['profesor_nombre'] . ' ' . $h['profesor_apellido']) ?></td>
            <td><?= htmlspecialchars($h['aula']) ?></td>
            <td><?= htmlspecialchars($h['capacidad']) ?></td>
            <td><?= htmlspecialchars($h['ubicacion']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <hr>

  <h3>Notas</h3>
  <?php if (empty($notas)): ?>
    <p>No tienes notas registradas aún.</p>
  <?php else: ?>
    <table class="table table-hover table-striped align-middle">
      <thead class="table-primary">
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
        <?php foreach ($notas as $n): ?>
          <tr>
            <td><?= htmlspecialchars($n['asignatura']) ?></td>
            <td><?= htmlspecialchars($n['parcial_1']) ?></td>
            <td><?= htmlspecialchars($n['parcial_2']) ?></td>
            <td><?= htmlspecialchars($n['examen_final']) ?></td>
            <td><strong><?= htmlspecialchars($n['promedio']) ?></strong></td>
            <td><?= nl2br(htmlspecialchars($n['observaciones'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <hr>

  <h3>Últimas Publicaciones</h3>
  <?php if (empty($publicaciones)): ?>
    <p>No hay publicaciones disponibles.</p>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($publicaciones as $pub): ?>
        <a href="#" class="list-group-item list-group-item-action flex-column align-items-start">
          <div class="d-flex w-100 justify-content-between">
            <h5 class="mb-1"><?= htmlspecialchars($pub['titulo']) ?></h5>
            <small><?= date('d/m/Y', strtotime($pub['creado_en'])) ?></small>
          </div>
          <p class="mb-1"><?= nl2br(htmlspecialchars(substr($pub['contenido'], 0, 150))) ?>...</p>
          <?php if ($pub['imagen']): ?>
            <img src="<?= htmlspecialchars($pub['imagen']) ?>" alt="Imagen" class="img-fluid mb-2" style="max-height: 150px;">
          <?php endif; ?>
          <?php if ($pub['archivo_adjunto']): ?>
            <a href="<?= htmlspecialchars($pub['archivo_adjunto']) ?>" target="_blank">Ver documento adjunto</a>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
