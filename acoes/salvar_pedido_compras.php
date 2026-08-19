<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) { http_response_code(403); exit(json_encode(['erro' => 'Não autorizado'])); }

header('Content-Type: application/json');

$idUsuario = (int) $_SESSION['user']['id'];
$input = json_decode(file_get_contents('php://input'), true);

$acao = $input['acao'] ?? '';

try {
    switch ($acao) {

        case 'carregar':
            $pedido = $pdo->prepare("SELECT * FROM pedidos_compra WHERE id_usuario = ? AND status = 'ativo' LIMIT 1");
            $pedido->execute([$idUsuario]);
            $pedidoRow = $pedido->fetch();

            if (!$pedidoRow) {
                echo json_encode(['pedido' => null, 'itens' => []]);
                exit;
            }

            $itens = $pdo->prepare("SELECT * FROM pedidos_compra_itens WHERE id_pedido = ?");
            $itens->execute([$pedidoRow['id']]);

            echo json_encode(['pedido' => $pedidoRow, 'itens' => $itens->fetchAll()]);
            break;

        case 'salvar':
            $itens       = $input['itens'] ?? [];
            $numeroFluig = trim($input['numero_fluig'] ?? '');

            if (empty($itens)) {
                echo json_encode(['erro' => 'Nenhum item para salvar']);
                exit;
            }

            $pdo->beginTransaction();

            $pedidoStmt = $pdo->prepare("SELECT id FROM pedidos_compra WHERE id_usuario = ? AND status = 'ativo' LIMIT 1");
            $pedidoStmt->execute([$idUsuario]);
            $pedidoExistente = $pedidoStmt->fetch();

            if ($pedidoExistente) {
                $idPedido = $pedidoExistente['id'];
                $pdo->prepare("UPDATE pedidos_compra SET numero_fluig = ? WHERE id = ?")
                    ->execute([$numeroFluig ?: null, $idPedido]);
                $pdo->prepare("DELETE FROM pedidos_compra_itens WHERE id_pedido = ?")->execute([$idPedido]);
            } else {
                $pdo->prepare("INSERT INTO pedidos_compra (id_usuario, numero_fluig) VALUES (?, ?)")
                    ->execute([$idUsuario, $numeroFluig ?: null]);
                $idPedido = (int) $pdo->lastInsertId();
            }

            $insertItem = $pdo->prepare("
                INSERT INTO pedidos_compra_itens
                    (id_pedido, id_material, descricao, protheus, saldo_atual, consumo_mensal, sugestao_compra, quantidade_solicitada)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($itens as $item) {
                $qtd = (int) ($item['quantidade_solicitada'] ?? 0);
                if ($qtd <= 0) continue;
                $insertItem->execute([
                    $idPedido,
                    $item['id_material'],
                    $item['descricao'],
                    $item['protheus'] ?? null,
                    (int) $item['saldo_atual'],
                    (int) $item['consumo_mensal'],
                    (int) $item['sugestao_compra'],
                    $qtd
                ]);
            }

            $pdo->commit();
            echo json_encode(['sucesso' => true, 'id_pedido' => $idPedido]);
            break;

        case 'limpar':
            $pdo->prepare("DELETE FROM pedidos_compra WHERE id_usuario = ? AND status = 'ativo'")
                ->execute([$idUsuario]);
            echo json_encode(['sucesso' => true]);
            break;

        default:
            echo json_encode(['erro' => 'Ação inválida']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Erro em salvar_pedido_compras: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno ao salvar pedido de compras.']);
}