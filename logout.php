<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

session_start();
session_destroy();
header('Location: login.php');
exit();
?>