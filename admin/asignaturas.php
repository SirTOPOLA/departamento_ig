<?php include_once('header.php'); ?>
<?php require_once '../includes/conexion.php';

// Obtener cursos y semestres para el formulario
$cursos = $pdo->query("SELECT * FROM cursos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$semestres = $pdo->query("SELECT * FROM semestres ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las asignaturas con JOIN
$asignaturas = $pdo->query("
  SELECT a.*, c.nombre AS curso, s.nombre AS semestre 
  FROM asignaturas a 
  LEFT JOIN cursos c ON a.curso_id = c.id_curso 
  LEFT JOIN semestres s ON a.semestre_id = s.id_semestre 
  ORDER BY a.id_asignatura DESC
")->fetchAll(PDO::FETCH_ASSOC);


// Paginación y búsqueda
$porPagina = 8;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$offset = ($pagina - 1) * $porPagina;

$where = "";
$params = [];

if ($buscar) {
  $where = "WHERE a.nombre LIKE :buscar OR c.nombre LIKE :buscar OR s.nombre LIKE :buscar";
  $params['buscar'] = "%$buscar%";
}

// Total de registros
$totalQuery = $pdo->prepare("SELECT COUNT(*) FROM asignaturas a 
  LEFT JOIN cursos c ON a.curso_id = c.id_curso 
  LEFT JOIN semestres s ON a.semestre_id = s.id_semestre $where");
$totalQuery->execute($params);
$total = $totalQuery->fetchColumn();
$totalPaginas = ceil($total / $porPagina);

// Datos paginados
$sql = "SELECT a.*, c.nombre AS curso, s.nombre AS semestre 
  FROM asignaturas a 
  LEFT JOIN cursos c ON a.curso_id = c.id_curso 
  LEFT JOIN semestres s ON a.semestre_id = s.id_semestre 
  $where
  ORDER BY a.id_asignatura DESC 
  LIMIT $offset, $porPagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
    <h3 class="mb-3">📘 Gestión de Asignaturas</h3>

    <form class="row mb-4 g-2 align-items-center" method="get">
      <div class="col-auto">
        <input type="text" name="buscar" class="form-control" placeholder="Buscar asignatura..."
          value="<?= htmlspecialchars($buscar) ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        <a href="?" class="btn btn-secondary">Reset</a>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" onclick="abrirModal()">
          <i class="bi bi-plus-circle"></i> Nueva Asignatura
        </button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead class="table-primary">
          <tr>
            <th>#</th>
            <th>Asignatura</th>
            <th>Curso</th>
            <th>Semestre</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asignaturas as $a): ?>
            <tr>
              <td><?= $a['id_asignatura'] ?></td>
              <td><?= htmlspecialchars($a['nombre']) ?><br><small><?= $a['descripcion'] ?></small></td>
              <td><?= $a['curso'] ?></td>
              <td><?= $a['semestre'] ?></td>
              <td>
                <button class="btn btn-sm btn-warning" onclick="editarAsignatura(<?= $a['id_asignatura'] ?>)">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="eliminarAsignatura(<?= $a['id_asignatura'] ?>)">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <nav aria-label="Paginación de asignaturas">
  <ul class="pagination justify-content-center">
    <!-- Botón anterior -->
    <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
      <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&buscar=<?= urlencode($buscar) ?>">Anterior</a>
    </li>

    <!-- Números de página -->
    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
      <li class="page-item <?= ($pagina === $i) ? 'active' : '' ?>">
        <a class="page-link" href="?pagina=<?= $i ?>&buscar=<?= urlencode($buscar) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>

    <!-- Botón siguiente -->
    <li class="page-item <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>">
      <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&buscar=<?= urlencode($buscar) ?>">Siguiente</a>
    </li>
  </ul>
</nav>

  </div>
</div>


<!-- Modal -->
<div class="modal fade" id="modalAsignatura" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formAsignatura">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Asignatura</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_asignatura" id="id_asignatura">
        <div class="mb-3">
          <label>Nombre</label>
          <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Descripción</label>
          <textarea name="descripcion" class="form-control"></textarea>
        </div>
        <div class="mb-3">
          <label>Curso</label>
          <select name="curso_id" class="form-select" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($cursos as $c): ?>
              <option value="<?= $c['id_curso'] ?>"><?= $c['nombre'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label>Semestre</label>
          <select name="semestre_id" class="form-select" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($semestres as $s): ?>
              <option value="<?= $s['id_semestre'] ?>"><?= $s['nombre'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" type="submit">Guardar</button>
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Aquí tus scripts JS, modal, fetch, etc -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const modal = new bootstrap.Modal(document.getElementById('modalAsignatura'));
  const form = document.getElementById('formAsignatura');

  // Abrir modal vacío
  function abrirModal() {
    form.reset();
    document.getElementById('id_asignatura').value = '';
    modal.show();
  }

  // Editar
  function editarAsignatura(id) {
    fetch(`../api/obtener_asignatura.php?id=${id}`)
      .then(res => res.json())
      .then(data => {
        for (let campo in data) {
          if (form.elements[campo]) {
            form.elements[campo].value = data[campo];
          }
        }
        modal.show();
      })
      .catch(err => alert("Error al cargar datos"));
  }

  // Guardar
  form.addEventListener('submit', e => {
    e.preventDefault();
    const datos = new FormData(form);
    fetch('../api/guardar_asignatura.php', {
      method: 'POST',
      body: datos
    })
      .then(res => res.json())
      .then(r => {
        alert(r.message);
        if (r.status) location.reload();
      })
      .catch(() => alert("Error de conexión"));
  });

  // Eliminar
  function eliminarAsignatura(id) {
    if (!confirm('¿Eliminar asignatura?')) return;
    const fd = new FormData();
    fd.append('id_asignatura', id);
    fetch('../api/eliminar_asignatura.php', {
      method: 'POST',
      body: fd
    })
      .then(res => res.json())
      .then(r => {
        alert(r.message);
        if (r.status) location.reload();
      });
  }
</script>
<?php include_once('footer.php'); ?>