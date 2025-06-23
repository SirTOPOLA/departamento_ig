<?php include_once('header.php'); ?>
<?php require_once '../includes/conexion.php';

$asignaturas = $pdo->query("SELECT id_asignatura, nombre FROM asignaturas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
  <h3 class="mb-4"><i class="bi bi-link-45deg"></i> Gestión de dependecias entre Asignaturas</h3>

  <!-- Formulario para asignar requisitos -->
  <form id="formRequisito" class="row g-3 align-items-end bg-light p-4 rounded shadow-sm">
    <div class="col-md-6">
      <label class="form-label">Asignatura condicionada (la que depende)</label>
      <select name="id_asignatura" id="condicionada_id" class="form-select" required>

        <option value="">-- Seleccione una asignatura --</option>
        <?php foreach ($asignaturas as $a): ?>
          <option value="<?= $a['id_asignatura'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">Requisito(s) necesario(s)</label>
      <div id="requisitos" class="form-check d-flex flex-column gap-2 border rounded p-3" style="max-height: 250px; overflow-y: auto;">
  <!-- Los checkboxes se insertarán aquí -->
</div>

    </div>

    <div class="col-12 text-end mt-3">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save2-fill"></i> Guardar Relación
      </button>
    </div>
  </form>

  <!-- Vista actual -->
  <div class="mt-5">
    <h5 class="mb-3">Relaciones de requisitos registradas</h5>
    <div id="tablaRequisitos"></div>
  </div>
</div>
</div>

<script>
const condicionada = document.getElementById('condicionada_id');
const requisitos = document.getElementById('requisitos');
const tabla = document.getElementById('tablaRequisitos');
const form = document.getElementById('formRequisito');

// Al cambiar asignatura condicionada, cargar otras como posibles requisitos
condicionada.addEventListener('change', () => {
  const id = condicionada.value;
  requisitos.innerHTML = '';

  if (!id) return;

  fetch('../api/listar_asignatura.php')
    .then(res => res.json())
    .then(asignaturas => { 

asignaturas.forEach(asig => {
  if (asig.id_asignatura !== id) {
    const div = document.createElement('div');
    div.className = 'form-check';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'form-check-input';
    checkbox.name = 'requisitos[]';
    checkbox.value = asig.id_asignatura;
    checkbox.id = 'req_' + asig.id_asignatura;

    const label = document.createElement('label');
    label.className = 'form-check-label';
    label.htmlFor = 'req_' + asig.id_asignatura;
    label.textContent = asig.nombre;

    div.appendChild(checkbox);
    div.appendChild(label);
    requisitos.appendChild(div);
  }
});

    });

  // Mostrar requisitos ya registrados para esa asignatura
  fetch(`../api/requisitos_por_asignatura.php?id=${id}`)
    .then(res => res.text())
    .then(html => tabla.innerHTML = html);
});

// Enviar formulario
form.addEventListener('submit', e => {
  e.preventDefault();

  const seleccionados = form.querySelectorAll('input[name="requisitos[]"]:checked');
  if (seleccionados.length === 0) {
    alert("Debe seleccionar al menos un requisito.");
    return;
  }

  const datos = new FormData(form);
  fetch('../api/guardar_requisisto_asignatura.php', {
    method: 'POST',
    body: datos
  })
  .then(res => res.json())
  .then(r => {
    alert(r.message);
    if (r.status) condicionada.dispatchEvent(new Event('change'));
  });
});

// Eliminar relación
function eliminarRequisito(id) {
  if (!confirm('¿Eliminar este requisito?')) return;
  fetch(`../api/eliminar_requisitos_asignatura.php?id=${id}`)
    .then(res => res.json())
    .then(r => {
      alert(r.message);
      if (r.status) condicionada.dispatchEvent(new Event('change'));
    });
}
</script>

<?php include_once('footer.php'); ?>
