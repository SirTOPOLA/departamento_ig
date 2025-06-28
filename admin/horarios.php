<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Obtener todos los periodos académicos para el filtro y el modal
$stmtPeriodos = $pdo->query("SELECT * FROM anios_academicos ");
$periodos = $stmtPeriodos->fetchAll(PDO::FETCH_ASSOC);

// Determinar el periodo académico activo o el seleccionado por el usuario
$currentPeriodoId = null;
foreach ($periodos as $p) {
    if ($p['activo']) {
        $currentPeriodoId = $p['id_anio'];
        break;
    }
}
if (isset($_GET['anio_id']) && is_numeric($_GET['anio_id'])) {
    $currentPeriodoId = $_GET['periodo_id'];
}

// Obtener todos los horarios con joins, filtrados por el periodo académico
$sqlHorarios = "SELECT h.*,
                       a.nombre AS asignatura,
                       u.nombre AS profesor_nombre,
                       u.apellido AS profesor_apellido,
                       au.nombre AS aula,
                       pa.nombre_periodo AS periodo_academico_nombre
                FROM horarios h
                JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
                JOIN profesores p ON h.id_profesor = p.id_profesor
                JOIN usuarios u ON p.id_profesor = u.id_usuario
                JOIN aulas au ON h.aula_id = au.id_aula
                JOIN periodos_academicos pa ON h.id_periodo = pa.id_periodo";

$whereClauses = [];
$params = [];

if ($currentPeriodoId) {
    $whereClauses[] = "h.id_periodo = :periodo_id";
    $params[':periodo_id'] = $currentPeriodoId;
}

if (!empty($whereClauses)) {
    $sqlHorarios .= " WHERE " . implode(' AND ', $whereClauses);
}

$sqlHorarios .= " ORDER BY FIELD(dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), hora_inicio";

$stmtHorarios = $pdo->prepare($sqlHorarios);
$stmtHorarios->execute($params);
$horarios = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);

// Obtener profesores y aulas para el modal
$profs = $pdo->query("SELECT p.id_profesor, u.nombre, u.apellido
                       FROM profesores p JOIN usuarios u ON p.id_profesor = u.id_usuario ORDER BY u.nombre")->fetchAll();
$aulas = $pdo->query("SELECT id_aula, nombre FROM aulas ORDER BY nombre")->fetchAll();
?>

<div class="content" id="content" tabindex="-1">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-calendar-week"></i> Gestión de Horarios</h3>
            <div class="d-flex align-items-center">
                <select id="filtroPeriodo" class="form-select me-2" onchange="filtrarPorPeriodo()">
                    <option value="">Todos los Periodos</option>
                    <?php foreach ($periodos as $p): ?>
                        <option value="<?= $p['id_periodo'] ?>" <?= ($p['id_periodo'] == $currentPeriodoId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre_periodo']) ?> <?= $p['activo'] ? '(Activo)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-success me-2" onclick="abrirModalHorario()">
                    <i class="bi bi-plus-circle"></i> Nuevo Horario
                </button>
                <button class="btn btn-info text-white" onclick="window.location.href='configuracion_horarios.php'">
                    <i class="bi bi-gear"></i> Configuración
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-info">
                    <tr>
                        <th>#</th>
                        <th>Período</th>
                        <th>Asignatura</th>
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
                            <td><?= htmlspecialchars($h['periodo_academico_nombre']) ?></td>
                            <td><?= htmlspecialchars($h['asignatura']) ?></td>
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
                            <td colspan="8" class="text-center">No hay horarios registrados para este período o filtros.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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
                    <label class="form-label">Período Académico</label>
                    <select name="id_periodo" id="id_periodo" class="form-select" required>
                        <?php foreach ($periodos as $p): ?>
                            <option value="<?= $p['id_periodo'] ?>" <?= ($p['activo']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre_periodo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Profesor</label>
                    <select name="id_profesor" id="id_profesor" class="form-select" required>
                        <option value="">Seleccione un profesor</option>
                        <?php foreach ($profs as $p): ?>
                            <option value='<?= $p['id_profesor'] ?>'><?= htmlspecialchars($p['nombre'] . ' ' . $p['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Asignatura</label>
                    <select name="id_asignatura" id="id_asignatura" class="form-select" disabled required>
                        <option value="">Seleccione un profesor primero</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Aula</label>
                    <select name="aula_id" id="aula_id" class="form-select" required>
                        <option value="">Seleccione un aula</option>
                        <?php foreach ($aulas as $a): ?>
                            <option value='<?= $a['id_aula'] ?>'><?= htmlspecialchars($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Día</label>
                    <select name="dia" id="dia" class="form-select" required>
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
                        <input type="time" name="hora_inicio" id="hora_inicio" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Fin</label>
                        <input type="time" name="hora_fin" id="hora_fin" class="form-control" required>
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

<!-- Custom Alert/Message Box (Replaces alert() and confirm()) -->
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
    // Inicialización del modal de Bootstrap
    const modalHorario = new bootstrap.Modal(document.getElementById('modalHorario'));
    const formHorario = document.getElementById('formHorario');
    const selectProfesor = document.getElementById('id_profesor');
    const selectAsignatura = document.getElementById('id_asignatura');
    const selectDia = document.getElementById('dia');
    const inputHoraInicio = document.getElementById('hora_inicio');
    const inputHoraFin = document.getElementById('hora_fin');
    const customAlertModal = new bootstrap.Modal(document.getElementById('customAlertModal'));
    const customAlertModalBody = document.getElementById('customAlertModalBody');
    const customAlertModalFooter = document.getElementById('customAlertModalFooter');

    // Variable para almacenar la configuración de horarios por día
    let configuracionHorarios = {};

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
                    resolve(true); // Siempre resuelve a true para mensajes simples
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

    /**
     * Abre el modal para crear un nuevo horario.
     */
    async function abrirModalHorario() {
        formHorario.reset();
        formHorario.id_horario.value = '';
        selectAsignatura.innerHTML = '<option value="">Seleccione un profesor primero</option>';
        selectAsignatura.disabled = true;

        // Preseleccionar el periodo activo si existe
        const periodoActivo = document.querySelector('#id_periodo option[selected]');
        if (periodoActivo) {
            document.getElementById('id_periodo').value = periodoActivo.value;
        }

        // Si es sábado, sugerir horas aleatorias
        if (selectDia.value === 'Sábado') {
            await sugerirHorasSabado();
        } else {
            // Para otros días, limpiar las horas si no hay un horario específico
            inputHoraInicio.value = '';
            inputHoraFin.value = '';
        }
        modalHorario.show();
    }

    /**
     * Edita un horario existente.
     * @param {number} id El ID del horario a editar.
     */
    async function editarHorario(id) {
        try {
            const response = await fetch(`../api/obtener_horario.php?id=${id}`);
            const d = await response.json();

            if (d.status && d.horario) {
                const horario = d.horario;
                formHorario.id_horario.value = horario.id_horario;
                document.getElementById('id_periodo').value = horario.id_periodo;
                selectProfesor.value = horario.id_profesor;
                selectDia.value = horario.dia;
                inputHoraInicio.value = horario.hora_inicio.substring(0, 5);
                inputHoraFin.value = horario.hora_fin.substring(0, 5);
                
                // Cargar asignaturas del profesor seleccionado y luego seleccionar la correcta
                await cargarAsignaturasProfesor(horario.id_profesor, horario.id_asignatura);

                modalHorario.show();
            } else {
                showCustomMessage("Error al obtener los datos del horario: " + (d.message || "Desconocido"));
            }
        } catch (error) {
            console.error("Error de red al editar horario:", error);
            showCustomMessage("Error de red al editar el horario.");
        }
    }

    /**
     * Elimina un horario.
     * @param {number} id El ID del horario a eliminar.
     */
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

    /**
     * Filtra la tabla de horarios por el período académico seleccionado.
     */
    function filtrarPorPeriodo() {
        const periodoId = document.getElementById('filtroPeriodo').value;
        window.location.href = `horarios.php?periodo_id=${periodoId}`;
    }

    // Event listener para el envío del formulario
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

    // Event listener para el cambio de profesor: carga sus asignaturas
    selectProfesor.addEventListener('change', async e => {
        const idProfesor = e.target.value;
        if (idProfesor) {
            await cargarAsignaturasProfesor(idProfesor);
        } else {
            selectAsignatura.innerHTML = '<option value="">Seleccione un profesor primero</option>';
            selectAsignatura.disabled = true;
        }
    });

    // Event listener para el cambio de día: sugerir horas si es Sábado
    selectDia.addEventListener('change', async e => {
        if (e.target.value === 'Sábado') {
            await sugerirHorasSabado();
        } else {
            // Limpiar sugerencias si se cambia a un día que no es Sábado
            inputHoraInicio.value = '';
            inputHoraFin.value = '';
        }
    });

    /**
     * Carga las asignaturas de un profesor específico y opcionalmente selecciona una.
     * @param {number} idProfesor El ID del profesor.
     * @param {number} [selectedAsignaturaId=null] El ID de la asignatura a seleccionar (para edición).
     */
    async function cargarAsignaturasProfesor(idProfesor, selectedAsignaturaId = null) {
        selectAsignatura.innerHTML = '<option value="">Cargando...</option>';
        selectAsignatura.disabled = true;

        try {
            const response = await fetch(`../api/obtener_asignaturas_profesor.php?id_profesor=${idProfesor}`);
            const data = await response.json();

            selectAsignatura.innerHTML = '';
            if (data.length === 0) {
                selectAsignatura.innerHTML = '<option value="">Sin asignaturas disponibles</option>';
            } else {
                data.forEach(asig => {
                    const opt = document.createElement('option');
                    opt.value = asig.id_asignatura;
                    opt.textContent = asig.nombre + (asig.ya_asignada ? ' ⚠️ Ya tiene horario' : '');
                    // Si es edición y es la asignatura actual, no deshabilitarla
                    if (asig.ya_asignada && asig.id_asignatura != selectedAsignaturaId) {
                        opt.disabled = true;
                        opt.classList.add('text-muted');
                    }
                    selectAsignatura.appendChild(opt);
                });
            }
            selectAsignatura.disabled = false;
            // Seleccionar la asignatura si se proporcionó un ID
            if (selectedAsignaturaId) {
                selectAsignatura.value = selectedAsignaturaId;
            }
        } catch (error) {
            console.error("Error al cargar asignaturas:", error);
            selectAsignatura.innerHTML = '<option value="">Error al cargar asignaturas</option>';
        }
    }

    /**
     * Sugiere horas de inicio y fin aleatorias para el Sábado, dentro del rango permitido.
     */
    async function sugerirHorasSabado() {
        const configDia = getConfigForDay('Sábado');
        if (!configDia.hora_inicio_permitida || !configDia.hora_fin_permitida) {
            showCustomMessage("No hay configuración de horario para el Sábado. Por favor, configure en 'Configuración'.");
            return;
        }

        const startMin = new Date(`1970-01-01T${configDia.hora_inicio_permitida}`);
        const endMax = new Date(`1970-01-01T${configDia.hora_fin_permitida}`);

        // Convertir a minutos desde medianoche para facilitar el cálculo
        const startMinMinutes = startMin.getHours() * 60 + startMin.getMinutes();
        const endMaxMinutes = endMax.getHours() * 60 + endMax.getMinutes();

        const minDurationMinutes = configDia.min_duracion_clase_min;
        const maxDurationMinutes = configDia.max_duracion_clase_min;

        // Calcular un rango de inicio posible para asegurar que quepa una clase
        const possibleStartMaxMinutes = endMaxMinutes - minDurationMinutes;

        if (possibleStartMaxMinutes < startMinMinutes) {
            showCustomMessage("El rango de horas configurado para el Sábado es demasiado pequeño para una clase.");
            return;
        }

        // Elegir una hora de inicio aleatoria dentro del rango posible
        let randomStartMinutes = Math.floor(Math.random() * (possibleStartMaxMinutes - startMinMinutes + 1)) + startMinMinutes;
        // Redondear a la media hora más cercana para horarios más "limpios"
        randomStartMinutes = Math.round(randomStartMinutes / 30) * 30;

        // Asegurarse de que no se salga del rango inicial permitido
        randomStartMinutes = Math.max(startMinMinutes, randomStartMinutes);
        randomStartMinutes = Math.min(possibleStartMaxMinutes, randomStartMinutes);


        // Elegir una duración aleatoria (1h o 2h)
        let randomDurationMinutes = Math.random() < 0.5 ? minDurationMinutes : maxDurationMinutes;

        // Asegurarse de que la duración no exceda el fin del rango
        if (randomStartMinutes + randomDurationMinutes > endMaxMinutes) {
            randomDurationMinutes = minDurationMinutes; // Si 2h no cabe, intentar 1h
            if (randomStartMinutes + randomDurationMinutes > endMaxMinutes) {
                randomStartMinutes = endMaxMinutes - minDurationMinutes; // Ajustar inicio si aún no cabe
            }
        }
        
        const randomEndMinutes = randomStartMinutes + randomDurationMinutes;

        // Convertir minutos de vuelta a formato HH:MM
        const formatTime = (totalMinutes) => {
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        };

        inputHoraInicio.value = formatTime(randomStartMinutes);
        inputHoraFin.value = formatTime(randomEndMinutes);
    }


    /**
     * Valida el horario antes de enviarlo al backend.
     * Incluye validaciones de rango, duración, solapamiento y reglas mixtas.
     */
    async function validarHorario() {
        const idProfesor = selectProfesor.value;
        const dia = selectDia.value;
        const horaInicio = inputHoraInicio.value;
        const horaFin = inputHoraFin.value;
        const idHorario = formHorario.id_horario.value || ""; // Para edición
        const idPeriodo = document.getElementById('id_periodo').value;

        if (!idProfesor || !dia || !horaInicio || !horaFin || !idPeriodo) {
            showCustomMessage("Por favor, complete todos los campos requeridos.");
            return false;
        }

        const inicio = new Date(`1970-01-01T${horaInicio}:00`);
        const fin = new Date(`1970-01-01T${horaFin}:00`);

        if (fin <= inicio) {
            showCustomMessage("La hora de fin debe ser posterior a la hora de inicio.");
            return false;
        }

        const configDia = getConfigForDay(dia);
        if (!configDia.hora_inicio_permitida || !configDia.hora_fin_permitida) {
            showCustomMessage(`No hay configuración de horario para el día ${dia}. Por favor, configure en 'Configuración'.`);
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
                dia: dia,
                hora_inicio: horaInicio,
                hora_fin: horaFin,
                id_horario: idHorario, // Para excluir el horario actual en edición
                id_periodo: idPeriodo
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

            // Validaciones adicionales de horas totales y tipo mixto
            const horariosExistentes = data.horarios_dia ?? [];
            let horasUsadasProfesor = 0;
            let count1h = 0; // Clases de 1 hora
            let count2h = 0; // Clases de 2 horas

            horariosExistentes.forEach(h => {
                const hi = new Date(`1970-01-01T${h.hora_inicio}:00`);
                const hf = new Date(`1970-01-01T${h.hora_fin}:00`);
                const d = (hf - hi) / 1000 / 60; // Duración en minutos
                horasUsadasProfesor += d;
                if (Math.abs(d - 60) < 1) count1h++; // Aproximadamente 1 hora
                else if (Math.abs(d - 120) < 1) count2h++; // Aproximadamente 2 horas
            });

            // Sumar el nuevo horario propuesto
            const duracionNuevaClase = duracionMinutos;
            const totalHorasProfesor = horasUsadasProfesor + duracionNuevaClase;

            if (totalHorasProfesor > (configDia.max_horas_dia_profesor * 60)) { // Convertir max_horas_dia a minutos
                showCustomMessage(`El profesor no puede exceder las ${configDia.max_horas_dia_profesor} horas por día. Total: ${totalHorasProfesor / 60} horas.`);
                return false;
            }

            // Actualizar conteos para la regla mixta con la nueva clase
            if (Math.abs(duracionNuevaClase - 60) < 1) count1h++;
            else if (Math.abs(duracionNuevaClase - 120) < 1) count2h++;

            // Aplicar la regla de combinación mixta si está activada y se alcanza el máximo de horas
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

    // Cargar la configuración de horarios al iniciar la página
    document.addEventListener('DOMContentLoaded', cargarConfiguracionHorarios);
</script>
<?php include_once('footer.php'); ?>
