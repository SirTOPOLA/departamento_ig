 
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UNGE - Gestión Académica | <?php echo ucfirst($_SESSION['user_role'] ?? 'Invitado'); ?></title>
     
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="../public/css/style.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

</head>
<body>
    <div class="d-flex" id="wrapper">
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-white bg-secondary py-4">
                <i class="fas fa-graduation-cap me-2"></i> UNGE
            </div>
            <div class="list-group list-group-flush">
                <?php
                $current_page = basename($_SERVER['PHP_SELF']); // Obtiene el nombre del archivo actual (ej: index.php)
                $current_folder = basename(dirname($_SERVER['PHP_SELF'])); // Obtiene el nombre de la carpeta actual (ej: admin)
                ?>

<a href="../logout.php" class="list-group-item list-group-item-action bg-dark text-danger">
    <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
</a>


                <?php if (isset($_SESSION['user_role'])): ?>
                    <?php if ($_SESSION['user_role'] === 'Administrador'): ?>
                        <a href="../admin/index.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'index.php' && $current_folder == 'admin') ? 'active' : ''; ?>">
    <i class="fas fa-chart-line me-2"></i> Panel Principal
</a>
<a href="../admin/usuarios.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'usuarios.php') ? 'active' : ''; ?>">
    <i class="fas fa-user-shield me-2"></i> Gestión de Usuarios
</a>
<a href="../admin/profesores.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'profesores.php') ? 'active' : ''; ?>">
    <i class="fas fa-user-tie me-2"></i> Profesores
</a>
<a href="../admin/estudiantes.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'estudiantes.php') ? 'active' : ''; ?>">
    <i class="fas fa-user-graduate me-2"></i> Estudiantes
</a>
<a href="../admin/horarios.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'horarios.php') ? 'active' : ''; ?>">
    <i class="far fa-calendar-alt me-2"></i> Horarios
</a>
<a href="../admin/actas.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'actas.php') ? 'active' : ''; ?>">
    <i class="fas fa-file-signature me-2"></i> Actas / Historial
</a>
<a href="../admin/asignaturas.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'asignaturas.php') ? 'active' : ''; ?>">
    <i class="fas fa-book me-2"></i> Asignaturas
</a>
<a href="../admin/semestres.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'semestres.php') ? 'active' : ''; ?>">
    <i class="fas fa-layer-group me-2"></i> Semestres
</a>
<a href="../admin/cursos.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'cursos.php') ? 'active' : ''; ?>">
    <i class="fas fa-school me-2"></i> Cursos
</a>
<a href="../admin/aulas.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'aulas.php') ? 'active' : ''; ?>">
    <i class="fas fa-door-open me-2"></i> Aulas
</a>
<a href="../admin/anios_academicos.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'anios_academicos.php') ? 'active' : ''; ?>">
    <i class="fas fa-calendar-alt me-2"></i> Año Académico
</a>
<a href="../admin/configuracion.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'configuracion.php') ? 'active' : ''; ?>">
    <i class="fas fa-cogs me-2"></i> Configuración
</a>


                    <?php elseif ($_SESSION['user_role'] === 'Estudiante'): ?>
                        <a href="../estudiantes/index.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'index.php' && $current_folder == 'estudiantes') ? 'active' : ''; ?>">
    <i class="fas fa-home me-2"></i> Panel Principal
</a>
<a href="../estudiantes/horarios.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'horarios.php') ? 'active' : ''; ?>">
    <i class="fas fa-calendar-day me-2"></i> Mi Horario
</a>
<a href="../estudiantes/inscripciones.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'inscripciones.php') ? 'active' : ''; ?>">
    <i class="fas fa-user-check me-2"></i> Inscripción
</a>
<a href="../estudiantes/historial_academico.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'historial_academico.php') ? 'active' : ''; ?>">
    <i class="fas fa-history me-2"></i> Historial Académico
</a>
<a href="../estudiantes/asignaturas.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'asignaturas.php') ? 'active' : ''; ?>">
    <i class="fas fa-book-reader me-2"></i> Mis Asignaturas
</a>

                    <?php elseif ($_SESSION['user_role'] === 'Profesor'): ?>
                        <a href="../profesores/index.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'index.php' && $current_folder == 'profesores') ? 'active' : ''; ?>">
    <i class="fas fa-home me-2"></i> Panel Principal
</a>
<!-- <a href="../profesores/my_subjects.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'my_subjects.php') ? 'active' : ''; ?>">
    <i class="fas fa-chalkboard me-2"></i> Mis Asignaturas
</a> -->
<a href="../profesores/actas.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'actas.php') ? 'active' : ''; ?>">
    <i class="fas fa-clipboard-list me-2"></i> Gestión de Notas
</a>
<!-- <a href="../profesores/estudiantes_lists.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'estudiantes_lists.php') ? 'active' : ''; ?>">
    <i class="fas fa-users me-2"></i> Listado de Estudiantes
</a> -->
<!-- <a href="../profesores/upload_cv.php" class="list-group-item list-group-item-action bg-dark text-white <?= ($current_page == 'upload_cv.php') ? 'active' : ''; ?>">
    <i class="fas fa-file-upload me-2"></i> Subir CV
</a> -->

                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <button class="btn btn-primary" id="menu-toggle"><i class="fas fa-bars"></i></button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item active">
                            <span class="nav-link">
                                ¡Hola, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Invitado'); ?></strong>!
                                <small>(Rol: <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'N/A'); ?>)</small>
                            </span>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid py-4">
              

 
    