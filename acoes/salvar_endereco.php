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

if ($acao === 'individual') {
    $idMaterial = trim($input['id_material'] ?? '');
    $eixo = $input['eixo'] ?? '';
    $valor = isset($input['valor']) ? (trim($input['valor']) === '' ? null : trim($input['valor'])) : null;

    if ($idMaterial === '') {
        http_response_code(400);
        echo json_encode(['erro' => 'Material não especificado.']); exit;
    }

    if (!in_array($eixo, ['x', 'y', 'z'])) {
        http_response_code(400);
        echo json_encode(['erro' => 'Eixo inválido. Deve ser x, y ou z.']); exit;
    }

    if ($valor !== null) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM endereco_params WHERE id_usuario = :uid AND eixo = :eixo AND valor = :valor");
        $stmtCheck->execute(['uid' => $idUsuario, 'eixo' => $eixo, 'valor' => $valor]);
        if ((int)$stmtCheck->fetchColumn() === 0) {
            http_response_code(400);
            echo json_encode(['erro' => 'Valor não cadastrado para este eixo.']); exit;
        }
    }

    if ($eixo === 'x') {
        $stmt = $pdo->prepare("INSERT INTO endereco_materiais (id_usuario, id_material, x) VALUES (:uid, :id_mat, :val) ON DUPLICATE KEY UPDATE x = :val_up");
    } elseif ($eixo === 'y') {
        $stmt = $pdo->prepare("INSERT INTO endereco_materiais (id_usuario, id_material, y) VALUES (:uid, :id_mat, :val) ON DUPLICATE KEY UPDATE y = :val_up");
    } else {
        $stmt = $pdo->prepare("INSERT INTO endereco_materiais (id_usuario, id_material, z) VALUES (:uid, :id_mat, :val) ON DUPLICATE KEY UPDATE z = :val_up");
    }

    try {
        $stmt->execute([
            'uid' => $idUsuario,
            'id_mat' => $idMaterial,
            'val' => $valor,
            'val_up' => $valor
        ]);
        echo json_encode(['sucesso' => true]); exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro interno ao salvar endereço.']); exit;
    }

} elseif ($acao === 'lote') {
    $itens = $input['materiais'] ?? $input['itens'] ?? [];
    $idsMateriais = $input['ids_materiais'] ?? [];

    $pdo->beginTransaction();
    try {
        if (!empty($itens) && is_array($itens)) {
            foreach ($itens as $item) {
                $idMat = trim($item['id_material'] ?? '');
                if ($idMat === '') continue;

                $x = isset($item['x']) ? (trim($item['x']) === '' ? null : trim($item['x'])) : null;
                $y = isset($item['y']) ? (trim($item['y']) === '' ? null : trim($item['y'])) : null;
                $z = isset($item['z']) ? (trim($item['z']) === '' ? null : trim($item['z'])) : null;

                foreach (['x' => $x, 'y' => $y, 'z' => $z] as $e => $v) {
                    if ($v !== null) {
                        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM endereco_params WHERE id_usuario = :uid AND eixo = :eixo AND valor = :valor");
                        $stmtCheck->execute(['uid' => $idUsuario, 'eixo' => $e, 'valor' => $v]);
                        if ((int)$stmtCheck->fetchColumn() === 0) {
                            throw new Exception("O valor '$v' não está cadastrado para o eixo $e.");
                        }
                    }
                }

                $stmt = $pdo->prepare("
                    INSERT INTO endereco_materiais (id_usuario, id_material, x, y, z)
                    VALUES (:uid, :id_mat, :x, :y, :z)
                    ON DUPLICATE KEY UPDATE x = :x_up, y = :y_up, z = :z_up
                ");
                $stmt->execute([
                    'uid' => $idUsuario,
                    'id_mat' => $idMat,
                    'x' => $x,
                    'y' => $y,
                    'z' => $z,
                    'x_up' => $x,
                    'y_up' => $y,
                    'z_up' => $z
                ]);
            }
        } elseif (!empty($idsMateriais) && is_array($idsMateriais)) {
            $valoresLote = [];
            $sets = [];
            $insertFields = ['id_usuario', 'id_material'];
            $insertPlaceholders = [':uid', ':id_mat'];
            
            foreach (['x', 'y', 'z'] as $eixo) {
                if (array_key_exists($eixo, $input) && $input[$eixo] !== '') {
                    $val = trim($input[$eixo]);
                    $val = ($val === '') ? null : $val;

                    if ($val !== null) {
                        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM endereco_params WHERE id_usuario = :uid AND eixo = :eixo AND valor = :valor");
                        $stmtCheck->execute(['uid' => $idUsuario, 'eixo' => $eixo, 'valor' => $val]);
                        if ((int)$stmtCheck->fetchColumn() === 0) {
                            throw new Exception("O valor '$val' não está cadastrado para o eixo $eixo.");
                        }
                    }
                    $valoresLote[$eixo] = $val;
                    $sets[] = "$eixo = :{$eixo}_up";
                    $insertFields[] = $eixo;
                    $insertPlaceholders[] = ":$eixo";
                }
            }

            if (empty($sets)) {
                throw new Exception('Nenhum eixo informado para atualização.');
            }

            $sqlInsert = "INSERT INTO endereco_materiais (" . implode(', ', $insertFields) . ") 
                          VALUES (" . implode(', ', $insertPlaceholders) . ") 
                          ON DUPLICATE KEY UPDATE " . implode(', ', $sets);

            $stmt = $pdo->prepare($sqlInsert);
            foreach ($idsMateriais as $idMat) {
                $params = [
                    'uid' => $idUsuario,
                    'id_mat' => $idMat
                ];
                foreach ($valoresLote as $eixo => $val) {
                    $params[$eixo] = $val;
                    $params["{$eixo}_up"] = $val;
                }
                $stmt->execute($params);
            }
        } else {
            throw new Exception('Formato de dados inválido para processamento em lote.');
        }

        $pdo->commit();
        echo json_encode(['sucesso' => true]); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['erro' => $e->getMessage()]); exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['erro' => 'Ação inválida.']); exit;
}
