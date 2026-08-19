<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];
$nomeEstoque = $_SESSION['user']['estoque_nome'] ?? '';
$loginUser = $_SESSION['user']['login'] ?? 'Usuário';

$idHistorico = isset($_GET['id_historico']) ? (int) $_GET['id_historico'] : null;
$modoLeitura = false;

if ($idHistorico) {
    $stmtInv = $pdo->prepare("
         SELECT pi.*, 
                COUNT(pii.id) AS total_itens,
                SUM(CASE WHEN pii.quantidade_bipada > 0 THEN 1 ELSE 0 END) AS itens_bipados
         FROM pre_inventario pi
         LEFT JOIN pre_inventario_itens pii ON pii.id_inventario = pi.id
         WHERE pi.id_usuario = :uid AND pi.id = :id_hist
         GROUP BY pi.id
     ");
    $stmtInv->execute(['uid' => $idUsuario, 'id_hist' => $idHistorico]);
    $inventarioAtivo = $stmtInv->fetch();
    $modoLeitura = true;
} else {
    $stmtInv = $pdo->prepare("
         SELECT pi.*, 
                COUNT(pii.id) AS total_itens,
                SUM(CASE WHEN pii.quantidade_bipada > 0 THEN 1 ELSE 0 END) AS itens_bipados
         FROM pre_inventario pi
         LEFT JOIN pre_inventario_itens pii ON pii.id_inventario = pi.id
         WHERE pi.id_usuario = :uid AND pi.status = 'ativo'
         GROUP BY pi.id
         ORDER BY pi.criado_em DESC
         LIMIT 1
     ");
    $stmtInv->execute(['uid' => $idUsuario]);
    $inventarioAtivo = $stmtInv->fetch();
}

$idInventario = $inventarioAtivo['id'] ?? null;
$totalItens = (int) ($inventarioAtivo['total_itens'] ?? 0);
$itensBipados = (int) ($inventarioAtivo['itens_bipados'] ?? 0);
$pctConcluido = $totalItens > 0 ? round(($itensBipados / $totalItens) * 100) : 0;

$stmtHistorico = $pdo->prepare("
    SELECT pi.*, 
           COUNT(pii.id) AS total_itens
    FROM pre_inventario pi
    LEFT JOIN pre_inventario_itens pii ON pii.id_inventario = pi.id
    WHERE pi.id_usuario = :uid AND pi.status != 'ativo'
    GROUP BY pi.id
    ORDER BY pi.criado_em DESC
");
$stmtHistorico->execute(['uid' => $idUsuario]);
$inventariosAntigos = $stmtHistorico->fetchAll();

$itens = [];
if ($idInventario) {
    $stmtItens = $pdo->prepare("
        SELECT pii.*,
               GROUP_CONCAT(pib.ds_barras ORDER BY pib.id SEPARATOR '|') AS barcodes,
               GROUP_CONCAT(pib.ds_lote   ORDER BY pib.id SEPARATOR '|') AS lotes
        FROM pre_inventario_itens pii
        LEFT JOIN pre_inventario_barcodes pib ON pib.id_item = pii.id
        WHERE pii.id_inventario = :inv
        GROUP BY pii.id
        ORDER BY pii.ds_material ASC
    ");
    $stmtItens->execute(['inv' => $idInventario]);
    $itens = $stmtItens->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HGestor - Pré-Inventário</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon-16x16.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo filemtime('../css/estilos.css'); ?>">
    <style>
        /* ── Dashboard de Progresso ── */
        .inv-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 0;
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

        /* Barra de progresso */
        .progresso-wrap {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
        }

        .progresso-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            color: #374151;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .progresso-bar {
            height: 10px;
            background: #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }

        .progresso-fill {
            height: 100%;
            border-radius: 5px;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            transition: width .4s ease;
        }

        /* ── Área de Bipagem ── */
        .painel-bipagem {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .painel-bipagem .grupo-input {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .painel-bipagem label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
        }

        .input-barcode {
            padding: 10px 14px;
            border: 2px solid #3b82f6;
            border-radius: 6px;
            font-size: 1rem;
            width: 260px;
            outline: none;
            background: #eff6ff;
            transition: border-color .15s, background .15s;
        }

        .input-barcode:focus {
            border-color: #1d4ed8;
            background: #fff;
        }

        .input-barcode.erro {
            border-color: #ef4444;
            background: #fef2f2;
            animation: shake .3s ease;
        }

        .input-barcode.ok {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .input-qtd-bipagem {
            width: 140px;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1rem;
            text-align: center;
        }

        /* Switch ON/OFF */
        .switch-automotico {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
        }

        .switch-wrapper {
            position: relative;
            width: 44px;
            height: 22px;
        }

        .switch-wrapper input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider-switch {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 22px;
        }

        .slider-switch:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked+.slider-switch {
            background-color: #3b82f6;
        }

        input:checked+.slider-switch:before {
            transform: translateX(22px);
        }

        .feedback-bipagem {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 6px;
            width: 100%;
            text-align: center;
            box-sizing: border-box;
        }

        .feedback-ok {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .feedback-erro {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .feedback-vazio {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0)
            }

            25% {
                transform: translateX(-6px)
            }

            75% {
                transform: translateX(6px)
            }
        }

        /* ── Tabela de conferência ── */
        .dif-zero {
            color: #065f46;
            background: #d1fae5;
        }

        .dif-alerta {
            color: #92400e;
            background: #fef3c7;
        }

        .dif-critica {
            color: #991b1b;
            background: #fee2e2;
        }

        .dif-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            display: inline-block;
        }

        .linha-bipada-recente {
            animation: blink-verde .8s ease;
        }

        @keyframes blink-verde {
            0% {
                background: #d1fae5;
            }

            100% {
                background: inherit;
            }
        }

        /* Import/Export panel */
        .painel-acoes {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
        }

        .painel-acoes .grupo-upload {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .painel-acoes label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
        }

        /* Estado vazio */
        .estado-vazio {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .estado-vazio .icone {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        .estado-vazio h4 {
            color: #374151;
            margin-bottom: 6px;
        }

        /* Badge autosave */
        #badge-autosave {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 10px;
            background: #f3f4f6;
            color: #6b7280;
            transition: all .3s;
        }

        #badge-autosave.salvando {
            background: #fef3c7;
            color: #92400e;
        }

        #badge-autosave.salvo {
            background: #d1fae5;
            color: #065f46;
        }

        /* Estilos do Modal de Log */
        .badge-log {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .badge-log-bip {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .badge-log-manual {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #ffedd5;
        }

        .tabela-log {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin-top: 10px;
        }

        .tabela-log th {
            background: #f8fafc;
            padding: 8px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .tabela-log td {
            padding: 8px;
            border-bottom: 1px solid #edf2f7;
        }

        /* Status do Histórico */
        .status-finalizado {
            background: #d1fae5;
            color: #065f46;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.75rem;
        }

        .status-cancelado {
            background: #f3f4f6;
            color: #374151;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.75rem;
        }

        .acoes-inline {
            display: flex;
            gap: 4px;
            justify-content: center;
            flex-wrap: nowrap;
        }

        @media (max-width: 768px) {
            .painel-bipagem {
                flex-direction: column;
                align-items: stretch;
            }

            .input-barcode {
                width: 100%;
            }

            .inv-dashboard {
                grid-template-columns: 1fr 1fr;
            }

            .painel-acoes {
                flex-direction: column;
                align-items: stretch;
            }

            .painel-acoes .grupo-upload {
                width: 100%;
            }

            .painel-acoes .grupo-upload > div {
                flex-wrap: wrap;
            }

            .painel-acoes .grupo-upload input[type="file"] {
                flex: 1;
                min-width: 0;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php include 'componente_header.php'; ?>
    <div id="loading-global" class="loading-overlay">
        <div class="loading-box">
            <div class="loading-texto">⚡ <span id="loading-mensagem-texto">Processando...</span></div>
            <div class="barra-progresso-container">
                <div id="loading-linha" class="barra-progresso-linha"></div>
            </div>
            <div id="loading-pct" class="loading-porcentagem">0%</div>
        </div>
    </div>
    <div id="container-toasts-global" class="container-toasts"></div>

    <div class="dashboard-container">
        <main class="conteudo-principal">
            <?php if ($modoLeitura): ?>
                <div
                    style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 14px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div style="color: #b45309; font-weight: 600; font-size: 0.9rem;">
                        ⚠️ Você está visualizando um inventário antigo (Modo de Apenas Leitura). Novas bipagens estão
                        bloqueadas.
                    </div>
                    <a href="pre_inventario.php" class="btn-acao btn-neutro"
                        style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;">
                        <i class="fa-solid fa-arrow-left"></i> Voltar ao Inventário Ativo
                    </a>
                </div>
            <?php endif; ?>
            <!-- ── PAINEL DE AÇÕES ── -->
            <section class="painel-acoes">
                <div class="grupo-upload">
                    <label>📥 Importar Lista (CSV ou Excel)</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="file" id="input-arquivo-inv" accept=".csv,.xls,.xlsx"
                            style="padding:6px; border:1px dashed #cbd5e1; border-radius:4px; font-size:0.82rem; background:#f8fafc;">
                        <button class="btn-acao btn-sucesso" style="padding:8px 14px;" onclick="importarArquivo()"><i class="fa-solid fa-file-import"></i>
                            Importar</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-top:16px;">
                    <button class="btn-acao btn-info" style="padding:8px 14px;" onclick="exportarPDF()" <?php echo !$idInventario ? 'disabled title="Importe um inventário primeiro"' : ''; ?>>
                        <i class="fa-solid fa-file-pdf"></i> Exportar PDF
                    </button>
                    <?php if ($idInventario): ?>
                        <button class="btn-acao btn-perigo" style="padding:8px 14px; font-size:0.82rem;"
                            onclick="abrirModalEncerrar()">
                            <i class="fa-solid fa-flag-checkered"></i> Encerrar Inventário
                        </button>
                    <?php endif; ?>
                    <span id="badge-autosave">● Auto-save ativo</span>
                </div>
            </section>
            <section class="card-dados" style="margin-top: 24px;">
                <div class="card-header-tabela">
                    <h3>📁 Histórico de Inventários Anteriores</h3>
                </div>
                <div class="table-responsive">
                    <table class="tabela-hgestor">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descrição / Identificação</th>
                                <th>Data de Criação</th>
                                <th>Total Itens</th>
                                <th class="txt-centro">Status</th>
                                <th class="txt-centro">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventariosAntigos)): ?>
                                <tr>
                                    <td colspan="6" class="txt-centro msg-vazia">Nenhum inventário antigo no histórico.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inventariosAntigos as $invOld):
                                    $dataCriacao = date('d/m/Y H:i', strtotime($invOld['criado_em']));
                                    $statusClass = $invOld['status'] === 'finalizado' ? 'status-finalizado' : 'status-cancelado';
                                    $statusTxt = $invOld['status'] === 'finalizado' ? 'Finalizado' : 'Cancelado';
                                    ?>
                                    <tr>
                                        <td><span class="txt-mutado">#
                                                <?php echo $invOld['id']; ?>
                                            </span></td>
                                        <td><strong>
                                                <?php echo htmlspecialchars($invOld['descricao']); ?>
                                            </strong></td>
                                        <td>
                                            <?php echo $dataCriacao; ?>
                                        </td>
                                        <td>
                                            <?php echo $invOld['total_itens']; ?> itens
                                        </td>
                                        <td class="txt-centro">
                                            <span class="<?php echo $statusClass; ?>">
                                                <?php echo $statusTxt; ?>
                                            </span>
                                        </td>
                                        <td class="txt-centro">
                                            <div class="acoes-inline">
                                                <button class="btn-acao btn-info"
                                                    style="padding:4px 8px; font-size:0.75rem;"
                                                    title="Carregar para visualizar e exportar"
                                                    onclick="carregarInventarioAntigo(<?php echo $invOld['id']; ?>)">
                                                    <i class="fa-solid fa-eye"></i> Ver dados
                                                </button>
                                                <button class="btn-acao btn-perigo"
                                                    style="padding:4px 8px; font-size:0.75rem;"
                                                    title="Excluir permanentemente"
                                                    onclick="abrirModalDeletar(<?php echo $invOld['id']; ?>)">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php if (!$idInventario): ?>
                <!-- Estado vazio -->
                <section class="card-dados card-estado-vazio" style="margin-top: 15px; border-left: 4px solid var(--accent); box-shadow: var(--shadow-md);">
                    <div class="estado-vazio">
                        <div class="icone">📋</div>
                        <h4>Nenhum inventário ativo</h4>
                        <p style="margin-bottom: 24px;">Importe a lista exportada do Tasy para iniciar a contagem.</p>

                        <div style="text-align: left; max-width: 650px; margin: 0 auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h5 style="color: #1e3a8a; font-size: 0.95rem; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                                <i class="fa-solid fa-book-open"></i> Como gerar o arquivo no Tasy (Relatório CSUP-332):
                            </h5>
                            <ol style="font-size: 0.85rem; line-height: 1.6; color: #4b5563; padding-left: 20px; display: flex; flex-direction: column; gap: 8px;">
                                <li>Acesse o relatório <strong>CSUP-332</strong> (Parâmetros - Saldo Estoque com Lote Fornecedor) no Tasy.</li>
                                <li>No campo <strong>Mês Referência</strong>, informe a data atual do inventário.</li>
                                <li>No campo <strong>Local de estoque</strong>, selecione o setor correto (ex: <em>HEM - CAF Agio</em>).</li>
                                <li>Marque a opção <strong>"Somente lotes com saldo"</strong> para evitar trazer itens zerados desnecessários.</li>
                                <li>Execute o relatório e, na barra de ferramentas do Tasy, clique no ícone de exportação para salvar o arquivo no formato <strong>Excel (.xls/.xlsx)</strong> ou <strong>CSV</strong>.</li>
                                <li>Com o arquivo salvo no seu computador, utilize o botão <strong>"Selecionar arquivo"</strong> no topo desta página e clique em <strong>"Importar"</strong>.</li>
                            </ol>
                        </div>
                    </div>
                </section>

            <?php else: ?>

                <!-- ── DASHBOARD KPIs ── -->
                <div class="inv-dashboard">
                    <div class="inv-kpi">
                        <span class="inv-kpi-label">Total de Itens</span>
                        <span class="inv-kpi-value" id="kpi-total"><?php echo $totalItens; ?></span>
                        <span class="inv-kpi-sub">materiais importados</span>
                    </div>
                    <div class="inv-kpi">
                        <span class="inv-kpi-label">Itens Bipados</span>
                        <span class="inv-kpi-value" id="kpi-bipados"
                            style="color:#10b981;"><?php echo $itensBipados; ?></span>
                        <span class="inv-kpi-sub">pelo menos 1 bipagem</span>
                    </div>
                    <div class="inv-kpi">
                        <span class="inv-kpi-label">Pendentes</span>
                        <span class="inv-kpi-value" id="kpi-pendentes"
                            style="color:#f59e0b;"><?php echo $totalItens - $itensBipados; ?></span>
                        <span class="inv-kpi-sub">sem bipagem</span>
                    </div>
                    <div class="inv-kpi">
                        <span class="inv-kpi-label">Diferenças</span>
                        <span class="inv-kpi-value" id="kpi-diferencas" style="color:#ef4444;">—</span>
                        <span class="inv-kpi-sub">itens com divergência</span>
                    </div>
                    <div class="progresso-wrap" style="grid-column: span 1;">
                        <div class="progresso-label">
                            <span>Progresso da Contagem</span>
                            <span id="kpi-pct"><?php echo $pctConcluido; ?>%</span>
                        </div>
                        <div class="progresso-bar">
                            <div class="progresso-fill" id="barra-progresso" style="width:<?php echo $pctConcluido; ?>%">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── PAINEL DE BIPAGEM ── -->
                <?php if (!$modoLeitura): ?>
                    <section class="painel-bipagem">
                        <div class="grupo-input">
                            <label for="input-qtd-bip">Quantidade por Bipagem</label>
                            <input type="number" id="input-qtd-bip" class="input-qtd-bipagem" value="1" min="1" step="1">
                        </div>
                        <div class="grupo-input" style="flex:1;">
                            <label for="input-bip">🔍 Código de Barras / Bipagem</label>
                            <input type="text" id="input-bip" class="input-barcode" placeholder="Bipe ou digite o código..."
                                autocomplete="off" autofocus>
                        </div>
                        <div class="grupo-input" style="justify-content: center; height: 42px; margin-bottom: 2px;">
                            <label class="switch-automotico">
                                <span>Bipagem Automática</span>
                                <div class="switch-wrapper">
                                    <input type="checkbox" id="chk-bip-auto" checked>
                                    <span class="slider-switch"></span>
                                </div>
                            </label>
                        </div>
                        <div id="feedback-bip" class="feedback-bipagem feedback-vazio">
                            Aguardando leitura...
                        </div>
                    </section>
                <?php endif; ?>

                <!-- ── TABELA DE CONFERÊNCIA ── -->
                <section class="card-dados">
                    <div class="card-header-tabela" style="display:flex; align-items:center; flex-wrap:wrap; gap:10px;">
                        <h3 style="margin:0; flex-shrink:0;">📊 Tabela de Conferência</h3>
                        <input type="text" id="filtro-busca-inv" placeholder="🔍 Buscar material..."
                            style="padding:7px 10px; border:1px solid #cbd5e1; border-radius:4px; font-size:0.85rem; flex:1; min-width:120px;">
                        <select id="filtro-status-inv" class="select-param" style="min-width:120px; flex:1;">
                            <option value="TODOS">Todos os Status</option>
                            <option value="OK">✅ Conferidos (dif=0)</option>
                            <option value="ALERTA">⚠️ Alerta</option>
                            <option value="CRITICO">🔴 Crítico</option>
                            <option value="PENDENTE">⏳ Pendentes</option>
                        </select>
                        <span class="status-banco" id="status-ultima-bipagem" style="margin-left:auto; flex-shrink:0;">Pronto para
                            contar</span>
                    </div>

                    <div class="table-responsive">
                        <table class="tabela-hgestor" id="tabela-inventario">
                            <thead>
                                <tr>
                                    <th onclick="ordenarInv(0,'num')" style="cursor:pointer;">Cód. Material ▲▼</th>
                                    <th onclick="ordenarInv(1,'text')" style="cursor:pointer;">Nome Material ▲▼</th>
                                    <th onclick="ordenarInv(2,'num')" class="txt-direita" style="cursor:pointer;">Saldo
                                        Sistema ▲▼</th>
                                    <th onclick="ordenarInv(3,'num')" class="txt-direita" style="cursor:pointer;">Qtd.
                                        Bipada ▲▼</th>
                                    <th onclick="ordenarInv(4,'num')" class="txt-centro" style="cursor:pointer;">Diferença
                                        ▲▼</th>
                                    <th class="txt-centro">Status</th>
                                    <th class="txt-centro" style="width: 90px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-inventario">
                                <?php if (empty($itens)): ?>
                                    <tr>
                                        <td colspan="7" class="txt-centro msg-vazia">Nenhum item carregado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($itens as $item):
                                        $saldo = (float) $item['qt_estoque_sistema'];
                                        $bipado = (float) $item['quantidade_bipada'];
                                        $dif = $saldo - $bipado;
                                        $difFmt = number_format(abs($dif), 0, ',', '.');
                                        $sinal = $dif > 0 ? '-' : ($dif < 0 ? '+' : '');
                                        $barcodes = $item['barcodes'] ?? '';
                                        ?>
                                        <tr class="linha-item-inv" data-id-item="<?php echo $item['id']; ?>"
                                            data-cd-material="<?php echo htmlspecialchars($item['cd_material']); ?>"
                                            data-saldo="<?php echo $saldo; ?>" data-bipado="<?php echo $bipado; ?>"
                                            data-barcodes="<?php echo htmlspecialchars($barcodes); ?>">
                                            <td><span
                                                    class="txt-mutado"><?php echo htmlspecialchars($item['cd_material']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['ds_material'] ?? ''); ?></td>
                                            <td class="txt-direita"><?php echo number_format($saldo, 0, ',', '.'); ?></td>
                                            <td class="txt-direita td-bipado"><?php echo number_format($bipado, 0, ',', '.'); ?>
                                            </td>
                                            <td class="txt-centro td-diferenca">
                                                <span class="dif-badge <?php
                                                if ($dif == 0)
                                                    echo 'dif-zero';
                                                elseif (abs($dif) <= 0)
                                                    echo 'dif-alerta';
                                                else
                                                    echo 'dif-critica';
                                                ?>">
                                                    <?php echo $dif == 0 ? '0' : $sinal . $difFmt; ?>
                                                </span>
                                            </td>
                                            <td class="txt-centro td-status">
                                                <?php if ($bipado == 0): ?>
                                                    <span class="badge-cobertura cob-vazia">Pendente</span>
                                                <?php elseif ($dif == 0): ?>
                                                    <span class="badge-cobertura cob-ok">Conferido</span>
                                                <?php else: ?>
                                                    <span class="badge-cobertura cob-critica">Divergente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="txt-centro">
                                                <div class="acoes-inline">
                                                    <button class="btn-acao btn-info"
                                                        style="padding:3px 10px; font-size:0.78rem;"
                                                        onclick="abrirModalEditar(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['ds_material'] ?? ''), ENT_QUOTES); ?>', <?php echo $bipado; ?>)">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button class="btn-acao btn-neutro"
                                                        style="padding:3px 10px; font-size:0.78rem;"
                                                        title="Ver histórico de auditoria"
                                                        onclick="abrirModalLog(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['ds_material'] ?? ''), ENT_QUOTES); ?>')">
                                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
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
            <a href="https://www.linkedin.com/in/d%C3%A2ndilo-silva-6a83a1152" target="_blank">🔗 LinkedIn</a>
            <a href="https://github.com/Dandilo-zz" target="_blank">💻 GitHub</a>
        </div>
        <p><a href="politicas.php" style="font-weight:600; color:#3b82f6;">Privacidade e Termos de uso</a></p>
    </footer>

    <!-- ── MODAL EDITAR QUANTIDADE ── -->
    <dialog id="modal-editar-bipado" onclick="event.target===this&&this.close()">
        <h3 style="color:#1e3a8a; margin-bottom:8px;">✏️ Corrigir Quantidade Bipada</h3>
        <p id="modal-editar-desc" style="color:#6b7280; font-size:0.85rem; margin-bottom:16px; line-height:1.4;"></p>
        <div class="form-group">
            <label style="font-weight:600;">Nova Quantidade Bipada</label>
            <input type="number" id="modal-editar-valor" min="0" step="1"
                style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;">
        </div>
        <div class="form-group" style="margin-top:10px;">
            <label style="font-weight:600;">Motivo da Correção <span
                    style="color:#9ca3af; font-size:0.75rem;">(auditoria)</span></label>
            <input type="text" id="modal-editar-motivo" placeholder="Ex: erro de leitura, releitura manual..."
                style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;">
        </div>
        <input type="hidden" id="modal-editar-id-item">
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button onclick="document.getElementById('modal-editar-bipado').close()" class="btn-acao btn-neutro" style="padding:8px 16px;">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button onclick="confirmarEdicao()" class="btn-acao btn-sucesso" style="padding:8px 16px;">
                <i class="fa-solid fa-floppy-disk"></i> Salvar Correção
            </button>
        </div>
    </dialog>

    <!-- ── MODAL ENCERRAR ── -->
    <dialog id="modal-encerrar-inv" onclick="event.target===this&&this.close()">
        <h3 style="color:#dc2626; margin-bottom:10px;">🏁 Encerrar Inventário</h3>
        <p style="color:#6b7280; font-size:0.88rem; line-height:1.5; margin-bottom:18px;">
            Ao encerrar, o inventário ficará arquivado e não será mais possível realizar bipagens.<br>
            Esta ação não apaga os dados.
        </p>
        <div class="form-group">
            <label style="font-weight:600;">Confirme sua senha</label>
            <input type="password" id="senha-encerrar"
                style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;">
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button onclick="document.getElementById('modal-encerrar-inv').close()" class="btn-acao btn-neutro" style="padding:8px 16px;">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button onclick="confirmarEncerrar()" class="btn-acao btn-perigo" style="padding:8px 16px;">
                <i class="fa-solid fa-flag-checkered"></i> Encerrar
            </button>
        </div>
    </dialog>
    <dialog id="modal-log-item" onclick="event.target===this&&this.close()" style="width: 80%; max-width: 750px;">
        <h3 style="color:#1e3a8a; margin-bottom:4px;">📜 Histórico de Auditoria do Item</h3>
        <p id="modal-log-desc" style="color:#4b5563; font-size:0.85rem; margin-bottom:12px; font-weight: 600;"></p>

        <div style="max-height: 350px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
            <table class="tabela-log">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Operação</th>
                        <th>Cód. Barras</th>
                        <th style="text-align:right;">Antigo</th>
                        <th style="text-align:right;">Novo</th>
                        <th style="text-align:right;">Dif</th>
                    </tr>
                </thead>
                <tbody id="tbody-log-item">
                    + </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:16px;">
            <button onclick="document.getElementById('modal-log-item').close()" class="btn-acao btn-neutro" style="padding:8px 16px;">
                <i class="fa-solid fa-xmark"></i> Fechar
            </button>
        </div>
    </dialog>
    <dialog id="modal-deletar-inv" onclick="event.target===this&&this.close()">
        <h3 style="color:#dc2626; margin-bottom:10px;">🗑️ Excluir Inventário Permanentemente</h3>
        <p style="color:#6b7280; font-size:0.88rem; line-height:1.5; margin-bottom:18px;">
            <strong>Atenção:</strong> Esta ação é irreversível.<br>
            Todos os itens, contagens e logs de auditoria deste inventário serão apagados definitivamente.
        </p>
        <div class="form-group">
            <label style="font-weight:600;">Confirme sua senha para prosseguir</label>
            <input type="password" id="senha-deletar"
                style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;">
        </div>
        <input type="hidden" id="modal-deletar-id-inv">
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button onclick="document.getElementById('modal-deletar-inv').close()" class="btn-acao btn-neutro" style="padding:8px 16px;">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button onclick="confirmarExclusao()" class="btn-acao btn-perigo" style="padding:8px 16px;">
                <i class="fa-solid fa-trash-can"></i> Excluir Definitivamente
            </button>
        </div>
    </dialog>
    <dialog id="modal-prompt-id" onclick="event.target===this&&this.close()">
        <h3 style="color:#1e3a8a; margin-bottom:8px;">⚠️ Item Não Encontrado</h3>
        <p style="color:#6b7280; font-size:0.85rem; margin-bottom:16px; line-height:1.4;">
            O código de barras <strong id="lbl-barcode-desconhecido"></strong> não possui correspondência direta.<br>
            Por favor, informe o <strong>Cód. Material (ID)</strong> para vinculá-lo:
        </p>
        <div class="form-group">
            <label style="font-weight:600;">Cód. Material (ID)</label>
            <input type="text" id="txt-prompt-id-material"
                style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;">
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button onclick="document.getElementById('modal-prompt-id').close()" class="btn-acao btn-neutro" style="padding:8px 16px;">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button onclick="processarProximoPassoMaterial()" class="btn-acao btn-info" style="padding:8px 16px;">
                Avançar <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </dialog>
    <dialog id="modal-prompt-nome" onclick="event.target===this&&this.close()">
        <h3 style="color:#1e3a8a; margin-bottom:8px;">📦 Cadastrar Novo Material</h3>
        <p style="color:#6b7280; font-size:0.85rem; margin-bottom:16px; line-height:1.4;">
            O Cód. Material <strong id="lbl-novo-id-material"></strong> também é novo no sistema.<br>
            Defina o nome/descrição para este novo item:
        </p>
        <div class="form-group">
            <label style="font-weight:600;">Nome / Descrição do Material</label>
            <input type="text" id="txt-prompt-nome-material"
                style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; margin-top:4px;"
                placeholder="Ex: Soro Fisiológico 500ml">
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button onclick="document.getElementById('modal-prompt-nome').close()" class="btn-acao btn-neutro" style="padding:8px 16px;">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button onclick="confirmarCriacaoMaterialInesperado()" class="btn-acao btn-sucesso" style="padding:8px 16px;">
                <i class="fa-solid fa-plus"></i> Criar e Contar
            </button>
        </div>
    </dialog>
    <script>
        // ── Dados do inventário em memória ──────────────────────────────────────
        const INV_ID = <?php echo $idInventario ? $idInventario : 'null'; ?>;
        const USUARIO = <?php echo json_encode($loginUser); ?>;
        const ESTOQUE = <?php echo json_encode($nomeEstoque); ?>;
        const TOTAL_ITENS = <?php echo $totalItens; ?>;

        // Mapa de barcodes: ds_barras => id_item
        const mapaBarcode = {};
        // Mapa de id_item => {saldo, bipado, trEl}
        const mapaItens = {};
        // Variáveis globais temporárias para o fluxo de novos itens
        let tempBarcodeLido = '';
        let tempQtdBipada = 1;
        let tempCdMaterial = '';
        document.querySelectorAll('.linha-item-inv').forEach(tr => {
            const id = tr.dataset.idItem;
            const saldo = parseFloat(tr.dataset.saldo) || 0;
            const bipado = parseFloat(tr.dataset.bipado) || 0;
            const cdMat = tr.dataset.cdMaterial || '';
            const bcs = (tr.dataset.barcodes || '').split('|').filter(Boolean);

            mapaItens[id] = { saldo, bipado, tr, cd_material: cdMat };
            bcs.forEach(bc => { mapaBarcode[bc.trim()] = id; });
        });

        function calcularClasseDif(dif, bipado) {
            if (bipado === 0) return { badge: 'dif-critica', status: 'PENDENTE', badgeStatus: 'cob-vazia', txtStatus: 'Pendente' };
            if (dif === 0) return { badge: 'dif-zero', status: 'OK', badgeStatus: 'cob-ok', txtStatus: 'Conferido' };
            return { badge: 'dif-critica', status: 'CRITICO', badgeStatus: 'cob-critica', txtStatus: 'Divergente' };
        }

        function recalcularTudo() {
            if (!Object.keys(mapaItens).length) return;
            let diferencas = 0;
            Object.values(mapaItens).forEach(({ saldo, bipado, tr }) => {
                const dif = saldo - bipado;
                const cls = calcularClasseDif(dif, bipado);
                const absDif = Math.abs(dif);
                const sinal = dif > 0 ? '-' : (dif < 0 ? '+' : '');
                const difText = dif === 0 ? '0' : sinal + absDif.toLocaleString('pt-BR');

                const tdDif = tr.querySelector('.td-diferenca');
                const tdStatus = tr.querySelector('.td-status');
                if (tdDif) tdDif.innerHTML = `<span class="dif-badge ${cls.badge}">${difText}</span>`;
                if (tdStatus) tdStatus.innerHTML = `<span class="badge-cobertura ${cls.badgeStatus}">${cls.txtStatus}</span>`;

                tr.dataset.status = cls.status;
                if (cls.status === 'CRITICO') diferencas++;
            });
            const elDif = document.getElementById('kpi-diferencas');
            if (elDif) elDif.textContent = diferencas;
            atualizarKPIs();
        }

        function atualizarKPIs() {
            const total = Object.keys(mapaItens).length;
            const bipados = Object.values(mapaItens).filter(i => i.bipado > 0).length;
            const pct = total > 0 ? Math.round((bipados / total) * 100) : 0;
            const elBip = document.getElementById('kpi-bipados');
            const elPend = document.getElementById('kpi-pendentes');
            const elPct = document.getElementById('kpi-pct');
            if (elBip) elBip.textContent = bipados;
            if (elPend) elPend.textContent = total - bipados;
            if (elPct) elPct.textContent = pct + '%';
            const barra = document.getElementById('barra-progresso');
            if (barra) barra.style.width = pct + '%';
        }

        // ── Bipagem ──────────────────────────────────────────────────────────
        const inputBip = document.getElementById('input-bip');
        const inputQtd = document.getElementById('input-qtd-bip');
        const feedback = document.getElementById('feedback-bip');

        let bipTimout;

        // Seleciona todo o conteúdo ao clicar ou dar foco no campo de quantidade
        inputQtd?.addEventListener('focus', function () {
            this.select();
        });
        inputQtd?.addEventListener('click', function () {
            this.select();
        });

        if (inputBip) {
            inputBip.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') processarBipagem(this.value.trim());
            });
            // Suporte a envio automático (opcional)
            inputBip.addEventListener('input', function () {
                const autoAtivo = document.getElementById('chk-bip-auto')?.checked;
                if (!autoAtivo) return;
                clearTimeout(bipTimout);
                bipTimout = setTimeout(() => {
                    const val = this.value.trim();
                    if (val.length >= 5) processarBipagem(val);
                }, 400);
            });
        }

        function processarBipagem(codigo) {
            if (!codigo || !INV_ID) return;
            const qtd = parseInt(inputQtd?.value) || 1;
            const idItem = mapaBarcode[codigo];

            if (!idItem) {
                // Abre o modal customizado do Passo 1
                tempBarcodeLido = codigo;
                tempQtdBipada = qtd;
                document.getElementById('lbl-barcode-desconhecido').textContent = codigo;
                document.getElementById('txt-prompt-id-material').value = '';
                document.getElementById('modal-prompt-id').showModal();
                setTimeout(() => document.getElementById('txt-prompt-id-material').focus(), 50);
                inputBip.value = '';
                return;
            }

            // Fluxo normal contínuo caso o código já exista
            executarIncrementoContagem(idItem, qtd, codigo);
        }

        // Encapsula o incremento visual e salvamento para reaproveitamento nos modais
        function executarIncrementoContagem(idItem, qtd, codigo) {
            mapaItens[idItem].bipado += qtd;
            const novo = mapaItens[idItem].bipado;

            // Atualiza célula
            const tr = mapaItens[idItem].tr;
            const tdB = tr.querySelector('.td-bipado');
            if (tdB) tdB.textContent = novo.toLocaleString('pt-BR');

            recalcularTudo();
            setFeedback('ok', `✅ +${qtd} → ${tr.querySelector('td:nth-child(2)').textContent.trim().substring(0, 45)}... (Total: ${novo})`);
            inputBip.classList.add('ok');
            tr.classList.add('linha-bipada-recente');
            setTimeout(() => { inputBip.classList.remove('ok'); tr.classList.remove('linha-bipada-recente'); }, 900);

            // Scroll para o item
            tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            autoSalvar(idItem, novo, codigo, qtd, 'bipagem', '', mapaItens[idItem].cd_material, tr.querySelector('td:nth-child(2)').textContent.trim());
            document.getElementById('status-ultima-bipagem').textContent =
                `Última bipagem: ${new Date().toLocaleTimeString('pt-BR')}`;
        }
        // Lógica do botão Avançar do Modal de ID
        function processarProximoPassoMaterial() {
            const cdMaterialInformado = document.getElementById('txt-prompt-id-material').value.trim();
            if (!cdMaterialInformado) { alert('Informe o código do material para prosseguir.'); return; }

            document.getElementById('modal-prompt-id').close();

            let itemExistenteId = null;
            for (const id in mapaItens) {
                if (String(mapaItens[id].cd_material) === String(cdMaterialInformado)) {
                    itemExistenteId = id;
                    break;
                }
            }

            if (itemExistenteId) {
                // O material já existe na lista! Associa e computa
                mapaBarcode[tempBarcodeLido] = itemExistenteId;
                executarIncrementoContagem(itemExistenteId, tempQtdBipada, tempBarcodeLido);
            } else {
                // Material também é novo, passa para o Modal do Passo 2
                tempCdMaterial = cdMaterialInformado;
                document.getElementById('lbl-novo-id-material').textContent = cdMaterialInformado;
                document.getElementById('txt-prompt-nome-material').value = '';
                document.getElementById('modal-prompt-nome').showModal();
                setTimeout(() => document.getElementById('txt-prompt-nome-material').focus(), 50);
            }
        }

        // Lógica do botão Finalizar do Modal de Nome do Material Novo
        function confirmarCriacaoMaterialInesperado() {
            const dsMaterialInformado = document.getElementById('txt-prompt-nome-material').value.trim();
            if (!dsMaterialInformado) { alert('O nome do material é obrigatório.'); return; }

            document.getElementById('modal-prompt-nome').close();
            const idItem = "novo_" + Date.now();

            const tbody = document.getElementById('tbody-inventario');
            if (tbody.querySelector('.msg-vazia')) tbody.innerHTML = '';

            const novaLinha = document.createElement('tr');
            novaLinha.className = 'linha-item-inv';
            novaLinha.dataset.idItem = idItem;
            novaLinha.dataset.cdMaterial = tempCdMaterial;
            novaLinha.dataset.saldo = "0";
            novaLinha.dataset.bipado = "0";
            novaLinha.dataset.barcodes = tempBarcodeLido;
            novaLinha.innerHTML = `
             <td><span class="txt-mutado">${tempCdMaterial}</span></td>
             <td>${dsMaterialInformado}</td>
             <td class="txt-direita">0</td>
             <td class="txt-direita td-bipado">0</td>
             <td class="txt-centro td-diferenca"><span class="dif-badge dif-critica">0</span></td>
             <td class="txt-centro td-status"><span class="badge-cobertura cob-vazia">Pendente</span></td>
             <td class="txt-centro">
                 <button class="btn-acao btn-info" style="padding:3px 10px; font-size:0.78rem;" onclick="abrirModalEditar('${idItem}', '${dsMaterialInformado.replace(/'/g, "\\'")}', 0)"><i class="fa-solid fa-pen"></i></button>
                 <button class="btn-acao btn-neutro" style="padding:3px 10px; font-size:0.78rem;" title="Ver histórico de auditoria" onclick="abrirModalLog('${idItem}', '${dsMaterialInformado.replace(/'/g, "\\'")}')"><i class="fa-solid fa-clock-rotate-left"></i></button>
             </td>
         `;
            tbody.appendChild(novaLinha);

            mapaItens[idItem] = { saldo: 0, bipado: 0, tr: novaLinha, cd_material: tempCdMaterial };
            mapaBarcode[tempBarcodeLido] = idItem;

            executarIncrementoContagem(idItem, tempQtdBipada, tempBarcodeLido);
        }
        function setFeedback(tipo, msg) {
            feedback.className = `feedback-bipagem feedback-${tipo}`;
            feedback.textContent = msg;
        }

        // ── Auto-save ────────────────────────────────────────────────────────
        let saveQueue = {};
        let saveTimer;
        const badge = document.getElementById('badge-autosave');

        function autoSalvar(idItem, qtdNova, codigoBarras, incremento, tipo = 'bipagem', motivo = '', cdMaterialInesperado = null, dsMaterialInesperado = null) {
            saveQueue[idItem] = {
                idItem, qtdNova, codigoBarras, incremento, tipo, motivo,
                cd_material_novo: cdMaterialInesperado,
                ds_material_novo: dsMaterialInesperado
            };
            clearTimeout(saveTimer);
            if (badge) { badge.className = 'salvando'; badge.textContent = '● Salvando...'; }
            saveTimer = setTimeout(flushSave, 600);
        }

        async function flushSave() {
            const payload = Object.values(saveQueue);
            saveQueue = {};
            if (!payload.length) return;
            try {
                const res = await fetch('../acoes/salvar_bipagem.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_inventario: INV_ID, bipagens: payload, csrf_token: window.csrfToken })
                });
                const data = await res.json();
                if (data.sucesso) {
                    if (badge) { badge.className = 'salvo'; badge.textContent = '● Salvo'; }
                    setTimeout(() => { if (badge) { badge.className = ''; badge.textContent = '● Auto-save ativo'; } }, 2000);
                } else {
                    lancarAlerta('Erro ao salvar bipagem: ' + (data.erro || ''), 'erro');
                }
            } catch (e) {
                lancarAlerta('Falha de comunicação com servidor.', 'erro');
            }
        }

        // ── Edição manual ────────────────────────────────────────────────────
        function abrirModalEditar(idItem, descricao, bipadoAtual) {
            document.getElementById('modal-editar-id-item').value = idItem;
            document.getElementById('modal-editar-desc').textContent = descricao;
            document.getElementById('modal-editar-valor').value = bipadoAtual;
            document.getElementById('modal-editar-motivo').value = '';
            document.getElementById('modal-editar-bipado').showModal();
            setTimeout(() => document.getElementById('modal-editar-valor').select(), 50);
        }

        async function confirmarEdicao() {
            const idItem = document.getElementById('modal-editar-id-item').value;
            const valor = parseFloat(document.getElementById('modal-editar-valor').value) || 0;
            const motivo = document.getElementById('modal-editar-motivo').value.trim();
            const anterior = mapaItens[idItem]?.bipado ?? 0;

            document.getElementById('modal-editar-bipado').close();

            mapaItens[idItem].bipado = valor;
            const tr = mapaItens[idItem].tr;
            const tdB = tr?.querySelector('.td-bipado');
            if (tdB) tdB.textContent = valor.toLocaleString('pt-BR');
            recalcularTudo();

            try {
                const res = await fetch('../acoes/salvar_bipagem.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_inventario: INV_ID,
                        bipagens: [{
                            idItem, qtdNova: valor,
                            codigoBarras: null,
                            incremento: valor - anterior,
                            tipo: 'edicao_manual',
                            motivo
                        }],
                        csrf_token: window.csrfToken
                    })
                });
                const data = await res.json();
                if (data.sucesso) lancarAlerta('Quantidade corrigida com sucesso.', 'sucesso');
                else lancarAlerta(data.erro || 'Erro ao salvar.', 'erro');
            } catch (e) {
                lancarAlerta('Falha de comunicação.', 'erro');
            }
        }
        // ── Carregar Logs do Item ────────────────────────────────────────────
        async function abrirModalLog(idItem, descricao) {
            document.getElementById('modal-log-desc').textContent = descricao;
            const tbody = document.getElementById('tbody-log-item');
            tbody.innerHTML = '<tr><td colspan="7" class="txt-centro">Carregando histórico...</td></tr>';
            document.getElementById('modal-log-item').showModal();

            try {
                const res = await fetch(`../acoes/buscar_log.php?id_item=${idItem}`);
                const data = await res.json();

                if (!data.sucesso) {
                    tbody.innerHTML = `<tr><td colspan="7" style="color:#ef4444;" class="txt-centro">Erro: ${data.erro}</td></tr>`;
                    return;
                }
                if (!data.logs.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="txt-centro">Nenhuma movimentação registrada para este item.</td></tr>';
                    return;
                }

                tbody.innerHTML = data.logs.map(log => {
                    const dataFmt = new Date(log.criado_em).toLocaleString('pt-BR');
                    const tipoBadge = log.tipo_alteracao === 'bipagem'
                        ? '<span class="badge-log badge-log-bip">Bipagem</span>'
                        : '<span class="badge-log badge-log-manual">Manual</span>';
                    const incSinal = log.qtd_incremento > 0 ? `+${log.qtd_incremento}` : log.qtd_incremento;

                    return `<tr>
                        <td>${dataFmt}</td>
                        <td><strong>${log.usuario_nome}</strong></td>
                        <td>${tipoBadge}</td>
                        <td class="txt-mutado">${log.ds_barras_lida || '—'}</td>
                        <td style="text-align:right;">${parseFloat(log.valor_anterior)}</td>
                        <td style="text-align:right; font-weight:600;">${parseFloat(log.valor_novo)}</td>
                        <td style="text-align:right; color:${log.qtd_incremento >= 0 ? '#10b981' : '#ef4444'}">${incSinal}</td>
                    </tr>`;
                }).join('');

            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" style="color:#ef4444;" class="txt-centro">Falha ao conectar com o servidor.</td></tr>';
            }
        }
        // ── Carregar Inventário Antigo ────────────────────────────────────────
        function carregarInventarioAntigo(idInventarioOld) {
            if (!idInventarioOld) return;
            // Recarrega a página atual passando o ID do histórico via parâmetro GET
            window.location.search = `?id_historico=${idInventarioOld}`;
        }
        // ── Exclusão do Inventário ────────────────────────────────────────────
        function abrirModalDeletar(idInventarioOld) {
            document.getElementById('modal-deletar-id-inv').value = idInventarioOld;
            document.getElementById('senha-deletar').value = '';
            document.getElementById('modal-deletar-inv').showModal();
        }

        async function confirmarExclusao() {
            const idInv = document.getElementById('modal-deletar-id-inv').value;
            const senha = document.getElementById('senha-deletar').value;

            if (!senha) { lancarAlerta('Digite sua senha para confirmar.', 'alerta'); return; }
            document.getElementById('modal-deletar-inv').close();

            try {
                const res = await fetch('../acoes/deletar_inventario.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_inventario: idInv, senha: senha, csrf_token: window.csrfToken })
                });

                if (res.status === 401) throw new Error('Senha incorreta.');
                const data = await res.json();

                if (data.sucesso) {
                    lancarAlerta('Inventário excluído do banco de dados.', 'sucesso');
                    setTimeout(() => {
                        // Se o usuário deletou o inventário que ele mesmo estava visualizando, limpa a URL
                        const p = new URLSearchParams(window.location.search);
                        if (p.get('id_historico') === idInv) {
                            window.location.href = 'pre_inventario.php';
                        } else {
                            location.reload();
                        }
                    }, 1200);
                } else {
                    lancarAlerta(data.erro || 'Erro ao deletar.', 'erro');
                }
            } catch (e) {
                lancarAlerta(e.message, 'erro');
            }
        }
        // ── Encerrar inventário ──────────────────────────────────────────────
        function abrirModalEncerrar() {
            document.getElementById('senha-encerrar').value = '';
            document.getElementById('modal-encerrar-inv').showModal();
        }

        async function confirmarEncerrar() {
            const senha = document.getElementById('senha-encerrar').value;
            if (!senha) { lancarAlerta('Digite sua senha.', 'alerta'); return; }
            document.getElementById('modal-encerrar-inv').close();
            try {
                const res = await fetch('../acoes/encerrar_inventario.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_inventario: INV_ID, senha, csrf_token: window.csrfToken })
                });
                if (res.status === 401) throw new Error('Senha incorreta.');
                const data = await res.json();
                if (data.sucesso) { lancarAlerta('Inventário encerrado.', 'sucesso'); setTimeout(() => location.reload(), 1200); }
                else lancarAlerta(data.erro || 'Erro ao encerrar.', 'erro');
            } catch (e) { lancarAlerta(e.message, 'erro'); }
        }

        // ── Importar arquivo ────────────────────────────────────────────────
        function importarArquivo() {
            const input = document.getElementById('input-arquivo-inv');
            if (!input.files.length) { lancarAlerta('Selecione um arquivo primeiro.', 'alerta'); return; }

            const fd = new FormData();
            fd.append('arquivo_inventario', input.files[0]);
            fd.append('csrf_token', window.csrfToken);

            dispararCarregamento('Processando lista de inventário...');
            setTimeout(async () => {
                try {
                    const res = await fetch('../acoes/importar_inventario.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    document.getElementById('loading-global').classList.remove('ativo');
                    if (data.sucesso) { lancarAlerta(data.mensagem, 'sucesso'); setTimeout(() => location.reload(), 1200); }
                    else lancarAlerta(data.erro || 'Erro ao importar.', 'erro');
                } catch (e) {
                    document.getElementById('loading-global').classList.remove('ativo');
                    lancarAlerta('Falha de comunicação.', 'erro');
                }
            }, 500);
        }

        // ── Filtros ──────────────────────────────────────────────────────────
        function aplicarFiltrosInv() {
            const busca = (document.getElementById('filtro-busca-inv')?.value || '').toLowerCase();
            const status = document.getElementById('filtro-status-inv')?.value || 'TODOS';
            document.querySelectorAll('.linha-item-inv').forEach(tr => {
                const txt = tr.textContent.toLowerCase();
                const st = tr.dataset.status || 'PENDENTE';
                const okBusca = txt.includes(busca);
                const okStatus = status === 'TODOS' || st === status;
                tr.style.display = (okBusca && okStatus) ? '' : 'none';
            });
        }

        document.getElementById('filtro-busca-inv')?.addEventListener('keyup', aplicarFiltrosInv);
        document.getElementById('filtro-status-inv')?.addEventListener('change', aplicarFiltrosInv);

        function limparFiltrosInv() {
            if (document.getElementById('filtro-busca-inv')) document.getElementById('filtro-busca-inv').value = '';
            if (document.getElementById('filtro-status-inv')) document.getElementById('filtro-status-inv').value = 'TODOS';
            aplicarFiltrosInv();
        }

        // ── Ordenação ────────────────────────────────────────────────────────
        let dirsOrdem = {};
        function ordenarInv(col, tipo) {
            const tbody = document.getElementById('tbody-inventario');
            if (!tbody) return;
            const linhas = Array.from(tbody.querySelectorAll('tr.linha-item-inv'));
            const dir = dirsOrdem[col] === 'asc' ? 'desc' : 'asc';
            dirsOrdem = { [col]: dir };
            linhas.sort((a, b) => {
                let vA = a.children[col]?.innerText.trim() ?? '';
                let vB = b.children[col]?.innerText.trim() ?? '';
                if (tipo === 'num') {
                    vA = parseFloat(vA.replace(/[^0-9,\-]/g, '').replace(',', '.')) || 0;
                    vB = parseFloat(vB.replace(/[^0-9,\-]/g, '').replace(',', '.')) || 0;
                } else { vA = vA.toLowerCase(); vB = vB.toLowerCase(); }
                if (vA < vB) return dir === 'asc' ? -1 : 1;
                if (vA > vB) return dir === 'asc' ? 1 : -1;
                return 0;
            });
            linhas.forEach(l => tbody.appendChild(l));
        }

        // ── Exportar PDF ─────────────────────────────────────────────────────
        async function exportarPDF() {
            const linhas = document.querySelectorAll('#tbody-inventario .linha-item-inv');
            if (!linhas.length) { lancarAlerta('Sem dados para exportar.', 'alerta'); return; }

            const dataGeracao = new Date().toLocaleString('pt-BR');
            let linhasHtml = '';

            linhas.forEach((tr, i) => {
                const cd = tr.children[0].textContent.trim();
                const nome = tr.children[1].textContent.trim();
                const saldo = tr.children[2].textContent.trim();
                const bip = tr.children[3].textContent.trim();
                const dif = tr.children[4].textContent.trim();
                const st = tr.children[5].textContent.trim();
                const bcsArr = (tr.dataset.barcodes || '').split('|').filter(Boolean);
                const barcodes = bcsArr.length
                    ? bcsArr.map(b => `<div class="bc-item">*${b.trim()}*</div><div class="bc-texto">${b.trim()}</div>`).join('')
                    : '<span style="color:#9ca3af;">—</span>';
                const cor = dif === '0' ? '#d1fae5' : (tr.dataset.status === 'ALERTA' ? '#fef3c7' : '#fee2e2');
                linhasHtml += `<tr style="background:${i % 2 === 0 ? '#f8fafc' : '#fff'}">
                <td>${cd}</td><td>${nome}</td>
                <td class="num">${saldo}</td><td class="num">${bip}</td>
                <td class="num" style="background:${cor}; font-weight:700;">${dif}</td>
                <td class="num">${st}</td><td class="bc">${barcodes}</td>
            </tr>`;
            });

            const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<title>Pré-Inventário - HGestor</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;color:#111827;font-size:11px;}
.header{background:#1e3a5f;color:#fff;padding:20px 28px;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.header-titulo{font-size:18px;font-weight:bold;}
.header-sub{font-size:10px;color:#93c5fd;margin-top:3px;}
.infos{display:flex;gap:16px;padding:0 28px 16px;}
.info-box{flex:1;background:#f1f5f9;border-left:4px solid #1e3a5f;padding:10px 14px;border-radius:0 4px 4px 0;}
.info-label{font-size:9px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;}
.info-valor{font-size:13px;font-weight:bold;}
.tabela-wrap{padding:0 28px;}
table{width:100%;border-collapse:collapse;font-size:10px;}
thead tr{background:#1e3a5f;color:#fff;}
thead th{padding:8px 10px;text-align:left;font-size:10px;font-weight:600;}
th.num,td.num{text-align:right;}
td.bc{font-size:9px;color:#6b7280;}
.bc-item{
   display:inline-block;
    background:#f1f5f9;
    border:1px solid #e2e8f0;
    border-radius:3px;
    padding:2px 6px;
    margin:1px 0;
    font-family:'Libre Barcode 39',monospace;
    font-size:28px;
    line-height:1;
    letter-spacing:2px;
    display:block;
}
.bc-texto{font-family:Arial,sans-serif;font-size:8px;color:#6b7280;text-align:center;margin-top:1px;}
td{padding:7px 10px;border-bottom:1px solid #e2e8f0;}
.footer{margin:20px 28px 0;padding-top:12px;border-top:1px solid #e2e8f0;font-size:9px;color:#9ca3af;text-align:center;}
@media print{@page{margin:12mm;size:A4 landscape;}body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style></head><body>
<div class="header">
  <div><div class="header-titulo">HGestor</div><div class="header-sub">Sistema de Gestão de Estoque Hospitalar</div></div>
  <div style="text-align:right;font-size:11px;color:#e0f2fe;">
    <div style="font-size:13px;font-weight:bold;">PRÉ-INVENTÁRIO</div>
    <div style="margin-top:4px;">Gerado em: ${dataGeracao}</div>
    <div>Usuário: ${USUARIO} | Estoque: ${ESTOQUE}</div>
  </div>
</div>
<div class="infos">
  <div class="info-box"><div class="info-label">Estoque / Setor</div><div class="info-valor">${ESTOQUE}</div></div>
  <div class="info-box"><div class="info-label">Usuário Responsável</div><div class="info-valor">${USUARIO}</div></div>
  <div class="info-box"><div class="info-label">Data / Hora</div><div class="info-valor">${dataGeracao}</div></div>
</div>
<div class="tabela-wrap">
  <table>
    <thead><tr>
      <th>Cód. Material</th><th>Nome Material</th>
      <th class="num">Saldo Sistema</th><th class="num">Qtd. Bipada</th>
      <th class="num">Diferença</th><th class="num">Status</th><th>Cód. Barras</th>
    </tr></thead>
    <tbody>${linhasHtml}</tbody>
  </table>
</div>
<div class="footer">Documento gerado automaticamente pelo HGestor — HorusDEV &bull; ${dataGeracao}</div>
</body></html>`;

            const w = window.open('', '_blank', 'width=950,height=750');
            w.document.write(html); w.document.close();
            w.onload = () => { w.focus(); w.print(); };
        }

        // ── Toasts ───────────────────────────────────────────────────────────
        function lancarAlerta(mensagem, tipo = 'info', tempo = 4000) {
            const container = document.getElementById('container-toasts-global');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `alerta-toast toast-${tipo}`;
            const icones = { sucesso: '✅', erro: '❌', alerta: '⚠️', info: 'ℹ️' };
            toast.innerHTML = `<div style="display:flex;align-items:center;gap:10px;"><span>${icones[tipo] || 'ℹ️'}</span><span>${mensagem}</span></div>
            <button class="toast-fechar" onclick="this.parentElement.remove()">✕</button>`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(-10px)'; setTimeout(() => toast.remove(), 300); }, tempo);
        }

        // ── Loading ──────────────────────────────────────────────────────────
        function dispararCarregamento(msg = 'Processando...') {
            const overlay = document.getElementById('loading-global');
            const linha = document.getElementById('loading-linha');
            const pct = document.getElementById('loading-pct');
            document.getElementById('loading-mensagem-texto').innerText = msg;
            if (!overlay) return;
            overlay.classList.add('ativo');
            let prog = 0;
            const timer = setInterval(() => {
                prog = Math.min(prog + 2, 90);
                if (linha) linha.style.width = prog + '%';
                if (pct) pct.innerText = prog + '%';
            }, 60);
        }

        // ── Init ─────────────────────────────────────────────────────────────
        window.addEventListener('DOMContentLoaded', () => {
            recalcularTudo();
            inputBip?.focus();
            const p = new URLSearchParams(window.location.search);
            if (p.has('sucesso')) { lancarAlerta(p.get('sucesso'), 'sucesso'); history.replaceState({}, '', location.pathname); }
            if (p.has('erro')) { lancarAlerta(p.get('erro'), 'erro'); history.replaceState({}, '', location.pathname); }
        });
    </script>
</body>

</html>