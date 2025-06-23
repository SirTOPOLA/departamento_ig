<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../login.php");
    exit;
}

require '../includes/conexion.php';

$idProfesor = $_SESSION['id_usuario']; // Suponiendo que aquí guardas el id_usuario del profesor en sesión

// Obtener datos del profesor
$stmt = $pdo->prepare("SELECT u.nombre, u.apellido, p.especialidad FROM usuarios u 
    JOIN profesores p ON u.id_usuario = p.id_profesor 
    WHERE p.id_profesor = ?");
$stmt->execute([$idProfesor]);
$profesor = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener asignaturas del profesor
$stmt = $pdo->prepare("SELECT a.id_asignatura, a.nombre FROM asignaturas a
    JOIN asignatura_profesor ap ON a.id_asignatura = ap.id_asignatura
    WHERE ap.id_profesor = ?");
$stmt->execute([$idProfesor]);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener horario del profesor (ordenado por día y hora)
$stmt = $pdo->prepare("SELECT h.dia, h.hora_inicio, h.hora_fin, a.nombre as aula, asig.nombre as asignatura 
    FROM horarios h
    JOIN aulas a ON h.aula_id = a.id_aula
    JOIN asignaturas asig ON h.id_asignatura = asig.id_asignatura
    WHERE h.id_profesor = ?
    ORDER BY FIELD(h.dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), h.hora_inicio");
$stmt->execute([$idProfesor]);
$horario = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Últimas 5 publicaciones visibles
$stmt = $pdo->query("SELECT titulo, tipo, creado_en FROM publicaciones WHERE visible = 1 ORDER BY creado_en DESC LIMIT 5");
$publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>


<?php include 'header.php'; ?>
<div id="main-content" class="main-content">
    <div class="container p-5">
        <header class="mb-5">
            <h2>Bienvenido, <?= htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellido']) ?></h2>
            <p>Especialidad: <strong><?= htmlspecialchars($profesor['especialidad']) ?></strong></p>
        </header>

        <div class="row g-4">

            <!-- Asignaturas -->
            <div class="col-md-6">
                <div class="card p-3">
                    <h3><i class="bi bi-journals me-2"></i>Asignaturas Asignadas</h3>
                    <?php if ($asignaturas): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($asignaturas as $asig): ?>
                                <li class="list-group-item"><?= htmlspecialchars($asig['nombre']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No tienes asignaturas asignadas actualmente.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Horario -->
            <div class="col-md-6">
                <div class="card p-3">
                    <h3><i class="bi bi-clock-history me-2"></i>Horario Semanal</h3>
                    <?php if ($horario): ?>
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Día</th>
                                    <th>Hora Inicio</th>
                                    <th>Hora Fin</th>
                                    <th>Aula</th>
                                    <th>Asignatura</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($horario as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['dia']) ?></td>
                                        <td><?= htmlspecialchars(substr($h['hora_inicio'], 0, 5)) ?></td>
                                        <td><?= htmlspecialchars(substr($h['hora_fin'], 0, 5)) ?></td>
                                        <td><?= htmlspecialchars($h['aula']) ?></td>
                                        <td><?= htmlspecialchars($h['asignatura']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No tienes horario asignado.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Publicaciones -->
            <div class="col-12">
                <div class="card p-3">
                    <h3><i class="bi bi-megaphone me-2"></i>Últimas Publicaciones</h3>
                    <?php if ($publicaciones): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($publicaciones as $pub): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($pub['titulo']) ?></strong> <br />
                                        <small class="text-muted text-capitalize"><?= htmlspecialchars($pub['tipo']) ?></small>
                                    </div>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($pub['creado_en'])) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No hay publicaciones disponibles.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>