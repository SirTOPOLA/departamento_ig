<?php
require '../includes/conexion.php';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("
  SELECT ar.id, a2.nombre 
  FROM asignatura_requisitos ar
  JOIN asignaturas a2 ON ar.requisito_id = a2.id_asignatura
  WHERE ar.asignatura_id = :id
");
$stmt->execute(['id' => $id]);
$requisitos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$requisitos) {
  echo "<div class='alert alert-warning'>No hay requisitos registrados para esta asignatura.</div>";
  exit;
}
?>

<table class="table table-bordered">
  <thead class="table-secondary">
    <tr>
      <th>Requisito</th>
      <th>Acción</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($requisitos as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['nombre']) ?></td>
      <td>
        <button class="btn btn-sm btn-danger" onclick="eliminarRequisito(<?= $r['id'] ?>)">
          <i class="bi bi-trash"></i> Eliminar
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

