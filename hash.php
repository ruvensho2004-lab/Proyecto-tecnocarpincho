<?php
$clave_plana = 'admin'; // Cambia aquí tu contraseña
$hash = password_hash($clave_plana, PASSWORD_DEFAULT);
echo "Hash generado: <br><code>$hash</code>";
?>
