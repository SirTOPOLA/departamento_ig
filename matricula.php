<?php 
include 'includes/header_publico.php';
require 'includes/conexion.php';

/* $info = $pdo->query("SELECT info_matricula FROM departamento LIMIT 1")->fetchColumn(); */
$requisitos = $pdo->query("SELECT * FROM requisitos_matricula WHERE visible = 1")->fetchAll();
?>

<style>
  .matricula-section {
    min-height: 100vh;
    padding: 4rem 1rem;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    background-color: #f9f9fb;
  }

  h2 {
    animation: fadeSlideUp 0.6s ease forwards;
    opacity: 0;
    transform: translateY(20px);
    text-align: center;
  }

  .matricula-info {
    font-size: 1.1rem;
    line-height: 1.6;
    animation: fadeSlideUp 0.6s ease forwards;
    animation-delay: 0.3s;
    opacity: 0;
    transform: translateY(20px);
    margin-bottom: 2rem;
  }

  .card-req {
    border-radius: 0.75rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeSlideUp 0.5s ease forwards;
    will-change: transform, opacity;
  }

  /* Animación con delay escalonado para las cards */
  <?php foreach ($requisitos as $i => $r): ?>
    .card-req:nth-child(<?= $i + 1 ?>) {
      animation-delay: <?= 0.4 + $i * 0.15 ?>s;
    }
  <?php endforeach; ?>

  .card-req:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(13, 110, 253, 0.25);
  }

  .not-found {
    min-height: 50vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    text-align: center;
    flex-direction: column;
    gap: 1rem;
    animation: fadeIn 1s ease forwards;
    opacity: 0;
  }

  .not-found i {
    font-size: 3rem;
    color: #0d6efd;
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
</style>

<section class="container matricula-section">
  <h2 class="fw-bold text-primary">Requisitos de Matrícula</h2>

  <?php if (  empty($requisitos)): ?>
    <div class="not-found w-100">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <h5 class="fw-bold">Información no disponible</h5>
      <p>Por el momento no se ha publicado información sobre el proceso de matrícula.</p>
    </div>
  <?php else: ?> 
    <?php if (!empty($requisitos)): ?>
      <div class="row">
        <?php foreach ($requisitos as $req): ?>
          <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm card-req">
              <div class="card-body">
                <h5 class="card-title text-primary"><?= htmlspecialchars($req['titulo']) ?></h5>
                <p><?= nl2br(htmlspecialchars($req['descripcion'])) ?></p>
                <?php if ($req['archivo_modelo']): ?>
                  <a href="<?= htmlspecialchars($req['archivo_modelo']) ?>" class="btn btn-outline-primary btn-sm mt-2" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Descargar Modelo
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php include 'includes/footer_publico.php'; ?>
