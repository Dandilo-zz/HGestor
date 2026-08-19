<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$usuarioLogado = $_SESSION['user']['login'];
$nomeEstoque = $_SESSION['user']['estoque_nome'];
$idUsuario = (int) $_SESSION['user']['id'];
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM tasy_estoque WHERE id_usuario = :uid");
$stmtTotal->execute(['uid' => $idUsuario]);
$totalEstoqueUsuario = (int) $stmtTotal->fetchColumn();

$stmtPadroes = $pdo->prepare("SELECT id, nome_padrao FROM config_padronizacoes WHERE id_usuario = :uid");
$stmtPadroes->execute(['uid' => $idUsuario]);
$padroes = $stmtPadroes->fetchAll(PDO::FETCH_ASSOC);

$stmtTipos = $pdo->prepare("SELECT id, nome_tipo FROM config_tipos_compra WHERE id_usuario = :uid");
$stmtTipos->execute(['uid' => $idUsuario]);
$tiposCompra = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

$filtroTipoCompra = isset($_GET['tipo_compra']) ? $_GET['tipo_compra'] : '';
$filtroPadronizacao = isset($_GET['padronizacao']) ? $_GET['padronizacao'] : '';
$filtroCoberturaMax = isset($_GET['cobertura_max']) && $_GET['cobertura_max'] !== '' ? (int) $_GET['cobertura_max'] : 150;

$where = ["te.id_usuario = :uid_te"];
$params = [
    'uid_te' => $idUsuario,
    'uid_cm' => $idUsuario,
    'uid_cg' => $idUsuario
];

if (!empty($filtroTipoCompra)) {
    $where[] = "ctc.nome_tipo = :tipo_compra";
    $params['tipo_compra'] = $filtroTipoCompra;
}
if (!empty($filtroPadronizacao)) {
    $where[] = "cp.nome_padrao = :padronizacao";
    $params['padronizacao'] = $filtroPadronizacao;
}

$whereClause = implode(" AND ", $where);

$query = "
    SELECT
        COALESCE(cg.nome_grupo, te.descricao) AS exibicao_nome,
        CASE WHEN cm.id_grupo IS NULL THEN te.id_material ELSE 'Agrupado' END AS exibicao_id,
        cm.id_grupo,
        MAX(te.protheus) AS protheus,
        SUM(te.saldo)    AS saldo_total,
        SUM(te.consumo)  AS consumo_total,
        ctc.nome_tipo    AS tipo_compra,
        cp.nome_padrao   AS padronizacao        
    FROM tasy_estoque te
    LEFT JOIN config_materiais cm ON te.id_material = cm.id_material AND cm.id_usuario = :uid_cm
    LEFT JOIN config_grupos cg    ON cm.id_grupo = cg.id AND cg.id_usuario = :uid_cg
    LEFT JOIN config_tipos_compra ctc ON cm.id_tipo_compra = ctc.id
    LEFT JOIN config_padronizacoes cp ON cm.id_padronizacao = cp.id
    WHERE $whereClause
    GROUP BY
        CASE WHEN cm.id_grupo IS NULL THEN te.id_material ELSE cm.id_grupo END,
        cg.nome_grupo,
        cm.id_grupo
    HAVING
        SUM(te.consumo) = 0
        OR (SUM(te.saldo) / SUM(te.consumo)) * 100 < :cobertura_max
    ORDER BY 
        CASE WHEN SUM(te.consumo) > 0 THEN 0 ELSE 1 END ASC,
        (SUM(te.saldo) / NULLIF(SUM(te.consumo), 0)) * 100 ASC
";
$params['cobertura_max'] = $filtroCoberturaMax;

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $itensEstoque = $stmt->fetchAll();

    $whereFilhos = ["cm.id_grupo IS NOT NULL", "te.id_usuario = :uid_te"];
    $paramsFilhos = [
        'uid_te' => $idUsuario,
        'uid_cm' => $idUsuario,
        'uid_ctc' => $idUsuario,
        'uid_cp' => $idUsuario
    ];

    if (!empty($filtroTipoCompra)) {
        $whereFilhos[] = "ctc.nome_tipo = :tipo_compra";
        $paramsFilhos['tipo_compra'] = $filtroTipoCompra;
    }
    if (!empty($filtroPadronizacao)) {
        $whereFilhos[] = "cp.nome_padrao = :padronizacao";
        $paramsFilhos['padronizacao'] = $filtroPadronizacao;
    }
    $whereFilhosClause = implode(" AND ", $whereFilhos);

    $queryFilhos = "
        SELECT te.id_material, te.descricao, te.protheus, te.saldo, te.consumo, cm.id_grupo,
               ctc.nome_tipo AS tipo_compra, cp.nome_padrao AS padronizacao
        FROM tasy_estoque te
        LEFT JOIN config_materiais cm ON te.id_material = cm.id_material AND cm.id_usuario = :uid_cm
        LEFT JOIN config_tipos_compra ctc ON cm.id_tipo_compra = ctc.id AND ctc.id_usuario = :uid_ctc
        LEFT JOIN config_padronizacoes cp ON cm.id_padronizacao = cp.id AND cp.id_usuario = :uid_cp
        WHERE $whereFilhosClause
        ORDER BY cm.id_grupo, te.descricao ASC
    ";
    
    $stmtFilhos = $pdo->prepare($queryFilhos);
    $stmtFilhos->execute($paramsFilhos);
    $filhosPorGrupo = [];
    foreach ($stmtFilhos->fetchAll() as $filho) {
        $filhosPorGrupo[$filho['id_grupo']][] = $filho;
    }
} catch (PDOException $e) {
    $itensEstoque = [];
    $filhosPorGrupo = [];
    error_log("Erro em compras: " . $e->getMessage() . " | Query: " . ($query ?? '') . " | Params: " . json_encode($params ?? []) . " | QueryFilhos: " . ($queryFilhos ?? '') . " | ParamsFilhos: " . json_encode($paramsFilhos ?? []));
    $erroQuery = "Erro ao carregar dados.";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HGestor - Pedido de Compras</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon-16x16.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo filemtime('../css/estilos.css'); ?>">
    <style>
        .badge-copiado {
            background: #d1fae5 !important;
            color: #065f46 !important;
            border-left: 3px solid #10b981;
        }

        .badge-copiado::after {
            content: ' ✓';
        }

        .col-qtd input[type="number"] {
            width: 80px;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            text-align: right;
            background: #f8fafc;
            transition: border-color .15s, background .15s;
        }

        .col-qtd input[type="number"]:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fff;
        }

        .col-qtd input[type="number"].preenchido {
            border-color: #10b981;
            background: #f0fdf4;
            font-weight: 600;
        }

        .protheus-clicavel {
            cursor: pointer;
            font-family: monospace;
            font-size: 0.85rem;
            padding: 3px 8px;
            border-radius: 4px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            transition: background .15s;
            display: inline-block;
        }

        .protheus-clicavel:hover {
            background: #e0f2fe;
            border-color: #7dd3fc;
        }

        .protheus-clicavel.copiado {
            background: #d1fae5;
            border-color: #6ee7b7;
            color: #065f46;
        }

        .painel-pedido {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .painel-pedido label {
            font-size: 0.8rem;
            color: #6b7280;
            display: block;
            margin-bottom: 4px;
        }

        .painel-pedido input[type="text"] {
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            width: 200px;
            max-width: 100%;
            box-sizing: border-box;
        }

        .contador-itens {
            font-size: 0.8rem;
            color: #6b7280;
            margin-left: auto;
        }

        .contador-itens strong {
            color: #1d4ed8;
        }

        .urgencia-critica {
            border-left: 3px solid #ef4444 !important;
        }

        .urgencia-alerta {
            border-left: 3px solid #f59e0b !important;
        }

        .th-sugestao {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .th-solicitado {
            background: #f0fdf4;
            color: #065f46;
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

            <?php if (isset($erroQuery)): ?>
                <div class="alerta alerta-erro"><?php echo htmlspecialchars($erroQuery); ?></div>
            <?php endif; ?>

            <?php if ($totalEstoqueUsuario === 0): ?>
                <section class="card-dados card-estado-vazio" style="margin-top: 15px; border-left: 4px solid var(--accent); box-shadow: var(--shadow-md);">
                    <div class="estado-vazio">
                        <div class="icone">🛒</div>
                        <h4>Nenhum dado de estoque disponível</h4>
                        <p style="margin-bottom: 24px;">Para gerar e visualizar pedidos de compra, você precisa primeiro carregar os dados de estoque no painel principal.</p>
                        <a href="dashboard.php" class="btn-acao btn-sucesso"><i class="fa-solid fa-arrow-left"></i> Ir para o Dashboard</a>
                    </div>
                </section>
            <?php else: ?>
                <!-- Painel do pedido -->
                <section class="painel-pedido">
                <div>
                    <label>Nº Solicitação (Fluig) <span style="color:#9ca3af; font-size:0.75rem;">—
                            opcional</span></label>
                    <input type="text" id="input-fluig" placeholder="Ex: SOL-2026-001">
                </div>
                <button class="btn-acao btn-sucesso" style="padding:8px 16px; margin-top:16px;" onclick="salvarPedido()">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar Pedido
                </button>
                <button class="btn-acao btn-info" style="margin-top:16px;" onclick="exportarPDF()">
                    <i class="fa-solid fa-file-pdf"></i> Exportar PDF
                </button>
                <button class="btn-acao btn-perigo" style="margin-top:16px;" onclick="limparPedido()">
                    <i class="fa-solid fa-trash-can"></i> Limpar Pedido
                </button>
                <div class="contador-itens">
                    Itens preenchidos: <strong id="contador-preenchidos">0</strong>
                    &nbsp;|&nbsp;
                    Itens na lista: <strong><?php echo count($itensEstoque); ?></strong>
                </div>
            </section>
            <section class="card-dados">
                <div class="card-header-tabela">
                    <form method="GET" action="compras.php"
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
                                    <?php foreach ($padroes as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p['nome_padrao']); ?>" <?php echo $filtroPadronizacao === $p['nome_padrao'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['nome_padrao']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;">Cobertura Máxima (%)</label>
                                <input type="number" name="cobertura_max" value="<?php echo htmlspecialchars($filtroCoberturaMax); ?>" min="1" step="1" placeholder="Sem limite" class="select-param" style="width: 100px; padding: 5px 8px; font-size: 0.8rem; box-sizing: border-box; height: 30px;" onclick="this.select()">
                            </div>
                            <button type="submit" class="btn-acao" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
                            <?php if (!empty($filtroTipoCompra) || !empty($filtroPadronizacao) || $filtroCoberturaMax !== 150): ?>
                                <a href="compras.php" class="btn-acao btn-neutro"
                                    style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-xmark"></i> Limpar</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="busca-container">
                        <input type="text" id="input-busca" placeholder="Buscar por código, Protheus ou descrição...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                </div>

                <div class="legenda-urgencia"
                    style="font-size:0.78rem; color:#6b7280; padding:6px 0 10px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <span style="display:inline-flex;align-items:center;gap:4px;"><span
                            style="width:10px;height:10px;border-radius:2px;background:#ef4444;display:inline-block;"></span>
                        Cobertura &lt; 50%</span>
                    <span style="display:inline-flex;align-items:center;gap:4px;"><span
                            style="width:10px;height:10px;border-radius:2px;background:#f59e0b;display:inline-block;"></span>
                        Cobertura 50%–100%</span>
                    <span style="display:inline-flex;align-items:center;gap:4px;"><span
                            style="font-family:monospace;font-size:0.8rem;background:#f1f5f9;padding:1px 4px;border-radius:3px;border:1px solid #e2e8f0;">ABC123</span>
                        Clique no código para copiar</span>
                </div>

                <div class="table-responsive">
                    <table class="tabela-hgestor" id="tabela-compras">
                        <thead>
                            <tr>
                                <th onclick="ordenarTabela(0,'num')" style="cursor:pointer;">ID Material <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                <th onclick="ordenarTabela(1,'text')" style="cursor:pointer;">Protheus <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                <th onclick="ordenarTabela(2,'text')" style="cursor:pointer;">Descrição <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                <th onclick="ordenarTabela(3,'num')" class="txt-direita" style="cursor:pointer;">Saldo <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                <th onclick="ordenarTabela(4,'num')" class="txt-direita" style="cursor:pointer;">Consumo Mensal <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                <th onclick="ordenarTabela(5,'num')" class="txt-centro" style="cursor:pointer;">Cobertura % <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                <th onclick="ordenarTabela(6,'num')" class="txt-direita th-sugestao" style="cursor:pointer;">Sugestão <i class="fa-solid fa-sort" style="font-size: 0.75rem; margin-left: 4px; color: var(--text-muted);"></i></th>
                                <th class="txt-direita th-solicitado">Qtd. Solicitar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($itensEstoque)): ?>
                                <tr>
                                    <td colspan="8" class="txt-centro msg-vazia">
                                        Nenhum item com cobertura abaixo de <?php echo $filtroCoberturaMax; ?>%. Estoque adequado ou dados ainda não
                                        importados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($itensEstoque as $item):
                                    $saldo = (int) $item['saldo_total'];
                                    $consumo = (int) $item['consumo_total'];

                                    // Cobertura
                                    if ($saldo > 0 && $consumo === 0) {
                                        $coberturaTxt = 'Sem Consumo';
                                        $classeCobertura = 'cob-sem-consumo';
                                        $classeUrgencia = '';
                                        $pct = 999999;
                                    } elseif ($saldo === 0 && $consumo === 0) {
                                        $coberturaTxt = 'Sem Estoque';
                                        $classeCobertura = 'cob-sem-estoque';
                                        $classeUrgencia = '';
                                        $pct = 999999;
                                    } else {
                                        $pct = ($saldo / $consumo) * 100;
                                        $coberturaTxt = round($pct) . '%';
                                        if ($pct < 50) {
                                            $classeCobertura = 'cob-critica';
                                            $classeUrgencia = 'urgencia-critica';
                                        } elseif ($pct < 100) {
                                            $classeCobertura = 'cob-alerta';
                                            $classeUrgencia = 'urgencia-alerta';
                                        } else {
                                            $classeCobertura = 'cob-ok';
                                            $classeUrgencia = '';
                                        }
                                    }

                                    // Sugestão: consumo_mensal * 1.5, sendo 0 quando a cobertura for > 150%
                                    $sugestao = ($pct > 150) ? 0 : (int) ceil($consumo * 1.5);

                                    $ehGrupo = $item['exibicao_id'] === 'Agrupado';
                                    $idGrupo = $item['id_grupo'] ?? null;
                                    $filhos = ($ehGrupo && $idGrupo && isset($filhosPorGrupo[$idGrupo]))
                                        ? $filhosPorGrupo[$idGrupo] : [];

                                    $idMaterial = htmlspecialchars($item['exibicao_id']);
                                    $protheus = $ehGrupo ? 'Agrupado' : htmlspecialchars($item['protheus'] ?? '—');
                                    $descricao = htmlspecialchars($item['exibicao_nome']);
                                    $vTipoCompra = htmlspecialchars($item['tipo_compra'] ?? '');
                                    $vPadronizacao = htmlspecialchars($item['padronizacao'] ?? '');
                                    ?>
                                    <?php $isGrupoComFilhos = $ehGrupo && $filhos; ?>
                                    <tr class="linha-pai-zebra <?php echo $classeUrgencia; ?> <?php echo $isGrupoComFilhos ? 'linha-grupo' : ''; ?>"
                                        data-id-material="<?php echo $idMaterial; ?>" data-descricao="<?php echo $descricao; ?>"
                                        data-protheus="<?php echo $protheus; ?>" data-saldo="<?php echo $saldo; ?>"
                                        data-consumo="<?php echo $consumo; ?>" data-sugestao="<?php echo $sugestao; ?>"
                                        data-grupo="<?php echo $isGrupoComFilhos ? '1' : '0'; ?>"
                                        data-tipo-compra="<?php echo $vTipoCompra; ?>"
                                        data-padronizacao="<?php echo $vPadronizacao; ?>">

                                        <td><span class="txt-mutado"><?php echo $idMaterial; ?></span></td>

                                        <td>
                                            <?php if (!$ehGrupo && ($item['protheus'] ?? '')): ?>
                                                <span class="protheus-clicavel"
                                                    data-codigo="<?php echo htmlspecialchars($item['protheus']); ?>"
                                                    onclick="copiarProtheus(this, event)">
                                                    <?php echo htmlspecialchars($item['protheus']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="txt-mutado">Agrupado</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?php if ($ehGrupo && $filhos): ?>
                                                    <span class="icone-accordion" style="margin-right:6px;"><i class="fa-solid fa-chevron-right"></i></span>
                                                <?php endif; ?>
                                                <?php echo $descricao; ?>
                                            </strong>
                                        </td>

                                        <td class="txt-direita"><?php echo number_format($saldo, 0, ',', '.'); ?></td>
                                        <td class="txt-direita"><?php echo number_format($consumo, 0, ',', '.'); ?></td>

                                        <td class="txt-centro">
                                            <span class="badge-cobertura <?php echo $classeCobertura; ?>">
                                                <?php echo $coberturaTxt; ?>
                                            </span>
                                        </td>

                                        <td class="txt-direita" style="color:#1d4ed8; font-weight:600;">
                                            <?php echo number_format($sugestao, 0, ',', '.'); ?>
                                        </td>

                                        <td class="txt-direita col-qtd" onclick="event.stopPropagation()">
                                            <?php if (!$ehGrupo): ?>
                                                <input type="number" min="0" step="1" placeholder="0" class="input-qtd"
                                                    data-id-material="<?php echo $idMaterial; ?>"
                                                    onchange="atualizarContador(this)">
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php if ($ehGrupo && $filhos):
                                        foreach ($filhos as $filho):
                                            $sF = (int) $filho['saldo'];
                                            $cF = (int) $filho['consumo'];
                                            if ($sF > 0 && $cF === 0) {
                                                $cobTxtF = 'Sem Consumo';
                                                $cobClsF = 'cob-sem-consumo';
                                                $pctF = 999999;
                                            } elseif ($sF === 0 && $cF === 0) {
                                                $cobTxtF = 'Sem Estoque';
                                                $cobClsF = 'cob-sem-estoque';
                                                $pctF = 999999;
                                            } else {
                                                $pctF = ($sF / $cF) * 100;
                                                $cobTxtF = round($pctF) . '%';
                                                if ($pctF < 50)
                                                    $cobClsF = 'cob-critica';
                                                elseif ($pctF < 100)
                                                    $cobClsF = 'cob-alerta';
                                                else
                                                    $cobClsF = 'cob-ok';
                                            }
                                            $sugF = ($pctF > 150) ? 0 : (int) ceil($cF * 1.5);
                                            $vTipoCompraF = htmlspecialchars($filho['tipo_compra'] ?? '');
                                            $vPadronizacaoF = htmlspecialchars($filho['padronizacao'] ?? '');
                                            ?>
                                            <tr class="linha-filho" style="display:none; background:#f8fafc;"
                                                data-id-material="<?php echo htmlspecialchars($filho['id_material']); ?>"
                                                data-descricao="<?php echo htmlspecialchars($filho['descricao']); ?>"
                                                data-protheus="<?php echo htmlspecialchars($filho['protheus'] ?? ''); ?>"
                                                data-saldo="<?php echo $sF; ?>" data-consumo="<?php echo $cF; ?>"
                                                data-sugestao="<?php echo $sugF; ?>" data-tipo-compra="<?php echo $vTipoCompraF; ?>"
                                                data-padronizacao="<?php echo $vPadronizacaoF; ?>">

                                                <td><span
                                                        class="txt-mutado"><?php echo htmlspecialchars($filho['id_material']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($filho['protheus'] ?? ''): ?>
                                                        <span class="protheus-clicavel"
                                                            data-codigo="<?php echo htmlspecialchars($filho['protheus']); ?>"
                                                            onclick="copiarProtheus(this, event)">
                                                            <?php echo htmlspecialchars($filho['protheus']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="txt-mutado">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding-left:32px;"><?php echo htmlspecialchars($filho['descricao']); ?></td>
                                                <td class="txt-direita"><?php echo number_format($sF, 0, ',', '.'); ?></td>
                                                <td class="txt-direita"><?php echo number_format($cF, 0, ',', '.'); ?></td>
                                                <td class="txt-centro">
                                                    <span class="badge-cobertura <?php echo $cobClsF; ?>"><?php echo $cobTxtF; ?></span>
                                                </td>
                                                <td class="txt-direita" style="color:#1d4ed8; font-weight:600;">
                                                    <?php echo number_format($sugF, 0, ',', '.'); ?>
                                                </td>
                                                <td class="txt-direita col-qtd" onclick="event.stopPropagation()">
                                                    <input type="number" min="0" step="1" placeholder="0" class="input-qtd"
                                                        data-id-material="<?php echo htmlspecialchars($filho['id_material']); ?>"
                                                        onchange="atualizarContador(this)">
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>

                                <?php endforeach; ?>
                            <?php endif; ?>
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
        // ── Copiar Protheus ──────────────────────────────────────────────
        function copiarProtheus(el, event) {
            event.stopPropagation();
            const codigo = el.dataset.codigo;
            navigator.clipboard.writeText(codigo).then(() => {
                el.classList.add('copiado');
                el.title = 'Copiado!';
                // Persiste visualmente (não remove)
            }).catch(() => {
                // Fallback para navegadores sem clipboard API
                const ta = document.createElement('textarea');
                ta.value = codigo;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                el.classList.add('copiado');
            });
        }

        // ── Contador de itens preenchidos ────────────────────────────────
        function atualizarContador(input) {
            input.classList.toggle('preenchido', parseInt(input.value) > 0);
            const total = document.querySelectorAll('.input-qtd').length;
            const preenchidos = document.querySelectorAll('.input-qtd.preenchido').length;
            document.getElementById('contador-preenchidos').textContent = preenchidos;
        }

        // ── Busca ────────────────────────────────────────────────────────
        function filtrarTabelaFrontend() {
            const termo = document.getElementById('input-busca').value.toLowerCase();

            // Primeiro passo: Filtrar e avaliar todas as linhas filhas de forma isolada por termo de busca
            document.querySelectorAll('#tabela-compras tbody tr.linha-filho').forEach(tr => {
                const textoBusca = (
                    tr.children[0].innerText + ' ' +
                    tr.children[1].innerText + ' ' +
                    tr.children[2].innerText
                ).toLowerCase();
                const atendeBusca = textoBusca.includes(termo);
                tr.dataset.atendeFiltroAbsoluto = atendeBusca ? "true" : "false";
                tr.style.display = 'none';
            });

            // Segundo passo: Filtrar as linhas pai (itens avulsos e cabeçalhos de grupos)
            document.querySelectorAll('#tabela-compras tbody tr').forEach(tr => {
                if (tr.querySelector('.msg-vazia')) return;
                if (tr.classList.contains('linha-filho')) return;

                const textoBusca = (
                    tr.children[0].innerText + ' ' +
                    tr.children[1].innerText + ' ' +
                    tr.children[2].innerText
                ).toLowerCase();
                const atendeBusca = textoBusca.includes(termo);
                const ehGrupo = tr.classList.contains('linha-grupo');
                
                if (ehGrupo) {
                    let temFilhoVisivel = false;
                    let irmao = tr.nextElementSibling;
                    while (irmao && irmao.classList.contains('linha-filho')) {
                        if (irmao.dataset.atendeFiltroAbsoluto === "true") {
                            temFilhoVisivel = true;
                        }
                        irmao = irmao.nextElementSibling;
                    }
                    tr.style.display = (atendeBusca || temFilhoVisivel) ? '' : 'none';
                    tr.classList.remove('grupo-expandido-pai');
                    const icone = tr.querySelector('.icone-accordion');
                    if (icone) icone.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                } else {
                    tr.style.display = atendeBusca ? '' : 'none';
                }
            });
        }

        document.getElementById('input-busca').addEventListener('keyup', filtrarTabelaFrontend);
        
        document.getElementsByName('tipo_compra')[0].addEventListener('change', function() {
            this.form.submit();
        });
        document.getElementsByName('padronizacao')[0].addEventListener('change', function() {
            this.form.submit();
        });

        const inputCob = document.getElementsByName('cobertura_max')[0];
        if (inputCob) {
            inputCob.addEventListener('change', function() {
                this.form.submit();
            });
        }

        // ── Accordion grupos ─────────────────────────────────────────────
        function toggleGrupo(linhaPai) {
            const icone = linhaPai.querySelector('.icone-accordion');
            const aberto = linhaPai.classList.contains('grupo-expandido-pai');
            linhaPai.classList.toggle('grupo-expandido-pai', !aberto);
            if (icone) icone.innerHTML = aberto ? '<i class="fa-solid fa-chevron-right"></i>' : '<i class="fa-solid fa-chevron-down"></i>';
            let el = linhaPai.nextElementSibling;
            while (el && el.classList.contains('linha-filho')) {
                if (aberto) {
                    el.style.display = 'none';
                    el.classList.remove('grupo-expandido-filho');
                } else {
                    // Só exibe o filho na expansão se ele passar pelo crivo do filtro absoluto atual
                    const atendeFiltro = el.dataset.atendeFiltroAbsoluto !== "false";
                    el.style.display = atendeFiltro ? '' : 'none';
                    el.classList.toggle('grupo-expandido-filho', atendeFiltro);
                }
                el = el.nextElementSibling;
            }
        }

        // ── Ordenação ────────────────────────────────────────────────────
        let direcoesOrdenacao = {};
        function ordenarTabela(colunaIndex, tipo) {
            const tabela = document.getElementById('tabela-compras');
            const tbody = tabela.querySelector('tbody');
            const linhas = Array.from(tbody.querySelectorAll('tr'));
            if (linhas.length === 1 && linhas[0].querySelector('.msg-vazia')) return;

            const dir = direcoesOrdenacao[colunaIndex] === 'asc' ? 'desc' : 'asc';
            direcoesOrdenacao = { [colunaIndex]: dir };

            const blocos = [];
            let bloco = null;
            linhas.forEach(linha => {
                if (linha.classList.contains('linha-filho')) {
                    if (bloco) bloco.filhos.push(linha);
                } else {
                    bloco = { pai: linha, filhos: [] };
                    blocos.push(bloco);
                }
            });

            blocos.sort((a, b) => {
                let vA = a.pai.children[colunaIndex]?.innerText.trim() ?? '';
                let vB = b.pai.children[colunaIndex]?.innerText.trim() ?? '';
                if (tipo === 'num') {
                    vA = parseFloat(vA.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
                    vB = parseFloat(vB.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
                } else {
                    vA = vA.toLowerCase();
                    vB = vB.toLowerCase();
                }
                if (vA < vB) return dir === 'asc' ? -1 : 1;
                if (vA > vB) return dir === 'asc' ? 1 : -1;
                return 0;
            });

            while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
            blocos.forEach(b => {
                tbody.appendChild(b.pai);
                b.filhos.forEach(f => tbody.appendChild(f));
            });
        }

        // ── Toasts ───────────────────────────────────────────────────────
        function lancarAlerta(mensagem, tipo = 'info', tempo = 4000) {
            const container = document.getElementById('container-toasts-global');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `alerta-toast toast-${tipo}`;
            const icones = {
                sucesso: '<i class="fa-solid fa-circle-check"></i>',
                erro: '<i class="fa-solid fa-circle-xmark"></i>',
                alerta: '<i class="fa-solid fa-triangle-exclamation"></i>',
                info: '<i class="fa-solid fa-circle-info"></i>'
            };
            toast.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;">
                <span>${icones[tipo] || icones.info}</span><span>${mensagem}</span>
            </div>
            <button class="toast-fechar" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(() => toast.remove(), 300);
            }, tempo);
        }

        // ── Carregar pedido salvo ────────────────────────────────────────
        async function carregarPedidoSalvo() {
            try {
                const res = await fetch('../acoes/salvar_pedido_compras.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'carregar' })
                });
                const data = await res.json();
                if (!data.pedido || !data.itens.length) return;

                // Preenche Fluig
                if (data.pedido.numero_fluig) {
                    document.getElementById('input-fluig').value = data.pedido.numero_fluig;
                }

                // Preenche inputs de quantidade
                data.itens.forEach(item => {
                    const input = document.querySelector(`.input-qtd[data-id-material="${CSS.escape(item.id_material)}"]`);
                    if (input && item.quantidade_solicitada > 0) {
                        input.value = item.quantidade_solicitada;
                        input.classList.add('preenchido');
                    }
                });

                // Atualiza contador
                const preenchidos = document.querySelectorAll('.input-qtd.preenchido').length;
                document.getElementById('contador-preenchidos').textContent = preenchidos;

                // Marca protheus copiados persistidos
                data.itens.forEach(item => {
                    if (item.protheus) {
                        document.querySelectorAll(`.protheus-clicavel[data-codigo="${item.protheus}"]`)
                            .forEach(el => el.classList.add('copiado'));
                    }
                });

            } catch (e) { /* silencioso */ }
        }

        // ── Salvar pedido ────────────────────────────────────────────────
        async function salvarPedido() {
            const itens = [];
            document.querySelectorAll('.input-qtd.preenchido').forEach(input => {
                const tr = input.closest('tr');
                itens.push({
                    id_material: tr.dataset.idMaterial,
                    descricao: tr.dataset.descricao,
                    protheus: tr.dataset.protheus,
                    saldo_atual: parseInt(tr.dataset.saldo) || 0,
                    consumo_mensal: parseInt(tr.dataset.consumo) || 0,
                    sugestao_compra: parseInt(tr.dataset.sugestao) || 0,
                    quantidade_solicitada: parseInt(input.value) || 0
                });
            });

            if (!itens.length) {
                lancarAlerta('Preencha a quantidade de pelo menos um item antes de salvar.', 'alerta');
                return;
            }

            try {
                const res = await fetch('../acoes/salvar_pedido_compras.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        acao: 'salvar',
                        numero_fluig: document.getElementById('input-fluig').value,
                        itens
                    })
                });
                const data = await res.json();
                if (data.sucesso) lancarAlerta('Pedido salvo com sucesso!', 'sucesso');
                else lancarAlerta(data.erro || 'Erro ao salvar.', 'erro');
            } catch (e) {
                lancarAlerta('Erro de comunicação com o servidor.', 'erro');
            }
        }

        // ── Exportar PDF (frontend via print) ────────────────────────────
        async function exportarPDF() {
            const inputs = document.querySelectorAll('.input-qtd.preenchido');
            if (!inputs.length) {
                lancarAlerta('Preencha a quantidade de pelo menos um item antes de exportar.', 'alerta');
                return;
            }

            await salvarPedido();

            const nomeEstoque = <?php echo json_encode($nomeEstoque); ?>;
            const numeroFluig = document.getElementById('input-fluig').value.trim() || '—';
            const dataGeracao = new Date().toLocaleString('pt-BR');

            const linhas = [];
            inputs.forEach(input => {
                const tr = input.closest('tr');
                linhas.push({
                    protheus: tr.dataset.protheus || '—',
                    descricao: tr.dataset.descricao || '—',
                    saldo: parseInt(tr.dataset.saldo) || 0,
                    sugestao: parseInt(tr.dataset.sugestao) || 0,
                    qtd: parseInt(input.value) || 0
                });
            });

            const fmt = n => n.toLocaleString('pt-BR');

            const linhasHtml = linhas.map((item, i) => `
            <tr style="background:${i % 2 === 0 ? '#f8fafc' : '#ffffff'}">
                <td>${item.protheus}</td>
                <td>${item.descricao}</td>
                <td class="num">${fmt(item.saldo)}</td>
                <td class="num">${fmt(item.sugestao)}</td>
                <td class="num bold">${fmt(item.qtd)}</td>
            </tr>`).join('');

            const html = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Pedido de Compras - HGestor</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; color: #111827; font-size: 12px; }
    .header { background: #1e3a5f; color: #fff; padding: 20px 28px; margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; }
    .header-titulo { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
    .header-sub { font-size: 10px; color: #93c5fd; margin-top: 3px; }
    .header-right { text-align: right; font-size: 11px; color: #e0f2fe; }
    .infos { display: flex; gap: 16px; padding: 0 28px 16px; }
    .info-box { flex:1; background: #f1f5f9; border-left: 4px solid #1e3a5f; padding: 10px 14px; border-radius: 0 4px 4px 0; }
    .info-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px; }
    .info-valor { font-size: 13px; font-weight: bold; color: #111827; }
    .resumo { margin: 0 28px 12px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 8px 14px; font-size: 11px; color: #1e40af; }
    .tabela-wrap { padding: 0 28px; }
    table { width: 100%; border-collapse: collapse; border: 1px solid #e2e8f0; font-size: 11px; }
    thead tr { background: #1e3a5f; color: #fff; }
    thead th { padding: 9px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
    thead th.num { text-align: right; }
    td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
    td.num { text-align: right; color: #374151; }
    td.bold { font-weight: bold; color: #1d4ed8; }
    .footer { margin: 24px 28px 0; padding-top: 14px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #9ca3af; text-align: center; }
    @media print {
        @page { margin: 12mm; size: A4; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
    <div class="header">
        <div>
            <div class="header-titulo">HGestor</div>
            <div class="header-sub">Sistema de Gestão de Estoque Hospitalar</div>
        </div>
        <div class="header-right">
            <div style="font-size:13px;font-weight:bold;">PEDIDO DE COMPRAS</div>
            <div style="margin-top:4px;">Gerado em: ${dataGeracao}</div>
        </div>
    </div>

    <div class="infos">
        <div class="info-box">
            <div class="info-label">Estoque / Setor</div>
            <div class="info-valor">${nomeEstoque}</div>
        </div>
        <div class="info-box">
            <div class="info-label">Nº Solicitação (Fluig)</div>
            <div class="info-valor">${numeroFluig}</div>
        </div>
    </div>

    <div class="resumo">
        Total de itens solicitados: <strong>${linhas.length}</strong>
    </div>

    <div class="tabela-wrap">
        <table>
            <thead>
                <tr>
                    <th>Cód. Protheus</th>
                    <th>Descrição</th>
                    <th class="num">Saldo Atual</th>
                    <th class="num">Sugestão</th>
                    <th class="num">Qtd. Solicitada</th>
                </tr>
            </thead>
            <tbody>${linhasHtml}</tbody>
        </table>
    </div>

    <div class="footer">
        Documento gerado automaticamente pelo HGestor &mdash; HorusDEV &bull; ${dataGeracao}
    </div>
</body>
</html>`;

            const janela = window.open('', '_blank', 'width=900,height=700');
            janela.document.write(html);
            janela.document.close();
            janela.onload = () => {
                janela.focus();
                janela.print();
            };
        }

        // ── Limpar pedido ────────────────────────────────────────────────
        function limparPedido() {
            document.getElementById('modal-confirmar-limpar').showModal();
        }

        async function confirmarLimparPedido() {
            document.getElementById('modal-confirmar-limpar').close();
            try {
                await fetch('../acoes/salvar_pedido_compras.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'limpar' })
                });
            } catch (e) { }
            document.querySelectorAll('.input-qtd').forEach(i => {
                i.value = '';
                i.classList.remove('preenchido');
            });
            document.querySelectorAll('.protheus-clicavel.copiado').forEach(el => el.classList.remove('copiado'));
            document.getElementById('input-fluig').value = '';
            document.getElementById('contador-preenchidos').textContent = '0';
            lancarAlerta('Pedido limpo.', 'info');
        }

        // ── Init ─────────────────────────────────────────────────────────
        window.addEventListener('DOMContentLoaded', () => {
            carregarPedidoSalvo();
            document.querySelector('tbody').addEventListener('click', function (e) {
                const tr = e.target.closest('tr[data-grupo="1"]');
                if (tr) toggleGrupo(tr);
            });
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('sucesso')) {
                lancarAlerta(urlParams.get('sucesso'), 'sucesso');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            if (urlParams.has('erro')) {
                lancarAlerta(urlParams.get('erro'), 'erro');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
    <dialog id="modal-confirmar-limpar"
        style="border:none; border-radius:10px; padding:0; max-width:380px; width:100%; box-shadow:0 8px 32px rgba(0,0,0,0.12);"
        onclick="event.target===this&&this.close()">
        <div style="padding:24px;">
            <h3 style="color:#dc2626; margin:0 0 10px;"><i class="fa-solid fa-trash-can"></i> Limpar Pedido</h3>
        <p style="color:#6b7280; font-size:0.88rem; line-height:1.5; margin:0 0 20px;">
            Todos os valores preenchidos serão removidos.<br>Esta ação não pode ser desfeita.
        </p>
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button onclick="document.getElementById('modal-confirmar-limpar').close()" class="btn-acao btn-neutro" style="padding:8px 16px;">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button onclick="confirmarLimparPedido()" class="btn-acao btn-perigo" style="padding:8px 16px;">
                <i class="fa-solid fa-trash-can"></i> Confirmar
            </button>
        </div>
    </div>
</dialog>

</html>