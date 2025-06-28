<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// --- PHP para la sección de Horarios ---
// Obtener todos los años académicos para el filtro y el modal de horarios
$stmtAnios = $pdo->query("SELECT id_anio, anio, activo, fecha_inicio, fecha_fin FROM anios_academicos ORDER BY anio DESC");
$anios = $stmtAnios->fetchAll(PDO::FETCH_ASSOC);

// Determinar el año académico activo o el seleccionado por el usuario para la tabla de horarios
$currentAnioId = null;
foreach ($anios as $a) {
    if ($a['activo']) {
        $currentAnioId = $a['id_anio'];
        break;
    }
}
if (isset($_GET['anio_id']) && is_numeric($_GET['anio_id'])) {
    $currentAnioId = $_GET['anio_id'];
}

// Obtener todos los horarios con joins, filtrados por el año académico
// Se añade JOIN a `cursos` para obtener el nombre del curso y el turno
$sqlHorarios = "SELECT h.*,
                       a.nombre AS asignatura,
                       u.nombre AS profesor_nombre,
                       u.apellido AS profesor_apellido,
                       au.nombre AS aula,
                       aa.anio AS anio_academico_nombre,
                       c.nombre AS curso_nombre,  -- Nuevo: Nombre del curso
                       c.turno AS curso_turno     -- Nuevo: Turno del curso
                FROM horarios h
                JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
                JOIN profesores p ON h.id_profesor = p.id_profesor
                JOIN usuarios u ON p.id_profesor = u.id_usuario
                JOIN aulas au ON h.aula_id = au.id_aula
                JOIN anios_academicos aa ON h.id_anio = aa.id_anio
                JOIN cursos c ON a.curso_id = c.id_curso"; // Nuevo JOIN para obtener el curso y su turno

$whereClauses = [];
$params = [];

if ($currentAnioId) {
    $whereClauses[] = "h.id_anio = :anio_id";
    $params[':anio_id'] = $currentAnioId;
}

if (!empty($whereClauses)) {
    $sqlHorarios .= " WHERE " . implode(' AND ', $whereClauses);
}

$sqlHorarios .= " ORDER BY FIELD(dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), hora_inicio";

$stmtHorarios = $pdo->prepare($sqlHorarios);
$stmtHorarios->execute($params);
$horarios = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);

// Obtener profesores y aulas para el modal de horarios
$profs = $pdo->query("SELECT p.id_profesor, u.nombre, u.apellido
                       FROM profesores p JOIN usuarios u ON p.id_profesor = u.id_usuario ORDER BY u.nombre")->fetchAll();
$aulas = $pdo->query("SELECT id_aula, nombre FROM aulas ORDER BY nombre")->fetchAll();

// --- PHP para la sección de Configuración de Horarios (Reglas por Día) ---
// Obtener todas las configuraciones de horarios
$stmtConfig = $pdo->query("SELECT * FROM configuracion_horarios ORDER BY FIELD(dia_semana, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Default')");
$configuraciones = $stmtConfig->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
    <div class="container py-5">
        <h3><i class="bi bi-calendar-check"></i> Sistema de Gestión de Horarios</h3>

        <!-- Pestañas de Navegación -->
        <ul class="nav nav-tabs mb-4" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="horarios-tab" data-bs-toggle="tab" data-bs-target="#horariosContent" type="button" role="tab" aria-controls="horariosContent" aria-selected="true">
                    <i class="bi bi-calendar-week"></i> Gestión de Horarios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="anios-tab" data-bs-toggle="tab" data-bs-target="#aniosContent" type="button" role="tab" aria-controls="aniosContent" aria-selected="false">
                    <i class="bi bi-calendar-event"></i> Años Académicos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reglas-tab" data-bs-toggle="tab" data-bs-target="#reglasContent" type="button" role="tab" aria-controls="reglasContent" aria-selected="false">
                    <i class="bi bi-gear"></i> Reglas por Día
                </button>
            </li>
        </ul>

        <!-- Contenido de las Pestañas -->
        <div class="tab-content" id="mainTabsContent">

            <!-- Pestaña de Gestión de Horarios -->
            <div class="tab-pane fade show active" id="horariosContent" role="tabpanel" aria-labelledby="horarios-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Horarios Asignados</h4>
                    <div class="d-flex align-items-center">
                        <select id="filtroAnio" class="form-select me-2" onchange="filtrarPorAnio()">
                            <option value="">Todos los Años</option>
                            <?php foreach ($anios as $a): ?>
                                <option value="<?= $a['id_anio'] ?>" <?= ($a['id_anio'] == $currentAnioId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['anio']) ?> <?= $a['activo'] ? '(Activo)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-success" onclick="abrirModalHorario()">
                            <i class="bi bi-plus-circle"></i> Nuevo Horario
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-info">
                            <tr>
                                <th>#</th>
                                <th>Año Académico</th>
                                <th>Asignatura</th>
                                <th>Curso</th>    <!-- Nueva columna -->
                                <th>Turno</th>    <!-- Nueva columna -->
                                <th>Profesor</th>
                                <th>Aula</th>
                                <th>Día</th>
                                <th>Hora</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios as $h): ?>
                                <tr>
                                    <td><?= $h['id_horario'] ?></td>
                                    <td><?= htmlspecialchars($h['anio_academico_nombre']) ?></td>
                                    <td><?= htmlspecialchars($h['asignatura']) ?></td>
                                    <td><?= htmlspecialchars($h['curso_nombre']) ?></td>  <!-- Mostrar nombre del curso -->
                                    <td><?= htmlspecialchars(ucfirst($h['curso_turno'])) ?></td> <!-- Mostrar turno (capitalizado) -->
                                    <td><?= htmlspecialchars($h['profesor_nombre'] . ' ' . $h['profesor_apellido']) ?></td>
                                    <td><?= htmlspecialchars($h['aula']) ?></td>
                                    <td><?= $h['dia'] ?></td>
                                    <td><?= substr($h['hora_inicio'], 0, 5) ?> - <?= substr($h['hora_fin'], 0, 5) ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editarHorario(<?= $h['id_horario'] ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarHorario(<?= $h['id_horario'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($horarios) === 0): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No hay horarios registrados para este año o filtros.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pestaña de Años Académicos -->
            <div class="tab-pane fade" id="aniosContent" role="tabpanel" aria-labelledby="anios-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Gestión de Años Académicos</h4>
                    <button class="btn btn-success" onclick="abrirModalAnio()"><i class="bi bi-plus-circle"></i> Nuevo Año</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-info">
                            <tr>
                                <th>#</th>
                                <th>Año</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Activo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($anios as $a): ?>
                                <tr>
                                    <td><?= $a['id_anio'] ?></td>
                                    <td><?= htmlspecialchars($a['anio']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($a['fecha_inicio'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($a['fecha_fin'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $a['activo'] ? 'success' : 'secondary' ?>">
                                            <?= $a['activo'] ? 'Sí' : 'No' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editarAnio(<?= $a['id_anio'] ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarAnio(<?= $a['id_anio'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php if (!$a['activo']): ?>
                                            <button class="btn btn-primary btn-sm" onclick="activarAnio(<?= $a['id_anio'] ?>)">
                                                <i class="bi bi-check-circle"></i> Activar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($anios) === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No hay años académicos registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pestaña de Reglas por Día -->
            <div class="tab-pane fade" id="reglasContent" role="tabpanel" aria-labelledby="reglas-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Reglas de Horarios por Día</h4>
                    <button class="btn btn-success" onclick="abrirModalNuevaConfiguracion()"><i class="bi bi-plus-circle"></i> Nueva Regla</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-info">
                            <tr>
                                <th>Día</th>
                                <th>Inicio Perm.</th>
                                <th>Fin Perm.</th>
                                <th>Máx Horas Prof.</th>
                                <th>Min/Max Clase (min)</th>
                                <th>Requiere Mixto</th>
                                <th>Min 1h/2h</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configuraciones as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['dia_semana']) ?></td>
                                    <td><?= substr($c['hora_inicio_permitida'], 0, 5) ?></td>
                                    <td><?= substr($c['hora_fin_permitida'], 0, 5) ?></td>
                                    <td><?= $c['max_horas_dia_profesor'] ?></td>
                                    <td><?= $c['min_duracion_clase_min'] ?> / <?= $c['max_duracion_clase_min'] ?></td>
                                    <td><?= $c['requiere_mixto_horas'] ? 'Sí' : 'No' ?></td>
                                    <td><?= $c['min_clases_1h_mixto'] ?> / <?= $c['min_clases_2h_mixto'] ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editarConfiguracion(<?= $c['id'] ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php if ($c['dia_semana'] !== 'Default'): ?>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarConfiguracion(<?= $c['id'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <!-- Fin tab-content -->
    </div> <!-- Fin container -->
</div> <!-- Fin content -->

<!-- Modal para Nuevo / Editar Horario -->
<div class="modal fade" id="modalHorario" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="formHorario">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Nuevo / Editar Horario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_horario" id="id_horario">

                <div class="mb-2">
                    <label class="form-label">Año Académico</label>
                    <select name="id_anio" id="id_anio_horario_modal" class="form-select" required>
                        <?php foreach ($anios as $a): ?>
                            <option value="<?= $a['id_anio'] ?>" <?= ($a['activo']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['anio']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Profesor</label>
                    <select name="id_profesor" id="id_profesor_horario_modal" class="form-select" required>
                        <option value="">Seleccione un profesor</option>
                        <?php foreach ($profs as $p): ?>
                            <option value='<?= $p['id_profesor'] ?>'><?= htmlspecialchars($p['nombre'] . ' ' . $p['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Asignatura</label>
                    <select name="id_asignatura" id="id_asignatura_horario_modal" class="form-select" disabled required>
                        <option value="">Seleccione un profesor primero</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Aula</label>
                    <select name="aula_id" id="aula_id_horario_modal" class="form-select" required>
                        <option value="">Seleccione un aula</option>
                        <?php foreach ($aulas as $a): ?>
                            <option value='<?= $a['id_aula'] ?>'><?= htmlspecialchars($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Día</label>
                    <select name="dia" id="dia_horario_modal" class="form-select" required>
                        <option>Lunes</option>
                        <option>Martes</option>
                        <option>Miércoles</option>
                        <option>Jueves</option>
                        <option>Viernes</option>
                        <option>Sábado</option>
                    </select>
                </div>

                <div class="mb-2 row">
                    <div class="col">
                        <label class="form-label">Inicio</label>
                        <input type="time" name="hora_inicio" id="hora_inicio_horario_modal" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Fin</label>
                        <input type="time" name="hora_fin" id="hora_fin_horario_modal" class="form-control" required>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Guardar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Nuevo / Editar Año Académico -->
<div class="modal fade" id="modalAnio" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="formAnio">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Nuevo / Editar Año Académico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_anio" id="anio_id">
                <div class="mb-2">
                    <label class="form-label">Año</label>
                    <input type="text" name="anio" id="anio_nombre" class="form-control" required placeholder="Ej: 2024-2025">
                </div>
                <div class="mb-2">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" id="anio_fecha_inicio" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Fecha de Fin</label>
                    <input type="date" name="fecha_fin" id="anio_fecha_fin" class="form-control" required>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="activo" id="anio_activo">
                    <label class="form-check-label" for="anio_activo">
                        Activo (solo un año puede estar activo a la vez)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Guardar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Editar Configuración de Horario por Día -->
<div class="modal fade" id="modalConfiguracion" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="formConfiguracion">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Editar Reglas de Horario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_config" id="config_id">
                <div class="mb-2">
                    <label class="form-label">Día de la Semana</label>
                    <input type="text" name="dia_semana" id="config_dia_semana" class="form-control" readonly>
                </div>
                <div class="mb-2 row">
                    <div class="col">
                        <label class="form-label">Hora Inicio Permitida</label>
                        <input type="time" name="hora_inicio_permitida" id="config_hora_inicio_permitida" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Hora Fin Permitida</label>
                        <input type="time" name="hora_fin_permitida" id="config_hora_fin_permitida" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Máx. Horas por Profesor al Día</label>
                    <input type="number" name="max_horas_dia_profesor" id="config_max_horas_dia_profesor" class="form-control" min="1" required>
                </div>
                <div class="mb-2 row">
                    <div class="col">
                        <label class="form-label">Min. Duración Clase (min)</label>
                        <input type="number" name="min_duracion_clase_min" id="config_min_duracion_clase_min" class="form-control" min="10" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Máx. Duración Clase (min)</label>
                        <input type="number" name="max_duracion_clase_min" id="config_max_duracion_clase_min" class="form-control" min="10" required>
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="requiere_mixto_horas" id="config_requiere_mixto_horas">
                    <label class="form-check-label" for="config_requiere_mixto_horas">
                        Requiere Combinación Mixta de Clases (1h/2h)
                    </label>
                </div>
                <div class="row" id="mixto_options_div">
                    <div class="col-6 mb-2">
                        <label class="form-label">Mín. Clases de 1h (Mixto)</label>
                        <input type="number" name="min_clases_1h_mixto" id="config_min_clases_1h_mixto" class="form-control" min="0">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Mín. Clases de 2h (Mixto)</label>
                        <input type="number" name="min_clases_2h_mixto" id="config_min_clases_2h_mixto" class="form-control" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Guardar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Nueva Configuración (para añadir un día no existente) -->
<div class="modal fade" id="modalNuevaConfiguracion" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="formNuevaConfiguracion">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Nueva Regla de Horario por Día</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Día de la Semana</label>
                    <select name="dia_semana" id="nueva_config_dia_semana" class="form-select" required>
                        <option value="">Seleccione un día</option>
                        <option>Lunes</option>
                        <option>Martes</option>
                        <option>Miércoles</option>
                        <option>Jueves</option>
                        <option>Viernes</option>
                        <option>Sábado</option>
                        <option>Default</option>
                    </select>
                </div>
                <div class="mb-2 row">
                    <div class="col">
                        <label class="form-label">Hora Inicio Permitida</label>
                        <input type="time" name="hora_inicio_permitida" id="nueva_config_hora_inicio_permitida" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Hora Fin Permitida</label>
                        <input type="time" name="hora_fin_permitida" id="nueva_config_hora_fin_permitida" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Máx. Horas por Profesor al Día</label>
                    <input type="number" name="max_horas_dia_profesor" id="nueva_config_max_horas_dia_profesor" class="form-control" min="1" required>
                </div>
                <div class="mb-2 row">
                    <div class="col">
                        <label class="form-label">Min. Duración Clase (min)</label>
                        <input type="number" name="min_duracion_clase_min" id="nueva_config_min_duracion_clase_min" class="form-control" min="10" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Máx. Duración Clase (min)</label>
                        <input type="number" name="max_duracion_clase_min" id="nueva_config_max_duracion_clase_min" class="form-control" min="10" required>
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="requiere_mixto_horas" id="nueva_config_requiere_mixto_horas">
                    <label class="form-check-label" for="nueva_config_requiere_mixto_horas">
                        Requiere Combinación Mixta de Clases (1h/2h)
                    </label>
                </div>
                <div class="row" id="nueva_mixto_options_div">
                    <div class="col-6 mb-2">
                        <label class="form-label">Mín. Clases de 1h (Mixto)</label>
                        <input type="number" name="min_clases_1h_mixto" id="nueva_config_min_clases_1h_mixto" class="form-control" min="0">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Mín. Clases de 2h (Mixto)</label>
                        <input type="number" name="min_clases_2h_mixto" id="nueva_config_min_clases_2h_mixto" class="form-control" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Guardar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </form>
    </div>
</div>


<!-- Custom Alert/Message Box -->
<div class="modal fade" id="customAlertModal" tabindex="-1" aria-labelledby="customAlertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customAlertModalLabel">Mensaje del Sistema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customAlertModalBody">
                <!-- Message will be inserted here -->
            </div>
            <div class="modal-footer" id="customAlertModalFooter">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Variables para Horarios ---
    const modalHorario = new bootstrap.Modal(document.getElementById('modalHorario'));
    const formHorario = document.getElementById('formHorario');
    const selectProfesorHorarioModal = document.getElementById('id_profesor_horario_modal');
    const selectAsignaturaHorarioModal = document.getElementById('id_asignatura_horario_modal');
    const selectDiaHorarioModal = document.getElementById('dia_horario_modal');
    const inputHoraInicioHorarioModal = document.getElementById('hora_inicio_horario_modal');
    const inputHoraFinHorarioModal = document.getElementById('hora_fin_horario_modal');
    const selectAnioHorarioModal = document.getElementById('id_anio_horario_modal');
    const selectAulaHorarioModal = document.getElementById('aula_id_horario_modal');


    // --- Variables para Años Académicos ---
    const modalAnio = new bootstrap.Modal(document.getElementById('modalAnio'));
    const formAnio = document.getElementById('formAnio');

    // --- Variables para Configuración de Horarios por Día ---
    const modalConfiguracion = new bootstrap.Modal(document.getElementById('modalConfiguracion'));
    const formConfiguracion = document.getElementById('formConfiguracion');
    const checkboxMixto = document.getElementById('config_requiere_mixto_horas');
    const mixtoOptionsDiv = document.getElementById('mixto_options_div');

    // --- Variables para NUEVA Configuración de Horarios por Día ---
    const modalNuevaConfiguracion = new bootstrap.Modal(document.getElementById('modalNuevaConfiguracion'));
    const formNuevaConfiguracion = document.getElementById('formNuevaConfiguracion');
    const nuevaCheckboxMixto = document.getElementById('nueva_config_requiere_mixto_horas');
    const nuevaMixtoOptionsDiv = document.getElementById('nueva_mixto_options_div');


    // --- Variables Comunes ---
    const customAlertModal = new bootstrap.Modal(document.getElementById('customAlertModal'));
    const customAlertModalBody = document.getElementById('customAlertModalBody');
    const customAlertModalFooter = document.getElementById('customAlertModalFooter');

    // Variable para almacenar la configuración de horarios por día
    let configuracionHorarios = {};

    // Definir los rangos de horario para cada turno
    const turnosHorarios = {
        'tarde': {
            inicio: '12:00',
            fin: '16:00'
        },
        'noche': {
            inicio: '16:00',
            fin: '22:00'
        }
    };

    /**
     * Muestra un mensaje personalizado en lugar de alert().
     * @param {string} message El mensaje a mostrar.
     * @param {boolean} isConfirm Si es una confirmación, añade botones Sí/No.
     * @returns {Promise<boolean>} Resuelve a true si se confirma, false si se cancela.
     */
    function showCustomMessage(message, isConfirm = false) {
        return new Promise((resolve) => {
            customAlertModalBody.innerHTML = message;
            customAlertModalFooter.innerHTML = ''; // Limpiar botones anteriores

            if (isConfirm) {
                const btnYes = document.createElement('button');
                btnYes.type = 'button';
                btnYes.className = 'btn btn-danger me-2';
                btnYes.textContent = 'Sí';
                btnYes.onclick = () => {
                    customAlertModal.hide();
                    resolve(true);
                };

                const btnNo = document.createElement('button');
                btnNo.type = 'button';
                btnNo.className = 'btn btn-secondary';
                btnNo.textContent = 'No';
                btnNo.onclick = () => {
                    customAlertModal.hide();
                    resolve(false);
                };
                customAlertModalFooter.appendChild(btnYes);
                customAlertModalFooter.appendChild(btnNo);
            } else {
                const btnOk = document.createElement('button');
                btnOk.type = 'button';
                btnOk.className = 'btn btn-primary';
                btnOk.textContent = 'Aceptar';
                btnOk.onclick = () => {
                    customAlertModal.hide();
                    resolve(true);
                };
                customAlertModalFooter.appendChild(btnOk);
            }
            customAlertModal.show();
        });
    }

    /**
     * Carga la configuración de horarios desde el backend.
     */
    async function cargarConfiguracionHorarios() {
        try {
            const response = await fetch('../api/obtener_configuracion_horarios.php');
            const data = await response.json();
            if (data.status) {
                configuracionHorarios = {}; // Limpiar antes de recargar
                data.configuraciones.forEach(config => {
                    configuracionHorarios[config.dia_semana] = config;
                });
            } else {
                console.error("Error al cargar la configuración de horarios:", data.message);
                showCustomMessage("Error al cargar la configuración de horarios.");
            }
        } catch (error) {
            console.error("Error de red al cargar la configuración de horarios:", error);
            showCustomMessage("Error de red al cargar la configuración de horarios.");
        }
    }

    /**
     * Obtiene la configuración para un día específico, o la configuración por defecto.
     * @param {string} dia El día de la semana.
     * @returns {object} La configuración del día.
     */
    function getConfigForDay(dia) {
        return configuracionHorarios[dia] || configuracionHorarios['Default'] || {};
    }

    // --- Funciones para Gestión de Horarios ---
    async function abrirModalHorario() {
        formHorario.reset();
        formHorario.id_horario.value = '';
        selectAsignaturaHorarioModal.innerHTML = '<option value="">Seleccione un profesor primero</option>';
        selectAsignaturaHorarioModal.disabled = true;

        // Preseleccionar el año activo si existe
        const anioActivo = document.querySelector('#id_anio_horario_modal option[selected]');
        if (anioActivo) {
            selectAnioHorarioModal.value = anioActivo.value;
        }

        // Si es sábado, sugerir horas aleatorias
        if (selectDiaHorarioModal.value === 'Sábado') {
            await sugerirHorasSabado();
        } else {
            // Para otros días, limpiar las horas si no hay un horario específico
            inputHoraInicioHorarioModal.value = '';
            inputHoraFinHorarioModal.value = '';
        }
        modalHorario.show();
    }

    async function editarHorario(id) {
        try {
            const response = await fetch(`../api/obtener_horario.php?id=${id}`);
            const d = await response.json();

            if (d.status && d.horario) {
                const horario = d.horario;
                formHorario.id_horario.value = horario.id_horario;
                selectAnioHorarioModal.value = horario.id_anio;
                selectProfesorHorarioModal.value = horario.id_profesor;
                selectDiaHorarioModal.value = horario.dia;
                inputHoraInicioHorarioModal.value = horario.hora_inicio.substring(0, 5);
                inputHoraFinHorarioModal.value = horario.hora_fin.substring(0, 5);
                
                // Cargar asignaturas del profesor seleccionado y luego seleccionar la correcta
                await cargarAsignaturasProfesor(horario.id_profesor, horario.id_asignatura);

                // Asegurarse de que el aula también se preseleccione
                selectAulaHorarioModal.value = horario.aula_id;

                modalHorario.show();
            } else {
                showCustomMessage("Error al obtener los datos del horario: " + (d.message || "Desconocido"));
            }
        } catch (error) {
            console.error("Error de red al editar horario:", error);
            showCustomMessage("Error de red al editar el horario.");
        }
    }

    async function eliminarHorario(id) {
        const confirmed = await showCustomMessage("¿Está seguro de que desea eliminar este horario?", true);
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/eliminar_horario.php?id=${id}`);
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al eliminar horario:", error);
            showCustomMessage("Error de red al eliminar el horario.");
        }
    }

    function filtrarPorAnio() {
        const anioId = document.getElementById('filtroAnio').value;
        window.location.href = `horarios.php?anio_id=${anioId}`;
    }

    formHorario.addEventListener('submit', async e => {
        e.preventDefault();
        const valido = await validarHorario();
        if (!valido) return;

        const datos = new FormData(formHorario);
        try {
            const response = await fetch('../api/guardar_horario.php', {
                method: 'POST',
                body: datos
            });
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al guardar horario:", error);
            showCustomMessage("Error de red al guardar el horario.");
        }
    });

    selectProfesorHorarioModal.addEventListener('change', async e => {
        const idProfesor = e.target.value;
        if (idProfesor) {
            await cargarAsignaturasProfesor(idProfesor);
        } else {
            selectAsignaturaHorarioModal.innerHTML = '<option value="">Seleccione un profesor primero</option>';
            selectAsignaturaHorarioModal.disabled = true;
        }
    });

    // Nuevo: Listener para el cambio de asignatura para mostrar el turno
    selectAsignaturaHorarioModal.addEventListener('change', () => {
        const selectedOption = selectAsignaturaHorarioModal.options[selectAsignaturaHorarioModal.selectedIndex];
        const turno = selectedOption.dataset.turno;
        if (turno) {
            showCustomMessage(`El turno de esta asignatura es: ${turno.toUpperCase()}. Horario permitido: ${turnosHorarios[turno].inicio} - ${turnosHorarios[turno].fin}.`);
        }
    });


    selectDiaHorarioModal.addEventListener('change', async e => {
        if (e.target.value === 'Sábado') {
            await sugerirHorasSabado();
        } else {
            inputHoraInicioHorarioModal.value = '';
            inputHoraFinHorarioModal.value = '';
        }
    });

    /**
     * Carga las asignaturas de un profesor específico y opcionalmente selecciona una.
     * Ahora también captura el turno de la asignatura.
     * @param {number} idProfesor El ID del profesor.
     * @param {number} [selectedAsignaturaId=null] El ID de la asignatura a seleccionar (para edición).
     */
    async function cargarAsignaturasProfesor(idProfesor, selectedAsignaturaId = null) {
        selectAsignaturaHorarioModal.innerHTML = '<option value="">Cargando...</option>';
        selectAsignaturaHorarioModal.disabled = true;

        // Obtener el ID del año académico del modal de horario
        const idAnio = selectAnioHorarioModal.value;

        try {
            const response = await fetch(`../api/obtener_asignaturas_profesor.php?id_profesor=${idProfesor}&id_anio=${idAnio}`);
            const data = await response.json();

            if (data.status === false) {
                selectAsignaturaHorarioModal.innerHTML = '<option value="">Error al cargar asignaturas</option>';
                showCustomMessage("Error al cargar asignaturas: " + data.message);
            } else if (Array.isArray(data)) {
                if (data.length === 0) {
                    selectAsignaturaHorarioModal.innerHTML = '<option value="">Sin asignaturas disponibles</option>';
                } else {
                    data.forEach(asig => { 
                        const opt = document.createElement('option');
                        opt.value = asig.id_asignatura;
                        opt.textContent = asig.nombre + (asig.ya_asignada ? ' ⚠️ Ya tiene horario en este año' : ''); 
                        opt.dataset.turno = asig.turno; // Guardar el turno en el dataset
                        selectAsignaturaHorarioModal.appendChild(opt);
                    });
                }
            } else {
                console.error("Unexpected data format from API:", data);
                selectAsignaturaHorarioModal.innerHTML = '<option value="">Error: Formato de datos inesperado</option>';
                showCustomMessage("Error: Formato de datos inesperado al cargar asignaturas.");
            }
            selectAsignaturaHorarioModal.disabled = false;
            if (selectedAsignaturaId) {
                selectAsignaturaHorarioModal.value = selectedAsignaturaId;
            }
        } catch (error) {
            console.error("Error de red al cargar asignaturas:", error);
            selectAsignaturaHorarioModal.innerHTML = '<option value="">Error de red al cargar asignaturas</option>';
            showCustomMessage("Error de red al cargar las asignaturas.");
        }
    }

    selectAnioHorarioModal.addEventListener('change', async () => {
        const idProfesor = selectProfesorHorarioModal.value;
        if (idProfesor) {
            await cargarAsignaturasProfesor(idProfesor);
        }
    });

    async function sugerirHorasSabado() {
        const configDia = getConfigForDay('Sábado');
        if (!configDia.hora_inicio_permitida || !configDia.hora_fin_permitida) {
            showCustomMessage("No hay configuración de horario para el Sábado. Por favor, configure en 'Reglas por Día'.");
            return;
        }

        const startMin = new Date(`1970-01-01T${configDia.hora_inicio_permitida}`);
        const endMax = new Date(`1970-01-01T${configDia.hora_fin_permitida}`);

        const startMinMinutes = startMin.getHours() * 60 + startMin.getMinutes();
        const endMaxMinutes = endMax.getHours() * 60 + endMax.getMinutes();

        const minDurationMinutes = configDia.min_duracion_clase_min;
        const maxDurationMinutes = configDia.max_duracion_clase_min;

        const possibleStartMaxMinutes = endMaxMinutes - minDurationMinutes;

        if (possibleStartMaxMinutes < startMinMinutes) {
            showCustomMessage("El rango de horas configurado para el Sábado es demasiado pequeño para una clase.");
            return;
        }

        let randomStartMinutes = Math.floor(Math.random() * (possibleStartMaxMinutes - startMinMinutes + 1)) + startMinMinutes;
        randomStartMinutes = Math.round(randomStartMinutes / 30) * 30;
        randomStartMinutes = Math.max(startMinMinutes, randomStartMinutes);
        randomStartMinutes = Math.min(possibleStartMaxMinutes, randomStartMinutes);

        let randomDurationMinutes = Math.random() < 0.5 ? minDurationMinutes : maxDurationMinutes;

        if (randomStartMinutes + randomDurationMinutes > endMaxMinutes) {
            randomDurationMinutes = minDurationMinutes;
            if (randomStartMinutes + randomDurationMinutes > endMaxMinutes) {
                randomStartMinutes = endMaxMinutes - minDurationMinutes;
            }
        }
        
        const randomEndMinutes = randomStartMinutes + randomDurationMinutes;

        const formatTime = (totalMinutes) => {
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        };

        inputHoraInicioHorarioModal.value = formatTime(randomStartMinutes);
        inputHoraFinHorarioModal.value = formatTime(randomEndMinutes);
    }

    /**
     * Valida el horario antes de enviarlo al backend, incluyendo la validación por turno.
     */
    async function validarHorario() {
        const idProfesor = selectProfesorHorarioModal.value;
        const dia = selectDiaHorarioModal.value;
        const horaInicio = inputHoraInicioHorarioModal.value;
        const horaFin = inputHoraFinHorarioModal.value;
        const idHorario = formHorario.id_horario.value || "";
        const idAnio = selectAnioHorarioModal.value;
        const aulaId = selectAulaHorarioModal.value;
        const selectedAsignaturaOption = selectAsignaturaHorarioModal.options[selectAsignaturaHorarioModal.selectedIndex];
        const turnoAsignatura = selectedAsignaturaOption ? selectedAsignaturaOption.dataset.turno : null;

        if (!idProfesor || !dia || !horaInicio || !horaFin || !idAnio || !aulaId || !turnoAsignatura) {
            showCustomMessage("Por favor, complete todos los campos requeridos y asegúrese de que la asignatura tenga un turno asociado.");
            return false;
        }

        const inicio = new Date(`1970-01-01T${horaInicio}:00`);
        const fin = new Date(`1970-01-01T${horaFin}:00`);

        if (fin <= inicio) {
            showCustomMessage("La hora de fin debe ser posterior a la hora de inicio.");
            return false;
        }

        // --- Validación por Turno ---
        const turnoInfo = turnosHorarios[turnoAsignatura];
        if (!turnoInfo) {
            showCustomMessage(`Turno desconocido para la asignatura: ${turnoAsignatura}.`);
            return false;
        }

        const turnoInicio = new Date(`1970-01-01T${turnoInfo.inicio}:00`);
        const turnoFin = new Date(`1970-01-01T${turnoInfo.fin}:00`);

        if (inicio < turnoInicio || fin > turnoFin) {
            showCustomMessage(`Las horas del horario (${horaInicio}-${horaFin}) deben estar dentro del rango del turno '${turnoAsignatura}' (${turnoInfo.inicio}-${turnoInfo.fin}).`);
            return false;
        }
        // --- Fin Validación por Turno ---


        const configDia = getConfigForDay(dia);
        if (!configDia.hora_inicio_permitida || !configDia.hora_fin_permitida) {
            showCustomMessage(`No hay configuración de horario para el día ${dia}. Por favor, configure en 'Reglas por Día'.`);
            return false;
        }

        const minHoraPermitida = new Date(`1970-01-01T${configDia.hora_inicio_permitida}`);
        const maxHoraPermitida = new Date(`1970-01-01T${configDia.hora_fin_permitida}`);

        if (inicio < minHoraPermitida || fin > maxHoraPermitida) {
            showCustomMessage(`Las horas deben estar entre ${configDia.hora_inicio_permitida.substring(0, 5)} y ${configDia.hora_fin_permitida.substring(0, 5)} para el día ${dia}.`);
            return false;
        }

        const duracionMinutos = (fin - inicio) / 1000 / 60;
        const minDuracionClase = configDia.min_duracion_clase_min;
        const maxDuracionClase = configDia.max_duracion_clase_min;

        if (duracionMinutos < minDuracionClase || duracionMinutos > maxDuracionClase) {
            showCustomMessage(`La duración de la clase debe ser entre ${minDuracionClase / 60} y ${maxDuracionClase / 60} horas.`);
            return false;
        }

        try {
            const query = new URLSearchParams({
                id_profesor: idProfesor,
                aula_id: aulaId,
                dia: dia,
                hora_inicio: horaInicio,
                hora_fin: horaFin,
                id_horario: idHorario,
                id_anio: idAnio
            });

            const response = await fetch(`../api/consultar_horarios_profesor.php?${query}`);
            const data = await response.json();

            if (data.solapamiento_profesor) {
                showCustomMessage("El profesor ya tiene un horario que se solapa con el horario propuesto.");
                return false;
            }
            if (data.solapamiento_aula) {
                showCustomMessage("El aula seleccionada ya está ocupada en el horario propuesto.");
                return false;
            }

            const horariosExistentes = data.horarios_dia ?? [];
            let horasUsadasProfesor = 0;
            let count1h = 0;
            let count2h = 0;

            horariosExistentes.forEach(h => {
                const hi = new Date(`1970-01-01T${h.hora_inicio}:00`);
                const hf = new Date(`1970-01-01T${h.hora_fin}:00`);
                const d = (hf - hi) / 1000 / 60;
                horasUsadasProfesor += d;
                if (Math.abs(d - 60) < 1) count1h++;
                else if (Math.abs(d - 120) < 1) count2h++;
            });

            const duracionNuevaClase = duracionMinutos;
            const totalHorasProfesor = horasUsadasProfesor + duracionNuevaClase;

            if (totalHorasProfesor > (configDia.max_horas_dia_profesor * 60)) {
                showCustomMessage(`El profesor no puede exceder las ${configDia.max_horas_dia_profesor} horas por día. Total: ${totalHorasProfesor / 60} horas.`);
                return false;
            }

            if (Math.abs(duracionNuevaClase - 60) < 1) count1h++;
            else if (Math.abs(duracionNuevaClase - 120) < 1) count2h++;

            if (configDia.requiere_mixto_horas && (totalHorasProfesor / 60) === configDia.max_horas_dia_profesor) {
                if (count1h < configDia.min_clases_1h_mixto || count2h < configDia.min_clases_2h_mixto) {
                    showCustomMessage(`Para un total de ${configDia.max_horas_dia_profesor} horas, debe haber al menos ${configDia.min_clases_1h_mixto} clase(s) de 1h y ${configDia.min_clases_2h_mixto} clase(s) de 2h.`);
                    return false;
                }
            }

            return true;

        } catch (error) {
            console.error("Error validando horarios:", error);
            showCustomMessage("Error validando horarios: " + error.message);
            return false;
        }
    }

    // --- Funciones para Años Académicos ---
    function abrirModalAnio() {
        formAnio.reset();
        formAnio.anio_id.value = '';
        document.getElementById('anio_activo').checked = false;
        modalAnio.show();
    }

    async function editarAnio(id) {
        try {
            const response = await fetch(`../api/anios_academicos_crud.php?id=${id}`);
            const d = await response.json();
            if (d.status && d.anio) {
                const a = d.anio;
                formAnio.anio_id.value = a.id_anio;
                document.getElementById('anio_nombre').value = a.anio;
                document.getElementById('anio_fecha_inicio').value = a.fecha_inicio;
                document.getElementById('anio_fecha_fin').value = a.fecha_fin;
                document.getElementById('anio_activo').checked = (a.activo == 1);
                modalAnio.show();
            } else {
                showCustomMessage("Error al obtener datos del año académico: " + (d.message || "Desconocido"));
            }
        } catch (error) {
            console.error("Error de red al editar año académico:", error);
            showCustomMessage("Error de red al editar el año académico.");
        }
    }

    async function eliminarAnio(id) {
        const confirmed = await showCustomMessage("¿Está seguro de que desea eliminar este año académico? Esto podría afectar horarios asociados.", true);
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/anios_academicos_crud.php?action=delete&id=${id}`);
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al eliminar año académico:", error);
            showCustomMessage("Error de red al eliminar el año académico.");
        }
    }

    async function activarAnio(id) {
        const confirmed = await showCustomMessage("Al activar este año académico, cualquier otro año activo será desactivado. ¿Desea continuar?", true);
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/anios_academicos_crud.php?action=activate&id=${id}`);
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al activar año académico:", error);
            showCustomMessage("Error de red al activar el año académico.");
        }
    }

    formAnio.addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(formAnio);
        formData.append('activo', document.getElementById('anio_activo').checked ? 1 : 0);

        try {
            const response = await fetch('../api/anios_academicos_crud.php', {
                method: 'POST',
                body: formData
            });
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al guardar año académico:", error);
            showCustomMessage("Error de red al guardar el año académico.");
        }
    });

    // --- Funciones para Configuración de Horarios por Día (Edición) ---
    function toggleMixtoOptions() {
        if (checkboxMixto.checked) {
            mixtoOptionsDiv.style.display = 'flex';
            document.getElementById('config_min_clases_1h_mixto').setAttribute('required', 'required');
            document.getElementById('config_min_clases_2h_mixto').setAttribute('required', 'required');
        } else {
            mixtoOptionsDiv.style.display = 'none';
            document.getElementById('config_min_clases_1h_mixto').removeAttribute('required');
            document.getElementById('config_min_clases_2h_mixto').removeAttribute('required');
        }
    }
    checkboxMixto.addEventListener('change', toggleMixtoOptions);
    // document.addEventListener('DOMContentLoaded', toggleMixtoOptions); // Se llama al cargar el modal

    async function editarConfiguracion(id) {
        try {
            const response = await fetch(`../api/configuracion_horarios_crud.php?id=${id}`);
            const d = await response.json();
            if (d.status && d.configuracion) {
                const c = d.configuracion;
                formConfiguracion.config_id.value = c.id_config;
                document.getElementById('config_dia_semana').value = c.dia_semana;
                document.getElementById('config_hora_inicio_permitida').value = c.hora_inicio_permitida.substring(0,5);
                document.getElementById('config_hora_fin_permitida').value = c.hora_fin_permitida.substring(0,5);
                document.getElementById('config_max_horas_dia_profesor').value = c.max_horas_dia_profesor;
                document.getElementById('config_min_duracion_clase_min').value = c.min_duracion_clase_min;
                document.getElementById('config_max_duracion_clase_min').value = c.max_duracion_clase_min;
                document.getElementById('config_requiere_mixto_horas').checked = (c.requiere_mixto_horas == 1);
                document.getElementById('config_min_clases_1h_mixto').value = c.min_clases_1h_mixto;
                document.getElementById('config_min_clases_2h_mixto').value = c.min_clases_2h_mixto;
                toggleMixtoOptions(); // Actualizar visibilidad al abrir modal de edición
                modalConfiguracion.show();
            } else {
                showCustomMessage("Error al obtener datos de configuración: " + (d.message || "Desconocido"));
            }
        } catch (error) {
            console.error("Error de red al editar configuración:", error);
            showCustomMessage("Error de red al editar la configuración.");
        }
    }

    async function eliminarConfiguracion(id) {
        const confirmed = await showCustomMessage("¿Está seguro de que desea eliminar esta configuración específica del día? Se usará la configuración por defecto.", true);
        if (!confirmed) return;

        try {
            const response = await fetch(`../api/configuracion_horarios_crud.php?action=delete&id=${id}`);
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => location.reload());
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al eliminar configuración:", error);
            showCustomMessage("Error de red al eliminar la configuración.");
        }
    }

    formConfiguracion.addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(formConfiguracion);
        formData.append('requiere_mixto_horas', document.getElementById('config_requiere_mixto_horas').checked ? 1 : 0);

        try {
            const response = await fetch('../api/configuracion_horarios_crud.php', {
                method: 'POST',
                body: formData
            });
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => {
                    cargarConfiguracionHorarios(); // Recargar la configuración para que el frontend la tenga actualizada
                    modalConfiguracion.hide(); // Cerrar el modal
                    // Opcional: location.reload() si prefieres recargar toda la página
                });
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al guardar configuración:", error);
            showCustomMessage("Error de red al guardar la configuración.");
        }
    });

    // --- Funciones para NUEVA Configuración de Horarios por Día ---
    function abrirModalNuevaConfiguracion() {
        formNuevaConfiguracion.reset();
        nuevaCheckboxMixto.checked = false; // Asegurar que esté desmarcado por defecto
        toggleNuevaMixtoOptions(); // Ocultar opciones de mixto inicialmente
        modalNuevaConfiguracion.show();
    }

    function toggleNuevaMixtoOptions() {
        if (nuevaCheckboxMixto.checked) {
            nuevaMixtoOptionsDiv.style.display = 'flex';
            document.getElementById('nueva_config_min_clases_1h_mixto').setAttribute('required', 'required');
            document.getElementById('nueva_config_min_clases_2h_mixto').setAttribute('required', 'required');
        } else {
            nuevaMixtoOptionsDiv.style.display = 'none';
            document.getElementById('nueva_config_min_clases_1h_mixto').removeAttribute('required');
            document.getElementById('nueva_config_min_clases_2h_mixto').removeAttribute('required');
        }
    }
    nuevaCheckboxMixto.addEventListener('change', toggleNuevaMixtoOptions);
    // No es necesario llamar a toggleNuevaMixtoOptions en DOMContentLoaded para este modal,
    // ya que se inicializa al abrir el modal.

    formNuevaConfiguracion.addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(formNuevaConfiguracion);
        formData.append('requiere_mixto_horas', nuevaCheckboxMixto.checked ? 1 : 0);
        formData.append('action', 'create'); // Indicar al backend que es una creación

        try {
            const response = await fetch('../api/configuracion_horarios_crud.php', {
                method: 'POST',
                body: formData
            });
            const r = await response.json();
            if (r.status) {
                showCustomMessage(r.message).then(() => {
                    cargarConfiguracionHorarios(); // Recargar la configuración global
                    modalNuevaConfiguracion.hide(); // Cerrar el modal
                    location.reload(); // Recargar la página para ver la nueva regla en la tabla
                });
            } else {
                showCustomMessage("Error: " + r.message);
            }
        } catch (error) {
            console.error("Error de red al guardar nueva configuración:", error);
            showCustomMessage("Error de red al guardar la nueva configuración.");
        }
    });


    // Cargar la configuración de horarios al iniciar la página
    document.addEventListener('DOMContentLoaded', cargarConfiguracionHorarios);
</script>
<?php include_once('footer.php'); ?>
