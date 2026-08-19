<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['erro' => 'Não autenticado.']);
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];
$input = json_decode(file_get_contents('php://input'), true);

$idInv = (int) ($input['id_inventario'] ?? 0);
$bipagens = $input['bipagens'] ?? [];
$csrfToken = $input['csrf_token'] ?? '';

if (!validarCsrf($csrfToken)) {
    echo json_encode(['erro' => 'Requisição inválida (CSRF).']);
    exit;
}

if (!$idInv || empty($bipagens)) {
    echo json_encode(['erro' => 'Dados insuficientes.']);
    exit;
}

$stmtChk = $pdo->prepare("SELECT id FROM pre_inventario WHERE id=:id AND id_usuario=:uid AND status='ativo'");
$stmtChk->execute(['id' => $idInv, 'uid' => $idUsuario]);
if (!$stmtChk->fetch()) {
    echo json_encode(['erro' => 'Inventário não encontrado ou sem permissão.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtUpd = $pdo->prepare("
        UPDATE pre_inventario_itens
        SET quantidade_bipada = :qtd, data_ultima_bipagem = NOW()
        WHERE id = :id AND id_inventario = :inv
    ");

    $stmtLog = $pdo->prepare("
        INSERT INTO pre_inventario_log
            (id_item, id_usuario, tipo_alteracao, ds_barras_lida, valor_anterior, valor_novo, qtd_incremento)
        VALUES (:id_item, :uid, :tipo, :bc, :ant, :novo, :inc)
    ");

    $stmtInsItem = $pdo->prepare("
         INSERT INTO pre_inventario_itens 
             (id_inventario, cd_material, ds_material, qt_estoque_sistema, quantidade_bipada) 
         VALUES (:inv, :cd, :ds, 0, 0)
     ");

    $stmtInsBarcode = $pdo->prepare("
         INSERT IGNORE INTO pre_inventario_barcodes (id_item, ds_barras) 
         VALUES (:id_item, :barras)
     ");

    $idsFiltrados = array_filter($bipagens, fn($b) => strpos($b['idItem'], 'novo_') !== 0);
    $ids = array_map(fn($b) => (int) $b['idItem'], $idsFiltrados);
    $anteriores = [];
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmtAnt = $pdo->prepare("SELECT id, quantidade_bipada FROM pre_inventario_itens WHERE id IN ($ph) AND id_inventario=?");
        $stmtAnt->execute(array_merge(array_values($ids), [$idInv]));
        foreach ($stmtAnt->fetchAll() as $r)
            $anteriores[$r['id']] = (float) $r['quantidade_bipada'];
    }
    foreach ($bipagens as $bip) {
        $idItemRaw = $bip['idItem'];
        $qtdNova = (float) ($bip['qtdNova'] ?? 0);
        $bc = $bip['codigoBarras'] ?? null;
        $inc = (float) ($bip['incremento'] ?? 0);
        $tipo = in_array($bip['tipo'] ?? '', ['bipagem', 'edicao_manual', 'reset'])
            ? $bip['tipo'] : 'bipagem';

        if (strpos($idItemRaw, 'novo_') === 0) {
            $cdMaterialNovo = $bip['cd_material_novo'] ?? 'S/C';
            $dsMaterialNovo = $bip['ds_material_novo'] ?? 'Material Inesperado';

            $stmtInsItem->execute([
                'inv' => $idInv,
                'cd' => $cdMaterialNovo,
                'ds' => $dsMaterialNovo
            ]);
            $idItem = (int) $pdo->lastInsertId();

            if (!empty($bc)) {
                $stmtInsBarcode->execute([
                    'id_item' => $idItem,
                    'barras' => $bc
                ]);
            }
            $ant = 0;
        } else {
            $idItem = (int) $idItemRaw;
            $ant = $anteriores[$idItem] ?? 0;
        }

        $stmtUpd->execute(['qtd' => $qtdNova, 'id' => $idItem, 'inv' => $idInv]);
        $stmtLog->execute([
            'id_item' => $idItem,
            'uid' => $idUsuario,
            'tipo' => $tipo,
            'bc' => $bc,
            'ant' => $ant,
            'novo' => $qtdNova,
            'inc' => $inc,
        ]);
    }

    $pdo->commit();
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    error_log("Erro ao salvar bipagem: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno ao salvar a bipagem.']);
}