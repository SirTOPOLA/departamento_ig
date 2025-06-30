<?
// Dentro de professors.php, después de las inclusiones, antes de la lógica principal
if (isset($_GET['action']) && $_GET['action'] === 'get_professors_data_only') {
    header('Content-Type: application/json');
    try {
        $stmt_profesores_data = $pdo->query("
            SELECT 
                u.id, 
                (SELECT COUNT(*) FROM cvs_profesores WHERE id_profesor = u.id) AS total_cvs
            FROM usuarios u
            JOIN roles r ON u.id_rol = r.id
            WHERE r.nombre_rol = 'Profesor'
        ");
        echo json_encode(['success' => true, 'profesores' => $stmt_profesores_data->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener datos de profesores: ' . $e->getMessage()]);
    }
    exit(); // Terminar el script PHP después de enviar la respuesta JSON
}