<?php
include 'includes/header_publico.php';
require 'includes/conexion.php';

$anuncios = $pdo->query("SELECT * FROM publicaciones WHERE visible = 1 ORDER BY creado_en DESC")->fetchAll();
?>

<style>
  main {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
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
      animation-delay: <?= $index * 0.15 ?>s;
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
</style>

<main>
  <section class="container py-5">
    <h2 class="text-center mb-5 text-primary-emphasis">
      <i class="bi bi-megaphone-fill me-2"></i> Tablón de Anuncios
    </h2>

    <?php if (empty($anuncios)): ?>
      <div class="no-anuncios">
        <div>
          <i class="bi bi-inbox-fill d-block"></i>
          <h4 class="fw-semibold mb-2">No hay anuncios disponibles</h4>
          <p class="mb-0">Cuando se publique algo importante, aparecerá aquí. ¡Vuelve pronto!</p>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($anuncios as $a): ?>
      <div class="card anuncio-card">
        <div class="card-body">
          <h4 class="card-title text-primary"><?= htmlspecialchars($a['titulo']) ?></h4>

          <div class="d-flex justify-content-between align-items-center anuncio-meta mb-2">
            <span><i class="bi bi-tag-fill me-1"></i><?= ucfirst(htmlspecialchars($a['tipo'])) ?></span>
            <span><i class="bi bi-calendar-check me-1"></i><?= date('d/m/Y', strtotime($a['creado_en'])) ?></span>
          </div>

          <?php if ($a['imagen']): ?>
            <img src="api/<?= htmlspecialchars($a['imagen']) ?>" alt="Imagen del anuncio" class="img-fluid anuncio-img mb-3 w-100">
          <?php endif; ?>

          <p class="anuncio-contenido"><?= nl2br(htmlspecialchars($a['contenido'])) ?></p>

          <?php if ($a['archivo_adjunto']): ?>
            <a href="<?= htmlspecialchars($a['archivo_adjunto']) ?>" class="btn btn-outline-primary btn-sm mt-3" target="_blank" rel="noopener">
              <i class="bi bi-download me-1"></i> Descargar documento
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
</main>

<?php include 'includes/footer_publico.php'; ?>
