<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    header("Location: modulos/estoque");
    exit;
} else {
    header("Location: modulos/login");
    exit;
}