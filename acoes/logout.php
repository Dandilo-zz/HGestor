<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/conexao.php';
if (isset($_SESSION['user'])) {
    registrarLog($pdo, 'logout', 'Sessão encerrada.', 'info', (int)$_SESSION['user']['id'], $_SESSION['user']['login']);
}
session_destroy();
header("Location: ../modulos/login");
exit;