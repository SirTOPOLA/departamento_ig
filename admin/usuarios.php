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
 <style>
        /* Estilos adicionales para un mejor aspecto de los formularios y la tabla */
        body {
            font-family: "Inter", sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
        }
        .form-control.rounded-pill,
        .form-select.rounded-pill,
        .btn.rounded-pill {
            border-radius: 50rem !important;
        }
        .modal-header.bg-primary {
            background-color: #007bff !important; /* Asegura el color primario de Bootstrap */
        }
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden; /* Para que los bordes redondeados se apliquen al contenido */
        }
        .table thead {
            background-color: #007bff;
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2f0ff;
        }
    </style>
 

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">👥 Gestión de Usuarios</h3>
      <button class="btn btn-success rounded-pill px-4" onclick="abrirModal()">
        <i class="bi bi-plus-circle me-2"></i> Nuevo Usuario
      </button>
    </div>

    <!-- Formulario buscador -->
    <form method="GET" class="mb-3" style="max-width: 400px;">
      <div class="input-group">
        <input type="search" name="busqueda" class="form-control rounded-pill" placeholder="Buscar por nombre, email o DNI"
          value="<?= htmlspecialchars($busqueda ?? '') ?>" />
        <button class="btn btn-primary rounded-pill px-3" type="submit">Buscar</button>
      </div>
    </form>

    <div class="table-responsive shadow-sm">
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
          <?php
          // Esto es un placeholder. En un entorno real, $usuarios vendría de una consulta a la DB.
          // Y $busqueda, $pagina, $total_paginas vendrían de la lógica de paginación/búsqueda.
          $usuarios = $usuarios ?? []; // Define $usuarios si no está definido
          $busqueda = $busqueda ?? '';
          $pagina = $pagina ?? 1;
          $total_paginas = $total_paginas ?? 1;

          if (count($usuarios) === 0): ?>
            <tr>
              <td colspan="6" class="text-center py-4">No se encontraron usuarios</td>
            </tr>
          <?php else: ?>
            <?php foreach ($usuarios as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['id_usuario'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')) ?></td>
                <td><?= htmlspecialchars($u['email'] ?? 'N/A') ?><br><small class="text-muted"><?= htmlspecialchars($u['dni'] ?? 'N/A') ?></small></td>
                <td><span class="badge bg-secondary rounded-pill"><?= ucfirst(htmlspecialchars($u['rol'] ?? 'N/A')) ?></span></td>
                <td>
                  <span class="badge rounded-pill <?= ($u['estado'] ?? false) ? 'bg-success' : 'bg-danger' ?>">
                    <?= ($u['estado'] ?? false) ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td>
                  <button class="btn btn-sm btn-warning rounded-pill me-1" onclick="editarUsuario(<?= htmlspecialchars($u['id_usuario'] ?? 0) ?>)" title="Editar Usuario">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-dark rounded-pill" onclick="cambiarEstado(<?= htmlspecialchars($u['id_usuario'] ?? 0) ?>)" title="<?= ($u['estado'] ?? false) ? 'Desactivar' : 'Activar' ?> Usuario">
                    <?= ($u['estado'] ?? false) ? 'Desactivar' : 'Activar' ?>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <nav aria-label="Paginación de usuarios" class="mt-4">
      <ul class="pagination justify-content-center">
        <!-- Botón anterior -->
        <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
          <a class="page-link rounded-pill mx-1" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>"
            tabindex="-1">Anterior</a>
        </li>

        <!-- Números de página -->
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
          <li class="page-item <?= ($pagina === $i) ? 'active' : '' ?>">
            <a class="page-link rounded-pill mx-1" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>

        <!-- Botón siguiente -->
        <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
          <a class="page-link rounded-pill mx-1" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content rounded-4 shadow-lg" id="formUsuario">
      <div class="modal-header bg-primary text-white rounded-top-3 p-4">
        <h5 class="modal-title fs-5" id="modalUsuarioLabel"><i class="bi bi-person-plus me-2"></i> Registrar/Editar Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body row g-3 p-4">
        <input type="hidden" name="id_usuario" id="id_usuario">
        <div class="col-md-6">
          <label for="nombre" class="form-label">Nombre</label>
          <input type="text" name="nombre" id="nombre" class="form-control rounded-pill" required>
        </div>
        <div class="col-md-6">
          <label for="apellido" class="form-label">Apellido</label>
          <input type="text" name="apellido" id="apellido" class="form-control rounded-pill">
        </div>
        <div class="col-md-6">
          <label for="email" class="form-label">Nombre (Usuario)</label>
          <input type="text" name="email" id="email" class="form-control rounded-pill" required>
        </div>
        <div class="col-md-6">
          <label for="dni" class="form-label">DIP</label>
          <input type="text" name="dni" id="dni" class="form-control rounded-pill" required>
        </div>
        <div class="col-md-6">
          <label for="direccion" class="form-label">Dirección</label>
          <input type="text" name="direccion" id="direccion" class="form-control rounded-pill">
        </div>
        <div class="col-md-6">
          <label for="rolSelect" class="form-label">Rol</label>
          <select name="rol" id="rolSelect" class="form-select rounded-pill" required>
            <option value="">Seleccione</option>
            <option value="administrador">Administrador</option>
            <option value="profesor">Profesor</option>
            <option value="estudiante">Estudiante</option>
          </select>
        </div>
        <div class="col-md-6" id="passGroup">
          <label for="contrasena" class="form-label">Contraseña</label>
          <input type="password" name="contrasena" id="contrasena" class="form-control rounded-pill" required>
        </div>

        <!-- Campos extras (visibilidad controlada por JS según el rol) -->
        <div class="col-md-6 extra-campos" id="grupoTelefono" style="display: none;">
          <label for="telefono" class="form-label">Teléfono</label>
          <input type="text" name="telefono" id="telefono" class="form-control rounded-pill">
        </div>
        <div class="col-md-6 extra-campos" id="grupoEspecialidad" style="display: none;">
          <label for="especialidad" class="form-label">Especialidad</label>
          <input type="text" name="especialidad" id="especialidad" class="form-control rounded-pill">
        </div>
        <div class="col-md-6 extra-campos" id="grupoMatricula" style="display: none;">
          <label for="matricula" class="form-label">Matrícula</label>
          <input type="text" name="matricula" id="matricula" class="form-control rounded-pill">
        </div>
        <div class="col-md-6 extra-campos" id="grupoCurso" style="display: none;">
          <label for="cursoSelect" class="form-label">Curso Actual</label>
          <select name="id_curso" id="cursoSelect" class="form-select rounded-pill">
            <option value="">Cargando cursos...</option>
          </select>
        </div>
        <div class="col-md-6 extra-campos" id="grupoAnioAcademico" style="display: none;">
          <label for="anioAcademicoSelect" class="form-label">Año Académico</label>
          <select name="id_anio_academico" id="anioAcademicoSelect" class="form-select rounded-pill">
            <option value="">Cargando años...</option>
          </select>
        </div>

      </div>
      <div class="modal-footer d-flex justify-content-center border-0 p-4 pt-0">
        <button class="btn btn-secondary rounded-pill px-4" type="button" data-bs-dismiss="modal"><i
            class="bi bi-x-circle me-2"></i> Cancelar</button>
        <button class="btn btn-success rounded-pill px-4" type="submit"><i class="bi bi-save me-2"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Inicializa el modal de Bootstrap
  const modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
  const form = document.getElementById('formUsuario');
  const rolSelect = document.getElementById('rolSelect');
  const modalTitle = document.getElementById('modalUsuarioLabel'); // El título del modal

  /**
   * Maneja la visibilidad de los campos extra del formulario
   * basándose en el rol seleccionado.
   */
  rolSelect.addEventListener('change', () => {
    const rol = rolSelect.value;
    // Oculta todos los campos extra primero
    document.querySelectorAll('.extra-campos').forEach(el => el.style.display = 'none');

    // Muestra campos específicos según el rol
    if (rol === 'profesor' || rol === 'administrador') {
      document.getElementById('grupoTelefono').style.display = 'block';
      document.getElementById('grupoEspecialidad').style.display = 'block';
    } else if (rol === 'estudiante') {
      document.getElementById('grupoMatricula').style.display = 'block';
      document.getElementById('grupoCurso').style.display = 'block';
      document.getElementById('grupoAnioAcademico').style.display = 'block'; // Mostrar año académico para estudiantes
    }
  });

  /**
   * Abre el modal para registrar un nuevo usuario.
   * Resetea el formulario y muestra el campo de contraseña.
   */
  function abrirModal() {
    form.reset(); // Limpia todos los campos del formulario
    document.getElementById('id_usuario').value = ''; // Asegura que no haya ID de usuario para nueva creación
    document.getElementById('passGroup').style.display = 'block'; // Muestra el campo de contraseña para nuevos usuarios
    document.querySelectorAll('.extra-campos').forEach(el => el.style.display = 'none'); // Oculta campos extra
    modalTitle.textContent = '👥 Registrar Usuario'; // Cambia el título a "Registrar"

    cargarCursos(); // Carga los cursos disponibles
    cargarAniosAcademicos(); // Carga los años académicos disponibles

    modal.show(); // Muestra el modal
  }


  /**
   * Abre el modal para editar un usuario existente.
   * Carga los datos del usuario desde el servidor y prellena el formulario.
   * Oculta el campo de contraseña.
   * @param {number} id - El ID del usuario a editar.
   */
  function editarUsuario(id) {
    // Cambia el título del modal a "Editar Usuario"
    modalTitle.textContent = '✍️ Editar Usuario';

    fetch(`../api/obtener_usuario.php?id=${id}`)
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        // Verifica si la API devolvió un error o no encontró el usuario
        if (!data.status) {
          throw new Error(data.message || "Error al obtener datos del usuario.");
        }

        // Rellena los campos del formulario con los datos del usuario
        for (let campo in data.data) { // Asume que los datos del usuario están en data.data
          if (form.elements[campo]) {
            form.elements[campo].value = data.data[campo];
          }
        }
        document.getElementById('id_usuario').value = id; // Establece el ID del usuario para la edición
        document.getElementById('passGroup').style.display = 'none'; // Oculta el campo de contraseña para edición (no se edita la contraseña directamente aquí)

        // Dispara el evento 'change' en el select de rol para mostrar/ocultar campos extra
        rolSelect.dispatchEvent(new Event('change'));

        // Carga los selects de curso y año académico y preselecciona los valores
        if (data.data.rol === 'estudiante') {
          cargarCursos().then(() => {
            // Asegúrate de que el campo 'id_curso' exista en los datos del usuario
            if (data.data.id_curso) {
              form.elements['id_curso'].value = data.data['id_curso'];
            }
          }).catch(err => console.error("Error al cargar cursos para edición:", err));

          cargarAniosAcademicos().then(() => {
            // Asegúrate de que el campo 'id_anio_academico' exista en los datos del usuario
            if (data.data.id_anio_academico) {
              form.elements['id_anio_academico'].value = data.data['id_anio_academico'];
            }
          }).catch(err => console.error("Error al cargar años académicos para edición:", err));
        }

        modal.show(); // Muestra el modal después de cargar los datos
      })
      .catch(err => {
        console.error("Error al cargar los datos del usuario:", err);
        // Usar un modal personalizado en lugar de alert()
        mostrarMensajeModal("Error al cargar los datos del usuario. Por favor, intente de nuevo.");
      });
  }

  /**
   * Maneja el envío del formulario para guardar (crear o actualizar) un usuario.
   */
  form.addEventListener('submit', e => {
    e.preventDefault(); // Previene el envío tradicional del formulario
    const datos = new FormData(form);

    fetch('../api/guardar_usuario.php', {
        method: 'POST',
        body: datos
      })
      .then(res => {
        if (!res.status) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
      })
      .then(r => {
        if (r.status) {
          // Usar un modal personalizado en lugar de alert()
          mostrarMensajeModal(r.message, () => location.reload()); // Recarga la página al cerrar el modal
        } else {
          console.error("Error en la respuesta del servidor:", r.data);
          // Usar un modal personalizado en lugar de alert()
          console.log(r.message)
          mostrarMensajeModal('Error: ' + (r.message || 'Ocurrió un error al guardar el usuario.'));
        }
      })
      .catch(err => {
        console.error("Error de conexión o al procesar la respuesta:", err);
        // Usar un modal personalizado en lugar de alert()
        mostrarMensajeModal('Error de conexión. Por favor, intente de nuevo.');
      });
  });

  /**
   * Cambia el estado (activo/inactivo) de un usuario.
   * @param {number} id - El ID del usuario cuyo estado se va a cambiar.
   */
  function cambiarEstado(id) {
    // Reemplaza el confirm nativo con un modal de confirmación personalizado
    mostrarConfirmacionModal('¿Estás seguro de cambiar el estado del usuario?', () => {
      const formData = new FormData();
      formData.append('id_usuario', id);

      fetch('../api/cambiar_estado_usuario.php', {
          method: 'POST',
          body: formData
        })
        .then(res => {
          if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
          }
          return res.json();
        })
        .then(respuesta => {
          if (respuesta.status) {
            mostrarMensajeModal(respuesta.message, () => location.reload());
          } else {
            console.error("Error en la respuesta del servidor:", respuesta.data);
            mostrarMensajeModal('Error: ' + (respuesta.message || 'Ocurrió un error al cambiar el estado.'));
          }
        })
        .catch(err => {
          console.error("Error de conexión al cambiar estado:", err);
          mostrarMensajeModal('Error de conexión. Por favor, intente de nuevo.');
        });
    });
  }

  /**
   * Carga los cursos desde el servidor y rellena el select de cursos.
   * @returns {Promise<void>} Una promesa que se resuelve cuando los cursos han sido cargados.
   */
  function cargarCursos() {
    const cursoSelect = document.getElementById('cursoSelect');
    cursoSelect.innerHTML = '<option value="">Cargando cursos...</option>';

    return fetch('../api/obtener_cursos.php')
      .then(res => {
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        return res.json();
      })
      .then(data => {
        if (data.status && Array.isArray(data.data)) {
          cursoSelect.innerHTML = '<option value="">Seleccione un curso</option>';
          data.data.forEach(curso => {
            const option = document.createElement('option');
            option.value = curso.id_curso;
            option.textContent = curso.nombre;
            cursoSelect.appendChild(option);
          });
        } else {
          cursoSelect.innerHTML = '<option value="">Error al cargar cursos</option>';
          console.error("Error o formato de datos incorrecto para cursos:", data.message || data);
        }
      })
      .catch(err => {
        cursoSelect.innerHTML = '<option value="">Error de conexión</option>';
        console.error("Error de conexión al cargar cursos:", err);
      });
  }

  /**
   * Carga los años académicos desde el servidor y rellena el select de años académicos.
   * @returns {Promise<void>} Una promesa que se resuelve cuando los años han sido cargados.
   */
  function cargarAniosAcademicos() {
    const anioAcademicoSelect = document.getElementById('anioAcademicoSelect');
    anioAcademicoSelect.innerHTML = '<option value="">Cargando años...</option>';

    return fetch('../api/obtener_anios_academicos.php')
      .then(res => {
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        return res.json();
      })
      .then(data => {
        if (data.status && Array.isArray(data.data)) {
          anioAcademicoSelect.innerHTML = '<option value="">Seleccione un año académico</option>';
          data.data.forEach(anio => {
            const option = document.createElement('option');
            option.value = anio.id_anio_academico;
            option.textContent = anio.nombre_anio;
            anioAcademicoSelect.appendChild(option);
          });
        } else {
          anioAcademicoSelect.innerHTML = '<option value="">Error al cargar años</option>';
          console.error("Error o formato de datos incorrecto para años académicos:", data.message || data);
        }
      })
      .catch(err => {
        anioAcademicoSelect.innerHTML = '<option value="">Error de conexión</option>';
        console.error("Error de conexión al cargar años académicos:", err);
      });
  }

  // --- Funciones para reemplazar alert() y confirm() con modales de Bootstrap ---

  /**
   * Muestra un modal personalizado para mensajes.
   * @param {string} message - El mensaje a mostrar.
   * @param {function} [callback] - Función a ejecutar después de cerrar el modal.
   */
  function mostrarMensajeModal(message, callback = () => {}) {
    // Si el modal ya existe, lo actualiza. Si no, lo crea.
    let msgModal = document.getElementById('customMessageModal');
    if (!msgModal) {
      msgModal = document.createElement('div');
      msgModal.id = 'customMessageModal';
      msgModal.classList.add('modal', 'fade');
      msgModal.setAttribute('tabindex', '-1');
      msgModal.setAttribute('aria-hidden', 'true');
      msgModal.innerHTML = `
        <div class="modal-dialog modal-sm modal-dialog-centered">
          <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header bg-primary text-white rounded-top-3">
              <h5 class="modal-title">Mensaje</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center">
              <p id="customMessageText"></p>
            </div>
            <div class="modal-footer d-flex justify-content-center border-0">
              <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">Aceptar</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(msgModal);
    }

    document.getElementById('customMessageText').textContent = message;
    const bsModal = new bootstrap.Modal(msgModal);
    bsModal.show();

    // Limpiar el evento para evitar múltiples ejecuciones
    const dismissHandler = () => {
        callback();
        msgModal.removeEventListener('hidden.bs.modal', dismissHandler);
    };
    msgModal.addEventListener('hidden.bs.modal', dismissHandler);
  }

  /**
   * Muestra un modal personalizado para confirmaciones.
   * @param {string} message - El mensaje de confirmación.
   * @param {function} onConfirm - Función a ejecutar si el usuario confirma.
   */
  function mostrarConfirmacionModal(message, onConfirm) {
    let confModal = document.getElementById('customConfirmModal');
    if (!confModal) {
      confModal = document.createElement('div');
      confModal.id = 'customConfirmModal';
      confModal.classList.add('modal', 'fade');
      confModal.setAttribute('tabindex', '-1');
      confModal.setAttribute('aria-hidden', 'true');
      confModal.innerHTML = `
        <div class="modal-dialog modal-sm modal-dialog-centered">
          <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header bg-warning text-dark rounded-top-3">
              <h5 class="modal-title">Confirmación</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center">
              <p id="customConfirmText"></p>
            </div>
            <div class="modal-footer d-flex justify-content-center border-0">
              <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-primary rounded-pill px-4" id="confirmActionButton">Confirmar</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(confModal);
    }

    document.getElementById('customConfirmText').textContent = message;
    const bsModal = new bootstrap.Modal(confModal);
    bsModal.show();

    const confirmButton = document.getElementById('confirmActionButton');
    // Limpiar listener anterior para evitar múltiples ejecuciones
    const newConfirmHandler = () => {
      onConfirm();
      bsModal.hide(); // Oculta el modal después de la acción
      confirmButton.removeEventListener('click', newConfirmHandler); // Remueve el listener
    };
    confirmButton.addEventListener('click', newConfirmHandler);

    // Opcional: remover el listener si el modal se cierra sin confirmar
    const dismissHandler = () => {
      confirmButton.removeEventListener('click', newConfirmHandler);
      confModal.removeEventListener('hidden.bs.modal', dismissHandler);
    };
    confModal.addEventListener('hidden.bs.modal', dismissHandler);
  }

</script>
<?php include_once('footer.php'); ?>