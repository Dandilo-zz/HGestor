<?php
require_once '../config/conexao.php';
require_once __DIR__ . '/libs/SimpleXLSX.php';
require_once __DIR__ . '/libs/SimpleXLS.php';

use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLS;

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['erro' => 'Não autenticado.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['arquivo_inventario'])) {
    echo json_encode(['erro' => 'Requisição inválida.']); exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    echo json_encode(['erro' => 'Requisição inválida (CSRF).']); exit;
}

$idUsuario = (int) $_SESSION['user']['id'];
$file      = $_FILES['arquivo_inventario'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['erro' => 'Falha no upload do arquivo.']); exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
    echo json_encode(['erro' => 'Formato inválido. Use CSV, XLS ou XLSX.']); exit;
}

$tmpPath = $file['tmp_name'];
$linhas  = [];

if ($ext === 'csv') {
    $conteudo = file_get_contents($tmpPath);
    if (substr($conteudo, 0, 3) === "\xEF\xBB\xBF") $conteudo = substr($conteudo, 3);
    elseif (substr($conteudo, 0, 2) === "\xFF\xFE")  $conteudo = mb_convert_encoding(substr($conteudo, 2), 'UTF-8', 'UTF-16LE');
    elseif (substr($conteudo, 0, 2) === "\xFE\xFF")  $conteudo = mb_convert_encoding(substr($conteudo, 2), 'UTF-8', 'UTF-16BE');
    if (!mb_check_encoding($conteudo, 'UTF-8')) $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
    $conteudo = str_replace("\x00", "", $conteudo);

    $tmp2 = tempnam(sys_get_temp_dir(), 'inv_csv_');
    file_put_contents($tmp2, $conteudo);
    $fh = fopen($tmp2, 'r');
    if ($fh) {
        $delim  = null;
        $header = null;
        while (($row = fgetcsv($fh, 0, $delim ?? ';')) !== false) {
            if (!$header) {
                if ($delim === null) {
                    $linhaRaw = implode(';', $row);
                    $delim = (substr_count($linhaRaw, ';') >= substr_count($linhaRaw, ',')) ? ';' : ',';
                    rewind($fh);
                    continue;
                }
                $header = array_map('trim', $row);
                continue;
            }
            if (count($row) < 2) continue;
            $linhas[] = array_combine($header, array_pad($row, count($header), ''));
        }
        fclose($fh);
    }
    @unlink($tmp2);

} elseif ($ext === 'xlsx') {
    if ($xlsx = SimpleXLSX::parse($tmpPath)) {
        $rows = $xlsx->rows(0);
        $header = null;
        foreach ($rows as $row) {
            $row = array_map('trim', $row);
            if (!$header) {
                $header = $row;
                continue;
            }
            if (count(array_filter($row)) === 0) continue;
            $linhas[] = array_combine($header, array_pad($row, count($header), ''));
        }
    } else {
        echo json_encode(['erro' => 'Não foi possível ler o arquivo XLSX: ' . SimpleXLSX::parseError()]); exit;
    }

} elseif ($ext === 'xls') {
    if ($xls = SimpleXLS::parse($tmpPath)) {
        $rows = $xls->rows(0);
        $header = null;
        foreach ($rows as $row) {
            $row = array_map(function($v) { return trim((string)$v); }, $row);
            if (!$header) {
                $header = $row;
                continue;
            }
            if (count(array_filter($row)) === 0) continue;
            $linhas[] = array_combine($header, array_pad($row, count($header), ''));
        }
    } else {
        echo json_encode(['erro' => 'Não foi possível ler o arquivo XLS: ' . SimpleXLS::parseError()]); exit;
    }
}

if (empty($linhas)) {
    echo json_encode(['erro' => 'Arquivo vazio ou sem dados reconhecíveis.']); exit;
}

function normalizar(string $s): string {
    $s = mb_strtolower(trim($s));
    $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'];
    return strtr($s, $map);
}

$colMap   = [];
$firstRow = $linhas[0];
foreach (array_keys($firstRow) as $col) {
    $colMap[normalizar($col)] = $col;
}

function getCol(array $row, array $patterns, array $colMap): ?string {
    foreach ($patterns as $p) {
        foreach ($colMap as $norm => $orig) {
            if (strpos($norm, $p) !== false) {
                return isset($row[$orig]) ? (string)$row[$orig] : null;
            }
        }
    }
    return null;
}

$testCd = getCol($firstRow, ['cd material', 'cd_material', 'codigo material', 'cod material'], $colMap);
if ($testCd === null) {
    $cols = implode(', ', array_keys($firstRow));
    echo json_encode(['erro' => "Coluna 'Cd material' não encontrada. Colunas detectadas: $cols"]); exit;
}

$materiais = [];
foreach ($linhas as $row) {
    $cd  = trim(getCol($row, ['cd material', 'cd_material'], $colMap) ?? '');
    $ds  = trim(getCol($row, ['ds material', 'ds_material'], $colMap) ?? '');
    $csa = trim(getCol($row, ['cd sistema ant', 'cd_sistema_ant', 'sistema ant'], $colMap) ?? '');
    $qtRaw = getCol($row, ['qt estoque', 'qt_estoque', 'quantidade'], $colMap) ?? '0';
    $qt  = (float) str_replace(',', '.', trim($qtRaw));
    $bc  = trim(getCol($row, ['ds barras', 'ds_barras', 'barras', 'cod barras'], $colMap) ?? '');
    $lote= trim(getCol($row, ['ds lote', 'ds_lote', 'lote'], $colMap) ?? '');
    $val = trim(getCol($row, ['dt validade', 'dt_validade', 'validade'], $colMap) ?? '');
    $seq = trim(getCol($row, ['seq lote', 'seq_lote', 'seq'], $colMap) ?? '');

    if (empty($cd)) continue;

    if (!isset($materiais[$cd])) {
        $materiais[$cd] = ['ds' => $ds, 'csa' => $csa, 'qt' => 0, 'barcodes' => []];
    }
    $materiais[$cd]['qt'] += $qt;
    if (!empty($bc)) {
        $materiais[$cd]['barcodes'][] = ['bc' => $bc, 'lote' => $lote, 'val' => $val, 'seq' => $seq];
    }
}

if (empty($materiais)) {
    echo json_encode(['erro' => 'Nenhum material válido encontrado no arquivo.']); exit;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE pre_inventario SET status='cancelado' WHERE id_usuario=:uid AND status='ativo'")
        ->execute(['uid' => $idUsuario]);

    $pdo->prepare("INSERT INTO pre_inventario (id_usuario, descricao, status) VALUES (:uid, :desc, 'ativo')")
        ->execute(['uid' => $idUsuario, 'desc' => 'Inventário ' . date('d/m/Y H:i')]);
    $idInv = (int) $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO pre_inventario_itens
            (id_inventario, cd_material, ds_material, cd_sistema_ant, qt_estoque_sistema, quantidade_bipada)
        VALUES (:inv, :cd, :ds, :csa, :qt, 0)
        ON DUPLICATE KEY UPDATE
            ds_material = VALUES(ds_material),
            qt_estoque_sistema = VALUES(qt_estoque_sistema)
    ");

    $stmtBc = $pdo->prepare("
        INSERT IGNORE INTO pre_inventario_barcodes (id_item, ds_barras, ds_lote, dt_validade, seq_lote)
        VALUES (:id_item, :bc, :lote, :validade, :seq)
    ");

    // Limitação: lastInsertId no ON DUPLICATE KEY
    $stmtGetId = $pdo->prepare("
        SELECT id FROM pre_inventario_itens WHERE id_inventario=:inv AND cd_material=:cd LIMIT 1
    ");

    $contagem = 0;
    foreach ($materiais as $cd => $mat) {
        $stmtItem->execute([
            'inv' => $idInv,
            'cd'  => $cd,
            'ds'  => $mat['ds'],
            'csa' => $mat['csa'],
            'qt'  => $mat['qt'],
        ]);

        $idItem = (int) $pdo->lastInsertId();
        if (!$idItem) {
            $stmtGetId->execute(['inv' => $idInv, 'cd' => $cd]);
            $idItem = (int)($stmtGetId->fetchColumn() ?: 0);
        }

        if ($idItem) {
            foreach ($mat['barcodes'] as $bc) {
                $stmtBc->execute([
                    'id_item'  => $idItem,
                    'bc'       => $bc['bc'],
                    'lote'     => $bc['lote'],
                    'validade' => $bc['val'],
                    'seq'      => $bc['seq'],
                ]);
            }
        }
        $contagem++;
    }

    $pdo->commit();
    echo json_encode([
        'sucesso'       => true,
        'mensagem'      => "$contagem materiais importados com sucesso!",
        'id_inventario' => $idInv,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Erro ao importar inventario no banco: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno ao gravar os dados no banco de dados.']);
}