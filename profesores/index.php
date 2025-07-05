<?php
// Incluye funciones esenciales para el sistema, como la verificación de sesión y rol.
require_once '../includes/functions.php';
// Asegura que solo los usuarios con el rol 'Profesor' puedan acceder a esta página.
check_login_and_role('Profesor');

// Incluye la configuración de la base de datos para establecer la conexión.
require_once '../config/database.php';

// Define el título de la página.
$titulo_pagina = "Panel del Profesor - Resumen";
// Incluye el encabezado de la página.
include_once '../includes/header.php';

// Obtiene el ID del usuario actual de la sesión.
$id_usuario_actual = $_SESSION['user_id'];

// Obtiene el ID del profesor asociado al usuario logueado.
$stmt_profesor = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
$stmt_profesor->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
$stmt_profesor->execute();
$id_profesor_actual = $stmt_profesor->fetchColumn();

// Si no se encuentra un perfil de profesor asociado, redirige con un mensaje de error.
if (!$id_profesor_actual) {
    set_flash_message('danger', 'Error: No se encontró el perfil de profesor asociado a su usuario.');
    header('Location: ../logout.php'); // O a una página de error o inicio de sesión
    exit;
}

// --- Obtención de Datos para el Resumen ---

// 1. Contar CVs del profesor
$cantidad_cvs = 0;
$stmt_cvs = $pdo->prepare("SELECT COUNT(*) FROM cvs_profesores WHERE id_profesor = :id_profesor");
$stmt_cvs->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
$stmt_cvs->execute();
$cantidad_cvs = $stmt_cvs->fetchColumn();

// 2. Contar Asignaturas Asociadas (asignadas)
$cantidad_asignaturas_asignadas = 0;
$stmt_asignadas = $pdo->prepare("SELECT COUNT(*) FROM profesores_asignaturas_asignadas WHERE id_profesor = :id_profesor");
$stmt_asignadas->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
$stmt_asignadas->execute();
$cantidad_asignaturas_asignadas = $stmt_asignadas->fetchColumn();

// 3. Contar Asignaturas Sugeridas
$cantidad_asignaturas_sugeridas = 0;
// NOTA: Tu tabla `profesores_asignaturas_sugeridas` tiene `fk_profesor_sugiere` referenciando `usuarios.id`,
// pero `profesores.id` se obtiene de `usuarios.id`. Asumiendo que `id_profesor` en esta tabla
// se refiere al `id` de la tabla `profesores`. Si no, ajusta esta FK en tu DB.
$stmt_sugeridas = $pdo->prepare("SELECT COUNT(*) FROM profesores_asignaturas_sugeridas WHERE id_profesor = :id_profesor");
$stmt_sugeridas->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
$stmt_sugeridas->execute();
$cantidad_asignaturas_sugeridas = $stmt_sugeridas->fetchColumn();


// 4. Contar Estudiantes (únicos) asociados a las asignaturas del profesor en el semestre actual/activo
// Esta es más compleja, requiere unir `horarios`, `inscripciones_estudiantes` y `estudiantes`.
// Consideramos "estudiantes que dispone" aquellos en sus clases activas.
$cantidad_estudiantes_disponibles = 0;
try {
    $stmt_estudiantes = $pdo->prepare("
        SELECT COUNT(DISTINCT ie.id_estudiante)
        FROM horarios h
        JOIN inscripciones_estudiantes ie ON h.id = ie.id_horario
        JOIN profesores p ON h.id_profesor = p.id
        WHERE p.id = :id_profesor
        -- Opcional: Filtrar por semestre actual si tienes uno definido como 'activo'
        -- AND h.id_semestre = (SELECT id FROM semestres WHERE CURRENT_DATE BETWEEN fecha_inicio AND fecha_fin LIMIT 1)
    ");
    $stmt_estudiantes->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
    $stmt_estudiantes->execute();
    $cantidad_estudiantes_disponibles = $stmt_estudiantes->fetchColumn();
} catch (PDOException $e) {
    // Manejar el error, por ejemplo, loguear o establecer a 0
    error_log("Error al obtener estudiantes disponibles: " . $e->getMessage());
    $cantidad_estudiantes_disponibles = 0;
}


// 5. Contar Historiales Ya Enviados (Notas finales con estado_envio_acta='APROBADA_ADMIN')
// Un historial se considera "enviado" y finalizado cuando la nota ha sido aprobada por el administrador.
$cantidad_historiales_enviados = 0;
try {
    $stmt_historiales = $pdo->prepare("
        SELECT COUNT(DISTINCT ha.id)
        FROM historial_academico ha
        JOIN asignaturas a ON ha.id_asignatura = a.id
        JOIN profesores_asignaturas_asignadas paa ON a.id = paa.id_asignatura
        WHERE paa.id_profesor = :id_profesor
        
    ");
    $stmt_historiales->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
    $stmt_historiales->execute();
    $cantidad_historiales_enviados = $stmt_historiales->fetchColumn();
} catch (PDOException $e) {
    error_log("Error al obtener historiales enviados: " . $e->getMessage());
    $cantidad_historiales_enviados = 0;
}


// 6. Contar Cursos Asociados a sus asignaturas (únicos)
$cantidad_cursos_asociados = 0;
try {
    $stmt_cursos = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id)
        FROM cursos c
        JOIN asignaturas a ON c.id = a.id_curso
        JOIN profesores_asignaturas_asignadas paa ON a.id = paa.id_asignatura
        WHERE paa.id_profesor = :id_profesor
    ");
    $stmt_cursos->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
    $stmt_cursos->execute();
    $cantidad_cursos_asociados = $stmt_cursos->fetchColumn();
} catch (PDOException $e) {
    error_log("Error al obtener cursos asociados: " . $e->getMessage());
    $cantidad_cursos_asociados = 0;
}

?>
 

<h1 class="mt-4 text-center text-primary mb-2 fw-bold">
  <i class="fas fa-user-tie me-2"></i>Panel de Profesor
</h1>
<p class="text-center text-muted mb-4 fs-5">Resumen general de tus asignaciones y actividades</p>

<div class="container mb-5">
  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">

    <!-- Ejemplo de tarjeta reutilizable -->
    <div class="col">
      <div class="card shadow-sm border-0 rounded-4 h-100 hover-scale">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="icon-circle-soft bg-primary text-white">
            <i class="fas fa-file-alt fa-lg"></i>
          </div>
          <div>
            <h6 class="mb-1 fw-bold text-secondary">Currículum Vitae</h6>
            <h4 class="fw-bold"><?php echo $cantidad_cvs; ?></h4>
            <small class="text-muted">CV(s) subido(s)</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Estudiantes -->
    <div class="col">
      <div class="card shadow-sm border-0 rounded-4 h-100 hover-scale">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="icon-circle-soft bg-success text-white">
            <i class="fas fa-user-graduate fa-lg"></i>
          </div>
          <div>
            <h6 class="mb-1 fw-bold text-secondary">Estudiantes Activos</h6>
            <h4 class="fw-bold"><?php echo $cantidad_estudiantes_disponibles; ?></h4>
            <small class="text-muted">en tus clases</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Asignaturas -->
    <div class="col">
      <div class="card shadow-sm border-0 rounded-4 h-100 hover-scale">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="icon-circle-soft bg-info text-white">
            <i class="fas fa-chalkboard fa-lg"></i>
          </div>
          <div>
            <h6 class="mb-1 fw-bold text-secondary">Asignaturas Asignadas</h6>
            <h4 class="fw-bold"><?php echo $cantidad_asignaturas_asignadas; ?></h4>
            <small class="text-muted">bajo tu responsabilidad</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Historiales -->
    <div class="col">
      <div class="card shadow-sm border-0 rounded-4 h-100 hover-scale">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="icon-circle-soft bg-warning text-dark">
            <i class="fas fa-history fa-lg"></i>
          </div>
          <div>
            <h6 class="mb-1 fw-bold text-secondary">Historiales Finalizados</h6>
            <h4 class="fw-bold"><?php echo $cantidad_historiales_enviados; ?></h4>
            <small class="text-muted">actas aprobadas</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Asignaturas sugeridas -->
    <div class="col">
      <div class="card shadow-sm border-0 rounded-4 h-100 hover-scale">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="icon-circle-soft bg-secondary text-white">
            <i class="fas fa-lightbulb fa-lg"></i>
          </div>
          <div>
            <h6 class="mb-1 fw-bold text-secondary">Asignaturas Sugeridas</h6>
            <h4 class="fw-bold"><?php echo $cantidad_asignaturas_sugeridas; ?></h4>
            <small class="text-muted">por ti o administración</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Cursos Asociados -->
    <div class="col">
      <div class="card shadow-sm border-0 rounded-4 h-100 hover-scale">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="icon-circle-soft bg-dark text-white">
            <i class="fas fa-graduation-cap fa-lg"></i>
          </div>
          <div>
            <h6 class="mb-1 fw-bold text-secondary">Cursos Asociados</h6>
            <h4 class="fw-bold"><?php echo $cantidad_cursos_asociados; ?></h4>
            <small class="text-muted">donde impartes clase</small>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>









<?php
// Incluye el pie de página.
include_once '../includes/footer.php';
?>

<style>
    /* Estilos adicionales para un diseño más elegante y minimalista */
    body {
        background-color: #f8f9fa;
        /* Un fondo claro para realzar las tarjetas */
    }

    .dashboard-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        cursor: pointer;
        overflow: hidden;
        /* Para asegurar que el icono no se salga */
        position: relative;
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }

    .icon-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 15px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .dashboard-card .card-body {
        padding: 1.5rem;
    }

    /* Degradados de color para las tarjetas (opcional, pero añade elegancia) */
    .bg-gradient-info {
        background: linear-gradient(45deg, #0dcaf0, #0a99ba);
    }

    .bg-gradient-success {
        background: linear-gradient(45deg, #198754, #146c43);
    }

    .bg-gradient-primary {
        background: linear-gradient(45deg, #0d6efd, #0b5ed7);
    }

    .bg-gradient-warning {
        background: linear-gradient(45deg, #ffc107, #d39e00);
    }

    .bg-gradient-dark {
        background: linear-gradient(45deg, #212529, #343a40);
    }

    .bg-gradient-secondary {
        background: linear-gradient(45deg, #6c757d, #5c636a);
    }

    /* Ajuste para el enlace "stretched-link" para que se vea mejor */
    .stretched-link {
        font-size: 0.85rem;
        font-weight: 600;
        opacity: 0.8;
        transition: opacity 0.2s ease-in-out;
    }

    .stretched-link:hover {
        opacity: 1;
    }
</style>