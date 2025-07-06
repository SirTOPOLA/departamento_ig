<?php
require_once '../includes/functions.php';
check_login_and_role('Estudiante');
require_once '../config/database.php';

$page_title = "Panel de Estudiante";
include_once '../includes/header.php';

$id_usuario_logueado = $_SESSION['user_id'];

// Obtener información del estudiante
$stmtEstudiante = $pdo->prepare("
  SELECT u.nombre_completo, u.email, u.telefono, e.id AS estudiante_id, e.codigo_registro
  FROM usuarios u
  JOIN estudiantes e ON u.id = e.id_usuario
  WHERE u.id = :id_usuario_logueado AND u.id_rol = (SELECT id FROM roles WHERE nombre_rol = 'Estudiante')
");
$stmtEstudiante->bindParam(':id_usuario_logueado', $id_usuario_logueado, PDO::PARAM_INT);
$stmtEstudiante->execute();
$estudiante_info = $stmtEstudiante->fetch(PDO::FETCH_ASSOC);

if (!$estudiante_info) {
  header('Location: ../auth/login.php');
  exit();
}

$estudiante_id = $estudiante_info['estudiante_id'];

// Resumen
$sqlResumen = "
SELECT
  SUM(CASE WHEN ha.estado_final = 'APROBADO' THEN 1 ELSE 0 END) AS aprobadas,
  SUM(CASE WHEN ha.estado_final = 'REPROBADO' THEN 1 ELSE 0 END) AS reprobadas,
  COUNT(*) AS total_asignaturas
FROM historial_academico ha
WHERE ha.id_estudiante = :id_estudiante
";
$stmt = $pdo->prepare($sqlResumen);
$stmt->execute(['id_estudiante' => $estudiante_id]);
$resumen = $stmt->fetch(PDO::FETCH_ASSOC);

// Curso actual
$sqlCurso = "
SELECT c.nombre_curso
FROM curso_estudiante ce
INNER JOIN cursos c ON ce.id_curso = c.id
WHERE ce.id_estudiante = :id_estudiante AND ce.estado = 'activo'
LIMIT 1
";
$stmt = $pdo->prepare($sqlCurso);
$stmt->execute(['id_estudiante' => $estudiante_id]);
$cursoActual = $stmt->fetchColumn() ?: '-';

// Notas por semestre
$sqlNotas = "
SELECT s.numero_semestre, AVG(ha.nota_final) as promedio
FROM historial_academico ha
INNER JOIN semestres s ON ha.id_semestre = s.id
WHERE ha.id_estudiante = :id_estudiante
GROUP BY s.numero_semestre
ORDER BY s.numero_semestre
";
$stmt = $pdo->prepare($sqlNotas);
$stmt->execute(['id_estudiante' => $estudiante_id]);
$notasPorSemestre = $stmt->fetchAll(PDO::FETCH_ASSOC);

$progreso = ($resumen['total_asignaturas'] > 0)
  ? round(($resumen['aprobadas'] / $resumen['total_asignaturas']) * 100)
  : 0;



  // Obtener las asignaturas inscritas
$sqlAsignaturas = "
SELECT 
  a.nombre_asignatura,
  s.numero_semestre,
  ga.grupo,
  ga.turno,
  ie.confirmada,
  ha.estado_final,
  ha.nota_final
FROM inscripciones_estudiantes ie
INNER JOIN asignaturas a ON ie.id_asignatura = a.id
INNER JOIN semestres s ON ie.id_semestre = s.id
LEFT JOIN grupos_asignaturas ga ON ie.id_grupo_asignatura = ga.id
LEFT JOIN historial_academico ha ON ie.id_estudiante = ha.id_estudiante AND ie.id_asignatura = ha.id_asignatura
WHERE ie.id_estudiante = :id_estudiante
ORDER BY s.numero_semestre, a.nombre_asignatura
";
$stmt = $pdo->prepare($sqlAsignaturas);
$stmt->execute(['id_estudiante' => $estudiante_id]);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container my-5">
  <h2 class="text-center mb-4 fw-bold text-primary">
    <i class="bi bi-mortarboard-fill me-2"></i>Panel del Estudiante
  </h2>

  <div class="row g-4 text-center">
    <div class="col-md-4">
      <div class="card shadow-sm rounded-4 h-100">
        <div class="card-body">
          <h6 class="text-success">Asignaturas Aprobadas</h6>
          <h2 class="fw-bold"><?= $resumen['aprobadas'] ?? 0 ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm rounded-4 h-100">
        <div class="card-body">
          <h6 class="text-danger">Asignaturas Reprobadas</h6>
          <h2 class="fw-bold"><?= $resumen['reprobadas'] ?? 0 ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm rounded-4 h-100">
        <div class="card-body">
          <h6 class="text-primary">Curso Actual</h6>
          <h2 class="fw-bold"><?= htmlspecialchars($cursoActual) ?></h2>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm rounded-4 mt-4">
    <div class="card-body">
      <h6 class="fw-bold mb-3">Progreso Académico</h6>
      <div class="progress mb-3" style="height: 25px;">
        <div class="progress-bar bg-gradient" style="width: <?= $progreso ?>%;">
          <?= $progreso ?>%
        </div>
      </div>
      <!-- <canvas id="chartNotas"></canvas> -->
    </div>
  </div>
  <div class="card shadow-sm rounded-4 mb-4">
  <div class="card-body">
    <h6 class="fw-bold mb-3">Información Personal</h6>
    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <tbody>
          <tr>
            <th class="bg-light">Nombre completo</th>
            <td><?= htmlspecialchars($estudiante_info['nombre_completo']) ?></td>
          </tr>
          <tr>
            <th class="bg-light">Email</th>
            <td><?= htmlspecialchars($estudiante_info['email']) ?></td>
          </tr>
          <tr>
            <th class="bg-light">Teléfono</th>
            <td><?= htmlspecialchars($estudiante_info['telefono']) ?: '-' ?></td>
          </tr>
          <tr>
            <th class="bg-light">Código de registro</th>
            <td><?= htmlspecialchars($estudiante_info['codigo_registro']) ?></td>
          </tr>
          <tr>
            <th class="bg-light">Curso actual</th>
            <td><?= htmlspecialchars($cursoActual) ?: '-' ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('chartNotas').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($notasPorSemestre, 'numero_semestre')) ?>,
    datasets: [{
      label: 'Promedio por semestre',
      data: <?= json_encode(array_map(fn($n) => round($n['promedio'], 2), $notasPorSemestre)) ?>,
      fill: true,
      borderColor: 'rgb(75, 192, 192)',
      backgroundColor: 'rgba(75,192,192,0.2)',
      tension: 0.3,
      pointBackgroundColor: 'rgb(75, 192, 192)'
    }]
  },
  options: {
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: {
        beginAtZero: true,
        max: 20,
        ticks: { stepSize: 2 }
      }
    }
  }
});
</script>

<style>
.card-body h6 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}
.progress-bar {
  font-weight: 600;
}
.card {
  transition: transform 0.2s ease-in-out;
}
.card:hover {
  transform: translateY(-3px);
}
</style>

<?php include_once '../includes/footer.php'; ?>
