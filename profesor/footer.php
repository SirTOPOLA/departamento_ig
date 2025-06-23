<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
    const overlay = document.getElementById('overlay');
    if (!overlay) {
      const ov = document.createElement('div');
      ov.id = 'overlay';
      ov.className = 'overlay show';
      ov.onclick = toggleSidebar;
      document.body.appendChild(ov);
    } else {
      overlay.classList.toggle('show');
      if (!overlay.classList.contains('show')) {
        setTimeout(() => overlay.remove(), 300);
      }
    }
  }

  function confirmarLogout(event) {
    event.preventDefault();
    if (confirm('¿Seguro que deseas cerrar sesión?')) {
      window.location.href = '../logout.php';
    }
  }

  // Script para marcar el link activo según la URL actual
  document.addEventListener('DOMContentLoaded', () => {
    const links = document.querySelectorAll('.sidebar nav a');
    const current = window.location.pathname.split('/').pop();
    links.forEach(link => {
      if (link.getAttribute('href') === current) {
        link.classList.add('active');
      }
    });
  });
</script>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>