<?php
require_once '../includes/conexion.php';
include_once 'header.php';

// Obtener todos los años académicos y total de estudiantes por año
$stmt = $pdo->query("SELECT  * 
                      FROM anios_academicos    ");
$anios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
  <h3 class="mb-4"><i class="bi bi-calendar3"></i> Gestión de Años Académicos</h3>

  <!-- Formulario para nuevo año -->
  <form id="formAnio" class="row g-3 bg-light p-4 rounded shadow-sm">
    <input type="hidden" name="id_anio" id="id_anio">

    <div class="col-md-4">
      <label class="form-label">Año (ej. 2024-2025)</label>
      <input type="text" name="anio" id="anio" class="form-control" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Fecha de inicio</label>
      <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Fecha de fin</label>
      <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" required>
    </div>
    <div class="col-md-2 d-grid align-items-end">
      <button type="submit" class="btn btn-success"><i class="bi bi-save2"></i> Guardar</button>
    </div>
  </form>

  <!-- Tabla de años académicos -->
  <div class="mt-5">
  <table class="table table-striped table-hover">
  <thead class="table-primary">
        <tr>
          <th>#</th>
          <th>Año</th>
          <th>Inicio</th>
          <th>Fin</th>
          <th>Estudiantes</th>
          <th>Activo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($anios as $i => $a): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($a['anio']) ?></td>
          <td><?= htmlspecialchars($a['fecha_inicio']) ?></td>
          <td><?= htmlspecialchars($a['fecha_fin']) ?></td>
         <!--  <td><span class="badge bg-info"><?= $a['total_estudiantes'] ?></span></td> -->
          <td class="text-center">
  <div class="form-check form-switch">
    <input 
      class="form-check-input" 
      type="checkbox" 
      role="switch" 
      <?= $a['activo'] ? 'checked' : '' ?> 
      data-id="<?= $a['id_anio'] ?>" 
      data-anio="<?= htmlspecialchars($a['anio']) ?>" 
      onchange="cambiarEstadoAnio(this)"
    >
  </div>
</td>


          <td>
            <button class="btn btn-sm btn-warning" onclick='editarAnio(<?= json_encode($a) ?>)'><i class="bi bi-pencil"></i></button>
             
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<script>
const form = document.getElementById('formAnio');

form.addEventListener('submit', async e => {
  e.preventDefault();
  const datos = new FormData(form);
  const res = await fetch('../api/guardar_anio.php', {
    method: 'POST',
    body: datos
  });
  const r = await res.json();
  alert(r.message);
  if (r.status) location.reload();
});

function editarAnio(anio) {
  document.getElementById('id_anio').value = anio.id_anio;
  document.getElementById('anio').value = anio.anio;
  document.getElementById('fecha_inicio').value = anio.fecha_inicio;
  document.getElementById('fecha_fin').value = anio.fecha_fin;
}

function eliminarAnio(id) {
  if (!confirm('Confirma que desea eliminar este año académico')) return;
  fetch(`../api/eliminar_anio.php?id=${id}`)
    .then(res => res.json())
    .then(r => {
      alert(r.message);
      if (r.status) location.reload();
    });
}


function cambiarEstadoAnio(input) {
  const id = input.dataset.id;
  const anio = input.dataset.anio;
  const nuevoEstado = input.checked ? 1 : 0;

  const mensaje = nuevoEstado
    ? `¿Estás seguro de ACTIVAR el año académico "${anio}"?`
    : `¿Estás seguro de DESACTIVAR el año académico "${anio}"?`;

  if (!confirm(mensaje)) {
    input.checked = !nuevoEstado; // Revertir si cancela
    return;
  }

  const formData = new FormData();
  formData.append('id_anio', id);
  formData.append('activo', nuevoEstado);

  fetch('../api/cambiar_estado_anio.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message);
    if (data.status) location.reload();
  })
  .catch(() => {
    alert('Ocurrió un error al actualizar el año académico.');
    input.checked = !nuevoEstado; // Revertir en caso de error
  });
}
</script>

<?php include_once 'footer.php'; ?>
