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

$acao = $dados['acao'] ?? '';

$senhaAtual = $dados['senha_atual'] ?? '';
if (empty($senhaAtual)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Senha atual não informada.']);
    exit;
}

$stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
$stmt->execute(['id' => $idUsuario]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senhaAtual, $usuario['senha'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Senha atual incorreta.']);
    exit;
}

if ($acao === 'trocar_senha') {
    $novaSenha = $dados['nova_senha'] ?? '';
    if (strlen($novaSenha) < 4) {
        http_response_code(400);
        echo json_encode(['erro' => 'A nova senha deve ter ao menos 4 caracteres.']);
        exit;
    }
    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
    try {
        $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id")
            ->execute(['senha' => $hash, 'id' => $idUsuario]);
        echo json_encode(['mensagem' => 'Senha atualizada com sucesso.']);
        exit;
    } catch (\Exception $e) {
        error_log("Erro ao atualizar senha: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno ao atualizar senha.']);
        exit;
    }
}

if ($acao === 'trocar_estoque') {
    $novoEstoque = trim($dados['estoque_nome'] ?? '');
    if (empty($novoEstoque)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Selecione um estoque válido.']);
        exit;
    }
    try {
        $stmtE = $pdo->prepare("SELECT id FROM estoque_nomes WHERE nome_estoque = :nome");
        $stmtE->execute(['nome' => $novoEstoque]);
        if (!$stmtE->fetch()) {
            http_response_code(400);
            echo json_encode(['erro' => 'Estoque inválido.']);
            exit;
        }
        $pdo->prepare("UPDATE usuarios SET estoque_nome = :estoque WHERE id = :id")
            ->execute(['estoque' => $novoEstoque, 'id' => $idUsuario]);

        $_SESSION['user']['estoque_nome'] = $novoEstoque;
        echo json_encode(['mensagem' => 'Estoque atualizado. Recarregando...']);
        exit;
    } catch (\Exception $e) {
        error_log("Erro ao trocar estoque: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno ao trocar estoque.']);
        exit;
    }
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.']);