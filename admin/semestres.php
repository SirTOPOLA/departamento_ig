<?php include_once('header.php'); ?>
 

<style>
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
            background-color: #007bff !important;
        }
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .table thead {
            background-color: #007bff;
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #e2f0ff;
        }
        .text-break-word {
            word-break: break-word;
        }
    </style>
 

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">📅 Gestión de Semestres</h3>
      <button class="btn btn-success rounded-pill px-4" onclick="abrirModalSemestre()">
        <i class="bi bi-plus-circle me-2"></i> Nuevo Semestre
      </button>
    </div>

    <!-- Buscador Dinámico -->
    <div class="mb-3" style="max-width:400px;">
      <div class="input-group shadow-sm rounded-pill overflow-hidden">
        <input type="search" id="busquedaSemestre" class="form-control border-0 ps-3" placeholder="Buscar semestre o curso">
        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
      </div>
    </div>

    <div class="table-responsive shadow-sm rounded-3">
      <table class="table table-hover table-striped align-middle">
        <thead class="table-primary">
          <tr>
            <th>#</th>
            <th>Nombre Semestre</th>
            <th>Curso</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="listaSemestres">
          <!-- Aquí se cargarán los semestres dinámicamente -->
          <tr>
            <td colspan="4" class="text-center py-4">Cargando semestres...</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación (manejo en JS si es necesario para grandes datasets o búsqueda en servidor) -->
    <!-- Para búsqueda en tiempo real, se asume que todos los datos se cargan inicialmente o se maneja el filtro en cliente. -->
    <nav aria-label="Paginación de semestres" class="mt-4" style="display: none;">
      <ul class="pagination justify-content-center" id="paginacionSemestres">
        <!-- Los elementos de paginación se generarían aquí si fuera necesario -->
      </ul>
    </nav>
  </div>
</div>

<!-- Modal Semestre -->
<div class="modal fade" id="modalSemestre" tabindex="-1" aria-labelledby="modalSemestreLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="formSemestre" class="modal-content rounded-4 shadow-lg">
      <div class="modal-header bg-primary text-white rounded-top-3 p-4">
        <h5 class="modal-title fs-5" id="modalSemestreLabel"><i class="bi bi-calendar-event me-2"></i> Nuevo Semestre</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="id_semestre" id="id_semestre">
        <div class="mb-3">
          <label for="nombre_semestre" class="form-label">Nombre del semestre</label>
          <input type="text" class="form-control rounded-pill" id="nombre_semestre" name="nombre" required>
        </div>
        <div class="mb-3">
          <label for="curso_semestre" class="form-label">Curso</label>
          <select id="curso_semestre" name="curso_id" class="form-select rounded-pill" required>
            <option value="">Cargando cursos...</option>
            <!-- Opciones de cursos se cargarán dinámicamente -->
          </select>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-center border-0 p-4 pt-0">
        <button class="btn btn-secondary rounded-pill px-4" type="button" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i> Cancelar</button>
        <button class="btn btn-success rounded-pill px-4" type="submit"><i class="bi bi-save me-2"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Inicialización de modales de Bootstrap
  const modalSemestre = new bootstrap.Modal(document.getElementById('modalSemestre'));
  const formSemestre = document.getElementById('formSemestre');
  const listaSemestres = document.getElementById('listaSemestres');
  const busquedaSemestreInput = document.getElementById('busquedaSemestre');
  const modalSemestreLabel = document.getElementById('modalSemestreLabel'); // Título del modal

  let allSemestresData = []; // Para almacenar todos los semestres y permitir búsqueda en tiempo real
  let allCursosData = []; // Para almacenar todos los cursos para el select

  /**
   * Carga los cursos desde la API y los almacena para el select del modal.
   * @returns {Promise<void>} Una promesa que se resuelve cuando los cursos han sido cargados.
   */
  async function cargarCursosSemestre() {
    const cursoSelect = document.getElementById('curso_semestre');
    cursoSelect.innerHTML = '<option value="">Cargando cursos...</option>'; // Estado inicial

    try {
      const res = await fetch('../api/obtener_cursos.php');
      if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
      const data = await res.json();

      if (data.status && Array.isArray(data.data)) {
        allCursosData = data.data; // Almacena todos los cursos
        cursoSelect.innerHTML = '<option value="">Seleccione un curso</option>';
     
        data.data.forEach(curso => {
          const option = document.createElement('option');
          option.value = curso.id_curso;
          option.textContent = `${curso.nombre} (${curso.turno || 'N/A'}/${curso.grupo})`; // Muestra nombre y turno
          cursoSelect.appendChild(option);
        });
      } else {
        cursoSelect.innerHTML = '<option value="">Error al cargar cursos</option>';
        console.error("Error o formato de datos incorrecto para cursos:", data.message || data);
      }
    } catch (err) {
      cursoSelect.innerHTML = '<option value="">Error de conexión al cargar cursos</option>';
      console.error("Error de conexión al cargar cursos:", err);
    }
  }

  /**
   * Abre el modal para registrar un nuevo semestre.
   * Resetea el formulario y actualiza el título del modal.
   */
  async function abrirModalSemestre() {
    formSemestre.reset();
    document.getElementById('id_semestre').value = ''; // Asegura que no haya ID para nueva creación
    modalSemestreLabel.innerHTML = '<i class="bi bi-calendar-event me-2"></i> Nuevo Semestre'; // Título para nuevo

    await cargarCursosSemestre(); // Carga los cursos para el select
    modalSemestre.show();
  }

  /**
   * Abre el modal para editar un semestre existente.
   * Carga los datos del semestre y prellena el formulario.
   * @param {number} id - El ID del semestre a editar.
   */
  async function editarSemestre(id) {
    modalSemestreLabel.innerHTML = '<i class="bi bi-pencil-square me-2"></i> Editar Semestre'; // Título para edición

    await cargarCursosSemestre(); // Carga los cursos antes de intentar preseleccionar

    try {
      const res = await fetch(`../api/obtener_semestres.php?id=${id}`);
      if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
      const data = await res.json();

      if (data.status && data.data) {
        formSemestre.nombre_semestre.value = data.data.nombre;
        document.getElementById('curso_semestre').value = data.data.curso_id;
        document.getElementById('id_semestre').value = id;
      } else {
        mostrarMensajeModal('Error al cargar datos del semestre: ' + (data.message || 'Semestre no encontrado.'));
        console.error("Error o datos inválidos al obtener semestre:", data.message || data);
        return;
      }
    } catch (err) {
      mostrarMensajeModal('Error de conexión al cargar datos del semestre.');
      console.error("Error de conexión al cargar semestre:", err);
      return;
    }

    modalSemestre.show();
  }

  /**
   * Maneja el envío del formulario para guardar (crear o actualizar) un semestre.
   */
  formSemestre.addEventListener('submit', async e => {
    e.preventDefault();
    const formData = new FormData(formSemestre);

    try {
      const res = await fetch('../api/guardar_semestre.php', {
        method: 'POST',
        body: formData
      });
      if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
      const respuesta = await res.json();

      if (respuesta.status) {
        mostrarMensajeModal(respuesta.message, () => {
          modalSemestre.hide();
          cargarSemestres(); // Recarga la tabla de semestres
          location.reload()
        });
      } else {
        mostrarMensajeModal('Error: ' + (respuesta.message || 'Ocurrió un error al guardar el semestre.'));
        console.error("Error en la respuesta del servidor:", respuesta.data || respuesta.message);
      }
    } catch (err) {
      mostrarMensajeModal('Error de conexión. Por favor, intente de nuevo.');
      console.error("Error de conexión o al procesar la respuesta:", err);
    }
  });

  /**
   * Carga la lista de semestres desde la API y la muestra en la tabla.
   * Filtra los resultados si se proporciona un término de búsqueda.
   * @param {string} searchTerm - Término de búsqueda para filtrar la lista.
   */
  async function cargarSemestres(searchTerm = '') {
    listaSemestres.innerHTML = '<tr><td colspan="4" class="text-center py-4">Cargando semestres...</td></tr>';

    if (allSemestresData.length === 0 && !searchTerm) {
      // Solo cargar de la API si no tenemos datos y no estamos buscando específicamente
      try {
        const res = await fetch('../api/obtener_semestres.php');
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        const data = await res.json();

        if (data.status && Array.isArray(data.data)) {
          allSemestresData = data.data; // Almacena todos los semestres
        } else {
          listaSemestres.innerHTML = '<tr><td colspan="4" class="text-center py-4">Error al cargar semestres: ' + (data.message || 'Datos inválidos') + '</td></tr>';
          console.error("Error o formato de datos incorrecto para semestres:", data.message || data);
          return;
        }
      } catch (err) {
        listaSemestres.innerHTML = '<tr><td colspan="4" class="text-center py-4">Error de conexión al cargar semestres.</td></tr>';
        console.error("Error de conexión al cargar semestres:", err);
        return;
      }
    }

    const filteredSemestres = allSemestresData.filter(semestre => {
      const searchLower = searchTerm.toLowerCase();
      return (semestre.nombre || '').toLowerCase().includes(searchLower) ||
             (semestre.nombre_curso || '').toLowerCase().includes(searchLower);
    });

    renderSemestresTable(filteredSemestres);
  }

  /**
   * Renderiza la tabla de semestres con los datos proporcionados.
   * @param {Array} semestres - Array de objetos de semestre.
   */
  function renderSemestresTable(semestres) {
    listaSemestres.innerHTML = ''; // Limpia la tabla antes de añadir nuevos elementos

    if (semestres.length === 0) {
      listaSemestres.innerHTML = '<tr><td colspan="4" class="text-center py-4">No se encontraron semestres.</td></tr>';
      return;
    }

    semestres.forEach(semestre => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${semestre.id_semestre || 'N/A'}</td>
        <td>${semestre.nombre || 'N/A'}</td>
        <td>${semestre.nombre_curso || 'N/A'} (${semestre.turno || 'N/A'}) </td>
        <td>
          <button class="btn btn-sm btn-warning rounded-pill px-3" onclick="editarSemestre(${semestre.id_semestre})" title="Editar Semestre">
            <i class="bi bi-pencil-square"></i>
          </button>
        </td>
      `;
      listaSemestres.appendChild(row);
    });
  }

  // Evento para el buscador en tiempo real (con debounce)
  let searchTimeoutSemestre;
  busquedaSemestreInput.addEventListener('input', () => {
    clearTimeout(searchTimeoutSemestre);
    searchTimeoutSemestre = setTimeout(() => {
      cargarSemestres(busquedaSemestreInput.value);
    }, 300); // Pequeño retraso para evitar llamadas excesivas
  });

  // --- Funciones para reemplazar alert() y confirm() con modales de Bootstrap ---
  // (Estas funciones deben estar disponibles globalmente o ser copiadas si no lo están ya)

  /**
   * Muestra un modal personalizado para mensajes.
   * @param {string} message - El mensaje a mostrar.
   * @param {function} [callback] - Función a ejecutar después de cerrar el modal.
   */
  function mostrarMensajeModal(message, callback = () => {}) {
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

    const dismissHandler = () => {
        callback();
        msgModal.removeEventListener('hidden.bs.modal', dismissHandler);
    };
    msgModal.addEventListener('hidden.bs.modal', dismissHandler);
  }

  // Carga inicial de semestres al cargar la página
  document.addEventListener('DOMContentLoaded', () => {
    cargarSemestres();
  });
</script>
 

<?php include_once('footer.php'); ?>
