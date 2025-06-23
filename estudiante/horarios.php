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

// Obtener asignaturas inscritas del estudiante
$stmt = $pdo->prepare("
    SELECT a.id_asignatura, a.nombre AS asignatura, a.curso_id, a.semestre_id
    FROM asignatura_estudiante ae
    JOIN asignaturas a ON ae.id_asignatura = a.id_asignatura
    WHERE ae.id_estudiante = ?
");
$stmt->execute([$id_estudiante]);
$asignaturasInscritas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($asignaturasInscritas)) {
    exit("No tiene asignaturas inscritas.");
}

$asignaturaIds = array_column($asignaturasInscritas, 'id_asignatura');

// Preparar días y obtener franjas horarias ordenadas
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Obtener todos los horarios del estudiante para sus asignaturas
$placeholders = implode(',', array_fill(0, count($asignaturaIds), '?'));
$sql = "
    SELECT h.dia, 
           DATE_FORMAT(h.hora_inicio, '%H:%i') AS hora_inicio,
           DATE_FORMAT(h.hora_fin, '%H:%i') AS hora_fin,
           a.nombre AS asignatura,
           CONCAT(u.nombre, ' ', u.apellido) AS profesor,
           au.nombre AS aula,
           au.ubicacion
    FROM horarios h
    JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
    JOIN profesores p ON h.id_profesor = p.id_profesor
    JOIN usuarios u ON p.id_profesor = u.id_usuario
    JOIN aulas au ON h.aula_id = au.id_aula
    WHERE h.id_asignatura IN ($placeholders)
    ORDER BY h.hora_inicio, FIELD(h.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado')
";
$stmt = $pdo->prepare($sql);
$stmt->execute($asignaturaIds);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener franjas horarias únicas ordenadas
$franjas = [];
foreach ($horarios as $h) {
    $rango = $h['hora_inicio'] . ' - ' . $h['hora_fin'];
    if (!in_array($rango, $franjas)) {
        $franjas[] = $rango;
    }
}

// Crear matriz vacía para la tabla horarios
$tablaHorarios = [];
foreach ($franjas as $franja) {
    foreach ($dias as $dia) {
        $tablaHorarios[$franja][$dia] = null;
    }
}

// Rellenar matriz con los datos de horarios
foreach ($horarios as $h) {
    $rango = $h['hora_inicio'] . ' - ' . $h['hora_fin'];
    $tablaHorarios[$rango][$h['dia']] = $h;
}

?>

<?php include 'header.php'; ?>

<div class="container py-5 px-3 px-md-4">
  <div class="bg-white shadow rounded-4 p-4">
    <h2 class="text-primary mb-4">
      <i class="bi bi-calendar-week me-2"></i> Horarios de Asignaturas
    </h2>

    <?php if (empty($horarios)): ?>
      <div class="alert alert-info text-center fw-semibold">
        <i class="bi bi-info-circle me-2"></i> No hay horarios registrados para sus asignaturas.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle text-center">
          <thead class="table-primary">
            <tr>
              <th scope="col" style="min-width: 90px;">Hora</th>
              <?php foreach ($dias as $dia): ?>
                <th scope="col" class="text-uppercase" style="min-width: 140px;">
                  <?= htmlspecialchars($dia) ?>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tablaHorarios as $rango => $diasClases): ?>
              <tr>
                <td class="fw-semibold text-primary"><?= htmlspecialchars($rango) ?></td>
                <?php foreach ($dias as $dia): ?>
                  <td class="text-start small px-2 py-3">
                    <?php if (!empty($diasClases[$dia])): ?>
                      <div class="fw-bold text-dark"><?= htmlspecialchars($diasClases[$dia]['asignatura']) ?></div>
                      <div><i class="bi bi-person-badge me-1 text-muted"></i> <?= htmlspecialchars($diasClases[$dia]['profesor']) ?></div>
                      <div><i class="bi bi-door-open me-1 text-muted"></i> Aula <?= htmlspecialchars($diasClases[$dia]['aula']) ?></div>
                      <div><i class="bi bi-geo-alt-fill me-1 text-muted"></i> <?= htmlspecialchars($diasClases[$dia]['ubicacion']) ?></div>
                    <?php else: ?>
                      <div class="text-muted text-center">—</div>
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

<?php include 'footer.php'; ?>
