<?php include_once('header.php'); ?>
<!-- Contenido principal -->
<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
    <h2 class="mb-5 text-center fw-bold text-primary-emphasis">
      <i class="bi bi-speedometer2 me-2"></i> Resumen General
    </h2>
    <div class="row g-4">
      <!-- Usuarios Activos -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-primary text-white card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-people-fill fs-2 me-2"></i>
              <h6 class="mb-0">Usuarios Activos</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalUsuarios">0</h2>
          </div>
        </div>
      </div>

      <!-- Estudiantes Activos -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-success text-white card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-mortarboard-fill fs-2 me-2"></i>
              <h6 class="mb-0">Estudiantes Activos</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalEstudiantes">0</h2>
          </div>
        </div>
      </div>

      <!-- Profesores Activos -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-info text-white card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-person-badge-fill fs-2 me-2"></i>
              <h6 class="mb-0">Profesores Activos</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalProfesores">0</h2>
          </div>
        </div>
      </div>

      <!-- Cursos -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-warning text-dark card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-journal-text fs-2 me-2"></i>
              <h6 class="mb-0">Cursos</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalCursos">0</h2>
          </div>
        </div>
      </div>

      <!-- Semestres -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-secondary text-white card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-calendar-week-fill fs-2 me-2"></i>
              <h6 class="mb-0">Semestres</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalSemestres">0</h2>
          </div>
        </div>
      </div>

      <!-- Asignaturas -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-dark text-white card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-book-half fs-2 me-2"></i>
              <h6 class="mb-0">Asignaturas</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalAsignaturas">0</h2>
          </div>
        </div>
      </div>

      <!-- Aulas -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-light text-dark card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-building fs-2 me-2"></i>
              <h6 class="mb-0">Aulas</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalAulas">0</h2>
          </div>
        </div>
      </div>

      <!-- Horarios -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-info text-white card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-clock-history fs-2 me-2"></i>
              <h6 class="mb-0">Horarios</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalHorarios">0</h2>
          </div>
        </div>
      </div>

      <!-- Publicaciones -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-success text-white card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-megaphone-fill fs-2 me-2"></i>
              <h6 class="mb-0">Publicaciones</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalPublicaciones">0</h2>
          </div>
        </div>
      </div>

      <!-- Requisitos Matrícula -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-warning text-dark card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-card-checklist fs-2 me-2"></i>
              <h6 class="mb-0">Requisitos Matrícula</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalRequisitos">0</h2>
          </div>
        </div>
      </div>

      <!-- Notas Registradas -->
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100 bg-danger text-white card-hover">
          <div class="card-body d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-file-earmark-bar-graph-fill fs-2 me-2"></i>
              <h6 class="mb-0">Notas Registradas</h6>
            </div>
            <h2 class="display-6 fw-bold text-end mb-0" id="totalNotas">0</h2>
          </div>
        </div>
      </div>

    </div> <!-- Agrega más tarjetas aquí con sus íconos e IDs: totalSemestres, totalAsignaturas, etc. -->
  </div>
</div>

<!-- Estilos adicionales -->
<style>
  .card-hover {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  }

  .card-hover:hover {
    transform: translateY(-4px);
    box-shadow: 0 1rem 1.5rem rgba(0, 0, 0, 0.1);
  }

  h2,
  h6 {
    user-select: none;
  }
</style>

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">


<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script para cargar los datos -->
<script>
  fetch('../api/dashboard_data.php')
    .then(res => res.json())
    .then(data => {
      if (data.status) {
        document.getElementById('totalUsuarios').textContent = data.totales.usuarios;
        document.getElementById('totalEstudiantes').textContent = data.totales.estudiantes;
        document.getElementById('totalProfesores').textContent = data.totales.profesores;
        document.getElementById('totalCursos').textContent = data.totales.cursos;
        document.getElementById('totalSemestres').textContent = data.totales.semestres;
        document.getElementById('totalAsignaturas').textContent = data.totales.asignaturas;
        document.getElementById('totalAulas').textContent = data.totales.aulas;
        document.getElementById('totalHorarios').textContent = data.totales.horarios;
        document.getElementById('totalPublicaciones').textContent = data.totales.publicaciones;
        document.getElementById('totalRequisitos').textContent = data.totales.requisitos;
        document.getElementById('totalNotas').textContent = data.totales.notas;
      } else {
        console.error('Error al cargar datos:', data.message);
      }
    })
    .catch(err => console.error('Fetch error:', err));
</script>



<?php include_once('footer.php'); ?>
