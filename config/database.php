<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost'); // Usualmente 'localhost'
define('DB_NAME', 'departamento'); // ¡Cambia esto por el nombre real de tu BD!
define('DB_USER', 'root');       // ¡Cambia esto por tu usuario de BD!
define('DB_PASS', '');      // ¡Cambia esto por tu contraseña de BD!
define('DB_CHARSET', 'utf8mb4');

// Opciones PDO para una conexión robusta
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en caso de errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva la emulación de prepared statements (más seguro y rápido)
];

// Intentar establecer la conexión
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // En caso de error, muestra un mensaje y termina la ejecución
    // En un entorno de producción, NO muestres el error directamente al usuario.
    // En su lugar, registra el error y muestra un mensaje genérico.
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>