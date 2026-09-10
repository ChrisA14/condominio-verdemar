<?php
$servidor = "localhost";
$usuario = "root";
$pass = "";
$DBname = "Proyecto";

$connect = new mysqli($servidor, $usuario, $pass, $DBname);

if ($connect->connect_error) {
    die("Error de conexión: " . $connect->connect_error);
}

$connect->set_charset("utf8mb4");
?>