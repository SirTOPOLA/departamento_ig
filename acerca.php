<?php 
include 'includes/header_publico.php';
require 'includes/conexion.php';

$stmt = $pdo->query("SELECT * FROM departamento LIMIT 1");
$info = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<style>
  .about-section {
    min-height: 100vh;
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
</style>

<main class="about-section container">
  <?php if (!$info): ?>
    <div class="not-found w-100">
      <div>
        <i class="bi bi-info-circle-fill mb-3"></i>
        <h4 class="fw-bold">Información no disponible</h4>
        <p class="mb-0">Actualmente no hay datos cargados sobre el departamento. Por favor, vuelva más tarde.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="row align-items-center gy-4">
      <div class="col-md-6">
        <img src="api/<?= htmlspecialchars($info['imagen']) ?>" alt="Imagen del Departamento" class="img-fluid about-img">
      </div>
      <div class="col-md-6 about-content">
        <h4><?= htmlspecialchars($info['nombre']) ?></h4>
        <p><?= nl2br(htmlspecialchars($info['historia'])) ?></p>
        <div class="about-meta mt-4">
          <p><strong>📍 Dirección:</strong> <?= htmlspecialchars($info['direccion']) ?></p>
          <p><strong>📞 Teléfono:</strong> <?= htmlspecialchars($info['telefono']) ?></p>
          <p><strong>⏰ Horario:</strong> <?= htmlspecialchars($info['horario']) ?></p>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php include 'includes/footer_publico.php'; ?>
