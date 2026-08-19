<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexao.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.']);
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];
$dados = json_decode(file_get_contents('php://input'), true);

$csrfToken = $dados['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Requisição inválida (CSRF).']);
    exit;
}

$senha = $dados['senha'] ?? '';

if (empty($senha)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Senha não informada.']);
    exit;
}

$stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
$stmt->execute(['id' => $idUsuario]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Senha incorreta.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM tasy_estoque WHERE id_usuario = :id");
    $stmt->execute(['id' => $idUsuario]);
    registrarLog($pdo, 'reset_estoque', 'Snapshot de estoque removido.', 'warn', $idUsuario, $_SESSION['user']['login']);
    echo json_encode(['mensagem' => 'Snapshot de estoque removido com sucesso.']);
} catch (\Exception $e) {
    error_log("Erro ao resetar estoque: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno ao resetar o estoque.']);
}