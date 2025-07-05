<?php
require_once '../includes/functions.php';
check_login_and_role('Estudiante');
require_once '../config/database.php';

$page_title = "PANEL de Estudiante";
include_once '../includes/header.php';

$id_usuario_logueado = $_SESSION['user_id'];

// Obtener información del estudiante
$stmtEstudiante = $pdo->prepare("SELECT u.nombre_completo, u.email, u.telefono, e.id AS estudiante_id, e.codigo_registro FROM usuarios u JOIN estudiantes e ON u.id = e.id_usuario WHERE u.id = :id_usuario_logueado AND u.id_rol = (SELECT id FROM roles WHERE nombre_rol = 'Estudiante')");
$stmtEstudiante->bindParam(':id_usuario_logueado', $id_usuario_logueado, PDO::PARAM_INT);
$stmtEstudiante->execute();
$estudiante_info = $stmtEstudiante->fetch(PDO::FETCH_ASSOC);

if (!$estudiante_info) {
    header('Location: ../auth/login.php');
    exit();
}

$estudiante_id = $estudiante_info['estudiante_id'];

// Año y semestre actuales
$current_anio_id = null;
$current_anio_nombre = 'N/A';
$current_semestre_id = null;
$current_semestre_numero = 'N/A';
$current_curso_nombre = 'N/A';

$stmtAnio = $pdo->query("SELECT id, nombre_anio FROM anios_academicos WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
$anio_activo = $stmtAnio->fetch(PDO::FETCH_ASSOC);

if ($anio_activo) {
    $current_anio_id = $anio_activo['id'];
    $current_anio_nombre = $anio_activo['nombre_anio'];

    $stmtSemestre = $pdo->prepare("SELECT s.id, s.numero_semestre, c.nombre_curso FROM semestres s JOIN cursos c ON s.id_curso_asociado_al_semestre = c.id WHERE s.id_anio_academico = :anio_id AND CURDATE() BETWEEN s.fecha_inicio AND s.fecha_fin LIMIT 1");
    $stmtSemestre->bindParam(':anio_id', $current_anio_id, PDO::PARAM_INT);
    $stmtSemestre->execute();
    $semestre_actual = $stmtSemestre->fetch(PDO::FETCH_ASSOC);

    if ($semestre_actual) {
        $current_semestre_id = $semestre_actual['id'];
        $current_semestre_numero = $semestre_actual['numero_semestre'];
        $current_curso_nombre = $semestre_actual['nombre_curso'];
    }
}

$total_asignaturas_inscritas = 0;
if ($current_semestre_id) {
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM inscripciones_estudiantes WHERE id_estudiante = :estudiante_id AND id_semestre = :semestre_id AND confirmada = 1");
    $stmt->bindParam(':estudiante_id', $estudiante_id);
    $stmt->bindParam(':semestre_id', $current_semestre_id);
    $stmt->execute();
    $total_asignaturas_inscritas = $stmt->fetchColumn();
}

$proximas_clases_hoy = [];
$dia_semana_actual = date('N');
$dia_nombre = ['','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'][$dia_semana_actual];

if ($current_semestre_id && $dia_nombre) {
    $stmt = $pdo->prepare("SELECT h.hora_inicio, h.hora_fin, a.nombre_asignatura, u.nombre_completo AS profesor_nombre, au.nombre_aula, c.nombre_curso, h.turno FROM horarios h JOIN asignaturas a ON h.id_asignatura = a.id JOIN profesores p ON h.id_profesor = p.id JOIN usuarios u ON p.id_usuario = u.id JOIN aulas au ON h.id_aula = au.id JOIN cursos c ON h.id_curso = c.id JOIN inscripciones_estudiantes ie ON ie.id_asignatura = a.id AND ie.id_semestre = h.id_semestre WHERE ie.id_estudiante = :id_estudiante AND h.id_semestre = :id_semestre AND h.dia_semana = :dia_semana AND h.hora_inicio >= CURTIME() ORDER BY h.hora_inicio LIMIT 3");
    $stmt->execute([':id_estudiante' => $estudiante_id, ':id_semestre' => $current_semestre_id, ':dia_semana' => $dia_nombre]);
    $proximas_clases_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$ultimas_notas = [];
$stmt = $pdo->prepare("SELECT ha.nota_final, ha.estado_final, a.nombre_asignatura, s.numero_semestre FROM historial_academico ha JOIN asignaturas a ON ha.id_asignatura = a.id JOIN semestres s ON ha.id_semestre = s.id WHERE ha.id_estudiante = :id_estudiante ORDER BY ha.fecha_actualizacion DESC LIMIT 3");
$stmt->execute([':id_estudiante' => $estudiante_id]);
$ultimas_notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container my-5">
  <h2 class="text-center mb-4 fw-bold text-primary">
    <i class="bi bi-mortarboard-fill me-2"></i>Panel del Estudiante
  </h2>

  <div class="row g-4">

    <!-- Información personal -->
    <div class="col-lg-6">
      <div class="card shadow-sm border-start border-success border-4 rounded-4">
        <div class="card-body">
          <h5 class="card-title text-success"><i class="bi bi-person-badge-fill me-1"></i>Mis Datos</h5>
          <ul class="list-unstyled mb-0">
            <li><strong>Nombre:</strong> <?= $estudiante_info['nombre_completo'] ?></li>
            <li><strong>Código:</strong> <?= $estudiante_info['codigo_registro'] ?></li>
            <li><strong>Email:</strong> <?= $estudiante_info['email'] ?></li>
            <li><strong>Tel.:</strong> <?= $estudiante_info['telefono'] ?? 'N/A' ?></li>
            <li><strong>Año:</strong> <?= $current_anio_nombre ?></li>
            <li><strong>Semestre:</strong> <?= $current_semestre_numero ?> (<?= $current_curso_nombre ?>)</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Estado académico -->
    <div class="col-lg-6">
      <div class="card shadow-sm border-start border-warning border-4 rounded-4">
        <div class="card-body">
          <h5 class="card-title text-warning"><i class="bi bi-bar-chart-line-fill me-1"></i>Estado Académico</h5>
          <ul class="list-unstyled mb-0">
            <li><strong>Asignaturas inscritas:</strong> <?= $total_asignaturas_inscritas ?></li>
            <li><strong>Clases programadas hoy:</strong> <?= count($proximas_clases_hoy) ?></li>
            <li><strong>Notas recientes:</strong> <?= count($ultimas_notas) ?></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Próximas clases -->
    <div class="col-lg-8">
      <div class="card shadow-sm border-start border-info border-4 rounded-4">
        <div class="card-body">
          <h5 class="card-title text-info"><i class="bi bi-clock-history me-1"></i>Próximas Clases Hoy (<?= $dia_nombre ?>)</h5>
          <?php if ($proximas_clases_hoy): ?>
            <div class="list-group list-group-flush">
              <?php foreach ($proximas_clases_hoy as $clase): ?>
                <div class="list-group-item">
                  <div class="d-flex justify-content-between fw-bold">
                    <?= substr($clase['hora_inicio'], 0, 5) ?> - <?= substr($clase['hora_fin'], 0, 5) ?>
                    <span class="text-muted small"><?= $clase['turno'] ?> • <?= $clase['nombre_aula'] ?></span>
                  </div>
                  <div><?= $clase['nombre_asignatura'] ?> – <?= $clase['nombre_curso'] ?><br><small class="text-muted">Profesor: <?= $clase['profesor_nombre'] ?></small></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted">No tienes clases programadas para hoy.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Últimas notas -->
    <div class="col-lg-4">
      <div class="card shadow-sm border-start border-success border-4 rounded-4">
        <div class="card-body">
          <h5 class="card-title text-success"><i class="bi bi-clipboard-check me-1"></i>Últimas Notas</h5>
          <?php if ($ultimas_notas): ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($ultimas_notas as $nota): ?>
                <li class="list-group-item d-flex justify-content-between">
                  <div>
                    <strong><?= $nota['nombre_asignatura'] ?></strong><br>
                    <small>Semestre <?= $nota['numero_semestre'] ?></small>
                  </div>
                  <span class="badge bg-<?= $nota['estado_final'] == 'APROBADO' ? 'success' : 'danger' ?>">
                    <?= $nota['nota_final'] ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
            <a href="historial_academico.php" class="btn btn-outline-success btn-sm mt-2 w-100">Ver todas</a>
          <?php else: ?>
            <p class="text-muted">No hay notas recientes.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Panel inferior (resumen accesos) -->
    <div class="col-md-4">
      <div class="card border-start border-primary border-4 shadow-sm">
        <div class="card-body">
          <h6 class="card-title text-primary"><i class="bi bi-calendar-week"></i> Horario Semanal</h6>
          <p class="text-muted mb-1">Consulta tu horario completo en la sección de clases.</p>
          <small>Última actualización: <?= date('d/m/Y') ?></small>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card border-start border-primary border-4 shadow-sm">
        <div class="card-body">
          <h6 class="card-title text-primary"><i class="bi bi-pencil-square"></i> Inscripción</h6>
          <p class="text-muted mb-1">Asignaturas confirmadas: <?= $total_asignaturas_inscritas ?></p>
          <small>Consulta con coordinación académica si hay cambios.</small>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card border-start border-primary border-4 shadow-sm">
        <div class="card-body">
          <h6 class="card-title text-primary"><i class="bi bi-book"></i> Asignaturas</h6>
          <p class="text-muted mb-1">Carga académica actualizada.</p>
          <small>Corrobora en tu plan de estudios oficial.</small>
        </div>
      </div>
    </div>
  </div>
</div>




<style>
    .card-title {
  font-weight: 600;
  margin-bottom: 0.75rem;
}

.card ul li {
  margin-bottom: 0.5rem;
}

.card {
  transition: transform 0.2s ease-in-out;
}

.card:hover {
  transform: translateY(-4px);
}

.badge {
  font-size: 1rem;
  padding: 0.5em 0.75em;
  border-radius: 0.5rem;
}

</style>
<?php include_once '../includes/footer.php'; ?>
