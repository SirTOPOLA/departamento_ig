<?php
/* session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
  header("Location: ../login.php");
  exit;
} */
require '../includes/conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Dashboard Administrador | Departamento IT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap 5.3 CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      overflow-x: hidden;
      color: #495057;
    }

    /* Sidebar */
    .sidebar {
      position: fixed;
      top: 0; bottom: 0; left: 0;
      width: 260px;
      background-color: #212529; /* fondo oscuro elegante */
      color: #adb5bd; /* texto gris claro */
      display: flex;
      flex-direction: column;
      padding: 1.75rem 1rem;
      box-shadow: 3px 0 12px rgba(0, 0, 0, 0.2);
      z-index: 100;
      transition: transform 0.3s ease-in-out;
      overflow-y: auto;
    }

    .sidebar::-webkit-scrollbar {
      width: 6px;
    }
    .sidebar::-webkit-scrollbar-thumb {
      background-color: rgba(173, 181, 189, 0.3);
      border-radius: 3px;
    }

    /* Cabecera sidebar */
    .sidebar-header {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin-bottom: 2rem;
      color: #f8f9fa;
      user-select: none;
    }
    .sidebar-header i {
      font-size: 1.9rem;
      color: #0d6efd;
    }
    .sidebar-header h5 {
      font-weight: 700;
      font-size: 1.3rem;
      letter-spacing: 1.2px;
      margin: 0;
    }

    /* Enlaces sidebar */
    .sidebar nav a {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      color: #adb5bd;
      padding: 0.7rem 1.1rem;
      border-radius: 0.45rem;
      font-weight: 500;
      font-size: 1rem;
      transition: background-color 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
      text-decoration: none;
      user-select: none;
      white-space: nowrap;
    }
    .sidebar nav a i {
      font-size: 1.25rem;
      min-width: 24px;
      text-align: center;
      color: #adb5bd;
      transition: color 0.3s ease;
    }
    .sidebar nav a:hover {
      background-color: #0d6efd;
      color: #f8f9fa;
      box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
    }
    .sidebar nav a:hover i {
      color: #f8f9fa;
    }
    .sidebar nav a.active {
      background-color: #0d6efd;
      color: #f8f9fa;
      font-weight: 700;
      box-shadow: 0 0 15px 2px rgba(13, 110, 253, 0.6);
    }
    .sidebar nav a.active i {
      color: #f8f9fa;
    }

    /* Logout */
    .sidebar .logout-link {
      margin-top: auto;
      padding: 0.8rem 1.1rem;
      font-weight: 600;
      font-size: 1rem;
      color: #dc3545;
      display: flex;
      align-items: center;
      gap: 0.8rem;
      border-radius: 0.45rem;
      cursor: pointer;
      transition: background-color 0.3s ease, color 0.3s ease;
      user-select: none;
      text-decoration: none;
    }
    .sidebar .logout-link:hover {
      background-color: #dc3545;
      color: #f8f9fa;
      box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
    }
    .sidebar .logout-link i {
      font-size: 1.3rem;
      color: #dc3545;
      transition: color 0.3s ease;
    }
    .sidebar .logout-link:hover i {
      color: #f8f9fa;
    }

    /* Push content */
    .content {
      margin-left: 260px;
      padding: 2rem;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
      background-color: #fff;
    }

    /* Responsive: ocultar sidebar en móviles */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }
      .sidebar.show {
        transform: translateX(0);
      }
      .content {
        margin-left: 0 !important;
        padding: 1.5rem 1rem 2rem;
      }

      /* Overlay */
      .overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100vw; height: 100vh;
        background-color: rgba(0,0,0,0.4);
        z-index: 1050;
        transition: opacity 0.3s ease;
        opacity: 0;
      }
      .overlay.show {
        display: block;
        opacity: 1;
      }

      /* Botón hamburguesa */
      nav.navbar {
        position: fixed;
        top: 0; left: 0; right: 0;
        z-index: 1050;
        background-color: #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      }
      nav.navbar .btn {
        border-radius: 0.4rem;
      }
    }

    /* Botón cerrar sidebar móvil */
    .sidebar .close-btn {
      display: none;
      background: none;
      border: none;
      color: #adb5bd;
      font-size: 1.8rem;
      margin-left: auto;
      cursor: pointer;
      padding: 0;
      transition: color 0.3s ease;
    }
    .sidebar .close-btn:hover {
      color: #0d6efd;
    }
    @media (max-width: 768px) {
      .sidebar .close-btn {
        display: block;
      }
    }
  </style>
</head>
<body> 
<!-- Sidebar -->
<div class="sidebar d-flex flex-column" id="sidebar" aria-label="Menú principal">
  <div class="sidebar-header">
    <i class="bi bi-laptop-fill"></i>
    <h5>Admin IT</h5>
    <button class="close-btn" aria-label="Cerrar menú" onclick="toggleSidebar()">&times;</button>
  </div>
  <nav role="navigation" class="flex-grow-1 d-flex flex-column gap-1">
    <a href="index.php" aria-current="page"><i class="bi bi-house-door"></i>Inicio</a>
    <a href="usuarios.php"><i class="bi bi-people-fill"></i>Usuarios</a>
    <a href="profesores.php"><i class="bi bi-person-badge"></i>Profesores</a>
    <a href="estudiantes.php"><i class="bi bi-mortarboard"></i>Estudiantes</a>
    <a href="cursos.php"><i class="bi bi-journals"></i>Cursos</a>
    <a href="semestres.php"><i class="bi bi-calendar3"></i>Semestres</a>
    <a href="aulas.php"><i class="bi bi-door-open"></i>Aulas</a>
    <a class="nav-link" href="../admin/anios_academicos.php">
    <i class="bi bi-calendar-range-fill me-2"></i> Años Académicos
  </a>
    <a href="asignaturas.php"><i class="bi bi-book"></i>Asignaturas</a>
    <a href="asignatura_requisitos.php" class="list-group-item list-group-item-action d-flex align-items-center">
  <i class="bi bi-diagram-3 me-2"></i> R. Asignaturas
</a>

    <a href="horarios.php"><i class="bi bi-clock-history"></i>Horarios</a>
    <a href="notas.php"><i class="bi bi-clipboard-data"></i>Notas</a>
    <a href="publicaciones.php"><i class="bi bi-megaphone"></i>Publicaciones</a>
    <a href="requisitos.php"><i class="bi bi-card-checklist"></i>Requisitos</a>
    <a href="departamento.php"><i class="bi bi-building"></i>Departamento</a>
  </nav>
  <a href="#" class="logout-link" onclick="confirmarLogout(event)">
    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
  </a>
</div>

<!-- Overlay para cerrar menú en móvil -->
<div class="overlay" id="overlay" onclick="toggleSidebar()" aria-hidden="true"></div>

<!-- Navbar para móviles -->
<nav class="navbar navbar-light d-md-none shadow-sm">
  <div class="container-fluid">
    <button class="btn btn-outline-secondary" aria-label="Abrir menú" onclick="toggleSidebar()">
      <i class="bi bi-list fs-3"></i>
    </button>
    <span class="navbar-brand ms-2 fw-semibold">Admin IT</span>
  </div>
</nav>

<!-- Contenido -->

  <!-- Aquí va el contenido dinámico -->

 
