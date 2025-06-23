<?php include 'includes/header_publico.php'; ?>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');

  body, html {
    height: 100%;
    background: #f9fafb;
    font-family: 'Inter', sans-serif;
  }

  section.container {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
  }

  .card {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    background: #ffffff;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    max-width: 420px;
    width: 100%;
  }

  .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
  }

  .card-body {
    padding: 2.5rem 2rem;
  }

  h4 {
    font-weight: 600;
    font-size: 1.75rem;
    color: #111827;
    margin-bottom: 2rem;
  }

  label.form-label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
  }

  input.form-control {
    border-radius: 0.75rem;
    border: 1.5px solid #d1d5db;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
  }

  input.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 8px rgba(37, 99, 235, 0.4);
  }

  button.btn-primary {
    background: linear-gradient(90deg, #2563eb, #1e40af);
    border: none;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 1.125rem;
    padding: 0.85rem 0;
    transition: background 0.3s ease, box-shadow 0.3s ease;
    width: 100%;
    box-shadow: 0 8px 15px rgba(37, 99, 235, 0.3);
    cursor: pointer;
  }

  button.btn-primary:hover,
  button.btn-primary:focus {
    background: linear-gradient(90deg, #1e40af, #2563eb);
    box-shadow: 0 12px 25px rgba(37, 99, 235, 0.5);
    outline: none;
  }

  /* Responsive */
  @media (max-width: 480px) {
    h4 {
      font-size: 1.5rem;
    }

    .card-body {
      padding: 2rem 1.5rem;
    }
  }
</style>

<section class="container" aria-label="Formulario de acceso al sistema">
  <div class="card" role="region" aria-labelledby="loginTitle">
    <div class="card-body">
      <h4 id="loginTitle" class="text-center">Acceder al Sistema</h4>
      <form action="api/validar_login.php" method="POST" novalidate>
        <div class="mb-4">
          <label for="email" class="form-label">Nombre</label>
          <input type="type" name="email" id="email" class="form-control" placeholder="MiNombre123" required aria-required="true" autocomplete="email" autofocus>
        </div>
        <div class="mb-4">
          <label for="contrasena" class="form-label">DIP</label>
          <input type="password" name="contrasena" id="contrasena" class="form-control" placeholder="********" required aria-required="true" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary" aria-label="Ingresar al sistema">Ingresar</button>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer_publico.php'; ?>
