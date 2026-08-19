<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['erro' => 'Acesso não autorizado.']);
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];

$input = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['erro' => 'Requisição inválida (CSRF).']);
    exit;
}

$senhaConfirmacao = isset($input['senha']) ? trim($input['senha']) : '';

if (empty($senhaConfirmacao)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['erro' => 'A senha de confirmação é obrigatória.']);
    exit;
}

try {
    $stmtUser = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
    $stmtUser->execute(['id' => $idUsuario]);
    $usuario = $stmtUser->fetch();

    if (!$usuario || !password_verify($senhaConfirmacao, $usuario['senha'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['erro' => 'Senha incorreta. Operação cancelada.']);
        exit;
    }

    $stmtDelete = $pdo->prepare("DELETE FROM config_materiais WHERE id_usuario = :id");
    $stmtDelete->execute(['id' => $idUsuario]);

    registrarLog($pdo, 'reset_parametros', 'Todas as parametrizações foram apagadas.', 'warn', $idUsuario, $_SESSION['user']['login']);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Todas as parametrizações foram resetadas com sucesso!']);
    exit;

} catch (\PDOException $e) {
    error_log("Erro ao resetar parametros: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['erro' => 'Erro interno ao resetar parametrizações.']);
    exit;
}