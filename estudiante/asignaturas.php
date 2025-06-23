<?php
session_start();
require '../includes/conexion.php';

// Validación de sesión
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];

// Obtener curso actual
$stmt = $pdo->prepare("SELECT curso_actual FROM estudiantes WHERE id_estudiante = ?");
$stmt->execute([$id_estudiante]);
$curso_actual = $stmt->fetchColumn();

// Obtener asignaturas disponibles del curso actual
$sql = "SELECT a.id_asignatura, a.nombre AS asignatura, s.nombre AS semestre
        FROM asignaturas a
        JOIN semestres s ON a.semestre_id = s.id_semestre
        WHERE a.curso_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$curso_actual]);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>
<div class="container py-5">
    <h3 class="mb-4">📚 Solicitud de Inscripción de Asignaturas</h3>

    <form id="formInscripcion" method="POST" action="procesar_solicitud.php">
        <div class="row row-cols-1 row-cols-md-2 g-3">
            <?php foreach ($asignaturas as $asig): ?>
                <div class="col">
                    <div class="form-check border p-3 rounded shadow-sm">
                        <input class="form-check-input asignatura-check" type="checkbox" 
                               name="asignaturas[]" value="<?= $asig['id_asignatura'] ?>" id="asig<?= $asig['id_asignatura'] ?>">
                        <label class="form-check-label" for="asig<?= $asig['id_asignatura'] ?>">
                            <?= htmlspecialchars($asig['asignatura']) ?> 
                            <small class="text-muted">(<?= htmlspecialchars($asig['semestre']) ?>)</small>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success" id="btnEnviar">📝 Enviar Solicitud</button>
            <span id="mensaje" class="text-danger ms-3"></span>
        </div>
    </form>
</div>

<script>
// Límite máximo de asignaturas
const maxAsignaturas = 6;
const checkboxes = document.querySelectorAll('.asignatura-check');
const mensaje = document.getElementById('mensaje');

checkboxes.forEach(cb => {
    cb.addEventListener('change', () => {
        const seleccionadas = document.querySelectorAll('.asignatura-check:checked').length;
        if (seleccionadas > maxAsignaturas) {
            cb.checked = false;
            mensaje.textContent = "❌ Solo puedes seleccionar hasta " + maxAsignaturas + " asignaturas.";
        } else {
            mensaje.textContent = "";
        }
    });
});
</script>
<?php include 'footer.php'; ?>
