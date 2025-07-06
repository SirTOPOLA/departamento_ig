<?php
// Configuración PHP para entorno de producción
ini_set('display_errors', 0); // Deshabilitar la visualización de errores por seguridad
error_reporting(E_ALL); // Registrar todos los errores

// Asegúrate de que la ruta a database.php sea correcta. Se asume que este script está en la raíz web.
// Si tu database.php está en 'config/', y este script está en la raíz, es 'config/database.php'.
// Si este script está en un subdirectorio como 'public/', y 'config/' está fuera, sería '../config/database.php'.
require_once 'config/database.php';

// Definir el directorio base y la URL para los archivos subidos.
// Ajusta estas rutas según tu estructura real del servidor.
// __DIR__ proporciona el directorio del script actual.
// Ejemplo: si este script es /var/www/html/index.php y uploads es /var/www/html/uploads/
$upload_base_dir = '';
$upload_base_url = ''; // Ruta relativa para el navegador

// Valores por defecto para la información del departamento, logo e imagen
$logo_path =  'default_logo_unge.png'; // Logo por defecto
$hero_img_path =  'default_hero_image.png'; // Imagen de héroe por defecto
$department_info = [];
$requisitos = [];
$anuncios = [];
$horarios = [];

// --- Cargar Información del Departamento, Requisitos y Anuncios ---
try {
    // Cargar Información del Departamento
    $stmt_info = $pdo->query("SELECT * FROM departamento LIMIT 1");
    $department_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if ($department_info) {
      $logo_path = $department_info['logo_unge'];
        

        $img_full_path =  ($department_info['imagen'] ?? '');
        if (!empty($department_info['imagen']) ) {
            $hero_img_path =   ($department_info['imagen']);
        }
    }

    // Cargar Requisitos de Matrícula (Ajustado para ordenar por tipo y título)
    $stmt_req = $pdo->query("SELECT * FROM requisitos_matricula WHERE visible = 1 ORDER BY tipo ASC, titulo ASC");
    $requisitos = $stmt_req->fetchAll(PDO::FETCH_ASSOC);
    foreach ($requisitos as &$req) { // Asegurar que las rutas de los archivos modelo sean correctas
        if (!empty($req['archivo_modelo'])) {
            $req_file_full_path =  ($req['archivo_modelo']);
            if (file_exists($req_file_full_path)) {
                $req['archivo_modelo'] =   ($req['archivo_modelo']);
            } else {
                $req['archivo_modelo'] = ''; // Limpiar si el archivo no se encuentra
            }
        }
    }
    unset($req); // Eliminar la referencia

    // Cargar Anuncios (Publicaciones) (Ya estaba bien, solo se mantiene)
    $stmt_anuncios = $pdo->query("SELECT * FROM publicaciones WHERE visible = 1 ORDER BY creado_en DESC");
    $anuncios = $stmt_anuncios->fetchAll(PDO::FETCH_ASSOC);
    foreach ($anuncios as &$anuncio) { // Asegurar que las rutas de imagen y adjuntos sean correctas
        if (!empty($anuncio['imagen'])) {
            $anuncio_img_full_path = ($anuncio['imagen']);
            if (file_exists($anuncio_img_full_path)) {
              $anuncio['imagen'] =  ($anuncio['imagen']);
            } else {
                $anuncio['imagen'] = '';
            }
        }
        if (!empty($anuncio['archivo_adjunto'])) {
            $anuncio_file_full_path = ($anuncio['archivo_adjunto']);
            if (file_exists($anuncio_file_full_path)) {
                $anuncio['archivo_adjunto'] =  ($anuncio['archivo_adjunto']);
            } else {
                $anuncio['archivo_adjunto'] = '';
            }
        }
        // Para anuncios de tipo 'evento', podemos formatear la fecha del evento
        if ($anuncio['tipo'] === 'evento' && !empty($anuncio['fecha_evento'])) {
            $anuncio['fecha_evento_formatted'] = date('d/m/Y', strtotime($anuncio['fecha_evento']));
        }
    }
    unset($anuncio);

} catch (PDOException $e) {
    error_log("Error de base de datos en la página de inicio: " . $e->getMessage());
    // Los valores de respaldo ya están configurados o permanecen vacíos
}

// Días de la semana para la visualización del horario (orden consistente)
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

// --- Cargar Horarios ---
try {
    // ACTUALIZADO: Unir con la tabla 'grupos_asignaturas'
    $sql_horarios = "
        SELECT
            h.id AS id_horario,
            h.dia_semana AS dia,
            TIME_FORMAT(h.hora_inicio, '%H:%i') AS hora_inicio,
            TIME_FORMAT(h.hora_fin, '%H:%i') AS hora_fin,
            a.nombre_asignatura AS asignatura,
            u.nombre_completo AS profesor,
            au.nombre_aula AS aula,
            au.capacidad,
            au.ubicacion,
            c.id AS id_curso,
            c.nombre_curso AS curso,
            ga.grupo AS grupo_nombre, -- Obtener el nombre del grupo de grupos_asignaturas
            ga.turno, -- Obtener el turno de grupos_asignaturas
            s.id AS id_semestre,
            CONCAT(s.numero_semestre, ' - ', aa.nombre_anio) AS semestre_info
        FROM horarios h
        JOIN grupos_asignaturas ga ON h.id_grupo_asignatura = ga.id -- Unir con grupos_asignaturas
        JOIN asignaturas a ON ga.id_asignatura = a.id
        JOIN profesores p ON ga.id_profesor = p.id
        JOIN usuarios u ON p.id_usuario = u.id
        JOIN aulas au ON h.id_aula = au.id
        JOIN cursos c ON ga.id_curso = c.id -- Unir con cursos a través de grupos_asignaturas
        JOIN semestres s ON h.id_semestre = s.id
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        ORDER BY c.nombre_curso, s.numero_semestre, ga.grupo, ga.turno, h.hora_inicio, FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo')";

    $stmt_horarios = $pdo->prepare($sql_horarios);
    $stmt_horarios->execute();
    $horarios = $stmt_horarios->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar horarios: " . $e->getMessage());
    $horarios = []; // Asegurar que $horarios esté vacío en caso de error
}

// Organizar horarios por Curso, Semestre y Grupo para la visualización
$grouped_horarios = [];
$unique_time_ranges = [];

foreach ($horarios as $h) {
    // Se incluye el turno en la clave de grupo para diferenciar
    $group_key = $h['curso'] . ' | Semestre: ' . $h['semestre_info'] . ' | Grupo: ' . htmlspecialchars($h['grupo_nombre']) . ' | Turno: ' . ucfirst($h['turno']);
    $time_range = $h['hora_inicio'] . ' - ' . $h['hora_fin'];

    if (!isset($grouped_horarios[$group_key])) {
        $grouped_horarios[$group_key] = [];
    }

    // Recopilar todos los rangos de tiempo únicos en todos los horarios para asegurar la completitud del encabezado de la tabla
    if (!in_array($time_range, $unique_time_ranges)) {
        $unique_time_ranges[] = $time_range;
    }

    // Estructura: grouped_horarios[curso-semestre-grupo][rango_tiempo][dia_semana][] = detalles_horario
    $grouped_horarios[$group_key][$time_range][$h['dia']][] = $h;
}
sort($unique_time_ranges); // Ordenar los rangos de tiempo cronológicamente

// --- Contar Estudiantes y Profesores ---
$student_count = 0;
$professor_count = 0;

try {
    $stmt_students = $pdo->prepare("
        SELECT COUNT(e.id)
        FROM estudiantes e
        JOIN usuarios u ON e.id_usuario = u.id
        JOIN roles r ON u.id_rol = r.id
        WHERE r.nombre_rol = 'Estudiante'
    ");
    $stmt_students->execute();
    $student_count = $stmt_students->fetchColumn();

    $stmt_professors = $pdo->prepare("
        SELECT COUNT(p.id)
        FROM profesores p
        JOIN usuarios u ON p.id_usuario = u.id
        JOIN roles r ON u.id_rol = r.id
        WHERE r.nombre_rol = 'Profesor'
    ");
    $stmt_professors->execute();
    $professor_count = $stmt_professors->fetchColumn();

} catch (PDOException $e) {
    error_log("Error al contar usuarios: " . $e->getMessage());
    // Los conteos permanecen en 0
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departamento de Informática de Gestión - UNGE</title>

    <meta name="description" content="Sitio web oficial del Departamento de Informática de Gestión de la Universidad Nacional de Guinea Ecuatorial (UNGE). Consulta horarios, noticias y requisitos de matrícula.">
    <meta name="keywords" content="UNGE, Informática de Gestión, Universidad, Guinea Ecuatorial, Horarios, Noticias, Matrícula, Académico, Educación">
    <meta name="author" content="Departamento de Informática de Gestión - UNGE">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="http://www.yourdomain.com/"> <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #059669;
            --accent-color: #f59e0b;
            --gradient: linear-gradient(135deg, #1e3a8a 0%, #059669 100%);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa; /* Fondo claro para toda la página */
        }

        /* Sección Hero */
        .hero-section {
            background: linear-gradient(to right, rgba(0, 32, 91, 0.85), rgba(0, 32, 91, 0.85)),
                url("<?= htmlspecialchars($hero_img_path) ?>") no-repeat center center / cover;
            color: #fff;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
            min-height: 550px; /* Asegura una altura mínima */
            display: flex;
            align-items: center;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("<?= htmlspecialchars($hero_img_path) ?>"); /* Usando la misma imagen para un efecto de fondo sutil */
            background-size: cover;
            background-position: center;
            opacity: 0.25;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
        }

        .hero-section h1 {
            font-size: 3rem;
            line-height: 1.2;
            font-weight: 700;
        }

        .hero-section p.lead {
            font-size: 1.2rem;
            color: #e0e0e0;
        }

        /* Botones de Llamada a la Acción */
        .btn-primary-custom {
            background: var(--gradient);
            border: none;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-primary-custom:hover {
            background-color: #003d80;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            filter: brightness(1.1); /* Ligeramente más brillante al pasar el ratón */
        }

        .btn-outline-light:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        /* Elementos Flotantes de Fondo */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        /* Barra de Navegación */
        .navbar {
            background: rgba(30, 58, 138, 0.95) !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-brand img {
            height: 32px;
            width: 32px;
            object-fit: contain;
            border-radius: 4px;
        }

        .nav-link {
            font-weight: 500;
            transition: color 0.3s ease;
            color: rgba(255, 255, 255, 0.85) !important;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--accent-color) !important;
        }

        /* Estilos Generales de Tarjetas */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .card-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
        }

        /* Títulos de Sección */
        .section-title {
            position: relative;
            text-align: center;
            margin-bottom: 50px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--gradient);
            border-radius: 2px;
        }

        /* Tarjetas de Estadísticas */
        .stats-card {
            background: var(--gradient);
            color: white;
            text-align: center;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
        }

        .stats-number {
            font-size: 3rem;
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
        }
        .stats-card span {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Noticias/Anuncios */
        .anuncio-card {
            border: none;
            background-color: #fff;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            opacity: 0; /* Oculto por defecto para animación */
            transform: translateY(15px);
            animation: fadeSlideUp 0.5s forwards;
            will-change: opacity, transform;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }

        .anuncio-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(30, 58, 138, 0.3);
        }

        .anuncio-card img {
            max-height: 220px;
            object-fit: cover;
            border-radius: 0.5rem;
            transition: transform 0.3s ease;
            width: 100%;
        }

        .anuncio-card img:hover {
            transform: scale(1.03);
        }

        .anuncio-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .anuncio-type-badge {
            display: inline-block;
            padding: .25em .6em;
            font-size: .75em;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .375rem;
            color: #fff;
            margin-right: 0.5rem;
        }
        .anuncio-type-evento { background-color: #dc3545; } /* Rojo para eventos */
        .anuncio-type-noticia { background-color: #0d6efd; } /* Azul para noticias */
        .anuncio-type-comunicado { background-color: #198754; } /* Verde para comunicados */


        .no-anuncios, .no-horarios, .no-requisitos {
            min-height: 30vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
            opacity: 0;
            animation: fadeIn 1s forwards;
        }

        .no-anuncios i, .no-horarios i, .no-requisitos i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        /* Sección de Requisitos */
        .matricula-section .card-req {
            border-left: 4px solid var(--secondary-color);
            border-radius: 0.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .matricula-section .card-req:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.2);
        }
        .matricula-section .card-title {
            color: var(--primary-color);
            font-weight: 600;
        }
        .matricula-section .badge-obligatorio {
            background-color: var(--accent-color);
            color: white;
            font-size: 0.75em;
            padding: 0.3em 0.6em;
            border-radius: 0.3rem;
            margin-left: 0.5rem;
            vertical-align: middle;
        }

        /* Sección Sobre Nosotros */
        .about-section {
            padding: 5rem 0;
            background-color: #fff;
        }
        .about-section .img-fluid {
            max-height: 400px;
        }
        .about-section h4 {
            font-weight: 700;
            color: var(--primary-color);
        }
        .about-section p {
            line-height: 1.7;
            color: #555;
        }
        .about-section .bi {
            font-size: 1.1rem;
            color: var(--primary-color);
        }

        /* Pie de Página */
        .footer {
            background: #1f2937;
            color: #e2e8f0;
            padding: 50px 0 20px;
        }

        .footer-links a {
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--accent-color);
        }

        /* Animaciones */
        @keyframes fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(40px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Aplicar retrasos para las tarjetas de anuncios */
        <?php foreach ($anuncios as $index => $a): ?>
            .anuncio-card:nth-child(<?= $index + 1 ?>) {
                animation-delay: <?= $index * 0.15 ?>s;
            }
        <?php endforeach; ?>

        /* Estilos específicos del Modal de Inicio de Sesión */
        .modal-header {
            background-color: var(--primary-color) !important;
        }
        .modal-header img {
            transition: transform 0.3s ease;
        }
        .modal-header img:hover {
            transform: scale(1.05);
        }
        .modal-body .form-control {
            border-radius: 0.5rem;
        }
        .modal-body .btn-primary {
            background: var(--gradient);
            border: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .modal-body .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* Ajustes Responsivos */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2.5rem;
            }
            .hero-section p.lead {
                font-size: 1rem;
            }
            .navbar-brand {
                font-size: 1.2rem;
            }
            .stats-number {
                font-size: 2.5rem;
            }
        }
        @media (max-width: 576px) {
            .hero-section {
                padding: 80px 0 50px;
            }
            .hero-section h1 {
                font-size: 2rem;
            }
            .stats-card {
                padding: 20px;
            }
        }
    </style>

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#inicio">
                <?php if (!empty($logo_path)): ?>
                    <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo UNGE" class="rounded shadow-sm">
                <?php else: ?>
                    <i class="fas fa-university fa-lg"></i>
                <?php endif; ?>
                UNGE - Informática
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#inicio">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#horarios">Horarios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#noticias">Noticias</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#matricula">Matrícula</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contacto">Nosotros</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light rounded-pill ms-lg-3 px-3 py-1 mt-2 mt-lg-0" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="fas fa-sign-in-alt me-1"></i> Acceso
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <section id="inicio" class="hero-section">
            <div class="floating-elements"></div>
            <div class="container hero-content">
                <div class="row align-items-center">
                    <div class="col-lg-6" data-aos="fade-right">
                        <h1 class="display-4 fw-bold mb-4">Departamento de Informática de Gestión</h1>
                        <p class="lead mb-4">
                            Universidad Nacional de Guinea Ecuatorial — Formando profesionales en tecnologías de la
                            información para el desarrollo del país.
                        </p>
                        <a href="#horarios" class="btn btn-primary-custom me-3 animate-fade-up delay-1">Ver Horarios <i class="bi bi-arrow-right"></i></a>
                        <a href="#noticias" class="btn btn-outline-light rounded-pill animate-fade-up delay-2">Últimas Noticias <i class="bi bi-newspaper"></i></a>
                    </div>
                    <div class="col-lg-6 text-center mt-5 mt-lg-0" data-aos="fade-left">
                        <img src="img/eua.jpg" alt="Estudiantes en la UNGE" style="width: 80%; max-width: 450px;" class="img-fluid rounded-4 shadow-lg animate-fade-in delay-3">
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5 bg-light">
            <div class="container">
                <div class="row text-center">
                    <div class="col-md-4 col-sm-6 mb-4" data-aos="zoom-in" data-aos-delay="0">
                        <div class="stats-card">
                            <span class="stats-number"><?= htmlspecialchars($student_count) ?>+</span>
                            <span>Estudiantes</span>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-4" data-aos="zoom-in" data-aos-delay="150">
                        <div class="stats-card">
                            <span class="stats-number"><?= htmlspecialchars($professor_count) ?></span>
                            <span>Profesores</span>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-4" data-aos="zoom-in" data-aos-delay="300">
                        <div class="stats-card">
                            <span class="stats-number">2</span>
                            <span>Laboratorios</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="horarios" class="py-5 px-3 px-md-5">
            <div class="container">
                <h2 class="section-title mb-5" data-aos="fade-up">
                    <i class="bi bi-calendar3-week me-2"></i> Horarios Académicos
                </h2>

                <?php if (!empty($grouped_horarios)): ?>
                    <p class="text-muted text-center mb-4" data-aos="fade-up" data-aos-delay="100">
                        Consulta los horarios de clase organizados por curso, semestre, grupo y turno.
                    </p>

                    <?php foreach ($grouped_horarios as $group_info => $time_data): ?>
                        <div class="card my-5 shadow-sm animate-fade-up" data-aos="fade-up">
                            <div class="card-header bg-primary text-white py-3">
                                <h5 class="mb-0 fw-bold"><?= htmlspecialchars($group_info) ?></h5>
                            </div>
                            <div class="card-body table-responsive p-0">
                                <table class="table table-bordered align-middle mb-0 table-hover">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th style="min-width: 120px;">Hora</th>
                                            <?php foreach ($dias_semana as $dia): ?>
                                                <th><?= htmlspecialchars($dia) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Ordenar los rangos de tiempo para asegurar que aparezcan en orden en la tabla
                                        $sorted_time_ranges = array_keys($time_data);
                                        sort($sorted_time_ranges);
                                        ?>
                                        <?php foreach ($unique_time_ranges as $rango): ?>
                                            <tr>
                                                <td class="text-center fw-bold text-nowrap"><?= htmlspecialchars($rango) ?></td>
                                                <?php foreach ($dias_semana as $dia): ?>
                                                    <td>
                                                        <?php if (!empty($time_data[$rango][$dia])): ?>
                                                            <?php foreach ($time_data[$rango][$dia] as $h): ?>
                                                                <div class="p-2 mb-1 border rounded bg-light shadow-sm small">
                                                                    <strong><?= htmlspecialchars($h['asignatura']) ?></strong><br>
                                                                    Prof.: <?= htmlspecialchars($h['profesor']) ?><br>
                                                                    Aula: <?= htmlspecialchars($h['aula']) ?> (Cap.: <?= htmlspecialchars($h['capacidad']) ?>)<br>
                                                                    <span class="text-muted"><?= htmlspecialchars($h['ubicacion']) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="no-horarios">
                        <i class="bi bi-info-circle"></i>
                        <p class="lead">No hay horarios disponibles en este momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="noticias" class="py-5 bg-light">
            <div class="container">
                <h2 class="section-title mb-5" data-aos="fade-up">
                    <i class="bi bi-megaphone-fill me-2"></i> Tablón de Anuncios
                </h2>

                <?php if (!empty($anuncios)): ?>
                    <div class="row">
                        <?php foreach ($anuncios as $a): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 shadow-sm border-0 rounded-4 anuncio-card">
                                    <?php if (!empty($a['imagen'])): ?>
                                        <a href="<?= htmlspecialchars($a['imagen']) ?>" target="_blank" rel="noopener noreferrer" class="d-block">
                                            <img src="<?= htmlspecialchars($a['imagen']) ?>" alt="Imagen del anuncio: <?= htmlspecialchars($a['titulo']) ?>" class="card-img-top rounded-top-4 img-fluid">
                                        </a>
                                    <?php endif; ?>

                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title fw-bold text-primary"><?= htmlspecialchars($a['titulo']) ?></h5>
                                        <div class="mb-2 small text-muted d-flex justify-content-between anuncio-meta">
                                            <div>
                                                <?php
                                                $tipo_class = '';
                                                switch ($a['tipo']) {
                                                    case 'evento': $tipo_class = 'anuncio-type-evento'; break;
                                                    case 'noticia': $tipo_class = 'anuncio-type-noticia'; break;
                                                    case 'comunicado': $tipo_class = 'anuncio-type-comunicado'; break;
                                                }
                                                ?>
                                                <span class="anuncio-type-badge <?= $tipo_class ?>"><?= ucfirst(htmlspecialchars($a['tipo'])) ?></span>
                                                <span class="small text-muted"><i class="bi bi-calendar-check me-1"></i><?= date('d/m/Y', strtotime($a['creado_en'])) ?></span>
                                            </div>
                                            <?php if ($a['tipo'] === 'evento' && !empty($a['fecha_evento_formatted'])): ?>
                                                <span class="small text-muted">
                                                    <i class="bi bi-calendar-event me-1"></i>Evento: <?= htmlspecialchars($a['fecha_evento_formatted']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="card-text flex-grow-1 text-secondary anuncio-contenido"><?= nl2br(htmlspecialchars($a['contenido'])) ?></p>

                                        <?php if (!empty($a['archivo_adjunto'])): ?>
                                            <div class="mt-3">
                                                <a href="<?= htmlspecialchars($a['archivo_adjunto']) ?>" class="btn btn-sm btn-outline-primary w-100" target="_blank" rel="noopener noreferrer">
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Descargar archivo
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-anuncios">
                        <i class="bi bi-bell-slash"></i>
                        <p class="lead">No hay anuncios disponibles en este momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="matricula" class="py-5">
            <div class="container">
                <h2 class="section-title mb-5" data-aos="fade-up">
                    <i class="bi bi-list-check me-2"></i> Requisitos de Matrícula
                </h2>

                <?php if (!empty($requisitos)): ?>
                    <div class="row justify-content-center">
                        <?php
                        // Agrupar requisitos por tipo para una mejor presentación si la lista es larga
                        $grouped_requisitos = [];
                        foreach ($requisitos as $req) {
                            $grouped_requisitos[$req['tipo']][] = $req;
                        }
                        ?>
                        <?php foreach ($grouped_requisitos as $tipo_req => $reqs_por_tipo): ?>
                            <div class="col-12 mb-4">
                                <h4 class="mb-3 text-secondary" data-aos="fade-right">
                                    <i class="bi bi-folder2-open me-2"></i> Requisitos para Estudiantes:
                                    <span class="text-primary text-capitalize"><?= htmlspecialchars($tipo_req) ?></span>
                                </h4>
                            </div>
                            <?php foreach ($reqs_por_tipo as $req): ?>
                                <div class="col-md-6 col-lg-4 mb-4" data-aos="zoom-in">
                                    <div class="card h-100 shadow-sm card-req">
                                        <div class="card-body">
                                            <h5 class="card-title text-primary mb-3">
                                                <?= htmlspecialchars($req['titulo']) ?>
                                                <?php if ($req['obligatorio']): ?>
                                                    <span class="badge-obligatorio">Obligatorio</span>
                                                <?php endif; ?>
                                            </h5>
                                            <p class="card-text text-secondary"><?= nl2br(htmlspecialchars($req['descripcion'])) ?></p>
                                            <?php if (!empty($req['archivo_modelo'])): ?>
                                                <a href="<?= htmlspecialchars($req['archivo_modelo']) ?>" class="btn btn-outline-primary btn-sm mt-3"
                                                    target="_blank" rel="noopener noreferrer">
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Descargar Modelo
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-requisitos">
                        <i class="bi bi-journal-x"></i>
                        <p class="lead">No hay requisitos de matrícula publicados en este momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($department_info)): ?>
            <section id="contacto" class="about-section py-5 bg-light">
                <div class="container">
                    <h2 class="section-title mb-5" data-aos="fade-up">
                        <i class="bi bi-info-circle-fill me-2"></i> Sobre Nosotros
                    </h2>

                    <div class="row gy-4 align-items-stretch">
                        <div class="col-md-6" data-aos="fade-right">
                            <div class="h-100">
                                <img src="<?= htmlspecialchars($hero_img_path) ?>" alt="Imagen del Departamento: <?= htmlspecialchars($department_info['nombre'] ?? 'Departamento') ?>"
                                    class="img-fluid h-100 w-100 object-fit-cover rounded-4 shadow-lg">
                            </div>
                        </div>

                        <div class="col-md-6 d-flex flex-column justify-content-center p-4" data-aos="fade-left">
                            <p class="fs-6 text-justify text-primary fw-semibold">
                                <?= htmlspecialchars($department_info['nombre'] ?? 'Departamento de Informática de Gestión') ?>
                            </p>
                            <p class="fs-6 text-justify text-secondary">
                                <?= nl2br(htmlspecialchars($department_info['historia'] ?? 'Información sobre la historia del departamento no disponible.')) ?>
                            </p>
                            <div class="mt-4">
                                <p><i class="bi bi-geo-alt-fill text-primary me-2"></i> <strong>Dirección:</strong>
                                    <?= htmlspecialchars($department_info['direccion'] ?? 'N/A') ?></p>
                                <p><i class="bi bi-telephone-fill text-primary me-2"></i> <strong>Teléfono:</strong>
                                    <?= htmlspecialchars($department_info['telefono'] ?? 'N/A') ?></p>
                                <p><i class="bi bi-clock-fill text-primary me-2"></i> <strong>Horario de Atención:</strong>
                                    <?= htmlspecialchars($department_info['horario'] ?? 'N/A') ?></p>
                                <?php if (!empty($department_info['email'])): ?>
                                    <p><i class="bi bi-envelope-fill text-primary me-2"></i> <strong>Email:</strong>
                                        <a href="mailto:<?= htmlspecialchars($department_info['email']) ?>" class="text-decoration-none text-secondary">
                                            <?= htmlspecialchars($department_info['email']) ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <div class="no-requisitos my-5">
                <i class="bi bi-exclamation-circle"></i>
                <p class="lead">Información del departamento no disponible.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4 text-center text-md-start">
                    <h5 class="text-white mb-3">Contacto</h5>
                    <p class="small"><i class="bi bi-geo-alt-fill me-2"></i> <?= htmlspecialchars($department_info['direccion'] ?? 'Dirección no disponible') ?></p>
                    <p class="small"><i class="bi bi-telephone-fill me-2"></i> <?= htmlspecialchars($department_info['telefono'] ?? 'Teléfono no disponible') ?></p>
                    <p class="small"><i class="bi bi-envelope-fill me-2"></i> <?= htmlspecialchars($department_info['email'] ?? 'Email no disponible') ?></p>
                </div>
                <div class="col-md-6 mb-4 text-center text-md-end">
                    <h5 class="text-white mb-3">Enlaces Rápidos</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="#inicio" class="small">Inicio</a></li>
                        <li><a href="#horarios" class="small">Horarios</a></li>
                        <li><a href="#noticias" class="small">Noticias</a></li>
                        <li><a href="#matricula" class="small">Matrícula</a></li>
                        <li><a href="#contacto" class="small">Nosotros</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 border-secondary">
            <div class="text-center small text-secondary">
                <p>&copy; <?= date('Y') ?> Universidad Nacional de Guinea Ecuatorial - Departamento de Informática de Gestión. Todos los
                    derechos reservados.</p>
                <p>Desarrollado con <i class="bi bi-heart-fill text-danger"></i> por el Equipo de TI</p>
            </div>
        </div>
    </footer>


    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4">

                <div class="modal-header bg-primary text-white border-0 rounded-top-4 justify-content-center flex-column text-center p-4">
                    <?php if (!empty($logo_path)): ?>
                        <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo UNGE" style="width: 70px; height: 70px; object-fit: contain;"
                            class="mb-2 rounded shadow-sm">
                    <?php else: ?>
                        <i class="fas fa-university fa-3x mb-2"></i>
                    <?php endif; ?>
                    <h5 class="modal-title fw-semibold mt-2" id="loginModalLabel">Acceso al Sistema</h5>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-3 me-3"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body px-4 pt-4 pb-5">
                    <form action="auth/login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">
                                <i class="bi bi-person-circle me-1"></i> Nombre de Usuario
                            </label>
                            <input type="text" class="form-control rounded-3" name="usuario" id="username" placeholder="Tu nombre de usuario" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">
                                <i class="bi bi-lock-fill me-1"></i> Contraseña / DIP
                            </label>
                            <div class="input-group">
                                <input type="password" name="contrasena" class="form-control rounded-start-3" id="password" placeholder="••••••••" required>
                                <button type="button" class="btn btn-outline-secondary rounded-end-3" id="togglePassword" tabindex="-1" aria-label="Mostrar u ocultar contraseña">
                                    <i class="bi bi-eye-slash" id="iconToggle"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-3 shadow-sm py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar la librería AOS (Animate On Scroll)
            AOS.init({
                once: true, // Las animaciones solo ocurren una vez por elemento
                duration: 800, // Duración de la animación en ms
                easing: 'ease-out', // Easing de la animación
                offset: 150, // Desplazamiento (en px) desde la parte superior de la pantalla para activar la animación
                delay: 50 // Retraso (en ms) para los elementos
            });

            // Desplazamiento suave para los enlaces de navegación
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const target = document.querySelector(targetId);
                    if (target) {
                        // Obtener la altura de la barra de navegación para ajustar la posición de desplazamiento
                        const navbarHeight = document.querySelector('.navbar').offsetHeight;
                        const offsetTop = target.offsetTop - navbarHeight; // Ajustar para la barra de navegación fija

                        window.scrollTo({
                            top: offsetTop,
                            behavior: 'smooth'
                        });

                        // Opcionalmente, cerrar la barra de navegación en móviles después de hacer clic en un enlace
                        const navbarCollapse = document.getElementById('navbarNav');
                        if (navbarCollapse.classList.contains('show')) {
                            const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                                toggle: false
                            });
                            bsCollapse.hide();
                        }
                    }
                });
            });

            // Cambio de fondo de la barra de navegación al desplazarse
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) { // Cambiar el fondo después de desplazarse 50px
                    navbar.style.background = 'rgba(30, 58, 138, 0.98)';
                    navbar.classList.add('shadow-sm'); // Añadir sombra al desplazarse
                } else {
                    navbar.style.background = 'rgba(30, 58, 138, 0.95)';
                    navbar.classList.remove('shadow-sm'); // Quitar sombra
                }
            });

            // Alternar visibilidad de la contraseña en el Modal de Inicio de Sesión
            const toggleBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('iconToggle');

            if (toggleBtn && passwordInput && icon) {
                toggleBtn.addEventListener('click', function() {
                    const isPassword = passwordInput.type === 'password';
                    passwordInput.type = isPassword ? 'text' : 'password';
                    icon.classList.toggle('bi-eye');
                    icon.classList.toggle('bi-eye-slash');
                });
            }

            // Resaltar el enlace de navegación activo al desplazarse (Opcional pero bueno para la UX)
            const sections = document.querySelectorAll('section[id]');
            window.addEventListener('scroll', navHighlighter);

            function navHighlighter() {
                let scrollY = window.scrollY;
                sections.forEach(current => {
                    const sectionHeight = current.offsetHeight;
                    const sectionTop = current.offsetTop - document.querySelector('.navbar').offsetHeight;
                    const sectionId = current.getAttribute('id');

                    if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
                        document.querySelector('.navbar-nav a[href*=' + sectionId + ']').classList.add('active');
                    } else {
                        document.querySelector('.navbar-nav a[href*=' + sectionId + ']').classList.remove('active');
                    }
                });
            }
            navHighlighter(); // Llamar al cargar para establecer el enlace activo inicial
        });
    </script>
</body>

</html>