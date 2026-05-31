<?php
/**
 * Aromaris – Generador de contraseñas hasheadas
 * Úsalo una vez para crear el hash, luego borra este archivo.
 *
 * Accede a: http://localhost/página-web/generar_hash.php
 */

require_once 'config.php';

$password  = 'aromaris2024';   // ← Cambia esto por la contraseña deseada
$nombre    = 'Administrador';
$email     = 'admin@aromaris.es';

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<pre>Hash generado: $hash</pre>";

// Insertar en la BD directamente
try {
    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO Usuarios (nombre, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $email, $hash]);
    echo "<p style='color:green'>✔ Usuario '$email' creado (o ya existía) en la base de datos.</p>";
    echo "<p><a href='index.php'>Ir al inicio de sesión</a> · <strong>Email:</strong> $email · <strong>Password:</strong> $password</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><p style='color:orange'><strong>⚠ Elimina este archivo después de usarlo.</strong></p>";
?>
