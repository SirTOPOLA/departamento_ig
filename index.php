<?php

require 'includes/conexion.php';
$logo = '';
$img = '';
$stmt = $pdo->query("SELECT * FROM departamento LIMIT 1");
$info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!empty($info['logo_unge']) && file_exists(__DIR__ . '/api/' . $info['logo_unge'])) {
  $logo = 'api/' . $info['logo_unge'];
}
if (!empty($info['imagen']) && file_exists(__DIR__ . '/api/' . $info['imagen'])) {
  $img = 'api/' . $info['imagen'];
}


$anuncios = $pdo->query("SELECT * FROM publicaciones WHERE visible = 1 ORDER BY creado_en DESC")->fetchAll();


// Días de clase
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Consulta general de horarios con información completa
$sql = "
SELECT 
    h.id_horario,
    h.dia,
    TIME_FORMAT(h.hora_inicio, '%H:%i') AS hora_inicio,
    TIME_FORMAT(h.hora_fin, '%H:%i') AS hora_fin,
    a.nombre AS asignatura,
    CONCAT(u.nombre, ' ', u.apellido) AS profesor,
    au.nombre AS aula,
    au.capacidad,
    au.ubicacion,
    c.id_curso,
    c.nombre AS curso,
    c.turno,
    c.grupo,
    s.id_semestre,
    s.nombre AS semestre
FROM horarios h
JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
JOIN profesores p ON h.id_profesor = p.id_profesor
JOIN usuarios u ON p.id_profesor = u.id_usuario
JOIN aulas au ON h.aula_id = au.id_aula
JOIN cursos c ON a.curso_id = c.id_curso
JOIN semestres s ON a.semestre_id = s.id_semestre
ORDER BY c.nombre, s.nombre, h.hora_inicio, FIELD(h.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado')";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar por curso-semestre
$datos = [];
$rangosUnicos = [];

foreach ($horarios as $h) {
  $clave = $h['curso'] . ' - Turno: ' . ucfirst($h['turno']) . ' - Grupo: ' . $h['grupo'] . ' | ' . $h['semestre'];
  $rango = $h['hora_inicio'] . ' - ' . $h['hora_fin'];

  if (!isset($datos[$clave])) {
    $datos[$clave] = [];
  }

  if (!in_array($rango, $rangosUnicos)) {
    $rangosUnicos[] = $rango;
  }

  $datos[$clave][$rango][$h['dia']][] = $h;
}


/* profesores */
$estudiantes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'estudiante'")->fetchColumn();

/* profesores */
$profesores = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'profesor'")->fetchColumn();

?>


<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Departamento de Informática de Gestión - UNGE</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

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
    }

    .hero-section {
      background: linear-gradient(to right, rgba(0, 32, 91, 0.85), rgba(0, 32, 91, 0.85)),
        url("img/eua.jpg") no-repeat center center / cover;
      color: #fff;
      padding: 100px 0;
      position: relative;
      overflow: hidden;
    }

    .hero-section::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url("<?= $img ?>");
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
    }

    .hero-section p.lead {
      font-size: 1.2rem;
      color: #e0e0e0;
    }

    .btn-primary-custom {
      background-color: #0056b3;
      border: none;
      color: #fff;
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      transition: all 0.3s ease;
    }

    .btn-primary-custom:hover {
      background-color: #003d80;
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .btn-outline-light:hover {
      background-color: rgba(255, 255, 255, 0.1);
      color: #fff;
    }

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

    .navbar {
      background: rgba(30, 58, 138, 0.95) !important;
      backdrop-filter: blur(10px);
      transition: all 0.3s ease;
    }

    .navbar-brand {
      font-weight: 700;
      font-size: 1.5rem;
    }

    .nav-link {
      font-weight: 500;
      transition: color 0.3s ease;
    }

    .nav-link:hover {
      color: var(--accent-color) !important;
    }

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

    .section-title {
      position: relative;
      text-align: center;
      margin-bottom: 50px;
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

    .stats-card {
      background: var(--gradient);
      color: white;
      text-align: center;
      padding: 30px;
      border-radius: 15px;
      margin-bottom: 20px;
    }

    .stats-number {
      font-size: 3rem;
      font-weight: 700;
      display: block;
    }

    .news-card {
      border-left: 4px solid var(--secondary-color);
      padding: 20px;
      margin-bottom: 20px;
      background: #f8f9fa;
      border-radius: 0 10px 10px 0;
    }

    .btn-primary-custom {
      background: var(--gradient);
      border: none;
      padding: 12px 30px;
      border-radius: 25px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-primary-custom:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(30, 58, 138, 0.3);
    }

    .footer {
      background: #1f2937;
      color: white;
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

    .animate-fade-in {
      animation: fadeIn 1s ease-in;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .floating-elements {
      position: absolute;
      width: 100%;
      height: 100%;
      overflow: hidden;
      pointer-events: none;
    }

    .floating-elements::before,
    .floating-elements::after {
      content: '';
      position: absolute;
      background: rgba(38, 122, 201, 0.75);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite;
    }

    .floating-elements::before {
      width: 100px;
      height: 100px;
      top: 20%;
      left: 10%;
      animation-delay: 0s;
    }

    .floating-elements::after {
      width: 60px;
      height: 60px;
      top: 60%;
      right: 15%;
      animation-delay: 3s;
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0px);
      }

      50% {
        transform: translateY(-20px);
      }
    }



    .about-section {
      min-height: auto;
      display: flex;
      align-items: center;
      padding: 3rem 1rem;
    }

    .about-img {
      max-height: 350px;
      object-fit: cover;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
      border-radius: 1rem;
    }

    .about-content h4 {
      font-weight: 700;
      color: #0d6efd;
    }

    .about-meta p {
      margin-bottom: 0.5rem;
      font-size: 1rem;
    }

    .not-found {
      min-height: 70vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #6c757d;
    }

    .not-found i {
      font-size: 3rem;
      color: #0d6efd;
    }

    .anuncio-card {
      border: none;
      background-color: #fff;
      border-left: 4px solid #0d6efd;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      border-radius: 0.5rem;
      padding: 1rem;
      margin-bottom: 1.5rem;

      /* Animación inicial oculta */
      opacity: 0;
      transform: translateY(15px);
      animation: fadeSlideUp 0.5s forwards;
      will-change: opacity, transform;
    }

    /* Animación con delay para cada card */
    <?php foreach ($anuncios as $index => $a): ?>
      .anuncio-card:nth-child(<?= $index + 1 ?>) {
        animation-delay:
          <?= $index * 0.15 ?>
          s;
      }

    <?php endforeach; ?>

    .anuncio-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(13, 110, 253, 0.3);
      transition: box-shadow 0.3s ease, transform 0.3s ease;
    }

    .anuncio-img {
      max-height: 250px;
      object-fit: cover;
      border-radius: 0.5rem;
      transition: transform 0.3s ease;
      will-change: transform;
    }

    .anuncio-img:hover {
      transform: scale(1.03);
    }

    .anuncio-meta {
      font-size: 0.9rem;
      color: #6c757d;
    }

    .no-anuncios {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 4rem 2rem;
      color: #6c757d;

      opacity: 0;
      animation: fadeIn 1s forwards;
    }

    .no-anuncios i {
      font-size: 3rem;
      color: #0d6efd;
      margin-bottom: 1rem;
    }

    .anuncio-contenido {
      white-space: pre-line;
    }

    a.btn-outline-primary {
      transition: background-color 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
      will-change: background-color, color, box-shadow;
    }

    a.btn-outline-primary:hover {
      background-color: #0d6efd;
      color: #fff;
      box-shadow: 0 0 12px rgba(13, 110, 253, 0.6);
    }

    @keyframes fadeSlideUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeIn {
      to {
        opacity: 1;
      }
    }

    @media (max-width: 768px) {
      .anuncio-img {
        max-height: 200px;
      }
    }

    .modal-header img {
      transition: transform 0.3s ease;
    }

    .modal-header img:hover {
      transform: scale(1.05);
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

    .animate-fade-in {
      animation: fadeInUp 1s ease-out both;
    }

    .about-section p {
      line-height: 1.6;
      color: #333;
    }

    .about-section .bi {
      font-size: 1.1rem;
    }

    .object-fit-cover {
      object-fit: cover;
    }

    /* ANIMACIONES SUAVES */
    @keyframes fadeUp {
      0% {
        opacity: 0;
        transform: translateY(40px);
      }

      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeIn {
      0% {
        opacity: 0;
      }

      100% {
        opacity: 1;
      }
    }

    /* CLASES REUTILIZABLES */
    .animate-fade-up {
      opacity: 0;
      animation: fadeUp 0.8s ease forwards;
    }

    .animate-fade-in {
      opacity: 0;
      animation: fadeIn 1s ease forwards;
    }

    /* DELAY OPCIONAL */
    .delay-1 {
      animation-delay: 0.2s;
    }

    .delay-2 {
      animation-delay: 0.4s;
    }

    .delay-3 {
      animation-delay: 0.6s;
    }

    .delay-4 {
      animation-delay: 0.8s;
    }
  </style>

</head>

<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <?php if (!empty($logo)): ?>
          <img src="<?= $logo ?>" alt="Logo UNGE" style="height: 32px; width: 32px; object-fit: contain;"
            class="rounded shadow-sm">
        <?php else: ?>
          <i class="fas fa-university fa-lg"></i>
        <?php endif; ?>
        UNGE - Informática
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link active" href="#inicio">Inicio</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#programas">Horarios</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#noticias">Noticias</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#matricula">Matricula</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#contacto">Nosotros</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">
              <i class="fas fa-sign-in-alt me-1"></i>Acceso
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero inicio -->
  <section id="inicio" class="hero-section">
    <div class="floating-elements"></div>
    <div class="container hero-content">
      <div class="row align-items-center">
        <div class="col-lg-6" data-aos="fade-right">
          <h1 class="display-4 fw-bold mb-4">Departamento de Informática de Gestión</h1>
          <p class="lead mb-4">Universidad Nacional de Guinea Ecuatorial — Formando profesionales en tecnologías de la
            información para el desarrollo del país.</p>
        </div>
        <div class="col-lg-6 text-center" data-aos="fade-left">
          <img src="img/code.png" alt="Desarrollo" style="width: 80%; max-width: 450px;" class="img-fluid">
        </div>
      </div>
    </div>
  </section>


  <!-- estadisticas -->
  <section class="py-5 bg-light">
    <div class="container">
      <div class="row">
        <div class="col-md-4 col-sm-6" data-aos="zoom-in">
          <div class="stats-card">
            <span class="stats-number"><?= $estudiantes ?>+</span>
            <span>Estudiantes</span>
          </div>
        </div>
        <div class="col-md-4 col-sm-6" data-aos="zoom-in" data-aos-delay="150">
          <div class="stats-card">
            <span class="stats-number"><?= $profesores ?></span>
            <span>Profesores</span>
          </div>
        </div>
        <div class="col-md-4 col-sm-6" data-aos="zoom-in" data-aos-delay="300">
          <div class="stats-card">
            <span class="stats-number">2</span>
            <span>Laboratorios</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!--Horarios de clase -->
  <?php if (!empty($horarios)): ?>
    <h3 class="mb-3" data-aos="fade-up"><i class="bi bi-calendar3-week"></i> Horarios Académicos por Semestre</h3>
    <p class="text-muted" data-aos="fade-up" data-aos-delay="100">Se muestran los horarios organizados por semestre y
      curso.</p>

    <?php foreach ($datos as $semestreNombre => $tabla): ?>
      <div class="card my-5 shadow-sm" data-aos="fade-up" data-aos-delay="200">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><?= htmlspecialchars($semestreNombre) ?></h5>
        </div>
        <div class="card-body table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light text-center">
              <tr>
                <th>Hora</th>
                <?php foreach ($dias as $dia): ?>
                  <th><?= $dia ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rangosUnicos as $rango): ?>
                <tr>
                  <td class="text-center fw-bold"><?= $rango ?></td>
                  <?php foreach ($dias as $dia): ?>
                    <td>
                      <?php if (!empty($tabla[$rango][$dia])): ?>
                        <?php foreach ($tabla[$rango][$dia] as $h): ?>
                          <div class="p-2 mb-2 border rounded bg-light shadow-sm small">
                            <strong><?= $h['asignatura'] ?></strong><br>
                            Prof.: <?= $h['profesor'] ?><br>
                            Aula: <?= $h['aula'] ?> (<?= $h['capacidad'] ?>)<br>
                            <span class="text-muted"><?= $h['ubicacion'] ?></span>
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
    </section>
  <?php endif; ?>

  <!-- Seccion anuncios -->
  <?php if (!empty($anuncios)): ?>
    <section id="noticias" class="py-5 bg-light">

      <h2 class="text-center mb-5 text-primary-emphasis" data-aos="fade-up">
        <i class="bi bi-megaphone-fill me-2"></i> Tablón de Anuncios
      </h2>
      <?php foreach ($anuncios as $a): ?>
        <div class="card anuncio-card mb-4" data-aos="fade-up" data-aos-delay="100">

          <div class="card-body">
            <h4 class="card-title text-primary"><?= htmlspecialchars($a['titulo']) ?></h4>

            <div class="d-flex justify-content-between align-items-center anuncio-meta mb-2">
              <span><i class="bi bi-tag-fill me-1"></i><?= ucfirst(htmlspecialchars($a['tipo'])) ?></span>
              <span><i class="bi bi-calendar-check me-1"></i><?= date('d/m/Y', strtotime($a['creado_en'])) ?></span>
            </div>

            <?php if ($a['imagen']): ?>
              <img src="api/<?= htmlspecialchars($a['imagen']) ?>" alt="Imagen del anuncio"
                class="img-fluid anuncio-img mb-3 w-100">
            <?php endif; ?>

            <p class="anuncio-contenido"><?= nl2br(htmlspecialchars($a['contenido'])) ?></p>

            <?php if ($a['archivo_adjunto']): ?>
              <a href="<?= htmlspecialchars($a['archivo_adjunto']) ?>" class="btn btn-outline-primary btn-sm mt-3"
                target="_blank" rel="noopener">
                <i class="bi bi-download me-1"></i> Descargar documento
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <!-- requisitos matricula -->

  <?php if (!empty($requisitos)): ?>
    <section id="matricula" class="container matricula-section">
    <h2 class="fw-bold text-primary mb-4" data-aos="fade-up">Requisitos de Matrícula</h2>
<div class="row">
  <?php foreach ($requisitos as $req): ?>
    <div class="col-md-6 mb-4" data-aos="zoom-in" data-aos-delay="100">       
            <div class="card h-100 border-0 shadow-sm card-req">
              <div class="card-body">
                <h5 class="card-title text-primary"><?= htmlspecialchars($req['titulo']) ?></h5>
                <p><?= nl2br(htmlspecialchars($req['descripcion'])) ?></p>
                <?php if ($req['archivo_modelo']): ?>
                  <a href="<?= htmlspecialchars($req['archivo_modelo']) ?>" class="btn btn-outline-primary btn-sm mt-2"
                    target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Descargar Modelo
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- Informacion de contacto -->
  <?php if ($info): ?>
    <section id="contacto" class="about-section py-5 bg-light">
      <div class="container">
        <!-- Título centrado -->
        <div class="text-center mb-5">
          <h4 class="text-primary fw-bold border-bottom pb-2 d-inline-block">
          <i class="bi bi-person-badge-fill"></i>  NOSOTROS
          </h4>
        </div>

        <div class="row gy-4 align-items-stretch">
          <!-- Imagen: ocupa todo el alto del contenedor -->
          <div class="col-md-6 p4">
            <div class="h-100">
              <img src="api/<?= htmlspecialchars($info['imagen']) ?>" alt="Imagen del Departamento"
                class="img-fluid h-100 w-100 object-fit-cover rounded-4 shadow">
            </div>
          </div>

          <!-- Contenido de texto -->
          <div class="col-md-6 d-flex flex-column justify-content-center p-4">
            <p class="fs-6 text-justify text-primary">
            <?= htmlspecialchars($info['nombre']) ?>
            </p>
            <p class="fs-6 text-justify ">
              <?= nl2br(htmlspecialchars($info['historia'])) ?>
            </p>
            <div class="mt-4">
              <p><i class="bi bi-geo-alt-fill text-primary me-2"></i> <strong>Dirección:</strong>
                <?= htmlspecialchars($info['direccion']) ?></p>
              <p><i class="bi bi-telephone-fill text-primary me-2"></i> <strong>Teléfono:</strong>
                <?= htmlspecialchars($info['telefono']) ?></p>
              <p><i class="bi bi-clock-fill text-primary me-2"></i> <strong>Horario:</strong>
                <?= htmlspecialchars($info['horario']) ?></p>
            </div>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>



  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <hr class="my-4">
      <div class="text-center">
        <p>&copy; 2025 Universidad Nacional de Guinea Ecuatorial - Departamento de Informática de Gestión. Todos los
          derechos reservados.</p>
      </div>
    </div>
  </footer>


  <!-- Login Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content border-0 shadow-lg rounded-4">

        <!-- Header -->
        <div
          class="modal-header bg-primary text-white border-0 rounded-top-4 justify-content-center flex-column text-center">

          <?php if (!empty($logo)): ?>
            <img src="<?= $logo ?>" alt="Logo UNGE" style="width: 60px; height: 60px; object-fit: contain;"
              class="mb-2 rounded shadow-sm">
          <?php else: ?>
            <i class="fas fa-university fa-lg"></i>
          <?php endif; ?>

          <h5 class="modal-title fw-semibold mt-2" id="loginModalLabel">Acceso al Sistema</h5>
          <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-3 me-3"
            data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <!-- Cuerpo -->
        <div class="modal-body px-4 pt-4">
          <form action="api/validar_login.php" method="POST" >
            <div class="mb-3">
              <label for="email" class="form-label fw-semibold">
                <i class="bi bi-person-circle me-1"></i> Nombre
              </label>
              <input type="text" class="form-control rounded-3" name="usuario" id="email" placeholder="Jenny" required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label fw-semibold">
                <i class="bi bi-lock-fill me-1"></i> DIP
              </label>
              <div class="input-group">
                <input type="password" name="contrasena" class="form-control rounded-start-3" id="password" placeholder="••••••••"
                  required>
                <button type="button" class="btn btn-outline-secondary rounded-end-3" id="togglePassword" tabindex="-1"
                  aria-label="Mostrar u ocultar contraseña">
                  <i class="bi bi-eye-slash" id="iconToggle"></i>
                </button>
              </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-3 shadow-sm">
              <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>



  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

  <!-- AOS JS -->
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({
      once: true,         // solo una vez por elemento
      duration: 800,      // duración por defecto
      easing: 'ease-out'  // animación suave
    });


    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Navbar background on scroll
    window.addEventListener('scroll', function () {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 100) {
        navbar.style.background = 'rgba(30, 58, 138, 0.98)';
      } else {
        navbar.style.background = 'rgba(30, 58, 138, 0.95)';
      }
    });

    // Add animation classes on scroll
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-fade-in');
        }
      });
    }, observerOptions);

    document.querySelectorAll('.card, .stats-card, .news-card').forEach(el => {
      observer.observe(el);
    });

    document.addEventListener('DOMContentLoaded', function () {
      const toggleBtn = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      const icon = document.getElementById('iconToggle');

      toggleBtn.addEventListener('click', function () {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
      });
    });
  </script>
</body>

</html>