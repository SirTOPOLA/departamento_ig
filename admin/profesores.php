<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

$stmt = $pdo->query("
  SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.dni, u.estado, p.especialidad 
  FROM usuarios u 
  INNER JOIN profesores p ON u.id_usuario = p.id_profesor
  ORDER BY u.nombre
");

$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>👨‍🏫 Gestión de Profesores</h3>
  </div>

  <table class="table table-striped table-hover">
    <thead class="table-primary">
      <tr>
        <th>#</th>
        <th>Nombre</th>
        <th>Email / DNI</th>
        <th>Especialidad</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($profesores as $prof): ?>
        <tr>
          <td><?= $prof['id_usuario'] ?></td>
          <td><?= htmlspecialchars($prof['nombre'] . ' ' . $prof['apellido']) ?></td>
          <td><?= htmlspecialchars($prof['email']) ?><br><small class="text-muted"><?= $prof['dni'] ?></small></td>
          <td><?= htmlspecialchars($prof['especialidad']) ?></td>
          <td>
            <span class="badge <?= $prof['estado'] ? 'bg-success' : 'bg-danger' ?>">
              <?= $prof['estado'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-primary" onclick="asignarAsignaturas(<?= $prof['id_usuario'] ?>)">
              <i class="bi bi-journal-plus"></i> Asignar Asignaturas
            </button>
            <button class="btn btn-info btn-sm" onclick="verDetallesProfesor(<?= $prof['id_usuario'] ?>)">
              <i class="bi bi-eye"></i> Ver Detalles
            </button>

          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal Asignar Asignaturas -->
<div class="modal fade" id="modalAsignar" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formAsignarAsignaturas">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Asignar Asignaturas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_profesor" id="id_profesor_asignar">
        <div id="contenedorAsignaturas" class="row g-3">
          <!-- Aquí se cargan las asignaturas dinámicamente -->
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" type="submit">Guardar Asignaciones</button>
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Detalles del Profesor -->
<div class="modal fade" id="modalDetallesProfesor" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Detalles del Profesor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="contenidoDetalles">
        <p>Cargando datos...</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script>
  const modalDetalles = new bootstrap.Modal(document.getElementById('modalDetallesProfesor'));
  const contenidoDetalles = document.getElementById('contenidoDetalles');

  const modalAsignar = new bootstrap.Modal(document.getElementById('modalAsignar'));
  const formAsignar = document.getElementById('formAsignarAsignaturas');
  const contenedorAsignaturas = document.getElementById('contenedorAsignaturas');

  function asignarAsignaturas(idProfesor) {
    document.getElementById('id_profesor_asignar').value = idProfesor;
    contenedorAsignaturas.innerHTML = '<p>Cargando asignaturas...</p>';

    fetch('../api/asignaturas_disponibles.php?id_profesor=' + idProfesor)
      .then(res => res.json())
      .then(asignaturas => {
        if (asignaturas.length === 0) {
          contenedorAsignaturas.innerHTML = '<p>No hay asignaturas disponibles</p>';
          return;
        }

        contenedorAsignaturas.innerHTML = '';
        asignaturas.forEach(asig => {
          const div = document.createElement('div');
          div.className = 'col-md-6';
          div.innerHTML = `
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="asignaturas[]" value="${asig.id_asignatura}" id="asig${asig.id_asignatura}" ${asig.asignada ? 'checked' : ''}>
            <label class="form-check-label" for="asig${asig.id_asignatura}">
              ${asig.nombre}
            </label>
          </div>
        `;
          contenedorAsignaturas.appendChild(div);
        });
        modalAsignar.show();
      });
  }

  
  formAsignar.addEventListener('submit', e => {
    e.preventDefault();

    const datos = new FormData(formAsignar);

    fetch('../api/guardar_asignaturas_profesor.php', {
      method: 'POST',
      body: datos
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.status) {
          alert(resp.message);
          modalAsignar.hide();
        } else {
          alert('Error: ' + resp.message);
        }
      })
      .catch(() => {
        alert('Error al guardar las asignaciones');
      });
  });


  function verDetallesProfesor(idProfesor) {
    contenidoDetalles.innerHTML = '<p>Cargando...</p>';

    fetch(`../api/detalles_profesor.php?id_profesor=${idProfesor}`)
      .then(res => res.json())
      .then(data => {
        if (!data.profesor) {
          contenidoDetalles.innerHTML = '<p>No se encontraron datos del profesor.</p>';
          return;
        }

        const p = data.profesor;

        let html = `
        <h5 class="text-primary">Información Personal</h5>
        <ul class="list-group mb-3">
          <li class="list-group-item"><strong>Nombre:</strong> ${p.nombre} ${p.apellido}</li>
          <li class="list-group-item"><strong>Email:</strong> ${p.email}</li>
          <li class="list-group-item"><strong>DNI:</strong> ${p.dni}</li>
          <li class="list-group-item"><strong>Especialidad:</strong> ${p.especialidad || 'No especificada'}</li>
          <li class="list-group-item"><strong>Teléfono:</strong> ${p.telefono || 'No disponible'}</li>
        </ul>
      `;

        html += `<h5 class="text-success">Asignaturas Asignadas</h5>`;
        if (data.asignaturas.length === 0) {
          html += `<p class="text-muted">Este profesor aún no tiene asignaturas asignadas.</p>`;
        } else {
          html += '<ul class="list-group">';
          data.asignaturas.forEach(a => {
            html += `<li class="list-group-item">${a.nombre}</li>`;
          });
          html += '</ul>';
        }

        contenidoDetalles.innerHTML = html;
        modalDetalles.show();
      })
      .catch(() => {
        contenidoDetalles.innerHTML = '<p>Error al obtener los detalles del profesor.</p>';
      });
  }
</script>
<?php include_once('footer.php'); ?>
