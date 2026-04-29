<?php
// db.php
$host = 'localhost';
$db   = 'barbearia_db';
$user = 'root';           // ← altere se necessário
$pass = '';               // ← altere se necessário

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}