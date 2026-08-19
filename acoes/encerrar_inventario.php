<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.']); exit;
}

$idUsuario = (int) $_SESSION['user']['id'];
$input     = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Requisição inválida (CSRF).']);
    exit;
}

$idInv     = (int)($input['id_inventario'] ?? 0);
$senha     = $input['senha'] ?? '';

if (!$idInv || empty($senha)) {
    echo json_encode(['erro' => 'Dados insuficientes.']); exit;
}

$stmtU = $pdo->prepare("SELECT senha FROM usuarios WHERE id=:id");
$stmtU->execute(['id'=>$idUsuario]);
$usuario = $stmtU->fetch();
if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Senha incorreta.']); exit;
}

try {
    $stmt = $pdo->prepare("UPDATE pre_inventario SET status='finalizado', atualizado_em=NOW() WHERE id=:id AND id_usuario=:uid");
    $stmt->execute(['id'=>$idInv,'uid'=>$idUsuario]);
    registrarLog($pdo, 'inventario_encerrado', 'Inventário #' . $idInv . ' encerrado.', 'warn', $idUsuario, $_SESSION['user']['login']);
    echo json_encode(['sucesso'=>true]);
} catch (\Exception $e) {
    error_log("Erro ao encerrar inventário: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno ao encerrar o inventário.']);
}