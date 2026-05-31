-- ============================================================
--  Aromaris – Script de configuración base de datos MiTienda
-- ============================================================

CREATE DATABASE IF NOT EXISTS MiTienda;
USE MiTienda;

-- 1. Tabla Usuarios
CREATE TABLE IF NOT EXISTS Usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabla Productos
CREATE TABLE IF NOT EXISTS Productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10, 2) NOT NULL,
    stock INT DEFAULT 0
);

-- 3. Tabla Pedidos
CREATE TABLE IF NOT EXISTS Pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE
);

-- 4. Tabla Detalles_Pedidos
CREATE TABLE IF NOT EXISTS Detalles_Pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT,
    producto_id INT,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES Pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES Productos(id)
);

-- ============================================================
--  Datos de prueba
-- ============================================================

-- Usuario admin (password: aromaris2024)
-- Hash generado con password_hash('aromaris2024', PASSWORD_DEFAULT)
INSERT IGNORE INTO Usuarios (nombre, email, password_hash) VALUES
('Administrador', 'admin@aromaris.org',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- NOTA: El hash de arriba es para el password 'password' (de Laravel).
-- Para generar el tuyo, ejecuta este PHP: echo password_hash('aromaris2024', PASSWORD_DEFAULT);
-- Luego reemplaza el hash en esta línea.

-- Productos de muestra
INSERT IGNORE INTO Productos (nombre, descripcion, precio, stock) VALUES
('Jabón Lavanda', 'Jabón artesanal de lavanda con aceite de argán. Hidratante y calmante.', 8.50, 100),
('Jabón Rosa Mosqueta', 'Con extracto de rosa mosqueta y vitamina E. Ideal para pieles maduras.', 9.95, 75),
('Jabón Árbol de Té', 'Propiedades antisépticas naturales. Perfecto para pieles mixtas.', 7.50, 120),
('Jabón Avena y Miel', 'Suave y nutritivo. Especial para pieles sensibles y bebés.', 8.00, 90),
('Jabón Menta Refrescante', 'Efecto frío y refrescante. Ideal para el cuidado de pies.', 6.95, 60);

-- ============================================================
--  Verificación
-- ============================================================
SELECT 'Usuarios' AS tabla, COUNT(*) AS registros FROM Usuarios
UNION ALL SELECT 'Productos', COUNT(*) FROM Productos
UNION ALL SELECT 'Pedidos', COUNT(*) FROM Pedidos
UNION ALL SELECT 'Detalles_Pedidos', COUNT(*) FROM Detalles_Pedidos;
