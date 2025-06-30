<?php
// submit_grades.php
// --- INICIO DE DEPURACIÓN TEMPORAL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

require_once '../includes/functions.php';
check_login_and_role('Profesor'); // Solo profesores pueden enviar notas

require_once '../config/database.php';

// Esto es un endpoint de API, por lo que responde con JSON
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Solicitud no válida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_horario = filter_var($_POST['id_horario'] ?? null, FILTER_VALIDATE_INT);
    $id_semestre = filter_var($_POST['id_semestre'] ?? null, FILTER_VALIDATE_INT);
    $grades_data = $_POST['grades'] ?? []; // Array asociativo: [id_inscripcion => ['nota' => X, 'estado' => Y]]

    if (!$id_horario || !$id_semestre || empty($grades_data)) {
        $response['message'] = 'Datos incompletos para guardar notas.';
        echo json_encode($response);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $updated_count = 0;
        $inserted_count = 0;

        foreach ($grades_data as $id_inscripcion => $data) {
            $nota = filter_var($data['nota'] ?? null, FILTER_VALIDATE_FLOAT);
            // El campo `estado` en la tabla `notas` es un ENUM('APROBADO','REPROBADO','PENDIENTE')
            $estado = sanitize_input($data['estado'] ?? null);

            // Validar nota y estado
            if ($nota === false || $nota < 0 || $nota > 100) {
                // Si la nota no es válida, la establecemos a NULL y el estado a PENDIENTE
                $nota = null;
                $estado = 'PENDIENTE';
            }
            if (!in_array($estado, ['APROBADO', 'REPROBADO', 'PENDIENTE'])) {
                $estado = 'PENDIENTE'; // Valor por defecto si no es válido
            }

            // Verificar si ya existe una nota para esta inscripción en la tabla `notas`
            $stmt_check_note = $pdo->prepare("SELECT id, acta_final_confirmada FROM notas WHERE id_inscripcion = :id_inscripcion");
            $stmt_check_note->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
            $stmt_check_note->execute();
            $existing_note = $stmt_check_note->fetch(PDO::FETCH_ASSOC);

            if ($existing_note) {
                // Si el acta final ya está confirmada (`acta_final_confirmada` = 1), no permitir cambios
                if ($existing_note['acta_final_confirmada'] == 1) {
                    // Opcional: registrar que se intentó modificar una nota confirmada
                    continue; // Saltar esta inscripción
                }
                // Actualizar nota existente en la tabla `notas`
                $stmt_update_note = $pdo->prepare("UPDATE notas SET nota = :nota, estado = :estado, fecha_registro = NOW() WHERE id = :id_nota");
                $stmt_update_note->bindParam(':nota', $nota);
                $stmt_update_note->bindParam(':estado', $estado);
                $stmt_update_note->bindParam(':id_nota', $existing_note['id'], PDO::PARAM_INT);
                $stmt_update_note->execute();
                $updated_count++;
            } else {
                // Insertar nueva nota en la tabla `notas`
                $stmt_insert_note = $pdo->prepare("INSERT INTO notas (id_inscripcion, nota, estado, fecha_registro, acta_final_confirmada) VALUES (:id_inscripcion, :nota, :estado, NOW(), 0)");
                $stmt_insert_note->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
                $stmt_insert_note->bindParam(':nota', $nota);
                $stmt_insert_note->bindParam(':estado', $estado);
                $stmt_insert_note->execute();
                $inserted_count++;
            }
        }

        $pdo->commit();
        $response['success'] = true;
        $response['message'] = "Notas guardadas correctamente. Insertadas: $inserted_count, Actualizadas: $updated_count.";

    } catch (PDOException $e) {
        $pdo->rollBack();
        $response['message'] = 'Error de base de datos al guardar notas: ' . $e->getMessage();
    }
}

echo json_encode($response);
exit;
?>