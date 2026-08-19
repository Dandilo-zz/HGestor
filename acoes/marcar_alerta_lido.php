<?php
require_once '../config/conexao.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id']) || !isset($_POST['id_alerta'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Requisição inválida.']);
    exit;
}

$idUsuario = (int)$_SESSION['user']['id'];
$idAlerta = (int)$_POST['id_alerta'];

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO alerta_leituras (id_alerta, id_usuario) VALUES (:id_alerta, :id_usuario)");
    $stmt->execute([
        'id_alerta' => $idAlerta,
        'id_usuario' => $idUsuario
    ]);

    echo json_encode(['sucesso' => true]);
} catch (Exception $e) {
    error_log("Erro ao marcar alerta como lido: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao processar.']);
}
exit;