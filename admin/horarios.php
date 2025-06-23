<?php include_once('header.php'); ?>
<?php
require_once '../includes/conexion.php';

// Obtener todos los horarios con joins
$stmt = $pdo->query("SELECT h.*, 
                            a.nombre AS asignatura, 
                            u.nombre AS profesor,
                            u.apellido AS apellido,
                            au.nombre AS aula
                     FROM horarios h
                     JOIN asignaturas a ON h.id_asignatura = a.id_asignatura
                     JOIN profesores p ON h.id_profesor = p.id_profesor
                     JOIN usuarios u ON p.id_profesor = u.id_usuario
                     JOIN aulas au ON h.aula_id = au.id_aula
                     ORDER BY FIELD(dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), hora_inicio");

$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content" id="content" tabindex="-1">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3><i class="bi bi-calendar-week"></i> Gestión de Horarios</h3>
      <button class="btn btn-success" onclick="abrirModalHorario()">
        <i class="bi bi-plus-circle"></i> Nuevo Horario
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead class="table-info">
          <tr>
            <th>#</th>
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
              <td><?= htmlspecialchars($h['asignatura']) ?></td>
              <td><?= htmlspecialchars($h['profesor'] . ' ' . $h['apellido']) ?></td>
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
              <td colspan="7" class="text-center">No hay horarios registrados</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalHorario" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formHorario">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Nuevo / Editar Horario</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_horario" id="id_horario">

        <div class="mb-2">
          <label class="form-label">Profesor</label>
          <select name="id_profesor" id="id_profesor" class="form-select" required>
            <?php
            $profs = $pdo->query("SELECT p.id_profesor, u.nombre, u.apellido 
                                    FROM profesores p JOIN usuarios u ON p.id_profesor = u.id_usuario")->fetchAll();
            foreach ($profs as $p)
              echo "<option value='{$p['id_profesor']}'>" . htmlspecialchars($p['nombre'] . ' ' . $p['apellido']) . "</option>";
            ?>
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
            <?php
            $aulas = $pdo->query("SELECT id_aula, nombre FROM aulas")->fetchAll();
            foreach ($aulas as $a)
              echo "<option value='{$a['id_aula']}'>" . htmlspecialchars($a['nombre']) . "</option>";
            ?>
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
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const modalHorario = new bootstrap.Modal(document.getElementById('modalHorario'));
  const formHorario = document.getElementById('formHorario');

  function abrirModalHorario() {
    formHorario.reset();
    formHorario.id_horario.value = '';
    modalHorario.show();
  }

  function editarHorario(id) {
    fetch(`../api/obtener_horario.php?id=${id}`)
      .then(res => res.json())
      .then(d => {
        for (let campo in d) {
          if (formHorario.elements[campo]) {
            formHorario.elements[campo].value = d[campo];
          }
        }
        modalHorario.show();
      });
  }

  function eliminarHorario(id) {
    if (!confirm("¿Eliminar este horario?")) return;
    fetch(`../api/eliminar_horario.php?id=${id}`)
      .then(res => res.json())
      .then(r => {
        if (r.status) location.reload();
        else alert("Error: " + r.message);
      });
  }

  formHorario.addEventListener('submit', async e => {
    e.preventDefault();
    const valido = await validarHorarioMixto();
    if (!valido) return;
    const datos = new FormData(formHorario);
    fetch('../api/guardar_horario.php', {
      method: 'POST',
      body: datos
    })
      .then(res => res.json())
      .then(r => {
        if (r.status) {
          alert(r.message);
          location.reload();
        } else {
          alert("Error: " + r.message);
        }
      });
  });

  document.getElementById('id_profesor').addEventListener('change', e => {
    const idProfesor = e.target.value;
    const selectAsignatura = document.getElementById('id_asignatura');
    selectAsignatura.innerHTML = '<option value="">Cargando...</option>';
    selectAsignatura.disabled = true; // deshabilita mientras carga

    fetch(`../api/obtener_asignaturas_profesor.php?id_profesor=${idProfesor}`)
      .then(res => res.json())
      .then(data => {
        selectAsignatura.innerHTML = '';

        if (data.length === 0) {
          selectAsignatura.innerHTML = '<option value="">Sin asignaturas disponibles</option>';
        } else {
          data.forEach(asig => {
            const opt = document.createElement('option');
            opt.value = asig.id_asignatura;
            opt.textContent = asig.nombre + (asig.ya_asignada ? ' ⚠️ Ya tiene horario' : '');
            if (asig.ya_asignada) {
              opt.disabled = true;
              opt.classList.add('text-muted');
            }
            selectAsignatura.appendChild(opt);
          });
        }

        selectAsignatura.disabled = false; // habilita al terminar
      });
  });

  async function validarHorarioMixto() {
  const idProfesor = formHorario.id_profesor.value;
  const dia = formHorario.dia.value;
  const horaInicio = formHorario.hora_inicio.value;
  const horaFin = formHorario.hora_fin.value;
  const idHorario = formHorario.id_horario.value || ""; // por si es edición

  if (!horaInicio || !horaFin) return false;

const inicio = new Date(`1970-01-01T${horaInicio}:00`);
const fin = new Date(`1970-01-01T${horaFin}:00`);

// ✅ Nuevo rango permitido: 12:00 a 22:00
const minHora = new Date("1970-01-01T12:00:00");
const maxHora = new Date("1970-01-01T22:00:00");

if (inicio < minHora || fin > maxHora) {
  alert("Las horas deben estar entre las 12:00 y 22:00.");
  return false;
}


  const duracion = (fin - inicio) / 1000 / 60 / 60;
  if (duracion < 1 || duracion > 2) {
    alert("La duración debe ser entre 1 y 2 horas.");
    return false;
  }

  if (fin <= inicio) {
    alert("La hora fin debe ser mayor que la hora inicio.");
    return false;
  }

  try {
    const query = new URLSearchParams({
      id_profesor: idProfesor,
      dia: dia,
      hora_inicio: horaInicio,
      hora_fin: horaFin,
      id_horario: idHorario
    });

    const response = await fetch(`../api/consultar_horarios_profesor.php?${query}`);
    const data = await response.json();

    if (data.solapamiento) {
      alert("El horario seleccionado se solapa con otro ya registrado.");
      return false;
    }

    // Validaciones adicionales de horas totales y tipo mixto
    const horarios = data.horarios ?? [];
    let horasUsadas = 0;
    let count1h = 0;
    let count2h = 0;

    horarios.forEach(h => {
      const hi = new Date(`1970-01-01T${h.hora_inicio}:00`);
      const hf = new Date(`1970-01-01T${h.hora_fin}:00`);
      const d = (hf - hi) / 1000 / 60 / 60;
      horasUsadas += d;
      if (Math.abs(d - 1) < 0.01) count1h++;
      else if (Math.abs(d - 2) < 0.01) count2h++;
    });

    // Sumar el nuevo horario
    const totalHoras = horasUsadas + duracion;
    if (totalHoras > 6) {
      alert("El total de horas por día no puede exceder las 6 horas.");
      return false;
    }

    if (Math.abs(duracion - 1) < 0.01) count1h++;
    else if (Math.abs(duracion - 2) < 0.01) count2h++;

    const totalAsignaturas = count1h + count2h;
    if (totalAsignaturas >= 6 && (count1h === 6 || count2h === 6)) {
      alert("Debe haber una combinación mixta de asignaturas de 1h y 2h.");
      return false;
    }

    return true;

  } catch (error) {
    console.error(error);
    alert("Error validando horarios: " + error.message);
    return false;
  }
}

</script>
<?php include_once('footer.php'); ?>