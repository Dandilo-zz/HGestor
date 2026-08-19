<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.']); exit;
}

$idInv  = (int)($_GET['id_inventario'] ?? 0);
$idItem = (int)($_GET['id_item'] ?? 0);

if (!$idInv && !$idItem) {
    echo json_encode(['erro' => 'Parâmetros insuficientes.']); exit;
}

try {
    if ($idItem > 0) {
        $sql = "SELECT l.*, u.login AS usuario_nome, i.ds_material 
                FROM pre_inventario_log l
                JOIN usuarios u ON u.id = l.id_usuario
                JOIN pre_inventario_itens i ON i.id = l.id_item
                WHERE l.id_item = :id_item
                ORDER BY l.criado_em DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);
    } else {
        $sql = "SELECT l.*, u.login AS usuario_nome, i.ds_material, i.cd_material
                FROM pre_inventario_log l
                JOIN usuarios u ON u.id = l.id_usuario
                JOIN pre_inventario_itens i ON i.id = l.id_item
                WHERE i.id_inventario = :id_inv
                ORDER BY l.criado_em DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_inv' => $idInv]);
    }

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['sucesso' => true, 'logs' => $logs]);

} catch (Exception $e) {
    error_log("Erro ao buscar logs: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno ao buscar logs.']);
}