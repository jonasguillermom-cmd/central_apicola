<?php
/* Conexión a la base de datos (WampServer: usuario root sin contraseña) */
$host = 'localhost';
$base = 'marketplace_miel';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$base;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('No se pudo conectar a la base de datos. Revisa que WampServer esté en verde.');
}

/* Atajo para escapar texto antes de mostrarlo en HTML (evita XSS) */
function h($texto): string {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/* Formato de dinero: 1234.5 -> $1,234.50 */
function dinero($n): string {
    return '$' . number_format((float)$n, 2);
}
