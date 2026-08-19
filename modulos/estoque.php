<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: login");
    exit;
}

$usuarioLogado = $_SESSION['user']['login'];
$nomeEstoque = $_SESSION['user']['estoque_nome'];
$idUsuario = (int) $_SESSION['user']['id'];

$filtroTipoCompra = isset($_GET['tipo_compra']) ? $_GET['tipo_compra'] : '';
$filtroPadronizacao = isset($_GET['padronizacao']) ? $_GET['padronizacao'] : '';
$filtroSemConsumo = isset($_GET['sem_consumo']) ? (int) $_GET['sem_consumo'] : 1;

$stmtTipos = $pdo->prepare("SELECT * FROM config_tipos_compra WHERE id_usuario = :uid ORDER BY nome_tipo ASC");
$stmtTipos->execute(['uid' => $idUsuario]);
$tiposCompra = $stmtTipos->fetchAll();

$stmtPadr = $pdo->prepare("SELECT * FROM config_padronizacoes WHERE id_usuario = :uid ORDER BY nome_padrao ASC");
$stmtPadr->execute(['uid' => $idUsuario]);
$padronizacoes = $stmtPadr->fetchAll();

$query = "
    SELECT 
        COALESCE(cg.nome_grupo, te.descricao) AS exibicao_nome,
        CASE WHEN cm.id_grupo IS NULL THEN te.id_material ELSE 'Agrupado' END AS exibicao_id,
        cm.id_grupo,
        MAX(te.protheus) AS protheus,
        SUM(te.saldo) AS saldo_total,
        SUM(te.consumo) AS consumo_total,
            ctc.nome_tipo AS tipo_compra,
            cp.nome_padrao AS padronizacao
    FROM tasy_estoque te
    LEFT JOIN config_materiais cm  ON te.id_material = cm.id_material AND cm.id_usuario = :uid_cm
    LEFT JOIN config_grupos cg     ON cm.id_grupo = cg.id AND cg.id_usuario = :uid_cg
    LEFT JOIN config_tipos_compra ctc  ON cm.id_tipo_compra = ctc.id
    LEFT JOIN config_padronizacoes cp  ON cm.id_padronizacao = cp.id
    WHERE te.id_usuario = :uid_te
    GROUP BY 
        CASE WHEN cm.id_grupo IS NULL THEN te.id_material ELSE cm.id_grupo END,
        cg.nome_grupo,
        cm.id_grupo
";

$having = [];
$params = [
    'uid_cm' => $idUsuario,
    'uid_cg' => $idUsuario,
    'uid_te' => $idUsuario
];
if (!empty($filtroTipoCompra)) {
    $having[] = "(tipo_compra = :tipo_compra OR (exibicao_id = 'Agrupado' AND id_grupo IN (SELECT cm2.id_grupo FROM config_materiais cm2 LEFT JOIN config_tipos_compra ctc2 ON cm2.id_tipo_compra = ctc2.id AND ctc2.id_usuario = :uid_tc2 WHERE cm2.id_usuario = :uid_cm2 AND ctc2.nome_tipo = :tipo_compra_grp)))";
    $params['tipo_compra'] = $filtroTipoCompra;
    $params['tipo_compra_grp'] = $filtroTipoCompra;
    $params['uid_tc2'] = $idUsuario;
    $params['uid_cm2'] = $idUsuario;
}
if (!empty($filtroPadronizacao)) {
    $having[] = "(padronizacao = :padronizacao OR (exibicao_id = 'Agrupado' AND id_grupo IN (SELECT cm3.id_grupo FROM config_materiais cm3 LEFT JOIN config_padronizacoes cp3 ON cm3.id_padronizacao = cp3.id AND cp3.id_usuario = :uid_cp3 WHERE cm3.id_usuario = :uid_cm3 AND cp3.nome_padrao = :padronizacao_grp)))";
    $params['padronizacao'] = $filtroPadronizacao;
    $params['padronizacao_grp'] = $filtroPadronizacao;
    $params['uid_cp3'] = $idUsuario;
    $params['uid_cm3'] = $idUsuario;
}

try {
    if (!empty($having)) {
        $query .= ' HAVING ' . implode(' AND ', $having);
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }
    $itensEstoque = $stmt->fetchAll();

    $queryFilhos = "
        SELECT
            te.id_material,
            te.descricao,
            te.protheus,
            te.saldo,
            te.consumo,
            cm.id_grupo,
            ctc.nome_tipo AS tipo_compra,
            cp.nome_padrao AS padronizacao
        FROM tasy_estoque te
        LEFT JOIN config_materiais cm      ON te.id_material = cm.id_material AND cm.id_usuario = :uid_cm
        LEFT JOIN config_tipos_compra ctc  ON cm.id_tipo_compra = ctc.id AND ctc.id_usuario = :uid_ctc
        LEFT JOIN config_padronizacoes cp  ON cm.id_padronizacao = cp.id AND cp.id_usuario = :uid_cp
        WHERE cm.id_grupo IS NOT NULL
        AND te.id_usuario = :uid_te
        ORDER BY cm.id_grupo, te.descricao ASC
    ";
    $stmtFilhos = $pdo->prepare($queryFilhos);
    $stmtFilhos->execute([
        'uid_cm'  => $idUsuario,
        'uid_ctc' => $idUsuario,
        'uid_cp'  => $idUsuario,
        'uid_te'  => $idUsuario
    ]);
    $filhosPorGrupo = [];
    foreach ($stmtFilhos->fetchAll() as $filho) {
        $filhosPorGrupo[$filho['id_grupo']][] = $filho;
    }

} catch (\PDOException $e) {
    $itensEstoque = [];
    $filhosPorGrupo = [];
    error_log("Erro no dashboard: " . $e->getMessage());
    $erroQuery = "Erro ao processar consolidação de dados.";
}

$totalItens = 0;
$totalCurvaC = 0;
$totalAcordo = 0;
$totalSemConsumo = 0;

if (!empty($itensEstoque)) {
    foreach ($itensEstoque as $item) {
        if ($item['exibicao_id'] === 'Agrupado') {
            $idGrupo = $item['id_grupo'] ?? null;
            $filhos = ($idGrupo && isset($filhosPorGrupo[$idGrupo])) ? $filhosPorGrupo[$idGrupo] : [];
            foreach ($filhos as $filho) {
                $totalItens++;
                $tipo = strtolower($filho['tipo_compra'] ?? '');
                if (strpos($tipo, 'curva c') !== false) {
                    $totalCurvaC++;
                }
                if (strpos($tipo, 'acordo') !== false) {
                    $totalAcordo++;
                }
                $s = (int)$filho['saldo'];
                $c = (int)$filho['consumo'];
                if ($s > 0 && $c === 0) {
                    $totalSemConsumo++;
                }
            }
        } else {
            $totalItens++;
            $tipo = strtolower($item['tipo_compra'] ?? '');
            if (strpos($tipo, 'curva c') !== false) {
                $totalCurvaC++;
            }
            if (strpos($tipo, 'acordo') !== false) {
                $totalAcordo++;
            }
            $s = (int)$item['saldo_total'];
            $c = (int)$item['consumo_total'];
            if ($s > 0 && $c === 0) {
                $totalSemConsumo++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HGestor - Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon-16x16.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo filemtime('../css/estilos.css'); ?>">
    <style>
        .inv-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 8px;
        }

        .inv-kpi {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }


        .inv-kpi-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-weight: 600;
        }

        .inv-kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1e3a8a;
        }

        .inv-kpi-sub {
            font-size: 0.78rem;
            color: #9ca3af;
        }

        .cob-sem-consumo {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .cob-sem-estoque {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #cbd5e1;
        }

        .estado-vazio {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            font-size: 0.95rem;
        }
        .estado-vazio .icone {
            font-size: 3.5rem;
            margin-bottom: 16px;
        }
        .estado-vazio h4 {
            color: #374151;
            margin-bottom: 8px;
            font-size: 1.2rem;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <?php include 'componente_header.php'; ?>
    <div id="loading-global" class="loading-overlay">
        <div class="loading-box">
            <div class="loading-texto"><i class="fa-solid fa-spinner fa-spin" style="color: var(--accent);"></i> <span id="loading-mensagem-texto">Carregando dados...</span></div>
            <div class="barra-progresso-container">
                <div id="loading-linha" class="barra-progresso-linha"></div>
            </div>
            <div id="loading-pct" class="loading-porcentagem">0%</div>
        </div>
    </div>
    <div id="container-toasts-global" class="container-toasts"></div>
    <div class="dashboard-container">
        <main class="conteudo-principal">

            <?php if (isset($_GET['sucesso'])): ?>
                <div class="alerta alerta-sucesso"><?php echo htmlspecialchars($_GET['sucesso']); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['erro'])): ?>
                <div class="alerta alerta-erro"><?php echo htmlspecialchars($_GET['erro']); ?></div>
            <?php endif; ?>
            <?php if (isset($erroQuery)): ?>
                <div class="alerta alerta-erro"><?php echo htmlspecialchars($erroQuery); ?></div>
            <?php endif; ?>
            <section class="card-upload">
                <h3>Substituir dados de Estoque</h3>
                <p>Selecione o arquivo bruto extraído do Tasy (formato .csv delimitado por ponto e vírgula).</p>
                <form action="../acoes/upload_csv.php" method="POST" enctype="multipart/form-data"
                    class="upload-form" id="form-upload-tasy">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <div class="file-input-wrapper">
                        <input type="file" name="arquivo_tasy" id="arquivo_tasy" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn-acao btn-sucesso"><i class="fa-solid fa-cloud-arrow-up"></i> Carregar Estoque</button>
                    <button type="button" class="btn-acao btn-perigo" onclick="abrirModalResetEstoque()"><i class="fa-solid fa-trash-can"></i> Resetar Estoque</button>
                </form>
            </section>
            
            <?php if (empty($itensEstoque)): ?>
                <section class="card-dados card-estado-vazio" style="margin-top: 15px; border-left: 4px solid var(--accent); box-shadow: var(--shadow-md);">
                    <div class="estado-vazio">
                        <div class="icone">📊</div>
                        <h4>Nenhum dado de estoque carregado</h4>
                        <p style="margin-bottom: 24px;">Faça o upload do arquivo carregar o estoque.</p>

                        <div style="text-align: left; max-width: 650px; margin: 0 auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h5 style="color: #1e3a8a; font-size: 0.95rem; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                                <i class="fa-solid fa-book-open"></i> Como gerar o arquivo no Tasy:
                            </h5>
                            <ol style="font-size: 0.85rem; line-height: 1.6; color: #4b5563; padding-left: 20px; display: flex; flex-direction: column; gap: 8px;">
                                <li>Vá até o gerenciamento de estoque do Tasy.</li>
                                <li>Carregue todos os dados do estoque desejado.</li>
                                <li>Use o comando <strong>Ctrl + E</strong> para gerar a exportação em CSV.</li>
                                <li>Salve o arquivo no seu computador e carregue-o aqui.</li>
                            </ol>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <div class="inv-dashboard" style="margin-top: 20px;">
                <div class="inv-kpi">
                    <span class="inv-kpi-label">Total de Itens</span>
                    <span class="inv-kpi-value"><?php echo number_format($totalItens, 0, ',', '.'); ?></span>
                    <span class="inv-kpi-sub">Materiais no estoque</span>
                </div>
                
                <div class="inv-kpi">
                    <span class="inv-kpi-label">Itens Curva C</span>
                    <span class="inv-kpi-value"><?php echo number_format($totalCurvaC, 0, ',', '.'); ?></span>
                    <span class="inv-kpi-sub">Materiais de compra Curva C</span>
                </div>
                
                <div class="inv-kpi">
                    <span class="inv-kpi-label">Acordo Comercial</span>
                    <span class="inv-kpi-value"><?php echo number_format($totalAcordo, 0, ',', '.'); ?></span>
                    <span class="inv-kpi-sub">Materiais de Acordo Comercial</span>
                </div>

                <div class="inv-kpi">
                    <span class="inv-kpi-label">Itens Sem Consumo</span>
                    <span class="inv-kpi-value"><?php echo number_format($totalSemConsumo, 0, ',', '.'); ?></span>
                    <span class="inv-kpi-sub">Itens sem movimentação</span>
                </div>
            </div>

            <?php if (!empty($itensEstoque)): ?>
                <section class="card-dados">
                    <div class="card-header-tabela">
                        <form method="GET" action="estoque"
                            style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin:0;">
                            <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;">Tipo de Compra</label>
                                    <select name="tipo_compra" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 120px;">
                                        <option value="">Todos</option>
                                        <?php foreach ($tiposCompra as $tc): ?>
                                            <option value="<?php echo htmlspecialchars($tc['nome_tipo']); ?>" <?php echo $filtroTipoCompra === $tc['nome_tipo'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tc['nome_tipo']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;">Padronização</label>
                                    <select name="padronizacao" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 120px;">
                                        <option value="">Todas</option>
                                        <?php foreach ($padronizacoes as $p): ?>
                                            <option value="<?php echo htmlspecialchars($p['nome_padrao']); ?>" <?php echo $filtroPadronizacao === $p['nome_padrao'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['nome_padrao']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;">Itens Sem Consumo</label>
                                    <select name="sem_consumo" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 100px;">
                                        <option value="0" <?php echo $filtroSemConsumo === 0 ? 'selected' : ''; ?>>Ocultar</option>
                                        <option value="1" <?php echo $filtroSemConsumo === 1 ? 'selected' : ''; ?>>Exibir</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-acao" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
                                <?php if (!empty($filtroTipoCompra) || !empty($filtroPadronizacao) || $filtroSemConsumo !== 1): ?>
                                    <a href="estoque" class="btn-acao btn-neutro"
                                        style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-xmark"></i> Limpar</a>
                                <?php endif; ?>
                            </div>
                        </form>
    
                        <div class="busca-container">
                            <input type="text" id="input-busca" placeholder="Buscar por código, descrição ou grupo...">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                    </div>
    
                    <div class="table-responsive">
                        <table class="tabela-hgestor" id="tabela-materiais">
                            <thead>
                                <tr>
                                    <th onclick="ordenarTabela(0, 'num')" style="cursor: pointer;">ID Material <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                    <th onclick="ordenarTabela(1, 'text')" style="cursor: pointer;">Protheus <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                    <th onclick="ordenarTabela(2, 'text')" style="cursor: pointer;">Descrição (Tasy / Grupo) <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                    <th onclick="ordenarTabela(3, 'num')" class="txt-direita" style="cursor: pointer;">Saldo Total <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                    <th onclick="ordenarTabela(4, 'num')" class="txt-direita" style="cursor: pointer;">Consumo Total <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                    <th onclick="ordenarTabela(5, 'num')" class="txt-centro" style="cursor: pointer;">Cobertura % <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                    <th onclick="ordenarTabela(6, 'text')" style="cursor: pointer;">Tipo de Compra <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                    <th onclick="ordenarTabela(7, 'text')" style="cursor: pointer;">Padronização <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itensEstoque as $item):
                                    $saldo = (int) $item['saldo_total'];
                                    $consumo = (int) $item['consumo_total'];
                                    $coberturaTxt = '';
                                    $classeCobertura = '';
                                    
                                    if ($saldo > 0 && $consumo === 0) {
                                        if ($filtroSemConsumo === 0) {
                                            continue;
                                        }
                                        $coberturaTxt = 'Sem Consumo';
                                        $classeCobertura = 'cob-sem-consumo';
                                    } elseif ($saldo === 0 && $consumo === 0) {
                                        $coberturaTxt = 'Sem Estoque';
                                        $classeCobertura = 'cob-sem-estoque';
                                    } else {
                                        $porcentagem = ($saldo / $consumo) * 100;
                                        $coberturaTxt = round($porcentagem) . '%';
                                        if ($porcentagem >= 100)
                                            $classeCobertura = 'cob-ok';
                                        elseif ($porcentagem >= 50)
                                            $classeCobertura = 'cob-alerta';
                                        else
                                            $classeCobertura = 'cob-critica';
                                    }
                                    
                                    $ehGrupo = $item['exibicao_id'] === 'Agrupado';
                                    $idGrupo = $item['id_grupo'] ?? null;
                                    $filhos = ($ehGrupo && $idGrupo && isset($filhosPorGrupo[$idGrupo]))
                                        ? $filhosPorGrupo[$idGrupo]
                                        : [];
                                    ?>
                                    <tr class="linha-pai-zebra <?php echo ($ehGrupo && $filhos) ? 'linha-grupo' : ''; ?>" <?php if ($ehGrupo && $filhos): ?>onclick="toggleGrupo(this)" <?php endif; ?>>
                                        <td><span
                                                class="txt-mutado"><?php echo htmlspecialchars($item['exibicao_id']); ?></span>
                                        </td>
                                        <td><span
                                                class="txt-mutado"><?php echo $ehGrupo ? 'Agrupado' : htmlspecialchars($item['protheus'] ?? '—'); ?></span>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php if ($ehGrupo && $filhos): ?>
                                                    <span class="icone-accordion" style="margin-right:6px;"><i class="fa-solid fa-chevron-right"></i></span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($item['exibicao_nome']); ?>
                                            </strong>
                                        </td>
                                        <td class="txt-direita"><?php echo number_format($saldo, 0, ',', '.'); ?></td>
                                        <td class="txt-direita"><?php echo number_format($consumo, 0, ',', '.'); ?></td>
                                        <td class="txt-centro">
                                            <span class="badge-cobertura <?php echo $classeCobertura; ?>">
                                                <?php echo $coberturaTxt; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['tipo_compra'])):
                                                $classeCompra = (strpos(strtolower($item['tipo_compra']), 'curva c') !== false) ? 'badge-compra-curva-c' : ((strpos(strtolower($item['tipo_compra']), 'acordo') !== false) ? 'badge-compra-acordo' : 'badge-compra-generico');
                                                ?>
                                                <span
                                                    class="badge-hgestor <?php echo $classeCompra; ?>"><?php echo htmlspecialchars($item['tipo_compra']); ?></span>
                                            <?php else: ?>
                                                <span class="badge-hgestor badge-padrao-indefinido">Não Definido</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['padronizacao'])):
                                                $classePadrao = in_array(trim(strtolower($item['padronizacao'])), ['padronizado', 'padronizada']) ? 'badge-padrao-sim' : 'badge-padrao-nao';
                                                ?>
                                                <span
                                                    class="badge-hgestor <?php echo $classePadrao; ?>"><?php echo htmlspecialchars($item['padronizacao']); ?></span>
                                            <?php else: ?>
                                                <span class="badge-hgestor badge-padrao-indefinido">Não Definido</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <?php if ($ehGrupo && $filhos): ?>
                                        <?php foreach ($filhos as $filho):
                                            $sF = (int) $filho['saldo'];
                                            $cF = (int) $filho['consumo'];
                                            $cobTxtF = '';
                                            $cobClsF = '';
                                            
                                            if ($sF > 0 && $cF === 0) {
                                                if ($filtroSemConsumo === 0) {
                                                    continue;
                                                }
                                                $cobTxtF = 'Sem Consumo';
                                                $cobClsF = 'cob-sem-consumo';
                                            } elseif ($sF === 0 && $cF === 0) {
                                                $cobTxtF = 'Sem Estoque';
                                                $cobClsF = 'cob-sem-estoque';
                                            } else {
                                                $pct = ($sF / $cF) * 100;
                                                $cobTxtF = round($pct) . '%';
                                                if ($pct >= 100)
                                                    $cobClsF = 'cob-ok';
                                                elseif ($pct >= 50)
                                                    $cobClsF = 'cob-alerta';
                                                else
                                                    $cobClsF = 'cob-critica';
                                            }
                                            ?>
                                            <tr class="linha-filho" style="display:none; background:#f8fafc;">
                                                <td><span
                                                        class="txt-mutado"><?php echo htmlspecialchars($filho['id_material']); ?></span>
                                                </td>
                                                <td><span
                                                        class="txt-mutado"><?php echo htmlspecialchars($filho['protheus'] ?? '—'); ?></span>
                                                </td>
                                                <td style="padding-left:32px;"><?php echo htmlspecialchars($filho['descricao']); ?></td>
                                                <td class="txt-direita"><?php echo number_format($sF, 0, ',', '.'); ?></td>
                                                <td class="txt-direita"><?php echo number_format($cF, 0, ',', '.'); ?></td>
                                                <td class="txt-centro">
                                                    <span class="badge-cobertura <?php echo $cobClsF; ?>"><?php echo $cobTxtF; ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($filho['tipo_compra'])):
                                                        $clsFCompra = (strpos(strtolower($filho['tipo_compra']), 'curva c') !== false) ? 'badge-compra-curva-c' : ((strpos(strtolower($filho['tipo_compra']), 'acordo') !== false) ? 'badge-compra-acordo' : 'badge-compra-generico');
                                                        ?>
                                                        <span
                                                            class="badge-hgestor <?php echo $clsFCompra; ?>"><?php echo htmlspecialchars($filho['tipo_compra']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge-hgestor badge-padrao-indefinido">Não Definido</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($filho['padronizacao'])):
                                                        $clsFPadrao = in_array(trim(strtolower($filho['padronizacao'])), ['padronizado', 'padronizada']) ? 'badge-padrao-sim' : 'badge-padrao-nao';
                                                        ?>
                                                        <span
                                                            class="badge-hgestor <?php echo $clsFPadrao; ?>"><?php echo htmlspecialchars($filho['padronizacao']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge-hgestor badge-padrao-indefinido">Não Definido</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

        </main>
    </div>
    <footer class="footer-horus">
        <p>&copy; <?php echo date('Y'); ?> <span>HorusDEV</span>. Todos os direitos reservados.</p>
        <p>Desenvolvido com excelência por <strong>Dândilo Silva</strong></p>
        <div class="footer-links">
            <a href="https://www.linkedin.com/in/d%C3%A2ndilo-silva-6a83a1152" target="_blank"><i class="fa-brands fa-linkedin"></i> LinkedIn</a>
            <a href="https://github.com/Dandilo-zz" target="_blank"><i class="fa-brands fa-github"></i> GitHub</a>
            <a href="https://instagram.com/dandilos" target="_blank"><i class="fa-brands fa-instagram"></i> Instagram</a>
        </div>
        <p><a href="politicas.php" style="font-weight: 600; color: #3b82f6; margin-right: 15px;">Privacidade e Termos de
                uso</a></p>
    </footer>
    <script>
        // ── Modal de Consentimento (LGPD / Primeiro Acesso) ────────────────
        window.addEventListener('DOMContentLoaded', () => {
            if (!localStorage.getItem('hgestor_aceite_termos')) {
                const dialogAviso = document.getElementById('modal-aviso-lgpd');
                if (dialogAviso) {
                    dialogAviso.showModal();
                }
            }
        });

        function confirmarAceiteLgpd() {
            const chk = document.getElementById('chk-aceite-lgpd');
            if (!chk || !chk.checked) {
                alert('Você precisa ler e marcar a caixa de seleção confirmando que concorda com os Termos de Uso e a Política de Privacidade para prosseguir.');
                return;
            }
            localStorage.setItem('hgestor_aceite_termos', 'true');
            document.getElementById('modal-aviso-lgpd').close();
            lancarAlerta('Termos aceitos com sucesso!', 'sucesso');
        }
        function toggleGrupo(linhaPai) {
            const icone = linhaPai.querySelector('.icone-accordion');
            const aberto = linhaPai.classList.contains('grupo-expandido-pai');

            if (aberto) {
                linhaPai.classList.remove('grupo-expandido-pai');
                if (icone) icone.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
            } else {
                linhaPai.classList.add('grupo-expandido-pai');
                if (icone) icone.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';
            }

            let el = linhaPai.nextElementSibling;
            while (el && el.classList.contains('linha-filho')) {
                if (aberto) {
                    el.style.display = 'none';
                    el.classList.remove('grupo-expandido-filho');
                } else {
                    el.style.display = '';
                    el.classList.add('grupo-expandido-filho');
                }
                el = el.nextElementSibling;
            }
        }

        const inputBusca = document.getElementById('input-busca');
        if (inputBusca) {
            inputBusca.addEventListener('keyup', function () {
                const termo = this.value.toLowerCase();
                const linhas = document.querySelectorAll('#tabela-materiais tbody tr');
                linhas.forEach(linha => {
                    if (linha.querySelector('.msg-vazia')) return;
                    if (linha.classList.contains('linha-filho')) return;
                    linha.style.display = linha.innerText.toLowerCase().includes(termo) ? '' : 'none';
                });
            });
        }

        let direcoesOrdenacao = {};

        function obterValorCobertura(valor) {
            valor = valor.trim().toLowerCase();
            if (valor === 'sem estoque') {
                return -1;
            }
            if (valor === 'sem consumo') {
                return Number.MAX_SAFE_INTEGER;
            }
            return parseFloat(valor.replace('%', '').replace(',', '.')) || 0;
        }

        function ordenarTabela(colunaIndex, tipo) {
            const tabela = document.getElementById('tabela-materiais');
            if (!tabela) return;
            const tbody = tabela.querySelector('tbody');
            const linhas = Array.from(tbody.querySelectorAll('tr'));

            if (linhas.length === 1 && linhas[0].querySelector('.msg-vazia')) return;

            const direcaoAtual = direcoesOrdenacao[colunaIndex] === 'asc' ? 'desc' : 'asc';
            direcoesOrdenacao = { [colunaIndex]: direcaoAtual };

            const blocos = [];
            let blocoAtual = null;
            linhas.forEach(linha => {
                if (linha.classList.contains('linha-filho')) {
                    if (blocoAtual) blocoAtual.filhos.push(linha);
                } else {
                    blocoAtual = { pai: linha, filhos: [] };
                    blocos.push(blocoAtual);
                }
            });

            blocos.sort((bA, bB) => {
                let celulaA = bA.pai.children[colunaIndex].innerText.trim();
                let celulaB = bB.pai.children[colunaIndex].innerText.trim();

                if (colunaIndex === 5) {
                    celulaA = obterValorCobertura(celulaA);
                    celulaB = obterValorCobertura(celulaB);
                } else if (tipo === 'num') {
                    celulaA = parseFloat(celulaA.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
                    celulaB = parseFloat(celulaB.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
                } else {
                    celulaA = celulaA.toLowerCase();
                    celulaB = celulaB.toLowerCase();
                }

                if (celulaA < celulaB) return direcaoAtual === 'asc' ? -1 : 1;
                if (celulaA > celulaB) return direcaoAtual === 'asc' ? 1 : -1;
                return 0;
            });

            while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
            blocos.forEach(bloco => {
                tbody.appendChild(bloco.pai);
                bloco.filhos.forEach(filho => tbody.appendChild(filho));
            });
        }
        // Comportamento Unificado de Seleção por Clique (Mesma cor do Hover)
        const tabelaMateriaisTbody = document.querySelector('#tabela-materiais tbody');
        if (tabelaMateriaisTbody) {
            tabelaMateriaisTbody.addEventListener('click', function (e) {
                const linha = e.target.closest('tr');

                if (!linha || linha.querySelector('.msg-vazia') || linha.classList.contains('linha-filho')) return;

                // Remove seleção prévia das outras linhas comuns
                document.querySelectorAll('#tabela-materiais tbody tr.linha-selecionada').forEach(tr => {
                    tr.classList.remove('linha-selecionada');
                });

                // Aplica o estado selecionado
                linha.classList.add('linha-selecionada');
            });
        }
        function lancarAlerta(mensagem, tipo = 'info', tempoExibicao = 4000) {
            const container = document.getElementById('container-toasts-global');
            if (!container) return;

            // Cria o elemento do toast
            const toast = document.createElement('div');
            toast.className = `alerta-toast toast-${tipo}`;

            // Define o ícone com base no tipo semântico
            let icone = '<i class="fa-solid fa-circle-info"></i>';
            if (tipo === 'sucesso') icone = '<i class="fa-solid fa-circle-check"></i>';
            if (tipo === 'erro') icone = '<i class="fa-solid fa-circle-xmark"></i>';
            if (tipo === 'alerta') icone = '<i class="fa-solid fa-triangle-exclamation"></i>';

            toast.innerHTML = `
        <div style="display:flex; align-items:center; gap:10px;">
            <span>${icone}</span>
            <span>${mensagem}</span>
        </div>
        <button class="toast-fechar" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    `;

            container.appendChild(toast);

            // Auto-remove após o tempo estipulado
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(() => toast.remove(), 300);
            }, tempoExibicao);
        }
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('sucesso')) {
                lancarAlerta(urlParams.get('sucesso'), 'sucesso');
                // Limpa o parâmetro da URL sem recarregar a página para manter a estética da barra de navegação
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            if (urlParams.has('erro')) {
                lancarAlerta(urlParams.get('erro'), 'erro');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            if (document.getElementById('tabela-materiais')) {
                ordenarTabela(5, 'num');
            }
        });
        function dispararCarregamentoVisivel(mensagem = "Carregando dados...") {
            const overlay = document.getElementById('loading-global');
            const linha = document.getElementById('loading-linha');
            const txtPct = document.getElementById('loading-pct');

            // Correção do erro de digitação anterior (mensagem)
            document.getElementById('loading-mensagem-texto').innerText = mensagem || "Carregando dados...";

            if (!overlay || !linha || !txtPct) return;

            // 1. Exibe o overlay na tela
            overlay.classList.add('ativo');

            // 2. Define um tempo aleatório entre 1000ms (1s) e 3000ms (3s)
            const tempoTotal = Math.floor(Math.random() * (3000 - 1000 + 1)) + 1000;

            let progresso = 0;
            const intervaloTempo = 30; // Atualiza a barra a cada 30 milissegundos
            const passosTotais = tempoTotal / intervaloTempo;
            const incremento = 100 / passosTotais;

            // 3. Inicia a animação da barra de progresso
            const temporizador = setInterval(function () {
                progresso += incremento;

                if (progresso >= 100) {
                    progresso = 100;
                    clearInterval(temporizador);
                    // Opcional: Você pode adicionar um leve fade-out aqui se não houver mudança de página imediata
                }

                // Atualiza o visual da barra e a porcentagem em texto
                linha.style.width = progresso + '%';
                txtPct.innerText = Math.floor(progresso) + '%';
            }, intervaloTempo);
        }
        document.getElementById('form-upload-tasy').addEventListener('submit', function (e) {
            e.preventDefault(); // Segura o envio imediato
            const form = this;

            // Ativa a animação
            dispararCarregamentoVisivel("Processando Snapshot do Estoque...");

            // Recupera o tempo gerado ou simplesmente força o envio após o tempo máximo estimado
            // Para garantir a sincronia exata, podemos usar um setTimeout de 2.5 a 3 segundos para enviar:
            setTimeout(function () {
                form.submit(); // Envia o arquivo de fato após o tempo da animação
            }, 2800);
        });
        function abrirModalResetEstoque() {
            document.getElementById('senha-reset-estoque').value = '';
            document.getElementById('modal-reset-estoque').showModal();
        }

        function executarResetEstoque() {
            const senha = document.getElementById('senha-reset-estoque').value;
            if (!senha) {
                lancarAlerta('Digite sua senha para confirmar.', 'alerta');
                return;
            }
            document.getElementById('modal-reset-estoque').close();
            document.getElementById('pageOverlay').classList.add('active');
            fetch('../acoes/resetar_estoque.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ senha, csrf_token: window.csrfToken })
            })
                .then(res => {
                    if (res.status === 401) throw new Error('Senha incorreta.');
                    if (!res.ok) throw new Error('Erro no servidor.');
                    return res.json();
                })
                .then(data => {
                    lancarAlerta(data.mensagem, 'sucesso');
                    setTimeout(() => window.location.reload(), 1200);
                })
                .catch(err => {
                    document.getElementById('pageOverlay').classList.remove('active');
                    lancarAlerta(err.message || 'Falha ao resetar estoque.', 'erro');
                });
        }                             
    </script>
    <dialog id="modal-aviso-lgpd" style="max-width: 550px;" oncancel="event.preventDefault()">
        <h3 style="color:#1e3a8a; margin-bottom:12px; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i> Aviso de Primeiro Acesso</h3>
        <div
            style="font-size:0.9rem; color:#374151; margin-bottom:15px; line-height:1.5; max-height:280px; overflow-y:auto; border:1px solid #e2e8f0; padding:12px; border-radius:6px; background:#f8fafc;">
            <p><strong>Importante</strong></p><br>
            <p>Esta aplicação destina-se exclusivamente ao processamento de dados operacionais relacionados à gestão de
                estoque, materiais e medicamentos.</p><br>
            <p>Não devem ser inseridos dados de pacientes, prontuários, diagnósticos, exames ou quaisquer dados pessoais
                sensíveis protegidos pela LGPD.</p><br>
            <p>Ao continuar, você declara estar autorizado pela instituição a utilizar os dados processados e concorda
                com os Termos de Uso e a Política de Privacidade da aplicação.</p>
        </div>
        <div style="margin-bottom:18px;">
            <label
                style="display:flex; align-items:center; gap:8px; font-size:0.88rem; cursor:pointer; color:#111827; font-weight:600;">
                <input type="checkbox" id="chk-aceite-lgpd" style="width:16px; height:16px;">
                Li e concordo com os <a href="politicas.php" target="_blank"
                    style="color:#3b82f6; text-decoration:none;">Termos de Uso e a Política de Privacidade</a>.
            </label>
        </div>
        <div style="display:flex; justify-content:flex-end;">
            <button type="button" class="btn-acao btn-sucesso" style="padding:10px 24px;"
                onclick="confirmarAceiteLgpd()"><i class="fa-solid fa-arrow-right"></i> Continuar para o Sistema</button>
        </div>
    </dialog>
    <dialog id="modal-reset-estoque" style="max-width:420px; border:none; border-radius:8px; padding:24px;"
        oncancel="event.preventDefault()">
        <h3 style="color:#dc2626; margin:0 0 8px;"><i class="fa-solid fa-triangle-exclamation"></i> Resetar Snapshot de Estoque</h3>
        <p style="color:#6b7280; font-size:0.88rem; margin:0 0 18px; line-height:1.5;">
            Isso removerá <strong>todos os itens importados</strong> do seu estoque atual.<br>
            Suas parametrizações (grupos, vínculos, tipos) serão preservadas.<br><br>
            Confirme sua senha para continuar.
        </p>
        <div class="form-group" style="margin-bottom:16px;">
            <label style="font-size:0.85rem; font-weight:600;">Senha</label>
            <input type="password" id="senha-reset-estoque" placeholder="Digite sua senha"
                style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px; box-sizing:border-box;">
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn-acao btn-neutro" style="padding:8px 16px;"
                onclick="document.getElementById('modal-reset-estoque').close()"><i class="fa-solid fa-xmark"></i> Cancelar</button>
            <button type="button" class="btn-acao btn-perigo" style="padding:8px 16px;"
                onclick="executarResetEstoque()"><i class="fa-solid fa-trash-can"></i> Confirmar Reset</button>
        </div>
    </dialog>
</body>

</html>