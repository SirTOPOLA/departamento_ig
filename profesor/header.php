<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
  header("Location: ../login.php");
  exit;
}
?>
<!-- Sidebar Profesor -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

<style>
  .sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: 260px;
    background-color: #1a1f36;
    color: #cfd8dc;
    display: flex;
    flex-direction: column;
    padding: 1.75rem 1rem;
    box-shadow: 3px 0 12px rgba(0, 0, 0, 0.25);
    z-index: 1100;
    transition: transform 0.3s ease-in-out;
    overflow-y: auto;
  }

  .sidebar-header {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 2rem;
    color: #e1e5ea;
  }

  .sidebar-header i {
    font-size: 1.9rem;
    color: #5a80ff;
  }

  .sidebar-header h5 {
    font-weight: 700;
    font-size: 1.3rem;
    margin: 0;
  }

  .sidebar nav a {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    color: #cfd8dc;
    padding: 0.75rem 1.1rem;
    border-radius: 0.45rem;
    font-size: 1rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
  }

  .sidebar nav a i {
    font-size: 1.3rem;
  }

  .sidebar nav a:hover,
  .sidebar nav a.active {
    background-color: #5a80ff;
    color: #fff;
    box-shadow: 0 0 12px rgba(90, 128, 255, 0.5);
  }

  .sidebar .logout-link {
    margin-top: auto;
    color: #ff6b6b;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.1rem;
    border-radius: 0.45rem;
    text-decoration: none;
  }

  .sidebar .logout-link:hover {
    background-color: #ff6b6b;
    color: white;
  }

  .sidebar .close-btn {
    display: none;
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #adb5bd;
  }

  @media (max-width: 768px) {
    .sidebar {
      transform: translateX(-100%);
    }

    .sidebar.show {
      transform: translateX(0);
    }

    .sidebar .close-btn {
      display: block;
    }

    #main-content {
      margin-left: 0 !important;
    }

    .overlay {
      display: block;
      position: fixed;
      top: 0; left: 0;
      width: 100vw;
      height: 100vh;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      display: none;
    }

    .overlay.show {
      display: block;
    }
  }

  #main-content {
    margin-left: 260px;
    transition: margin-left 0.3s ease;
  }

</style>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <i class="bi bi-person-badge-fill"></i>
    <h5>Profesor IT</h5>
    <button class="close-btn d-md-none" onclick="toggleSidebar()">&times;</button>
  </div>
  <nav>
  <a href="index.php"><i class="bi bi-house"></i>Inicio</a>
    <a href="asignaturas.php"><i class="bi bi-journal-text"></i>Mis Asignaturas</a>
    <a href="estudiantes.php"><i class="bi bi-people"></i>Estudiantes</a>
    <a href="notas.php"><i class="bi bi-pencil-square"></i>Notas</a>
    <a href="horarios.php"><i class="bi bi-calendar-week"></i>Horario</a>
       </nav>
  <a href="#" class="logout-link" onclick="confirmarLogout(event)">
    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
  </a>
</div>

<!-- Overlay móvil -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Navbar móvil -->
<nav class="navbar navbar-light bg-white shadow-sm d-md-none fixed-top">
  <div class="container-fluid">
    <button class="btn btn-outline-secondary" onclick="toggleSidebar()">
      <i class="bi bi-list fs-4"></i>
    </button>
    <span class="navbar-brand ms-2">Profesor IT</span>
  </div>
</nav>

