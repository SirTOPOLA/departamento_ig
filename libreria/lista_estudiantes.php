<?php
// lista_estudiantes.php (para generar PDF)
require_once 'fpdf.php';
require_once '../config/database.php';

// Validar que se reciba id_grupo_asignatura
if (!isset($_GET['id_grupo_asignatura']) || !is_numeric($_GET['id_grupo_asignatura'])) {
    die("ID de grupo de asignatura no válido.");
}

$id_grupo_asignatura = (int) $_GET['id_grupo_asignatura'];

try {
    // Obtener datos del grupo de asignatura y su horario asociado (si existe uno principal)
    // Esto es un poco más complejo ahora. Un grupo de asignatura puede tener MÚLTIPLES horarios.
    // Para el PDF, podemos mostrar el primer horario encontrado o resumir.
    // Para simplificar, obtenemos los datos del grupo y una referencia a un horario asociado.
    // Si necesitas todos los horarios, la lógica de presentación del PDF debería cambiar.

    $stmt_grupo_horario = $pdo->prepare("
        SELECT
            ga.id AS id_grupo_asignatura,
            a.nombre_asignatura,
            u.nombre_completo AS nombre_profesor,
            ga.turno,
            ga.grupo,
            h.dia_semana,
            h.hora_inicio,
            h.hora_fin,
            au.nombre_aula
        FROM grupos_asignaturas ga
        INNER JOIN asignaturas a ON ga.id_asignatura = a.id
        INNER JOIN profesores p ON ga.id_profesor = p.id
        INNER JOIN usuarios u ON p.id_usuario = u.id
        LEFT JOIN horarios h ON ga.id = h.id_grupo_asignatura -- Usamos LEFT JOIN para obtener un horario si existe
        LEFT JOIN aulas au ON h.id_aula = au.id
        WHERE ga.id = :id_grupo_asignatura
        LIMIT 1 -- Limitamos a 1 si solo queremos un horario de referencia para el PDF
    ");
    $stmt_grupo_horario->execute([':id_grupo_asignatura' => $id_grupo_asignatura]);
    $info = $stmt_grupo_horario->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        die("No se encontraron datos para el grupo de asignatura especificado.");
    }

    // Obtener estudiantes inscritos en este grupo de asignatura
    $stmt_estudiantes = $pdo->prepare("
        SELECT u.nombre_completo, u.email, e.codigo_registro
        FROM inscripciones_estudiantes ie
        JOIN estudiantes e ON ie.id_estudiante = e.id
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE ie.id_grupo_asignatura = :id_grupo_asignatura
        ORDER BY u.nombre_completo
    ");
    $stmt_estudiantes->execute([':id_grupo_asignatura' => $id_grupo_asignatura]);
    $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

    // Obtener info institucional
    $stmt_dep = $pdo->query("SELECT * FROM departamento LIMIT 1");
    $dep = $stmt_dep->fetch(PDO::FETCH_ASSOC);

    // Instanciar PDF
    $pdf = new FPDF();
    $pdf->AddPage();

    // Insertar logo
    if (!empty($dep['logo_unge'])) {
        $pdf->Image('../' . $dep['logo_unge'], 10, 8, 25); // x, y, tamaño
    }

    // Encabezado institucional
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 6, utf8_decode(strtoupper($dep['universidad'] ?? 'UNIVERSIDAD NACIONAL DE GUINEA ECUATORIAL')), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 6, utf8_decode($dep['nombre'] ?? 'FACULTAD DE CIENCIAS ECONÓMICAS, GESTIÓN Y ADMINISTRACIÓN'), 0, 1, 'C');

    if (!empty($dep['direccion'])) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, utf8_decode('Dirección: ' . $dep['direccion']), 0, 1, 'C');
    }
    if (!empty($dep['telefono'])) {
        $pdf->Cell(0, 6, utf8_decode('Teléfono: ' . $dep['telefono']), 0, 1, 'C');
    }

    $pdf->Ln(5);

    // Título principal
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode('LISTADO DE ESTUDIANTES POR ASIGNATURA Y GRUPO'), 0, 1, 'C');
    $pdf->Ln(2);

    // Información académica
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 8, 'Asignatura:', 0, 0);
    $pdf->Cell(0, 8, utf8_decode($info['nombre_asignatura']), 0, 1);
    $pdf->Cell(40, 8, 'Profesor:', 0, 0);
    $pdf->Cell(0, 8, utf8_decode($info['nombre_profesor']), 0, 1);
    $pdf->Cell(40, 8, 'Grupo:', 0, 0); // Nuevo campo para el grupo
    $pdf->Cell(0, 8, utf8_decode($info['grupo']), 0, 1);
    $pdf->Cell(40, 8, 'Turno:', 0, 0);
    $pdf->Cell(0, 8, utf8_decode($info['turno']), 0, 1);

    // Información de horario y aula (pueden ser NULL si el grupo aún no tiene un horario definido)
    if (!empty($info['dia_semana']) && !empty($info['hora_inicio']) && !empty($info['hora_fin'])) {
        $pdf->Cell(40, 8, 'Horario (Ref.):', 0, 0);
        $pdf->Cell(0, 8, utf8_decode($info['dia_semana'] . ' (' . substr($info['hora_inicio'], 0, 5) . '-' . substr($info['hora_fin'], 0, 5) . ')'), 0, 1);
    } else {
        $pdf->Cell(40, 8, 'Horario (Ref.):', 0, 0);
        $pdf->Cell(0, 8, utf8_decode('No asignado'), 0, 1);
    }

    if (!empty($info['nombre_aula'])) {
        $pdf->Cell(40, 8, 'Aula (Ref.):', 0, 0);
        $pdf->Cell(0, 8, utf8_decode($info['nombre_aula']), 0, 1);
    } else {
        $pdf->Cell(40, 8, 'Aula (Ref.):', 0, 0);
        $pdf->Cell(0, 8, utf8_decode('No asignada'), 0, 1);
    }

    $pdf->Ln(5);

    // Tabla estudiantes
    if (empty($estudiantes)) {
        $pdf->SetFont('Arial', 'I', 11);
        $pdf->Cell(0, 10, utf8_decode('No hay estudiantes inscritos en este grupo de asignatura.'), 0, 1, 'C');
    } else {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(10, 8, '#', 1, 0, 'C', true);
        $pdf->Cell(75, 8, utf8_decode('Nombre Completo'), 1, 0, 'L', true);
        $pdf->Cell(60, 8, utf8_decode('Email'), 1, 0, 'L', true);
        $pdf->Cell(40, 8, utf8_decode('Código Registro'), 1, 1, 'L', true);

        $pdf->SetFont('Arial', '', 10);
        $i = 1;
        foreach ($estudiantes as $est) {
            $pdf->Cell(10, 8, $i++, 1);
            $pdf->Cell(75, 8, utf8_decode($est['nombre_completo']), 1);
            $pdf->Cell(60, 8, utf8_decode($est['email'] ?? 'N/A'), 1);
            $pdf->Cell(40, 8, utf8_decode($est['codigo_registro'] ?? 'N/A'), 1, 1);
        }
    }

    $pdf->Output('I', 'listado_estudiantes_grupo_' . $id_grupo_asignatura . '.pdf'); // Nombre de archivo más específico

} catch (Exception $e) {
    error_log("Error PDF: " . $e->getMessage());
    die("Error al generar PDF: " . $e->getMessage()); // Mostrar el mensaje de error para depuración
}