


<script>
  // Toggle sidebar en móviles
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
  }

  // Confirmar logout
  function confirmarLogout(event) {
    event.preventDefault();
    if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
      window.location.href = '../logout.php';
    }
  }

  // Activar link activo del sidebar según URL
  document.addEventListener('DOMContentLoaded', () => {
    const sidebarLinks = document.querySelectorAll('.sidebar nav a');
    const currentPath = window.location.pathname.split('/').pop().split('?')[0];

    sidebarLinks.forEach(link => {
      const linkPath = link.getAttribute('href');
      if (linkPath === currentPath) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      } else {
        link.classList.remove('active');
        link.removeAttribute('aria-current');
      }
    });
  });
</script>
</div>
</body>
</html>