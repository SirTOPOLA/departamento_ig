<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Consultar si ya existe departamento
$stmt = $pdo->query("SELECT * FROM departamento LIMIT 1");
$departamento = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
  <h4>🏛 Información del Departamento</h4>
  <?php if ($departamento): ?>
    <button class="btn btn-warning" onclick="editarDepartamento()">
      <i class="bi bi-pencil-square"></i> Editar
    </button>
  <?php else: ?>
    <button class="btn btn-primary" onclick="abrirModal()">
      <i class="bi bi-plus-circle"></i> Registrar
    </button>
  <?php endif; ?>


  <?php if ($departamento): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title"><?= htmlspecialchars($departamento['nombre']) ?></h5>
        <p><strong>Universidad:</strong> <?= htmlspecialchars($departamento['universidad']) ?></p>
        <p><strong>Dirección:</strong> <?= htmlspecialchars($departamento['direccion']) ?></p>
        <p><strong>Teléfono:</strong> <?= htmlspecialchars($departamento['telefono']) ?></p>
        <p><strong>Horario:</strong> <?= htmlspecialchars($departamento['horario']) ?></p>
        <p><strong>Historia:</strong><br><?= nl2br(htmlspecialchars($departamento['historia'])) ?></p>
        <p><strong>Información de Matrícula:</strong><br><?= nl2br(htmlspecialchars($departamento['info_matricula'])) ?>
        </p>
        <?php if ($departamento['imagen']): ?>
          <p><strong>Imagen principal:</strong><br>
            <img src="../api/<?= $departamento['imagen'] ?>" class="img-fluid rounded" style="max-height:200px;">
          </p>
        <?php endif; ?>
        <?php if ($departamento['logo_unge']): ?>
          <p><strong>Logo UNGE:</strong><br>
            <img src="../api/<?= $departamento['logo_unge'] ?>" class="img-thumbnail" style="max-height:100px;">
          </p>
        <?php endif; ?>
        <?php if ($departamento['logo_pais']): ?>
          <p><strong>Logo Nacional:</strong><br>
            <img src="../api/<?= $departamento['logo_pais'] ?>" class="img-thumbnail" style="max-height:100px;">
          </p>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-info">No se ha registrado ningún departamento aún.</div>
  <?php endif; ?>
</div>
<!-- Modal -->
<div class="modal fade" id="modalDepartamento" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formDepartamento" enctype="multipart/form-data">
      <input type="hidden" name="id" id="id_departamento">
      <div class="modal-header">
        <h5 class="modal-title">Formulario de Departamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" id="nombre" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Universidad</label>
          <input type="text" name="universidad" id="universidad" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Teléfono</label>
          <input type="text" name="telefono" id="telefono" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Dirección</label>
          <input type="text" name="direccion" id="direccion" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Horario</label>
          <input type="text" name="horario" id="horario" class="form-control">
        </div>
        <div class="col-md-12">
          <label class="form-label">Historia</label>
          <textarea name="historia" id="historia" class="form-control" rows="4"></textarea>
        </div>
        <div class="col-md-12">
          <label class="form-label">Información de Matrícula</label>
          <textarea name="info_matricula" id="info_matricula" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label">Imagen Principal (URL o ruta)</label>
          <input type="file" name="imagen" id="imagen" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Logo UNGE (URL o ruta)</label>
          <input type="file" name="logo_unge" id="logo_unge" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Logo País (URL o ruta)</label>
          <input type="file" name="logo_pais" id="logo_pais" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Guardar</button>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const modal = new bootstrap.Modal(document.getElementById('modalDepartamento'));
  const form = document.getElementById('formDepartamento');

  function abrirModal() {
    form.reset();
    modal.show();
  }

  function editarDepartamento() {
  fetch('../api/obtener_departamento.php')
    .then(res => {
      if (!res.ok) throw new Error("Archivo no encontrado o error en servidor");
      return res.json();
    })
    .then(d => {
      for (let campo in d) {
        // Ignorar inputs tipo "file"
        const input = form.elements[campo];
        if (input && input.type !== 'file') {
          input.value = d[campo];
        }
      }
      modal.show();
    })
    .catch(err => {
      alert("Error al obtener datos del departamento:\n" + err.message);
    });
}


  form.addEventListener('submit', e => {
    e.preventDefault();
    const datos = new FormData(form);
    fetch('../api/guardar_departamento.php', {
      method: 'POST',
      body: datos
    })
      .then(res => res.json())
      .then(r => {
        alert(r.message);
        if (r.status) location.reload();
      });
  });
</script>
<?php include_once('footer.php'); ?>