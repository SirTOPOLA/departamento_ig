<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Parámetros paginación y búsqueda
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$por_pagina = 5;

// Cargar todos los cursos para el modal
$stmtCursos = $pdo->query("SELECT * FROM cursos ORDER BY nombre");
$cursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

// Construir condición WHERE si hay búsqueda (en nombre semestre o nombre curso)
$where = "";
$params = [];
if ($busqueda !== '') {
  $where = "WHERE s.nombre LIKE :busqueda OR c.nombre LIKE :busqueda";
  $params[':busqueda'] = "%$busqueda%";
}

// Contar total para paginación
$sqlCount = "SELECT COUNT(*) FROM semestres s INNER JOIN cursos c ON s.curso_id = c.id_curso $where";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total_registros = $stmtCount->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

// Calcular offset
$offset = ($pagina -1) * $por_pagina;

// Consultar semestres con curso
$sql = "SELECT s.*, c.nombre AS nombre_curso FROM semestres s
        INNER JOIN cursos c ON s.curso_id = c.id_curso
        $where
        ORDER BY s.id_semestre DESC
        LIMIT :offset, :por_pagina";
$stmt = $pdo->prepare($sql);
if ($busqueda !== '') {
  $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">📅 Gestión de Semestres</h3>
    <button class="btn btn-success" onclick="abrirModalSemestre()">
      <i class="bi bi-plus-circle"></i> Nuevo Semestre
    </button>
  </div>

  <!-- Buscador -->
  <form method="GET" class="mb-3" style="max-width:400px;">
    <div class="input-group">
      <input type="search" name="busqueda" class="form-control" placeholder="Buscar semestre o curso"
        value="<?= htmlspecialchars($busqueda) ?>">
      <button class="btn btn-primary" type="submit">Buscar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover table-striped align-middle">
      <thead class="table-primary">
        <tr>
          <th>#</th>
          <th>Nombre Semestre</th>
          <th>Curso</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($semestres) === 0): ?>
          <tr>
            <td colspan="4" class="text-center">No se encontraron semestres</td>
          </tr>
        <?php else: ?>
          <?php foreach ($semestres as $s): ?>
            <tr>
              <td><?= $s['id_semestre'] ?></td>
              <td><?= htmlspecialchars($s['nombre']) ?></td>
              <td><?= htmlspecialchars($s['nombre_curso']) ?></td>
              <td>
                <button class="btn btn-sm btn-warning" onclick="editarSemestre(<?= $s['id_semestre'] ?>)">
                  <i class="bi bi-pencil-square"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <nav aria-label="Paginación de semestres">
    <ul class="pagination justify-content-center">
      <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="?pagina=<?= $pagina-1 ?>&busqueda=<?= urlencode($busqueda) ?>" tabindex="-1">Anterior</a>
      </li>

      <?php for ($i=1; $i <= $total_paginas; $i++): ?>
        <li class="page-item <?= ($pagina === $i) ? 'active' : '' ?>">
          <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>

      <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
        <a class="page-link" href="?pagina=<?= $pagina+1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
      </li>
    </ul>
  </nav>
</div>
</div>

<!-- Modal Semestre -->
<div class="modal fade" id="modalSemestre" tabindex="-1" aria-labelledby="modalSemestreLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="formSemestre" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalSemestreLabel"><i class="bi bi-calendar-event me-2"></i> Nuevo Semestre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_semestre" id="id_semestre">
        <div class="mb-3">
          <label for="nombre_semestre" class="form-label">Nombre del semestre</label>
          <input type="text" class="form-control" id="nombre_semestre" name="nombre" required>
        </div>
        <div class="mb-3">
          <label for="curso_semestre" class="form-label">Curso</label>
          <select id="curso_semestre" name="curso_id" class="form-select" required>
            <?php foreach ($cursos as $curso): ?>
              <option value="<?= $curso['id_curso'] ?>"><?= htmlspecialchars($curso['nombre']) ?> (<?= $curso['turno'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" type="submit">Guardar</button>
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<!-- Aquí tus scripts JS, modal, fetch, etc -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const modalSemestre = new bootstrap.Modal(document.getElementById('modalSemestre'));
  const formSemestre = document.getElementById('formSemestre');

  function abrirModalSemestre() {
    formSemestre.reset();
    document.getElementById('id_semestre').value = '';
    modalSemestre.show();
  }

  function editarSemestre(id) {
    fetch(`../api/obtener_semestre.php?id=${id}`)
      .then(res => res.json())
      .then(data => {
        formSemestre.nombre.value = data.nombre;
        formSemestre.curso_id.value = data.curso_id;
        document.getElementById('id_semestre').value = id;
        modalSemestre.show();
      })
      .catch(() => {
        alert('Error al cargar datos del semestre');
      });
  }

  formSemestre.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(formSemestre);

    fetch('../api/guardar_semestre.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(respuesta => {
        if (respuesta.status) {
          alert('Semestre guardado con éxito');
          modalSemestre.hide();
          location.reload();
        } else {
          alert('Error: ' + respuesta.message);
        }
      })
      .catch(() => {
        alert('Error de conexión');
      });
  });
</script>
<?php include_once('footer.php'); ?>
