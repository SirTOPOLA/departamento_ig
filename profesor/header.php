<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
  header("Location: ../login.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard Estudiante</title>

  <!-- Bootstrap 5 CSS CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Bootstrap Icons CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Tu CSS personalizado -->
  <link href="/css/estilos.css" rel="stylesheet" />

  <!-- Opcional: favicon -->
  <link rel="icon" href="/img/favicon.ico" />

  <style>

  </style>
</head>

<body>

  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
      <a class="navbar-brand" href="/estudiante/dashboard.php">
        <i class="bi bi-mortarboard-fill"></i> Mi Universidad
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarEstudiante"
        aria-controls="navbarEstudiante" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarEstudiante">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="index.php"><i class="bi bi-house-door-fill"></i> Inicio</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="asignaturas.php"><i class="bi bi-journal-text"></i> Mis Asignaturas</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="estudiantes.php"><i class="bi bi-journal-text"></i> Mis estudiantes</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="horarios.php"><i class="bi bi-clock-history"></i>Horarios</a>
          </li> 
          <li class="nav-item">
            <a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i>
              Salir</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Contenedor principal -->
  <main class="py-4">