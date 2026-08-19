<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: ../modulos/login?erro=" . urlencode("Acesso não autorizado."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo_tasy'])) {
    header("Location: ../modulos/estoque?erro=" . urlencode("Requisição inválida."));
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    header("Location: ../modulos/estoque?erro=" . urlencode("Requisição inválida (CSRF)."));
    exit;
}

$arquivo = $_FILES['arquivo_tasy'];

if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../modulos/estoque?erro=" . urlencode("Erro ao subir arquivo para o servidor local."));
    exit;
}

$extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
if (strtolower($extensao) !== 'csv') {
    header("Location: ../modulos/estoque?erro=" . urlencode("Formato inválido. Por favor, envie um arquivo .csv"));
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];
$caminhoTemporario = $arquivo['tmp_name'];

$conteudoBruto = file_get_contents($caminhoTemporario);
if (substr($conteudoBruto, 0, 2) === "\xFF\xFE") {
    $conteudoBruto = mb_convert_encoding(substr($conteudoBruto, 2), 'UTF-8', 'UTF-16LE');
} elseif (substr($conteudoBruto, 0, 2) === "\xFE\xFF") {
    $conteudoBruto = mb_convert_encoding(substr($conteudoBruto, 2), 'UTF-8', 'UTF-16BE');
}
$conteudoBruto = str_replace("\x00", "", $conteudoBruto);
$tmpConvertido = tempnam(sys_get_temp_dir(), 'tasy_');
file_put_contents($tmpConvertido, $conteudoBruto);

if (($handle = fopen($tmpConvertido, "r")) !== false) {

    try {
        $pdo->beginTransaction();

        $stmtDel = $pdo->prepare("DELETE FROM tasy_estoque WHERE id_usuario = :uid");
        $stmtDel->execute(['uid' => $idUsuario]);

        $sql = "INSERT IGNORE INTO tasy_estoque (id_material, id_usuario, descricao, protheus, saldo, consumo) VALUES (:id, :id_usuario, :descricao, :protheus, :saldo, :consumo)";
        $stmt = $pdo->prepare($sql);

        $linhaCabecalhoRaw = fgets($handle);
        if (!$linhaCabecalhoRaw) {
            throw new \Exception("Arquivo CSV vazio ou ilegível.");
        }

        $linhaCabecalhoRaw = rtrim($linhaCabecalhoRaw, "\r\n");

        if (!mb_check_encoding($linhaCabecalhoRaw, 'UTF-8')) {
            $linhaCabecalhoRaw = mb_convert_encoding($linhaCabecalhoRaw, 'UTF-8', 'ISO-8859-1');
        }

        $linhaCabecalhoRaw = str_replace("\x00", "", $linhaCabecalhoRaw);
        $linhaCabecalhoRaw = preg_replace('/^(\xEF\xBB\xBF|\xFF\xFE|\xFE\xFF)/', '', $linhaCabecalhoRaw);

        $delimitador = (strpos($linhaCabecalhoRaw, ';') !== false) ? ';' : ',';
        $cabecalho = explode($delimitador, $linhaCabecalhoRaw);
        $cabecalho = array_map(function ($col) {
            return trim($col, " \t\n\r\0\x0B\"");
        }, $cabecalho);

        $idxId = $idxDesc = $idxProtheus = $idxSaldo = $idxConsumo = false;

        foreach ($cabecalho as $index => $coluna) {
            $colunaLimpa = preg_replace('/[áàãâä]/ui', 'a', $coluna);
            $colunaLimpa = preg_replace('/[éèêë]/ui', 'e', $colunaLimpa);
            $colunaLimpa = preg_replace('/[íìîï]/ui', 'i', $colunaLimpa);
            $colunaLimpa = preg_replace('/[óòõôö]/ui', 'o', $colunaLimpa);
            $colunaLimpa = preg_replace('/[úùûü]/ui', 'u', $colunaLimpa);
            $colunaLimpa = preg_replace('/[ç]/ui', 'c', $colunaLimpa);
            $colunaLimpa = trim(strtolower($colunaLimpa));

            if (preg_match('/codigo.*material/i', $colunaLimpa)) {
                $idxId = $index;
            } elseif (preg_match('/descricao.*material/i', $colunaLimpa)) {
                $idxDesc = $index;
            } elseif (preg_match('/sistema.*anterior/i', $colunaLimpa)) {
                $idxProtheus = $index;
            } elseif (preg_match('/quantidade.*disponivel/i', $colunaLimpa)) {
                $idxSaldo = $index;
            } elseif (preg_match('/consumo.*medio/i', $colunaLimpa)) {
                $idxConsumo = $index;
            }
        }

        if ($idxId === false || $idxDesc === false || $idxSaldo === false || $idxConsumo === false) {
            $debug = implode(' | ', $cabecalho);
            throw new \Exception("Colunas não encontradas. Cabeçalho detectado: " . $debug);
        }

        while (($dados = fgetcsv($handle, 0, $delimitador)) !== false) {
            if (empty($dados) || count($dados) <= max($idxId, $idxDesc, $idxSaldo, $idxConsumo)) {
                continue;
            }

            $idMaterial = trim($dados[$idxId], " \t\n\r\0\x0B\"");
            $descricao = trim($dados[$idxDesc], " \t\n\r\0\x0B\"");

            if (empty($idMaterial))
                continue;

            $protheusRaw = ($idxProtheus !== false && isset($dados[$idxProtheus]))
                ? trim($dados[$idxProtheus], " \t\n\r\0\x0B\"")
                : null;
            $protheus = ($protheusRaw !== null && $protheusRaw !== '')
                ? (strpos($protheusRaw, '0') === 0 ? $protheusRaw : '0' . $protheusRaw)
                : null;

            $saldoBruto = trim($dados[$idxSaldo], " \t\n\r\0\x0B\"");
            $consumoBruto = trim($dados[$idxConsumo], " \t\n\r\0\x0B\"");

            if (!mb_check_encoding($descricao, 'UTF-8')) {
                $descricao = mb_convert_encoding($descricao, 'UTF-8', 'ISO-8859-1');
            }

            $saldoClean = (int) floor((float) str_replace(',', '.', $saldoBruto));
            $consumoClean = (int) floor((float) str_replace(',', '.', $consumoBruto));

            $stmt->execute([
                'id' => $idMaterial,
                'id_usuario' => $idUsuario,
                'descricao' => $descricao,
                'protheus' => $protheus ?: null,
                'saldo' => $saldoClean,
                'consumo' => $consumoClean
            ]);
        }

        fclose($handle);
        if (isset($tmpConvertido) && file_exists($tmpConvertido))
            unlink($tmpConvertido);

        $pdo->commit();
        registrarLog($pdo, 'upload_csv', 'Snapshot de estoque atualizado.', 'info', $idUsuario, $_SESSION['user']['login']);

        header("Location: ../modulos/estoque?sucesso=" . urlencode("Estoque processado e atualizado com sucesso!"));
        exit;

    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_resource($handle))
            fclose($handle);
        if (isset($tmpConvertido) && file_exists($tmpConvertido))
            unlink($tmpConvertido);

        registrarLog($pdo, 'upload_csv_erro', 'Erro ao processar CSV: ' . $e->getMessage(), 'warn', $idUsuario, $_SESSION['user']['login'] ?? null);

        error_log("Erro ao processar dados CSV: " . $e->getMessage());
        header("Location: ../modulos/estoque?erro=" . urlencode("Erro ao processar dados do arquivo CSV."));
        exit;
    }
} else {
    header("Location: ../modulos/estoque?erro=" . urlencode("Não foi possível ler o arquivo temporário."));
    exit;
}