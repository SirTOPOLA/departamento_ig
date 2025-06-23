<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Obtener publicaciones
$stmt = $pdo->query("SELECT p.*, u.nombre AS autor
                     FROM publicaciones p
                     LEFT JOIN usuarios u ON p.creado_por = u.id_usuario
                     ORDER BY creado_en DESC");
$publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="content" id="content" tabindex="-1">
<div class="container py-5">

  <div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-megaphone-fill"></i> Publicaciones</h3>
    <button class="btn btn-success" onclick="abrirModalPublicacion()">
      <i class="bi bi-plus-circle"></i> Nueva publicación
    </button>
  </div>

  <div class="table-responsive">
  <table id="tablaUsuarios" class="table table-hover table-striped align-middle" style="width:100%">
  <thead class="table-primary">
        <tr>
          <th>#</th>
          <th>Título</th>
          <th>Tipo</th>
          <th>Autor</th>
          <th>Fecha</th>
          <th>Visible</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($publicaciones as $p): ?>
        <tr>
          <td><?= $p['id_publicacion'] ?></td>
          <td><?= htmlspecialchars($p['titulo']) ?></td>
          <td><span class="badge bg-primary"><?= ucfirst($p['tipo']) ?></span></td>
          <td><?= htmlspecialchars($p['autor']) ?></td>
          <td><?= date('d/m/Y', strtotime($p['creado_en'])) ?></td>
          <td>
            <span class="badge <?= $p['visible'] ? 'bg-success' : 'bg-secondary' ?>">
              <?= $p['visible'] ? 'Sí' : 'No' ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-warning" onclick="editarPublicacion(<?= $p['id_publicacion'] ?>)">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button class="btn btn-sm btn-secondary" onclick="toggleVisible(<?= $p['id_publicacion'] ?>)">
              <?= $p['visible'] ? 'Ocultar' : 'Mostrar' ?>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (count($publicaciones) === 0): ?>
        <tr><td colspan="7" class="text-center">No hay publicaciones</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalPublicacion" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formPublicacion" enctype="multipart/form-data">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Publicación</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_publicacion" id="id_publicacion">

        <div class="mb-3">
          <label class="form-label">Título</label>
          <input type="text" name="titulo" id="titulo" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Tipo</label>
          <select name="tipo" id="tipo" class="form-select" required>
            <option value="noticia">Noticia</option>
            <option value="evento">Evento</option>
            <option value="comunicado">Comunicado</option>
          </select>
        </div>

        <div class="mb-3" id="grupoFechaEvento" style="display: none;">
          <label class="form-label">Fecha del evento</label>
          <input type="date" name="fecha_evento" id="fecha_evento" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label">Contenido</label>
          <textarea name="contenido" id="contenido" class="form-control" rows="5" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Imagen destacada (opcional)</label>
          <input type="file" name="imagen" id="imagen" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label">Archivo adjunto (PDF, opcional)</label>
          <input type="file" name="archivo_adjunto" id="archivo_adjunto" class="form-control">
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
    const modalPublicacion = new bootstrap.Modal(document.getElementById('modalPublicacion'));
const formPublicacion = document.getElementById('formPublicacion');
const tipoSelect = document.getElementById('tipo');
const grupoFecha = document.getElementById('grupoFechaEvento');

tipoSelect.addEventListener('change', () => {
  grupoFecha.style.display = tipoSelect.value === 'evento' ? 'block' : 'none';
});

function abrirModalPublicacion() {
  formPublicacion.reset();
  document.getElementById('id_publicacion').value = '';
  grupoFecha.style.display = 'none';
  modalPublicacion.show();
}

function editarPublicacion(id) {
  fetch(`../api/obtener_publicacion.php?id=${id}`)
    .then(res => res.json())
    .then(p => {
      for (let campo in p) {
        if (formPublicacion.elements[campo]) {
          formPublicacion.elements[campo].value = p[campo];
        }
      }
      tipoSelect.dispatchEvent(new Event('change'));
      modalPublicacion.show();
    });
}

formPublicacion.addEventListener('submit', e => {
  e.preventDefault();
  const datos = new FormData(formPublicacion);

  fetch('../api/guardar_publicacion.php', {
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

function toggleVisible(id) {
  fetch(`../api/toggle_visible_publicacion.php?id=${id}`)
    .then(res => res.json())
    .then(r => {
      if (r.status) {
        location.reload();
      } else {
        alert("Error: " + r.message);
      }
    });
}

</script>
<?php include_once('footer.php'); ?>
