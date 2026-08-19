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

$acao = $input['acao'] ?? '';

if ($acao === 'adicionar') {
    $eixo = $input['eixo'] ?? '';
    $valor = trim($input['valor'] ?? '');

    if (!in_array($eixo, ['x', 'y', 'z'])) {
        http_response_code(400);
        echo json_encode(['erro' => 'Eixo inválido. Deve ser x, y ou z.']); exit;
    }

    if ($valor === '' || mb_strlen($valor) > 50) {
        http_response_code(400);
        echo json_encode(['erro' => 'Valor inválido. Deve ser preenchido e ter no máximo 50 caracteres.']); exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO endereco_params (id_usuario, eixo, valor) VALUES (:uid, :eixo, :valor)");
        $stmt->execute([
            'uid' => $idUsuario,
            'eixo' => $eixo,
            'valor' => $valor
        ]);
        $idNovo = (int) $pdo->lastInsertId();
        echo json_encode(['sucesso' => true, 'id' => $idNovo]); exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(['erro' => 'Este valor já está cadastrado para este eixo.']); exit;
        }
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno ao salvar.']); exit;
    }

} elseif ($acao === 'editar') {
    $id = (int) ($input['id'] ?? 0);
    $valor = trim($input['valor'] ?? '');

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID inválido.']); exit;
    }

    if ($valor === '' || mb_strlen($valor) > 50) {
        http_response_code(400);
        echo json_encode(['erro' => 'Valor inválido. Deve ser preenchido e ter no máximo 50 caracteres.']); exit;
    }

    $pdo->beginTransaction();
    try {
        $stmtGet = $pdo->prepare("SELECT eixo, valor FROM endereco_params WHERE id = :id AND id_usuario = :uid");
        $stmtGet->execute(['id' => $id, 'uid' => $idUsuario]);
        $paramAntigo = $stmtGet->fetch();
        
        if (!$paramAntigo) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['erro' => 'Parâmetro não encontrado.']); exit;
        }

        $eixo = $paramAntigo['eixo'];
        $valorAntigo = $paramAntigo['valor'];

        $stmtUpdate = $pdo->prepare("UPDATE endereco_params SET valor = :valor WHERE id = :id AND id_usuario = :uid");
        $stmtUpdate->execute(['valor' => $valor, 'id' => $id, 'uid' => $idUsuario]);

        if ($valorAntigo !== $valor) {
            if ($eixo === 'x') {
                $stmtMatUpdate = $pdo->prepare("UPDATE endereco_materiais SET x = :valorNovo WHERE id_usuario = :uid AND x = :valorAntigo");
            } elseif ($eixo === 'y') {
                $stmtMatUpdate = $pdo->prepare("UPDATE endereco_materiais SET y = :valorNovo WHERE id_usuario = :uid AND y = :valorAntigo");
            } else {
                $stmtMatUpdate = $pdo->prepare("UPDATE endereco_materiais SET z = :valorNovo WHERE id_usuario = :uid AND z = :valorAntigo");
            }
            $stmtMatUpdate->execute(['valorNovo' => $valor, 'uid' => $idUsuario, 'valorAntigo' => $valorAntigo]);
        }

        $pdo->commit();
        echo json_encode(['sucesso' => true]); exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(['erro' => 'Este valor já está cadastrado para este eixo.']); exit;
        }
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno ao atualizar.']); exit;
    }

} elseif ($acao === 'deletar') {
    $id = (int) ($input['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID inválido.']); exit;
    }

    try {
        $stmtGet = $pdo->prepare("SELECT valor FROM endereco_params WHERE id = :id AND id_usuario = :uid");
        $stmtGet->execute(['id' => $id, 'uid' => $idUsuario]);
        $param = $stmtGet->fetch();

        if (!$param) {
            http_response_code(404);
            echo json_encode(['erro' => 'Parâmetro não encontrado.']); exit;
        }

        $val = $param['valor'];

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM endereco_materiais WHERE id_usuario = :uid AND (x = :val_x OR y = :val_y OR z = :val_z)");
        $stmtCheck->execute(['uid' => $idUsuario, 'val_x' => $val, 'val_y' => $val, 'val_z' => $val]);
        $emUso = (int) $stmtCheck->fetchColumn();

        if ($emUso > 0) {
            http_response_code(400);
            echo json_encode(['erro' => 'Não é possível deletar: este valor está associado a um ou mais materiais.']); exit;
        }

        $stmtDel = $pdo->prepare("DELETE FROM endereco_params WHERE id = :id AND id_usuario = :uid");
        $stmtDel->execute(['id' => $id, 'uid' => $idUsuario]);

        echo json_encode(['sucesso' => true]); exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno ao deletar.']); exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['erro' => 'Ação inválida.']); exit;
}
