<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Búsqueda y paginación
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 5;
$offset = ($pagina - 1) * $por_pagina;

$where = '';
$params = [];
if ($busqueda !== '') {
  $where = "WHERE nombre LIKE :busqueda OR ubicacion LIKE :busqueda";
  $params[':busqueda'] = "%$busqueda%";
}

// Total de registros
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM aulas $where");
$total_stmt->execute($params);
$total = $total_stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Obtener registros
$sql = "SELECT * FROM aulas $where ORDER BY nombre ASC LIMIT :offset, :limite";
$stmt = $pdo->prepare($sql);
if ($busqueda !== '') $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$aulas = $stmt->fetchAll();
?>


<div class="content" id="content" tabindex="-1">
  <div class="container py-5"> <h3><i class="bi bi-building"></i> Gestión de Aulas</h3>
    <button class="btn btn-success" onclick="abrirModalAula()">
      <i class="bi bi-plus-circle"></i> Nueva Aula
    </button>
  </div>

  <!-- Búsqueda -->
  <form method="GET" class="mb-3" style="max-width:400px;">
    <div class="input-group">
      <input name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" class="form-control" placeholder="Buscar aula o ubicación">
      <button class="btn btn-primary" type="submit">Buscar</button>
    </div>
  </form>

  <!-- Tabla -->
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-primary">
        <tr>
          <th>#</th>
          <th>Nombre</th>
          <th>Capacidad</th>
          <th>Ubicación</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($aulas as $a): ?>
          <tr>
            <td><?= $a['id_aula'] ?></td>
            <td><?= htmlspecialchars($a['nombre']) ?></td>
            <td><?= $a['capacidad'] ?></td>
            <td><?= htmlspecialchars($a['ubicacion']) ?></td>
            <td>
              <button class="btn btn-sm btn-warning" onclick="editarAula(<?= $a['id_aula'] ?>)">
                <i class="bi bi-pencil-square"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($aulas) === 0): ?>
          <tr><td colspan="5" class="text-center">No se encontraron resultados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <nav>
    <ul class="pagination justify-content-center">
      <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Anterior</a>
      </li>
      <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <li class="page-item <?= $pagina === $i ? 'active' : '' ?>">
          <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
        <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
      </li>
    </ul>
  </nav>
</div>

<div class="modal fade" id="modalAula" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formAula">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Gestionar Aula</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_aula" id="id_aula">

        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" id="nombre" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Capacidad</label>
          <input type="number" name="capacidad" id="capacidad" class="form-control" min="1" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Ubicación</label>
          <input type="text" name="ubicacion" id="ubicacion" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" type="submit">Guardar</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const modal = new bootstrap.Modal(document.getElementById('modalAula'));
const form = document.getElementById('formAula');

function abrirModalAula() {
  form.reset();
  form.id_aula.value = '';
  modal.show();
}

function editarAula(id) {
  fetch(`../api/obtener_aula.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      form.id_aula.value = data.id_aula;
      form.nombre.value = data.nombre;
      form.capacidad.value = data.capacidad;
      form.ubicacion.value = data.ubicacion;
      modal.show();
    });
}

form.addEventListener('submit', e => {
  e.preventDefault();
  const datos = new FormData(form);

  fetch('../api/guardar_aula.php', {
    method: 'POST',
    body: datos
  })
  .then(res => res.json())
  .then(r => {
    if (r.status) {
      alert(r.message);
      location.reload();
    } else {
      alert("Error: " + r.message);
    }
  });
});

</script>
<?php include_once('footer.php'); ?>
