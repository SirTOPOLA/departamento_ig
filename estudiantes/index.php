<?php
require_once '../includes/functions.php';
check_login_and_role('Estudiante');

require_once '../config/database.php';

$page_title = "Dashboard de Estudiante";
include_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?php echo $page_title; ?></h2>
</div>

<div class="alert alert-info" role="alert">
    Bienvenido, estudiante <?php echo htmlspecialchars($_SESSION['username']); ?>.
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Mi Horario</h5>
                <p class="card-text">Consulta el horario de tus asignaturas para el semestre actual.</p>
                <a href="schedule.php" class="btn btn-primary">Ver Horario</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Inscripción al Semestre</h5>
                <p class="card-text">Regístrate en las asignaturas del semestre actual.</p>
                <a href="enrollment.php" class="btn btn-primary">Inscribirme</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Historial Académico</h5>
                <p class="card-text">Revisa tus notas y el progreso de tu carrera.</p>
                <a href="academic_history.php" class="btn btn-primary">Ver Historial</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Asignaturas Actuales</h5>
                <p class="card-text">Consulta las asignaturas en las que estás inscrito este semestre.</p>
                <a href="current_subjects.php" class="btn btn-primary">Ver Asignaturas</a>
            </div>
        </div>
    </div>
    </div>

<?php include_once '../includes/footer.php'; ?>