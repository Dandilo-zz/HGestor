<?php
require_once '../config/conexao.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = 2");
$stmt->execute();
$user = $stmt->fetch();
if ($user) {
    $_SESSION['user'] = $user;
    echo "LOGGED_IN";
} else {
    echo "USER_NOT_FOUND";
}
