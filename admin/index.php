<?php
require_once '../includes/functions.php';
// Inicia la sesión y verifica el rol antes de incluir el header

check_login_and_role('Administrador');
//$_SESSION['user_role'] = 'Administrador';
require_once '../config/database.php';

$page_title = "Panel de Administrador"; // Usado en el título de la página

// --- Fetch dynamic dashboard data ---
$total_estudiantes = 0;
$profesores_activos = 0;
$horarios_publicados = 0;
$noticias_recientes = 0; // Assuming 'noticias' refers to 'publicaciones' with type 'noticia'

try {
    // Total Students
    $stmt = $pdo->query("SELECT COUNT(id) FROM estudiantes");
    $total_estudiantes = $stmt->fetchColumn();

    // Active Professors (assuming 'Activo' status in 'usuarios' table and role 'Profesor')
    $stmt = $pdo->query("SELECT COUNT(p.id) 
                         FROM profesores p
                         JOIN usuarios u ON p.id_usuario = u.id
                         WHERE u.estado = 'Activo'");
    $profesores_activos = $stmt->fetchColumn();

    // Published Schedules (assuming 'horarios' table has published schedules)
    // This count might need refinement based on how "published" is defined in your system.
    // For now, it counts all entries in the 'horarios' table.
    $stmt = $pdo->query("SELECT COUNT(id) FROM horarios");
    $horarios_publicados = $stmt->fetchColumn();

    // Recent News (assuming 'publicaciones' with type 'noticia' in the last 30 days)
    $stmt = $pdo->query("SELECT COUNT(id_publicacion) 
                         FROM publicaciones 
                         WHERE tipo = 'noticia' AND creado_en >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $noticias_recientes = $stmt->fetchColumn();

} catch (PDOException $e) {
    // Log the error for debugging, but don't expose it to the user
    error_log("Database Error: " . $e->getMessage());
    // You could set values to 'Error' or 0, depending on desired behavior
    $total_estudiantes = "-";
    $profesores_activos = "-";
    $horarios_publicados = "-";
    $noticias_recientes = "-";
}


include_once '../includes/header.php'; // Incluye la estructura del sidebar

// --- Contenido específico del dashboard ---
?>


<h1 class="mt-4"> <?= $page_title ?> </h1>
<div class="row g-4 mt-4">
    <!-- Estudiantes -->
    <div class="col-xl-3 col-md-6">
        <div class="card text-white bg-primary shadow rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase fw-bold mb-2">Estudiantes</h6>
                    <h3 class="fw-bold"><?php echo $total_estudiantes; ?></h3>
                </div>
                <i class="fas fa-user-graduate fa-3x opacity-75"></i>
            </div>
        </div>
    </div>

    <!-- Profesores -->
    <div class="col-xl-3 col-md-6">
        <div class="card text-white bg-success shadow rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase fw-bold mb-2">Profesores Activos</h6>
                    <h3 class="fw-bold"><?php echo $profesores_activos; ?></h3>
                </div>
                <i class="fas fa-chalkboard-teacher fa-3x opacity-75"></i>
            </div>
        </div>
    </div>

    <!-- Horarios -->
    <div class="col-xl-3 col-md-6">
        <div class="card text-white bg-info shadow rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase fw-bold mb-2">Horarios Publicados</h6>
                    <h3 class="fw-bold"><?php echo $horarios_publicados; ?></h3>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-light" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
                <i class="fas fa-calendar-check fa-3x opacity-75"></i>
            </div>
        </div>
    </div>

    <!-- Noticias -->
    <div class="col-xl-3 col-md-6">
        <div class="card text-white bg-warning shadow rounded-4 h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase fw-bold mb-2">Noticias Recientes</h6>
                    <h3 class="fw-bold"><?php echo $noticias_recientes; ?></h3>
                </div>
                <i class="fas fa-newspaper fa-3x opacity-75"></i>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once '../includes/footer.php'; // Incluye el pie de página ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once '../includes/footer.php'; // Incluye el pie de página ?>