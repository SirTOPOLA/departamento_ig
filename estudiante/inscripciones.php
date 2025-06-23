<?php
require '../includes/conexion.php';
session_start();

$id_estudiante = $_SESSION['id_usuario'] ?? null; // asumimos que se guarda así en login

if (!$id_estudiante) {
  die("No has iniciado sesión");
}

// Obtener info del estudiante y su curso
$stmt = $pdo->prepare("
  SELECT e.id_estudiante, e.id_curso, u.nombre, u.apellido
  FROM estudiantes e
  JOIN usuarios u ON e.id_estudiante = u.id_usuario
  WHERE e.id_estudiante = ?
");
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
  die("Estudiante no encontrado.");
}

$id_curso = $estudiante['id_curso'];

// Obtener semestres del curso
$stmtSemestres = $pdo->prepare("SELECT id_semestre, nombre FROM semestres WHERE curso_id = ?");
$stmtSemestres->execute([$id_curso]);
$semestres = $stmtSemestres->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'header.php'; ?>

<div class="container py-5 px-3 px-md-4">
  <div class="bg-white shadow rounded-4 p-4">
    <form action="../api/procesar_inscripciones.php" method="POST" class="p-4 rounded shadow bg-light">
      <h5 class="mb-4">Formulario de Inscripción</h5>

      <!-- Mostrar nombre del estudiante -->
      <p><strong>Estudiante:</strong> <?= $estudiante['nombre'] . ' ' . $estudiante['apellido'] ?></p>
      <input type="hidden" name="id_estudiante" value="<?= $id_estudiante ?>">
      <input type="hidden" name="id_curso" value="<?= $id_curso ?>">

      <!-- Seleccionar semestre -->
      <div class="mb-3">
        <label for="id_semestre" class="form-label">Semestre</label>
        <select name="id_semestre" id="id_semestre" class="form-select" required>
          <option value="">Seleccione un semestre</option>
          <?php foreach ($semestres as $sem): ?>
            <option value="<?= $sem['id_semestre'] ?>"><?= $sem['nombre'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Asignaturas por semestre (vía JS) -->
      <div class="mb-3">
        <label class="form-label">Asignaturas (máx. 6)</label>
        <div id="contenedorAsignaturas" class="row row-cols-1 row-cols-md-2 g-2">
          <!-- JS inserta aquí -->
        </div>
      </div>

      <input type="hidden" name="estado" value="preinscrito">

      <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('id_semestre').addEventListener('change', function () {
  const semestreId = this.value;
  const cursoId = document.querySelector('input[name="id_curso"]').value;

  fetch(`../api/obtener_asignaturas.php?semestre_id=${semestreId}&curso_id=${cursoId}`)
    .then(res => res.json())
    .then(data => {
      const contenedor = document.getElementById('contenedorAsignaturas');
      contenedor.innerHTML = '';

      data.forEach(asig => {
        const id = asig.id_asignatura;
        const nombre = asig.nombre;
        const habilitado = asig.habilitado;
        const tooltip = asig.requisitos.length
          ? `Requiere: ${asig.requisitos.join(', ')}`
          : '';

        contenedor.innerHTML += `
          <div class="col">
            <div class="form-check" title="${tooltip}">
              <input class="form-check-input" type="checkbox" 
                name="id_asignaturas[]" 
                value="${id}" 
                id="asig${id}"
                ${!habilitado ? 'disabled' : ''}>
              <label class="form-check-label ${!habilitado ? 'text-muted' : ''}" for="asig${id}">
                ${nombre} ${!habilitado ? '(No cumple requisitos)' : ''}
              </label>
            </div>
          </div>`;
      });

      // Limitar a 6 seleccionadas
      document.querySelectorAll('input[name="id_asignaturas[]"]').forEach(input => {
        input.addEventListener('change', function () {
          const seleccionados = document.querySelectorAll('input[name="id_asignaturas[]"]:checked').length;
          if (seleccionados >= 6) {
            document.querySelectorAll('input[name="id_asignaturas[]"]').forEach(i => {
              if (!i.checked) i.disabled = true;
            });
          } else {
            document.querySelectorAll('input[name="id_asignaturas[]"]').forEach(i => {
              if (!i.hasAttribute('data-requirements')) i.disabled = false;
            });
          }
        });
      });
    });
});

</script>
