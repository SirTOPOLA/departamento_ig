<?php
// generate_students_pdf.php

require_once 'fpdf.php'; // Ajusta esta ruta si tu carpeta fpdf está en otro lugar
require_once '../config/database.php';
require_once '../includes/functions.php'; // Si necesitas funciones de autenticación aquí

// Asegura que el usuario esté logeado y tenga permisos si es necesario
// check_login_and_role('Profesor'); // Descomenta si quieres una verificación estricta aquí

if (isset($_GET['id_asignatura']) && is_numeric($_GET['id_asignatura'])) {
    $id_asignatura = $_GET['id_asignatura'];
    $nombre_asignatura = ''; // Para almacenar el nombre de la asignatura

    try {
        // Primero, obtener el nombre de la asignatura
        $stmt_asignatura = $pdo->prepare("SELECT nombre_asignatura FROM asignaturas WHERE id = :id_asignatura");
        $stmt_asignatura->execute([':id_asignatura' => $id_asignatura]);
        $asignatura_info = $stmt_asignatura->fetch(PDO::FETCH_ASSOC);
        if ($asignatura_info) {
            $nombre_asignatura = $asignatura_info['nombre_asignatura'];
        } else {
            die("Asignatura no encontrada.");
        }

        // Consulta para obtener los estudiantes inscritos en la asignatura
        $sql_students = "
           SELECT
        u.nombre_completo,
        u.email,
        e.codigo_registro
    FROM
        inscripciones_estudiantes ie
    JOIN
        estudiantes e ON ie.id_estudiante = e.id
    JOIN
        usuarios u ON e.id_usuario = u.id
    WHERE
        ie.id_horario = :id_horario  -- ¡Filtrar directamente por el ID del horario!
    ORDER BY
        u.nombre_completo;
        ";
        $stmt_students = $pdo->prepare($sql_students);
        $stmt_students->execute([':id_asignatura' => $id_asignatura]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        // Crear una nueva instancia de FPDF
        $pdf = new FPDF();
        $pdf->AddPage(); // Añadir una nueva página

        // Título del documento
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, utf8_decode('Listado de Estudiantes'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, utf8_decode('Asignatura: ' . $nombre_asignatura), 0, 1, 'C');
        $pdf->Ln(10); // Salto de línea

        if (empty($students)) {
            $pdf->SetFont('Arial', 'I', 12);
            $pdf->Cell(0, 10, utf8_decode('No hay estudiantes inscritos en esta asignatura.'), 0, 1, 'C');
        } else {
            // Cabecera de la tabla
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(230, 230, 230); // Color de fondo para la cabecera
            $pdf->Cell(90, 8, utf8_decode('Nombre Completo'), 1, 0, 'L', true);
            $pdf->Cell(60, 8, utf8_decode('Email'), 1, 0, 'L', true);
            $pdf->Cell(40, 8, utf8_decode('Código de Registro'), 1, 1, 'L', true); // 1 al final para salto de línea

            // Contenido de la tabla
            $pdf->SetFont('Arial', '', 10);
            foreach ($students as $student) {
                // Asegúrate de usar utf8_decode() para caracteres especiales en el contenido de la tabla
                $pdf->Cell(90, 8, utf8_decode($student['nombre_completo']), 1, 0, 'L');
                $pdf->Cell(60, 8, utf8_decode($student['email'] ?? 'N/A'), 1, 0, 'L');
                $pdf->Cell(40, 8, utf8_decode($student['codigo_registro'] ?? 'N/A'), 1, 1, 'L');
            }
        }

        // Generar el PDF
        $filename = 'listado_estudiantes_' . str_replace(' ', '_', $nombre_asignatura) . '.pdf';
        $pdf->Output('D', $filename); // 'D' fuerza la descarga, 'I' abre en el navegador

    } catch (PDOException $e) {
        error_log("Error al generar PDF de estudiantes: " . $e->getMessage());
        die("Error al generar el PDF. Por favor, inténtalo de nuevo más tarde.");
    } catch (Exception $e) {
        error_log("Error de FPDF: " . $e->getMessage());
        die("Error interno al generar el PDF.");
    }

} else {
    die("ID de asignatura no proporcionado o inválido.");
}