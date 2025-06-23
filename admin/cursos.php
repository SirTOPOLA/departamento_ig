<?php 
include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Parámetros
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$por_pagina = 5;

// Filtro
$where = '';
$params = [];
if ($busqueda !== '') {
  $where = "WHERE nombre LIKE :busqueda OR descripcion LIKE :busqueda";
  $params[':busqueda'] = "%$busqueda%";
}

// Total de registros
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cursos $where");
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);
$offset = ($pagina - 1) * $por_pagina;

// Consulta cursos
$sql = "SELECT * FROM cursos $where ORDER BY id_curso DESC LIMIT :offset, :por_pagina";
$stmt = $pdo->prepare($sql);
if ($busqueda !== '') {
  $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">  <h3 class="mb-0">📚 Gestión de Cursos</h3>
    <button class="btn btn-success" onclick="abrirModal()">
      <i class="bi bi-plus-circle"></i> Nuevo Curso
    </button>
  </div>

  <!-- Buscador -->
  <form method="GET" class="mb-3" style="max-width: 400px;">
    <div class="input-group">
      <input type="search" name="busqueda" class="form-control" placeholder="Buscar por nombre o descripción"
        value="<?= htmlspecialchars($busqueda) ?>" />
      <button class="btn btn-primary" type="submit">Buscar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover table-striped align-middle">
      <thead class="table-primary">
        <tr>
          <th>#</th>
          <th>Nombre</th>
          <th>Turno</th>
          <th>Grupo</th>
          <th>Descripción</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cursos)): ?>
          <tr><td colspan="6" class="text-center">No se encontraron cursos</td></tr>
        <?php else: ?>
          <?php foreach ($cursos as $curso): ?>
            <tr>
              <td><?= $curso['id_curso'] ?></td>
              <td><?= htmlspecialchars($curso['nombre']) ?></td>
              <td><span class="badge bg-info"><?= ucfirst($curso['turno']) ?></span></td>
              <td><?= $curso['grupo'] ?></td>
              <td><?= htmlspecialchars($curso['descripcion']) ?></td>
              <td>
                <button class="btn btn-sm btn-warning me-1" onclick="editarCurso(<?= $curso['id_curso'] ?>)">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="eliminarCurso(<?= $curso['id_curso'] ?>)">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <nav aria-label="Paginación de cursos">
    <ul class="pagination justify-content-center">
      <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Anterior</a>
      </li>
      <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <li class="page-item <?= ($pagina === $i) ? 'active' : '' ?>">
          <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
        <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
      </li>
    </ul>
  </nav>
</div>

<!-- MODAL -->
<div class="modal fade" id="modalCurso" tabindex="-1" aria-labelledby="modalCursoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formCurso">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalCursoLabel"><i class="bi bi-book me-2"></i> Curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_curso" id="id_curso">

        <div class="mb-3">
          <label for="nombre" class="form-label">Nombre</label>
          <input type="text" class="form-control" name="nombre" id="nombre" required>
        </div>

        <div class="mb-3">
          <label for="turno" class="form-label">Turno</label>
          <select class="form-select" name="turno" id="turno" required>
            <option value="">Seleccione</option>
            <option value="tarde">Tarde</option>
            <option value="noche">Noche</option>
          </select>
        </div>

        <div class="mb-3">
          <label for="grupo" class="form-label">Grupo</label>
          <input type="number" class="form-control" name="grupo" id="grupo" value="1" min="1" required>
        </div>

        <div class="mb-3">
          <label for="descripcion" class="form-label">Descripción</label>
          <textarea class="form-control" name="descripcion" id="descripcion" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" type="submit"><i class="bi bi-save"></i> Guardar</button>
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Aquí tus scripts JS, modal, fetch, etc -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const modalCurso = new bootstrap.Modal(document.getElementById('modalCurso'));
  const formCurso = document.getElementById('formCurso');

  function abrirModal() {
    formCurso.reset();
    document.getElementById('id_curso').value = '';
    modalCurso.show();
  }

  function editarCurso(id) {
    fetch(`../api/obtener_curso.php?id=${id}`)
      .then(res => res.json())
      .then(data => {
        if (data && data.id_curso) {
          for (let campo in data) {
            if (formCurso.elements[campo]) {
              formCurso.elements[campo].value = data[campo];
            }
          }
          modalCurso.show();
        } else {
          alert("Curso no encontrado");
        }
      })
      .catch(() => alert("Error al cargar el curso."));
  }

  formCurso.addEventListener('submit', function (e) {
    e.preventDefault();
    const datos = new FormData(formCurso);

    fetch('../api/guardar_curso.php', {
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
    })
    .catch(() => alert("Error de conexión"));
  });

  function eliminarCurso(id) {
    if (!confirm("¿Seguro que deseas eliminar este curso?")) return;

    const formData = new FormData();
    formData.append('id_curso', id);

    fetch('../api/eliminar_curso.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(resp => {
      if (resp.status) {
        alert(resp.message);
        location.reload();
      } else {
        alert("Error: " + resp.message);
      }
    })
    .catch(() => alert("Error de conexión"));
  }
</script>
<?php include_once('footer.php'); ?>
