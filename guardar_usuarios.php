<?php
session_start();
if ($_SESSION['rol'] !== 'admin') { exit("❌ No autorizado"); }

$usuario = $_POST['usuario'];
$email = $_POST['email'];
$rol = $_POST['rol'];
$passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);

$sql = "INSERT INTO usuarios(usuario, email, password, rol) VALUES(?,?,?,?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $usuario, $email, $passwordHash, $rol);
$stmt->execute();

echo "✔ Usuario registrado";
