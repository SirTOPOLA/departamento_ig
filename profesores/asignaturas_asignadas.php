<?php
// asignaturas_asignadas.php

// Incluye las funciones necesarias y la configuración de la base de datos
require_once '../includes/functions.php';
require_once '../config/database.php';

// Asegura que el usuario haya iniciado sesión y tenga el rol de 'Profesor'
check_login_and_role('Profesor'); // Esta función debería manejar la redirección si no está autorizado

// Obtener el ID del usuario actual
$id_usuario_actual = $_SESSION['user_id']; // Asumiendo que guardas el ID del usuario en la sesión

try {

    // Consulta para obtener el ID del profesor a partir del ID de usuario
    $stmt_profesor = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
    $stmt_profesor->execute([':id_usuario' => $id_usuario_actual]);
    $datos_profesor = $stmt_profesor->fetch(PDO::FETCH_ASSOC);

    if (!$datos_profesor) {
        throw new Exception("No se encontró el perfil de profesor para el usuario actual.");
    }

    $id_profesor = $datos_profesor['id'];

    // Consulta para obtener las asignaturas asignadas al profesor a través de grupos_asignaturas
    // y obtener el turno del grupo
    $sql_asignaturas = "
        SELECT
            ga.id AS id_grupo_asignatura,
            a.id AS id_asignatura,
            a.nombre_asignatura,
            ga.turno,
            ga.grupo
        FROM
            grupos_asignaturas ga
        INNER JOIN asignaturas a ON ga.id_asignatura = a.id
        WHERE
            ga.id_profesor = :id_profesor
        ORDER BY
            a.nombre_asignatura;
    ";

    $stmt_asignaturas = $pdo->prepare($sql_asignaturas);
    $stmt_asignaturas->execute([':id_profesor' => $id_profesor]);
    $asignaturas_del_profesor = $stmt_asignaturas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Manejo de errores de la base de datos
    error_log("Error al cargar asignaturas: " . $e->getMessage());
    $_SESSION['mensaje_error'] = "Error de base de datos al cargar las asignaturas.";
    $asignaturas_del_profesor = []; // Asegurarse de que $asignaturas_del_profesor esté definido
} catch (Exception $e) {
    // Manejo de otros errores
    error_log("Error: " . $e->getMessage());
    $_SESSION['mensaje_error'] = $e->getMessage();
    $asignaturas_del_profesor = []; // Asegurarse de que $asignaturas_del_profesor esté definido
}

?>

<?php include_once '../includes/header.php'; // Tu archivo de cabecera ?>

<div class="container mt-5">
    <h2 class="mb-4">Mis Asignaturas Asignadas (Tarde y Noche)</h2>

    <?php if (isset($_SESSION['mensaje_error'])): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $_SESSION['mensaje_error'];
            unset($_SESSION['mensaje_error']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($asignaturas_del_profesor)): ?>
        <div class="alert alert-info" role="alert">
            No tienes asignaturas asignadas en los turnos de Tarde o Noche.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID Asignatura</th>
                        <th>Asignatura</th>
                        <th>Grupo</th>
                        <th>Turno</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($asignaturas_del_profesor as $asignatura): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($asignatura['id_asignatura']); ?></td>
                            <td><?php echo htmlspecialchars($asignatura['nombre_asignatura']); ?></td>
                            <td><?php echo htmlspecialchars($asignatura['grupo']); ?></td>
                            <td><?php echo htmlspecialchars($asignatura['turno']); ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm boton-ver-estudiantes"
                                    data-bs-toggle="modal" data-bs-target="#modalEstudiantes"
                                    data-id-grupo-asignatura="<?php echo $asignatura['id_grupo_asignatura']; ?>"
                                    data-nombre-asignatura="<?php echo htmlspecialchars($asignatura['nombre_asignatura']); ?>"
                                    data-turno-asignatura="<?php echo htmlspecialchars($asignatura['turno']); ?>">
                                    Ver Estudiantes
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalEstudiantes" tabindex="-1" aria-labelledby="etiquetaModalEstudiantes"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="etiquetaModalEstudiantes">Estudiantes de <span
                        id="nombreAsignaturaModal"></span> (<span id="turnoAsignaturaModal"></span>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="contenidoListaEstudiantes">
                    <p>Cargando estudiantes...</p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="downloadGradeReportPdfBtn" class="btn btn-danger" target="_blank">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; // Tu archivo de pie de página ?>

<script>

    document.addEventListener('DOMContentLoaded', function () {
        var modalEstudiantes = new bootstrap.Modal(document.getElementById('modalEstudiantes'), {
            keyboard: false
        });

        document.querySelectorAll('.boton-ver-estudiantes').forEach(boton => {
            boton.addEventListener('click', function () {
                const idGrupoAsignatura = this.dataset.idGrupoAsignatura; // Cambiado de idHorario a idGrupoAsignatura
                const nombreAsignatura = this.dataset.nombreAsignatura;
                const turnoAsignatura = this.dataset.turnoAsignatura;

                // Actualizar el título del modal
                document.getElementById('nombreAsignaturaModal').textContent = nombreAsignatura;
                document.getElementById('turnoAsignaturaModal').textContent = turnoAsignatura;
                document.getElementById('contenidoListaEstudiantes').innerHTML = '<p>Cargando estudiantes...</p>'; // Mensaje de carga

                // Mostrar el modal
                modalEstudiantes.show();

                // Actualizar el enlace del botón de descarga de PDF para usar id_grupo_asignatura
                document.getElementById('downloadGradeReportPdfBtn').href = `../libreria/lista_estudiantes.php?id_grupo_asignatura=${idGrupoAsignatura}`;


                // Realizar la petición AJAX para obtener los estudiantes
                // Se envía id_grupo_asignatura en lugar de id_horario y turno (turno ya viene en el grupo)
                fetch('../api/obtener_estudiantes_profesor.php?id_grupo_asignatura=' + idGrupoAsignatura)
                    .then(respuesta => {
                        if (!respuesta.ok) {
                            throw new Error('La respuesta de la red no fue exitosa ' + respuesta.statusText);
                        }
                        return respuesta.text(); // Obtener el HTML como texto
                    })
                    .then(html => {
                        document.getElementById('contenidoListaEstudiantes').innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error al obtener estudiantes:', error);
                        document.getElementById('contenidoListaEstudiantes').innerHTML = '<div class="alert alert-danger" role="alert">Error al cargar los estudiantes. Inténtalo de nuevo.</div>';
                    });
            });
        });
    });
</script>