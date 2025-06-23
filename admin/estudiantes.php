<?php include_once('header.php'); ?>
<?php require_once '../includes/conexion.php'; ?>

<?php
$pagina = isset($_GET['pagina']) ? max((int) $_GET['pagina'], 1) : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$por_pagina = 5;
$offset = ($pagina - 1) * $por_pagina;

$where = 'WHERE u.rol = "estudiante"';
$params = [];

if ($busqueda !== '') {
  $where .= ' AND (u.nombre LIKE :busqueda OR u.apellido LIKE :busqueda OR u.email LIKE :busqueda OR e.matricula LIKE :busqueda)';
  $params[':busqueda'] = "%$busqueda%";
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) 
    FROM estudiantes e 
    JOIN usuarios u ON e.id_estudiante = u.id_usuario 
    $where");
$totalStmt->execute($params);
$total_registros = $totalStmt->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

$sql = "SELECT u.*, e.matricula, e.id_curso, c.nombre AS nombre_curso
        FROM estudiantes e
        JOIN usuarios u ON e.id_estudiante = u.id_usuario
        JOIN cursos c ON e.id_curso = c.id_curso
        $where
        ORDER BY u.nombre ASC
        LIMIT :offset, :limite";
$stmt = $pdo->prepare($sql);

if ($busqueda !== '') {
  $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$stmt->execute();

$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">🎓 Gestión de Estudiantes</h3>
    </div>

    <form method="GET" class="mb-3" style="max-width: 400px;">
      <div class="input-group">
        <input type="search" name="busqueda" class="form-control" placeholder="Buscar por nombre, email o matrícula"
          value="<?= htmlspecialchars($busqueda) ?>">
        <button class="btn btn-primary" type="submit">Buscar</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead class="table-primary">
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Email / Matrícula</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($estudiantes) === 0): ?>
            <tr>
              <td colspan="5" class="text-center">No se encontraron estudiantes</td>
            </tr>
          <?php else: ?>
            <?php foreach ($estudiantes as $e): ?>
              <tr>
                <td><?= $e['id_usuario'] ?></td>
                <td><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?></td>
                <td><?= htmlspecialchars($e['email']) ?><br><small class="text-muted"><?= $e['matricula'] ?></small></td>
                <td>
                 
                  <button class="btn btn-sm btn-info btnVerHistorial" data-id="<?= $e['id_usuario'] ?>">
  <i class="bi bi-journal-text"></i> Ver Historial
</button>

                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <nav>
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Anterior</a>
        </li>
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
          <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
            <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
          <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<!-- Modal Historial CORREGIDO -->
<div class="modal fade" id="modalHistorial" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title">📚 Historial Académico</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="contenidoHistorial">
        <p class="text-center">Cargando historial...</p>
      </div>
      <div class="modal-footer">
        <a href="#" target="_blank" class="btn btn-outline-dark" id="btnImprimirPDF"><i class="bi bi-printer"></i> Imprimir</a>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- JS Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script funcional -->
<!-- Script funcional corregido -->
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('modalHistorial');
    const modalHistorial = new bootstrap.Modal(modalElement);
    const contenidoHistorial = document.getElementById('contenidoHistorial');
    const btnImprimirPDF = document.getElementById('btnImprimirPDF');

    document.querySelectorAll('.btnVerHistorial').forEach(btn => {
      btn.addEventListener('click', () => {
        const idEstudiante = btn.getAttribute('data-id');

        console.log('✅ Botón clicado con ID:', idEstudiante);

        contenidoHistorial.innerHTML = '<p class="text-center">Cargando...</p>';
        btnImprimirPDF.href = `../reports/historial_estudiante.php?id_estudiante=${idEstudiante}`;

        fetch(`../api/historial_estudiante.php?id_estudiante=${idEstudiante}`)
          .then(res => {
            if (!res.ok) throw new Error('Respuesta no válida');
            return res.json();
          })
          .then(data => {
            if (!data.estudiante) {
              contenidoHistorial.innerHTML = '<p class="text-danger">No se encontró información del estudiante.</p>';
              return;
            }

            let html = `
              <h5 class="text-primary">👤 ${data.estudiante.nombre} ${data.estudiante.apellido}</h5>
              <p><strong>Matrícula:</strong> ${data.estudiante.matricula}</p>
              <hr>
              <table class="table table-bordered table-striped table-sm">
                <thead class="table-light">
                  <tr>
                    <th>Asignatura</th>
                    <th>Curso</th>
                    <th>Semestre</th>
                    <th>Parcial 1</th>
                    <th>Parcial 2</th>
                    <th>Examen Final</th>
                    <th>Promedio</th>
                    <th>Observaciones</th>
                  </tr>
                </thead>
                <tbody>`;

            data.notas.forEach(n => {
              html += `<tr>
                <td>${n.asignatura}</td>
                <td>${n.curso}</td>
                <td>${n.semestre}</td>
                <td>${n.parcial_1 ?? '-'}</td>
                <td>${n.parcial_2 ?? '-'}</td>
                <td>${n.examen_final ?? '-'}</td>
                <td><strong>${n.promedio ?? '-'}</strong></td>
                <td>${n.observaciones ?? ''}</td>
              </tr>`;
            });

            html += '</tbody></table>';
            contenidoHistorial.innerHTML = html;
            modalHistorial.show();
          })
          .catch(err => {
            console.error('❌ Error al cargar historial:', err);
            contenidoHistorial.innerHTML = '<p class="text-danger">Error al cargar historial.</p>';
          });
      });
    });
  });
</script>



<?php include_once('footer.php'); ?>
