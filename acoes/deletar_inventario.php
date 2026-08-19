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

$stmtU = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
$stmtU->execute(['id' => $idUsuario]);
$usuario = $stmtU->fetch();

if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Senha incorreta.']); exit;
}

$stmtChk = $pdo->prepare("SELECT id FROM pre_inventario WHERE id = :id AND id_usuario = :uid AND status != 'ativo'");
$stmtChk->execute(['id' => $idInv, 'uid' => $idUsuario]);
if (!$stmtChk->fetch()) {
    echo json_encode(['erro' => 'Inventário ativo ou não encontrado.']); exit;
}

try {
    $pdo->beginTransaction();

    // Como as chaves estrangeiras no banco podem não estar com ON DELETE CASCADE,
    $stmtItens = $pdo->prepare("SELECT id FROM pre_inventario_itens WHERE id_inventario = :inv");
    $stmtItens->execute(['inv' => $idInv]);
    $itemIds = $stmtItens->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($itemIds)) {
        $ph = implode(',', array_fill(0, count($itemIds), '?'));
        
        $pdo->prepare("DELETE FROM pre_inventario_log WHERE id_item IN ($ph)")->execute($itemIds);
        
        $pdo->prepare("DELETE FROM pre_inventario_barcodes WHERE id_item IN ($ph)")->execute($itemIds);
        
        $pdo->prepare("DELETE FROM pre_inventario_itens WHERE id_inventario = ?")->execute([$idInv]);
    }

    $pdo->prepare("DELETE FROM pre_inventario WHERE id = :id")->execute(['id' => $idInv]);

    $pdo->commit();
    registrarLog($pdo, 'inventario_deletado', 'Inventário #' . $idInv . ' deletado permanentemente.', 'warn', $idUsuario, $_SESSION['user']['login']);
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Erro ao deletar inventário: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno ao deletar.']);
}