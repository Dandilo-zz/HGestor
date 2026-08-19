<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../modulos/parametros?erro=" . urlencode("Acesso não autorizado."));
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    header("Location: ../modulos/parametros?erro=" . urlencode("Requisição inválida (CSRF)."));
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];

if (!isset($_FILES['arquivo_json']) || $_FILES['arquivo_json']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../modulos/parametros?erro=" . urlencode("Falha ao receber o arquivo JSON no servidor."));
    exit;
}

$fileTmpPath = $_FILES['arquivo_json']['tmp_name'];
$jsonRaw     = file_get_contents($fileTmpPath);
$data        = json_decode($jsonRaw, true);

if (!$data || !isset($data['dicionario_parametros'])) {
    header("Location: ../modulos/parametros?erro=" . urlencode("Arquivo JSON inválido ou fora do padrão HorusDEV."));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtG = $pdo->prepare("SELECT id, nome_grupo FROM config_grupos WHERE id_usuario = :uid");
    $stmtG->execute(['uid' => $idUsuario]);
    $gruposCache = $stmtG->fetchAll(PDO::FETCH_KEY_PAIR);
    $gruposCache = array_map('strtolower', $gruposCache);
    $gruposCacheInvertido = array_flip($gruposCache);

    $stmtC = $pdo->prepare("SELECT id, nome_tipo FROM config_tipos_compra WHERE id_usuario = :uid");
    $stmtC->execute(['uid' => $idUsuario]);
    $comprasCache = $stmtC->fetchAll(PDO::FETCH_KEY_PAIR);
    $comprasCache = array_map('strtolower', $comprasCache);
    $comprasCacheInvertido = array_flip($comprasCache);

    $stmtP = $pdo->prepare("SELECT id, nome_padrao FROM config_padronizacoes WHERE id_usuario = :uid");
    $stmtP->execute(['uid' => $idUsuario]);
    $padraoCache = $stmtP->fetchAll(PDO::FETCH_KEY_PAIR);
    $padraoCache = array_map('strtolower', $padraoCache);
    $padraoCacheInvertido = array_flip($padraoCache);

    $insGrupo = $pdo->prepare("INSERT INTO config_grupos (nome_grupo, id_usuario) VALUES (:nome, :uid)");
    $insCompra = $pdo->prepare("INSERT INTO config_tipos_compra (nome_tipo, id_usuario) VALUES (:nome, :uid)");
    $insPadrao = $pdo->prepare("INSERT INTO config_padronizacoes (nome_padrao, id_usuario) VALUES (:nome, :uid)");

    $stmtMaterial = $pdo->prepare("
        INSERT INTO config_materiais (id_material, id_usuario, id_grupo, id_tipo_compra, id_padronizacao)
        VALUES (:id_mat, :uid, :id_g, :id_c, :id_p)
        ON DUPLICATE KEY UPDATE id_grupo = :id_g_up, id_tipo_compra = :id_c_up, id_padronizacao = :id_p_up
    ");

    $contagemSucesso = 0;

    foreach ($data['dicionario_parametros'] as $item) {
        $idMaterial = trim($item['id_material']);
        if (empty($idMaterial)) continue;

        $nGrupo  = !empty($item['nome_grupo']) ? trim($item['nome_grupo']) : null;
        $nCompra = !empty($item['nome_tipo_compra']) ? trim($item['nome_tipo_compra']) : null;
        $nPadrao = !empty($item['nome_padrao']) ? trim($item['nome_padrao']) : null;

        $idG = null; $idC = null; $idP = null;

        if ($nGrupo) {
            $chave = strtolower($nGrupo);
            if (isset($gruposCacheInvertido[$chave])) {
                $idG = $gruposCacheInvertido[$chave];
            } else {
                $insGrupo->execute(['nome' => $nGrupo, 'uid' => $idUsuario]);
                $idG = $pdo->lastInsertId();
                $gruposCacheInvertido[$chave] = $idG;
            }
        }

        if ($nCompra) {
            $chave = strtolower($nCompra);
            if (isset($comprasCacheInvertido[$chave])) {
                $idC = $comprasCacheInvertido[$chave];
            } else {
                $insCompra->execute(['nome' => $nCompra, 'uid' => $idUsuario]);
                $idC = $pdo->lastInsertId();
                $comprasCacheInvertido[$chave] = $idC;
            }
        }

        if ($nPadrao) {
            $chave = strtolower($nPadrao);
            if (isset($padraoCacheInvertido[$chave])) {
                $idP = $padraoCacheInvertido[$chave];
            } else {
                $insPadrao->execute(['nome' => $nPadrao, 'uid' => $idUsuario]);
                $idP = $pdo->lastInsertId();
                $padraoCacheInvertido[$chave] = $idP;
            }
        }

        $stmtMaterial->execute([
            'id_mat'   => $idMaterial,
            'uid'      => $idUsuario,
            'id_g'     => $idG,
            'id_c'     => $idC,
            'id_p'     => $idP,
            'id_g_up'  => $idG,
            'id_c_up'  => $idC,
            'id_p_up'  => $idP
        ]);

        $contagemSucesso++;
    }

    $pdo->commit();
    header("Location: ../modulos/parametros?sucesso=" . urlencode("Portabilidade concluída! {$contagemSucesso} materiais foram parametrizados automaticamente."));
    exit;

} catch (\Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Falha crítica ao realizar portabilidade: " . $e->getMessage());
    header("Location: ../modulos/parametros?erro=" . urlencode("Falha crítica ao realizar portabilidade."));
    exit;
}