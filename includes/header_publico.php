<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Departamento IT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <!-- DataTables (opcional si lo usas) -->
  <link rel="stylesheet" href="https://cdn.datatables.net/2.0.6/css/dataTables.dataTables.min.css">

  <style>
    .navbar-brand {
      font-weight: bold;
      letter-spacing: 0.5px;
    }

    .nav-link {
      font-weight: 500;
      transition: all 0.2s ease-in-out;
    }

    .nav-link:hover {
      color: #ffc107 !important;
    }

    .btn-login {
      font-weight: 500;
      padding: 0.4rem 0.9rem;
    }
  </style>
</head>

<body>
  <!-- Navbar mejorada -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="index.php"><i class="bi bi-cpu me-2"></i>Departamento I.G.</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPublic"
        aria-controls="navbarPublic" aria-expanded="false" aria-label="Menú">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarPublic">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
          <li class="nav-item"><a class="nav-link" href="acerca.php">Nosotros</a></li>
          <li class="nav-item"><a class="nav-link" href="matricula.php">Matrícula</a></li>
          <li class="nav-item"><a class="nav-link" href="horarios.php">Horarios</a></li>
          <li class="nav-item"><a class="nav-link" href="anuncios.php">Anuncios</a></li>
        </ul>
        <a href="login.php" class="btn btn-light btn-sm ms-lg-3 mt-2 mt-lg-0 btn-login">
          <i class="bi bi-box-arrow-in-right me-1"></i>Acceder
        </a>
      </div>
    </div>
  </nav>
