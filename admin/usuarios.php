<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Parámetros paginación y búsqueda
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Cantidad de filas por página
$por_pagina = 5;

// Construir la condición WHERE si hay búsqueda
$where = '';
$params = [];
if ($busqueda !== '') {
  $where = "WHERE nombre LIKE :busqueda OR apellido LIKE :busqueda OR email LIKE :busqueda OR dni LIKE :busqueda";
  $params[':busqueda'] = "%$busqueda%";
}

// Contar total de registros para paginación
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios $where");
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

// Calcular offset para SQL
$offset = ($pagina - 1) * $por_pagina;

// Consultar usuarios con límite y búsqueda
$sql = "SELECT * FROM usuarios $where ORDER BY creado_en DESC LIMIT :offset, :por_pagina";
$stmt = $pdo->prepare($sql);

// Bind de parámetros dinámicos
if ($busqueda !== '') {
  $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);

$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">👥 Gestión de Usuarios</h3>
      <button class="btn btn-success" onclick="abrirModal()">
        <i class="bi bi-plus-circle"></i> Nuevo Usuario
      </button>
    </div>

    <!-- Formulario buscador -->
    <form method="GET" class="mb-3" style="max-width: 400px;">
      <div class="input-group">
        <input type="search" name="busqueda" class="form-control" placeholder="Buscar por nombre, email o DNI"
          value="<?= htmlspecialchars($busqueda) ?>" />
        <button class="btn btn-primary" type="submit">Buscar</button>
      </div>
    </form>

    <div class="table-responsive">
      <table id="tablaUsuarios" class="table table-hover table-striped align-middle" style="width:100%">
        <thead class="table-primary">
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Email / DNI</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($usuarios) === 0): ?>
            <tr>
              <td colspan="6" class="text-center">No se encontraron usuarios</td>
            </tr>
          <?php else: ?>
            <?php foreach ($usuarios as $u): ?>
              <tr>
                <td><?= $u['id_usuario'] ?></td>
                <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?><br><small class="text-muted"><?= $u['dni'] ?></small></td>
                <td><span class="badge bg-secondary"><?= ucfirst($u['rol']) ?></span></td>
                <td>
                  <span class="badge <?= $u['estado'] ? 'bg-success' : 'bg-danger' ?>">
                    <?= $u['estado'] ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td>
                  <button class="btn btn-sm btn-warning me-1" onclick="editarUsuario(<?= $u['id_usuario'] ?>)">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-dark" onclick="cambiarEstado(<?= $u['id_usuario'] ?>)">
                    <?= $u['estado'] ? 'Desactivar' : 'Activar' ?>
                  </button>

                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <nav aria-label="Paginación de usuarios">
      <ul class="pagination justify-content-center">
        <!-- Botón anterior -->
        <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
          <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>"
            tabindex="-1">Anterior</a>
        </li>

        <!-- Números de página -->
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
          <li class="page-item <?= ($pagina === $i) ? 'active' : '' ?>">
            <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>

        <!-- Botón siguiente -->
        <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
          <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
        </li>
      </ul>
    </nav>
  </div>
</div>



<!-- MODAL -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formUsuario">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i> Registrar Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <input type="hidden" name="id_usuario" id="id_usuario">
        <div class="col-md-6"><label>Nombre</label><input type="text" name="nombre" class="form-control" required></div>
        <div class="col-md-6"><label>Apellido</label><input type="text" name="apellido" class="form-control"></div>
        <div class="col-md-6"><label> usuario</label><input type="text" name="email" class="form-control" required></div>
        <div class="col-md-6"><label>DNI</label><input type="text" name="dni" class="form-control" required></div>
        <div class="col-md-6"><label>Dirección</label><input type="text" name="direccion" class="form-control"></div>
        <div class="col-md-6">
          <label>Rol</label>
          <select name="rol" class="form-select" id="rolSelect" required>
            <option value="">Seleccione</option>
            <option value="administrador">Administrador</option>
            <option value="profesor">Profesor</option>
            <option value="estudiante">Estudiante</option>
          </select>
        </div>
        <div class="col-md-6" id="passGroup"><label>Contraseña</label><input type="password" name="contrasena"
            class="form-control" required></div>
        <!-- Campos extras -->
        <div class="col-md-6 extra-campos" id="grupoTelefono" style="display: none;"><label>Teléfono</label><input
            type="text" name="telefono" class="form-control"></div>
        <div class="col-md-6 extra-campos" id="grupoEspecialidad" style="display: none;">
          <label>Especialidad</label><input type="text" name="especialidad" class="form-control">
        </div>
        <div class="col-md-6 extra-campos" id="grupoMatricula" style="display: none;"><label>Matrícula</label><input
            type="text" name="matricula" class="form-control"></div>
        <div class="col-md-6 extra-campos" id="grupoCurso" style="display: none;">
          <label>Curso Actual</label>
          <select name="id_curso" class="form-select" id="cursoSelect">
            <option value="">Cargando cursos...</option>
          </select>
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
  const modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
  const form = document.getElementById('formUsuario');
  const rolSelect = document.getElementById('rolSelect');

  // Mostrar u ocultar campos según el rol seleccionado
  rolSelect.addEventListener('change', () => {
    const rol = rolSelect.value;
    document.querySelectorAll('.extra-campos').forEach(el => el.style.display = 'none');

    if (rol === 'profesor' || rol === 'administrador') {
      document.getElementById('grupoTelefono').style.display = 'block';
      document.getElementById('grupoEspecialidad').style.display = 'block';
    } else if (rol === 'estudiante') {
      document.getElementById('grupoMatricula').style.display = 'block';
      document.getElementById('grupoCurso').style.display = 'block';
    }
  });


  function abrirModal() {
    form.reset();
    document.getElementById('id_usuario').value = '';
    document.getElementById('passGroup').style.display = 'block';
    document.querySelectorAll('.extra-campos').forEach(el => el.style.display = 'none');
    cargarCursos(); // ✅ Cargar cursos al abrir el modal
    modal.show();
  }


  // Abrir modal para editar un usuario
  function editarUsuario(id) {
    fetch(`../api/obtener_usuario.php?id=${id}`)
      .then(res => res.json())
      .then(data => {
        for (let campo in data) {
          if (form.elements[campo]) {
            form.elements[campo].value = data[campo];
          }
        }
        document.getElementById('id_usuario').value = id;
        document.getElementById('passGroup').style.display = 'none';
        rolSelect.dispatchEvent(new Event('change'));

        if (data.rol === 'estudiante') {
          cargarCursos(); // Carga cursos antes de preseleccionar
          setTimeout(() => {
            form.elements['curso_actual'].value = data['curso_actual'];
          }, 300);
        }

        modal.show();
      })

      .catch(err => {
        alert("Error al cargar los datos del usuario");
        console.error(err);
      });
  }

  // Guardar usuario (crear o actualizar)
  form.addEventListener('submit', e => {
    e.preventDefault();
    const datos = new FormData(form);

    fetch('../api/guardar_usuario.php', {
      method: 'POST',
      body: datos
    })
      .then(res => res.json())
      .then(r => {
        if (r.status) {
          alert(r.message);
          location.reload();
        } else {
          console.log(r.data);
          alert('Error: ' + r.message);
        }
      })
      .catch(() => {
        alert('Error de conexión. Intente de nuevo.');
      });
  });

  // Cambiar estado de usuario (activar/desactivar)
  function cambiarEstado(id) {
    if (!confirm('¿Estás seguro de cambiar el estado del usuario?')) return;

    const formData = new FormData();
    formData.append('id_usuario', id);

    fetch('../api/cambiar_estado_usuario.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(respuesta => {
        if (respuesta.status) {
          alert(respuesta.message);
          location.reload();
        } else {
          alert('Error: ' + respuesta.message);
        }
      })
      .catch(() => {
        alert('Error de conexión. Intente de nuevo.');
      });
  }


  function cargarCursos() {
    const cursoSelect = document.getElementById('cursoSelect');
    cursoSelect.innerHTML = '<option value="">Cargando cursos...</option>';

    fetch('../api/obtener_cursos.php')
      .then(res => res.json())
      .then(data => {
        if (data.status) {
          cursoSelect.innerHTML = '<option value="">Seleccione un curso</option>';
          data.data.forEach(curso => {
            console.log(curso.id_curso)
            const option = document.createElement('option');
            option.value = curso.id_curso;
            option.textContent = curso.nombre;
            cursoSelect.appendChild(option);
          });
        } else {
          cursoSelect.innerHTML = '<option value="">Error al cargar</option>';
        }
      })
      .catch(() => {
        cursoSelect.innerHTML = '<option value="">Error de conexión</option>';
      });
  }

</script>
<?php include_once('footer.php'); ?>