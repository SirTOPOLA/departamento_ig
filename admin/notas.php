<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

$logPath = "../api/logs/log.txt";

// Obtener notas ya procesadas de la base de datos
$stmt = $pdo->query("SELECT n.*, u.nombre AS nombre_estudiante, u.apellido, a.nombre AS asignatura
                     FROM notas n
                     JOIN estudiantes e ON n.id_estudiante = e.id_estudiante
                     JOIN usuarios u ON e.id_estudiante = u.id_usuario
                     JOIN asignaturas a ON n.id_asignatura = a.id_asignatura
                     ORDER BY asignatura, nombre_estudiante");
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Leer líneas del log si existe
$lineasLog = [];
if (file_exists($logPath)) {
    $lineasLog = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}
?>

<div class="content" id="content" tabindex="-1">
<div class="container py-5">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-journal-check"></i> Gestión de Notas</h3>

    <button id="btnProcesarLog" class="btn btn-success">
      <i class="bi bi-arrow-repeat"></i> Procesar Notas Pendientes
      <span id="contadorLog" class="badge bg-danger ms-2"><?= count($lineasLog) ?></span>
    </button>
  </div>

  <!-- Sección: Notas pendientes en Log -->
  <div class="mb-4">
    <h5>Actías Pendientes (<?= count($lineasLog) ?>)</h5>
    <?php if (empty($lineasLog)): ?>
      <div class="alert alert-info">No hay actias pendientes.</div>
    <?php else: ?>
      <ul class="list-group" style="max-height: 250px; overflow-y: auto;">
        <?php foreach ($lineasLog as $idx => $linea): ?>
          <li class="list-group-item small">
            <?= htmlspecialchars($linea) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Tabla de notas ya registradas -->
  <div class="table-responsive">
   <table id="tablaNotas" class="table table-hover table-striped align-middle" style="width:100%">
      <thead class="table-primary">
        <tr>
          <th>#</th>
          <th>Estudiante</th>
          <th>Asignatura</th>
          <th>Parcial 1</th>
          <th>Parcial 2</th>
          <th>Final</th>
          <th>Prom.</th>
          <th>Obs.</th>
         <!--  <th>Acción</th> -->
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notas as $n): ?>
        <tr>
          <td><?= $n['id_nota'] ?></td>
          <td><?= htmlspecialchars($n['nombre_estudiante'] . ' ' . $n['apellido']) ?></td>
          <td><?= htmlspecialchars($n['asignatura']) ?></td>
          <td><?= $n['parcial_1'] ?></td>
          <td><?= $n['parcial_2'] ?></td>
          <td><?= $n['examen_final'] ?></td>
          <td><strong><?= $n['promedio'] ?></strong></td>
          <td><?= nl2br(htmlspecialchars($n['observaciones'])) ?></td>
         <!--  <td>
            <button class="btn btn-sm btn-primary" onclick="editarNota(<?= $n['id_nota'] ?>)">
              <i class="bi bi-pencil-square"></i>
            </button>
          </td> -->
        </tr>
        <?php endforeach; ?>
        <?php if (count($notas) === 0): ?>
        <tr><td colspan="9" class="text-center">No hay notas registradas</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<script>
  const btnProcesar = document.getElementById('btnProcesarLog');
  const contadorLog = document.getElementById('contadorLog');

  btnProcesar.addEventListener('click', async () => {
    if (contadorLog.textContent === '0') {
      alert('No hay actías pendientes.');
      return;
    }
    if (!confirm('¿Desea confirmar la actía? Esta acción no se puede deshacer.')) return;

    btnProcesar.disabled = true;
    btnProcesar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    try {
      const response = await fetch('../api/procesar_nota.php', { method: 'POST' });
      if (!response.ok) throw new Error('Error en el servidor');
      const result = await response.text();

      alert('Proceso finalizado:\n' + result);

      // Recargar la página para refrescar tablas y log
      location.reload();
    } catch (error) {
      alert('Error al procesar: ' + error.message);
    } finally {
      btnProcesar.disabled = false;
      btnProcesar.innerHTML = '<i class="bi bi-arrow-repeat"></i> Procesar Actía';
    }
  });



  
</script>

<?php include_once('footer.php'); ?>
