<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sistema_horarios";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Configurar codificación de caracteres
$conn->set_charset("utf8");
?>