<?php
$host = 'localhost';
$db = 'sistema_escolar';
$user = 'root';
$pass = '123456';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=sistema_escolar;charset=utf8", "root", "123456");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>