<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$busqueda = trim($_GET['busqueda'] ?? '');
$por_pagina = 6;

$where = '';
$params = [];

if ($busqueda !== '') {
  $where = "WHERE titulo LIKE :b OR descripcion LIKE :b";
  $params[':b'] = "%$busqueda%";
}

$total = $pdo->prepare("SELECT COUNT(*) FROM requisitos_matricula $where");
$total->execute($params);
$total_registros = $total->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);
$offset = ($pagina - 1) * $por_pagina;

$sql = "SELECT * FROM requisitos_matricula $where ORDER BY tipo, titulo LIMIT :offset, :limite";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$requisitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
  <div class="d-flex justify-content-between mb-3">
    <h4>📑 Requisitos de Matrícula</h4>
    <button class="btn btn-primary" onclick="abrirModal()">
      <i class="bi bi-plus-circle"></i> Nuevo Requisito
    </button>
  </div>

  <form method="GET" class="mb-3" style="max-width: 400px;">
    <div class="input-group">
      <input type="search" name="busqueda" class="form-control" placeholder="Buscar requisitos..."
        value="<?= htmlspecialchars($busqueda) ?>">
      <button class="btn btn-outline-secondary" type="submit">Buscar</button>
    </div>
  </form>

  <div class="table-responsive">
<table class="table table-striped table-hover">
    <thead class="table-primary">
        <tr>
          <th>#</th>
          <th>Título</th>
          <th>Tipo</th>
          <th>Obligatorio</th>
          <th>Archivo</th>
          <th>Visible</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$requisitos): ?>
          <tr><td colspan="7" class="text-center">No se encontraron requisitos</td></tr>
        <?php else: foreach ($requisitos as $r): ?>
          <tr>
            <td><?= $r['id_requisito'] ?></td>
            <td><?= htmlspecialchars($r['titulo']) ?></td>
            <td><span class="badge bg-info text-dark"><?= ucfirst($r['tipo']) ?></span></td>
            <td><?= $r['obligatorio'] ? '✅' : '❌' ?></td>
            <td>
              <?php if ($r['archivo_modelo']): ?>
                <a href="<?= $r['archivo_modelo'] ?>" target="_blank">Ver</a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $r['visible'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $r['visible'] ? 'Visible' : 'Oculto' ?>
              </span>
            </td>
            <td>
              <button class="btn btn-sm btn-warning" onclick="editarRequisito(<?= $r['id_requisito'] ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-dark" onclick="cambiarVisibilidad(<?= $r['id_requisito'] ?>)">
                <i class="bi bi-eye<?= $r['visible'] ? '-slash' : '' ?>"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <nav>
    <ul class="pagination justify-content-center">
      <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Anterior</a>
      </li>
      <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <li class="page-item <?= ($pagina == $i) ? 'active' : '' ?>">
          <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
        <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
      </li>
    </ul>
  </nav>
</div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalRequisito" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formRequisito" enctype="multipart/form-data">
      <input type="hidden" name="id_requisito" id="id_requisito">
      <div class="modal-header">
        <h5 class="modal-title">Formulario de Requisito</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label>Título</label>
          <input type="text" name="titulo" id="titulo" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Descripción</label>
          <textarea name="descripcion" id="descripcion" class="form-control" required></textarea>
        </div>
        <div class="mb-3">
          <label>Tipo</label>
          <select name="tipo" id="tipo" class="form-select" required>
            <option value="nuevo">Nuevo</option>
            <option value="antiguo">Antiguo</option>
            <option value="extranjero">Extranjero</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="mb-3">
          <label>Archivo modelo (opcional)</label> 
          <input type="text" name="archivo_modelo" id="archivo_modelo" class="form-control" accept="application/pdf">
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="obligatorio" id="obligatorio" checked>
          <label class="form-check-label" for="obligatorio">Obligatorio</label>
        </div>
        <div class="form-check mt-1">
          <input class="form-check-input" type="checkbox" name="visible" id="visible" checked>
          <label class="form-check-label" for="visible">Visible</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const modal = new bootstrap.Modal(document.getElementById('modalRequisito'));
  const form = document.getElementById('formRequisito');

  function abrirModal() {
    form.reset();
    form.id_requisito.value = '';
    document.getElementById('obligatorio').checked = true;
    document.getElementById('visible').checked = true;
    modal.show();
  }

  function editarRequisito(id) {
    fetch(`../api/obtener_requisitos.php?id=${id}`)
      .then(res => res.json())
      .then(r => {
        for (let campo in r) {
          if (form.elements[campo]) {
            if (form.elements[campo].type === 'checkbox') {
              form.elements[campo].checked = r[campo] == 1;
            } else {
              form.elements[campo].value = r[campo];
            }
          }
        }
        modal.show();
      });
  }

  function cambiarVisibilidad(id) {
    fetch(`../api/toggle_visible_requisitos.php?id=${id}`)
      .then(res => res.json())
      .then(r => {
        alert(r.message);
        location.reload();
      });
  }

  form.addEventListener('submit', e => {
    e.preventDefault();
    const datos = new FormData(form);
    fetch('../api/guardar_requisitos.php', {
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
