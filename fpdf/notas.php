<?php
require('fpdf.php'); // Asegúrate de que la ruta sea correcta
include("../conexion.php");


// Si se presiona el botón
if (isset($_POST['descargar'])) {
    // Conexión a la base de datos
   

    if ($conexion->connect_error) {
        die('Conexión fallida: ' . $conn->connect_error);
    }

    // Consulta de notas del estudiante


    $cod_matr =  trim($_POST['codigo']) ; 

   


    $sql = "SELECT * FROM notas WHERE cod_matr='$cod_matr'";
    $result = $conexion->query($sql);

    $row=mysqli_fetch_assoc($result);

  


    // Crear PDF
    $pdf = new FPDF();
    $pdf->AddPage();

    // Agregar logos
    $pdf->Image('../imagenes_tfg/GE.png', 10, 10, 20); // Ajusta la ruta y tamaño
    $pdf->Image('../imagenes_tfg/unge 1.png', 170, 10, 20); // Ajusta la ruta y tamaño

    $pdf->Ln(35); // Espacio después de los logos

    // Título del boletín
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Boletin de Notas del Estudiante', 0, 1, 'C');

    $pdf->Ln(5);

    // Tabla de notas
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(30, 10, 'C.Matrucula', 1);
    $pdf->Cell(20, 10, 'C.Notas', 1);
    $pdf->Cell(30, 10, 'C.Asignatura', 1);
      $pdf->Cell(20, 10, 'Curso', 1);
    $pdf->Cell(65, 10, 'Nombre ', 1);
    $pdf->Cell(15, 10, 'Nota ', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 11);

    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(30, 10, $row['cod_matr'], 1);
        $pdf->Cell(20, 10, $row['cod_notas'], 1);
        $pdf->Cell(30, 10, $row['cod_asig'], 1);
        $pdf->Cell(20, 10, $row['curso_asig'], 1);
          $pdf->Cell(65, 10, $row['nombre'], 1);
          $pdf->Cell(15, 10, $row['nota'], 1);
        $pdf->Ln();
    }

    $conexion->close();

    // Descargar el archivo
    $pdf->Output('D', 'boletin_notas.pdf'); // Forzar descarga
}
?>
