<?php
require_once '../config/conexao.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.']); exit;
}

$idUsuario = (int) $_SESSION['user']['id'];
$input = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Requisição inválida (CSRF).']); exit;
}

$senha = isset($input['senha']) ? trim($input['senha']) : '';
if ($senha === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Senha de confirmação é obrigatória.']); exit;
}

try {
    $stmtUser = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
    $stmtUser->execute(['id' => $idUsuario]);
    $usuario = $stmtUser->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Senha incorreta.']); exit;
    }

    $stmtDelete = $pdo->prepare("DELETE FROM endereco_materiais WHERE id_usuario = :uid");
    $stmtDelete->execute(['uid' => $idUsuario]);
    $removidos = $stmtDelete->rowCount();

    echo json_encode(['sucesso' => true, 'mensagem' => "$removidos endereços removidos."]); exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno ao resetar endereços.']); exit;
}
