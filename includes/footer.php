</div>
</div>
</div>
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutConfirmModalLabel">Confirmar Cierre de Sesión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Estás seguro de que quieres cerrar tu sesión?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="../logout.php" class="btn btn-danger">Cerrar Sesión</a>
            </div>
        </div>
    </div>
</div>

<footer class="footer mt-auto py-3 bg-light border-top">
    <div class="container text-center">
        <span class="text-muted">© <?php echo date('Y'); ?> Departamento de Informática de Gestión - UNGE.</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
<script src="../public/js/script.js"></script>
 
 



<script>


function mostrarToast(mensaje, tipo = 'success') {
    const colores = {
        success: 'bg-success text-white',
        error: 'bg-danger text-white',
        warning: 'bg-warning text-dark',
        info: 'bg-info text-dark'
    };

    const iconos = {
        success: '✔️',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    const clase = colores[tipo] || colores.info;
    const icono = iconos[tipo] || iconos.info;

    const toastId = 'toast-' + Date.now();
    const toastHTML = `
      <div id="${toastId}" class="toast align-items-center ${clase} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="d-flex">
          <div class="toast-body">
            ${icono} ${mensaje}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
      </div>
    `;

    const contenedor = document.getElementById('toast-container');
    contenedor.insertAdjacentHTML('beforeend', toastHTML);

    const toastElemento = document.getElementById(toastId);
    const toastBootstrap = new bootstrap.Toast(toastElemento);
    toastBootstrap.show();

    toastElemento.addEventListener('hidden.bs.toast', () => {
        toastElemento.remove();
    });
}





    // Tu script para el toggle del menú (si aplica)
    var el = document.getElementById("wrapper");
    var toggleButton = document.getElementById("menu-toggle");

    if (toggleButton) {
        toggleButton.onclick = function () {
            if (el) {
                el.classList.toggle("toggled");
            }
        };
    }
</script>
 

</body>

</html>