<?php
$clave_plana = 'prueba1234'; // Cambia aquí tu contraseña
$hash = password_hash($clave_plana, PASSWORD_DEFAULT);
echo "Hash generado: <br><code>$hash</code>";
?>
