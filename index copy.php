<?php include 'includes/header_publico.php'; ?>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;600&display=swap');

  body, html {
    height: 100%;
    margin: 0;
    font-family: 'Montserrat', sans-serif;
    background: #121212;
    color: #f5f5f5;
  }

  .hero {
    position: relative;
    height: 100vh;
    background: url('img/eua.jpg') no-repeat center center/cover;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    text-align: center;
    overflow: hidden;
  }
/* 
  .hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: repeating-radial-gradient(circle at center, rgba(255,255,255,0.02) 0 2px, transparent 2px 6px);
    pointer-events: none;
    z-index: 1;
  } */

  .hero-content {
    position: relative;
    max-width: 680px; 
    z-index: 2;
  }

  .hero-title {
    font-weight: 600;
    font-size: clamp(2.8rem, 6vw, 4rem);
    margin-bottom: 1rem;
    letter-spacing: 0.05em;
    line-height: 1.15;
    text-shadow: 0 3px 10px rgba(0,0,0,0.7);
  }

  .hero-subtitle {
    font-weight: 300;
    font-size: clamp(1.2rem, 2vw, 1.5rem);
    margin-bottom: 2.8rem;
    color: #ddd;
    letter-spacing: 0.03em;
    text-shadow: 0 2px 8px rgba(0,0,0,0.5);
  }

  .btn-cta {
    background-color: transparent;
    border: 2px solid #ffc107;
    color: #ffc107;
    font-weight: 600;
    padding: 0.9rem 2.5rem;
    font-size: 1.15rem;
    border-radius: 9999px;
    cursor: pointer;
    transition:
      background-color 0.35s ease,
      color 0.35s ease,
      box-shadow 0.35s ease;
    box-shadow: 0 0 8px rgba(255, 193, 7, 0.6);
    user-select: none;
  }

  .btn-cta:hover,
  .btn-cta:focus {
    background-color: #ffc107;
    color: #121212;
    box-shadow: 0 0 20px rgba(255, 193, 7, 0.9);
    outline: none;
  }

  .carousel-text {
    color: #ffc107;
    font-style: italic;
    font-weight: 500;
    font-size: clamp(1.1rem, 2vw, 1.4rem);
    min-height: 3.5rem;
    margin-bottom: 3rem;
    position: relative;
    overflow: hidden;
  }

  .carousel-text > span {
    position: absolute;
    width: 100%;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.8s ease, transform 0.8s ease;
    will-change: opacity, transform;
  }

  .carousel-text > span.active {
    opacity: 1;
    transform: translateY(0);
  }

  @media (max-width: 576px) {
    .hero-title {
      font-size: 2rem;
    }
    .hero-subtitle {
      font-size: 1rem;
      margin-bottom: 2rem;
    }
    .btn-cta {
      font-size: 1rem;
      padding: 0.75rem 2rem;
    }
  }
</style>

<section class="hero" role="main" aria-label="Bienvenida al Departamento de Informática de Gestión">
  <div class="hero-content">
    <h1 class="hero-title" tabindex="0">Bienvenido al Departamento de Informática de Gestión</h1>
    <p class="hero-subtitle" tabindex="0">Consulta toda la información sobre matrículas, horarios, anuncios importantes y mucho más.</p>

    <div class="carousel-text" aria-live="polite" aria-atomic="true" tabindex="0" id="carouselFrases" aria-label="Frases motivadoras">
      <!-- Frases inyectadas por JS -->
    </div>

    <a href="matricula.php" class="btn-cta" role="button" aria-label="Ir a la información de matrícula">
      Información de Matrícula
    </a>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const frases = [
      "La educación no cambia el mundo. Cambia a las personas que van a cambiar el mundo. – Paulo Freire",
      "Piensa, sueña, cree y atrévete. – Walt Disney",
      "El futuro pertenece a quienes creen en la belleza de sus sueños. – Eleanor Roosevelt",
      "No hay ascensor al éxito. Tienes que tomar las escaleras. – Zig Ziglar",
      "Sé tú el cambio que quieres ver en el mundo. – Mahatma Gandhi"
    ];

    const container = document.getElementById('carouselFrases');

    // Crear spans con frases ocultas inicialmente
    frases.forEach((frase, i) => {
      const span = document.createElement('span');
      span.textContent = frase;
      if(i === 0) span.classList.add('active');
      container.appendChild(span);
    });

    let index = 0;
    setInterval(() => {
      const spans = container.querySelectorAll('span');
      spans[index].classList.remove('active');
      index = (index + 1) % spans.length;
      spans[index].classList.add('active');
    }, 6000);
  });
</script>

<?php include 'includes/footer_publico.php'; ?>
