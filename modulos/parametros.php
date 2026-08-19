<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];

$stmtG = $pdo->prepare("SELECT id, nome_grupo FROM config_grupos WHERE id_usuario = :uid ORDER BY nome_grupo ASC");
$stmtG->execute(['uid' => $idUsuario]);
$grupos = $stmtG->fetchAll(PDO::FETCH_ASSOC);

$stmtTC = $pdo->prepare("SELECT id, nome_tipo FROM config_tipos_compra WHERE id_usuario = :uid ORDER BY nome_tipo ASC");
$stmtTC->execute(['uid' => $idUsuario]);
$tiposCompra = $stmtTC->fetchAll(PDO::FETCH_ASSOC);

$stmtP = $pdo->prepare("SELECT id, nome_padrao FROM config_padronizacoes WHERE id_usuario = :uid ORDER BY nome_padrao ASC");
$stmtP->execute(['uid' => $idUsuario]);
$padronizacoes = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$queryMateriais = "
    SELECT 
        te.id_material,
        te.descricao,
        cm.id_grupo,
        cm.id_tipo_compra,
        cm.id_padronizacao
    FROM tasy_estoque te
    LEFT JOIN config_materiais cm ON te.id_material = cm.id_material AND cm.id_usuario = :uid_cm
    WHERE te.id_usuario = :uid_te
    ORDER BY te.descricao ASC
";
$stmtMat = $pdo->prepare($queryMateriais);
$stmtMat->execute(['uid_cm' => $idUsuario, 'uid_te' => $idUsuario]);
$materiais = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HGestor - Parametrização Avançada</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon-16x16.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo filemtime('../css/estilos.css'); ?>">
    <style>
        .grid-gerenciar {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .box-recurso {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
        }

        .box-recurso h4 {
            color: #1e3a8a;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-toggle-card {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            color: #6b7280;
            padding: 2px 8px;
            border-radius: 4px;
            transition: background 0.2s;
            user-select: none;
        }

        .btn-toggle-card:hover {
            background: #f3f4f6;
            color: #1e3a8a;
        }

        .corpo-card-colapsavel {
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
            max-height: 2000px;
            opacity: 1;
        }

        .corpo-card-colapsavel.recolhido {
            max-height: 0;
            opacity: 0;
        }

        .lista-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .tag-item {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tag-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            font-weight: bold;
        }

        .tag-btn-del:hover {
            color: #ef4444;
        }

        .tag-btn-edit:hover {
            color: #2563eb;
        }

        .select-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .select-searchable {
            width: 100%;
            padding: 4px 6px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            background: white;
            text-align: left;
        }

        .select-dropdown {
            display: none;
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            min-width: 220px;
            max-height: 260px;
            overflow: hidden;
            flex-direction: column;
        }

        .select-dropdown.aberto {
            display: flex;
        }

        .select-dropdown-busca {
            padding: 6px 8px;
            border: none;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.85rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
        }

        .select-dropdown-lista {
            overflow-y: auto;
            flex: 1;
        }

        .select-dropdown-item {
            padding: 6px 10px;
            font-size: 0.85rem;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .select-dropdown-item:hover,
        .select-dropdown-item.ativo {
            background: #eff6ff;
            color: #1e3a8a;
        }

        /* Painel Unificado HorusDEV de Governança de Dados */
        .painel-governanca {
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .painel-governanca h3 {
            color: #1e3a8a;
            margin-bottom: 4px;
        }

        .painel-governanca .subtitulo-painel {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 12px;
        }

        .grid-acoes-dados {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 24px;
            align-items: start;
        }

        .coluna-acao-dados {
            display: flex;
            flex-direction: column;
            gap: 8px;
            height: 100%;
        }

        .coluna-acao-dados h5 {
            color: #1f2937;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .coluna-acao-dados p {
            color: #6b7280;
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: auto;
            /* Empurra os botões para alinhar embaixo */
        }

        .divisoria-vertical {
            border-left: 1px solid #e2e8f0;
            padding-left: 24px;
        }

        /* Barra de Ação em Lote */
        .barra-lote {
            position: fixed;
            bottom: -100px;
            left: 0;
            right: 0;
            background: #1e3a8a;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.2);
            transition: bottom 0.3s ease;
            z-index: 999;
        }

        .barra-lote.ativa {
            bottom: 0;
        }

        .lote-acoes {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* Área de Filtros Avançados */
        .painel-filtros {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            background: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            align-items: flex-end;
        }

        .filtro-grupo-input {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filtro-grupo-input label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4b5563;
        }

        /* Cabeçalho de Tabela Ordenável */
        .ordenavel {
            cursor: pointer;
            user-select: none;
        }

        .ordenavel:hover {
            background-color: #e5e7eb !important;
        }

        dialog {
            border: none;
            border-radius: 8px;
            padding: 25px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        dialog::backdrop {
            background: rgba(0, 0, 0, 0.5);
        }

        @media (max-width: 900px) {
            .grid-acoes-dados {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .divisoria-vertical {
                border-left: none;
                padding-left: 0;
                border-top: 1px solid #e2e8f0;
                padding-top: 16px;
            }

            .grid-gerenciar {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'componente_header.php'; ?>
    <div id="loading-global" class="loading-overlay">
        <div class="loading-box">
            <div class="loading-texto">⚡ <span id="loading-mensagem-texto">Carregando dados...</span></div>
            <div class="barra-progresso-container">
                <div id="loading-linha" class="barra-progresso-linha"></div>
            </div>
            <div id="loading-pct" class="loading-porcentagem">0%</div>
        </div>
    </div>
    <div id="container-toasts-global" class="container-toasts"></div>
    <div class="dashboard-container">
        <main class="conteudo-principal">
            <section class="grid-gerenciar">
                <div class="box-recurso">
                    <h4>
                        <span>📦 Tipos de Material (Grupos)</span>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <button class="btn-acao btn-sucesso" style="padding:4px 8px; font-size:0.8rem;"
                                onclick="abrirModalRecurso('grupo')"><i class="fa-solid fa-plus"></i> Novo</button>
                            <button class="btn-toggle-card" id="btn-toggle-grupos"
                                onclick="toggleCard('grupos')">▼</button>
                        </div>
                    </h4>
                    <div class="lista-tags" id="preview-grupos">
                        <?php foreach (array_slice($grupos, 0, 3) as $g): ?>
                            <div class="tag-item" style="opacity:0.6;">
                                <span><?php echo htmlspecialchars($g['nome_grupo']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($grupos) > 3): ?>
                            <span
                                style="font-size:0.8rem; color:#6b7280; align-self:center;">+<?php echo count($grupos) - 3; ?>
                                mais...</span>
                        <?php endif; ?>
                    </div>
                    <div class="corpo-card-colapsavel recolhido" id="corpo-grupos">
                        <div class="lista-tags" style="margin-top:8px;">
                            <?php foreach ($grupos as $g): ?>
                                <div class="tag-item">
                                    <span><?php echo htmlspecialchars($g['nome_grupo']); ?></span>
                                    <button class="tag-btn tag-btn-edit"
                                        onclick="abrirModalRecurso('grupo', <?php echo $g['id']; ?>, '<?php echo htmlspecialchars(addslashes($g['nome_grupo'])); ?>')">✏️</button>
                                    <button class="tag-btn tag-btn-del"
                                        onclick="deletarRecurso('grupo', <?php echo $g['id']; ?>)">✕</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="box-recurso">
                    <h4>
                        <span>💳 Tipos de Compra</span>
                        <button class="btn-acao btn-sucesso" style="padding:4px 8px; font-size:0.8rem;"
                            onclick="abrirModalRecurso('compra')"><i class="fa-solid fa-plus"></i> Novo</button>
                    </h4>
                    <div class="lista-tags">
                        <?php foreach ($tiposCompra as $tc): ?>
                            <div class="tag-item">
                                <span><?php echo htmlspecialchars($tc['nome_tipo']); ?></span>
                                <button class="tag-btn tag-btn-edit"
                                    onclick="abrirModalRecurso('compra', <?php echo $tc['id']; ?>, '<?php echo htmlspecialchars(addslashes($tc['nome_tipo'])); ?>')">✏️</button>
                                <button class="tag-btn tag-btn-del"
                                    onclick="deletarRecurso('compra', <?php echo $tc['id']; ?>)">✕</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="box-recurso">
                    <h4>
                        <span>📋 Padronização</span>
                        <button class="btn-acao btn-sucesso" style="padding:4px 8px; font-size:0.8rem;"
                            onclick="abrirModalRecurso('padronizacao')"><i class="fa-solid fa-plus"></i> Novo</button>
                    </h4>
                    <div class="lista-tags">
                        <?php foreach ($padronizacoes as $p): ?>
                            <div class="tag-item">
                                <span><?php echo htmlspecialchars($p['nome_padrao']); ?></span>
                                <button class="tag-btn tag-btn-edit"
                                    onclick="abrirModalRecurso('padronizacao', <?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['nome_padrao'])); ?>')">✏️</button>
                                <button class="tag-btn tag-btn-del"
                                    onclick="deletarRecurso('padronizacao', <?php echo $p['id']; ?>)">✕</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="painel-governanca">
                <h3>💼 Central de Governança e Portabilidade</h3>
                <p class="subtitulo-painel">Gerencie a persistência, transferência e ciclo de vida das suas regras
                    inteligentes de mapeamento.</p>

                <div class="grid-acoes-dados">
                    <div class="coluna-acao-dados">
                        <h5>📥 Importar Configurações</h5>
                        <p>Carregue um arquivo dicionário (.json) gerado pelo HGestor para assimilar de forma automática
                            e em lote as parametrizações externas feitas em outra filial.</p>
                        <form action="../acoes/importar.php" method="POST" enctype="multipart/form-data"
                            class="upload-form" id="form-importar-json"
                            style="display: flex; gap: 8px; width: 100%; margin-top: 10px;">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="file" name="arquivo_json" accept=".json" required
                                style="font-size: 0.85rem; padding: 6px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px; flex: 1;">
                            <button type="submit" class="btn-acao btn-sucesso"
                                style="white-space: nowrap;"><i class="fa-solid fa-bolt"></i> Alimentar</button>
                        </form>
                    </div>

                    <div class="coluna-acao-dados divisoria-vertical">
                        <h5>📤 Exportar Backup</h5>
                        <p>Gere um arquivo inteligente criptografado contendo todos os seus vínculos semânticos atuais
                            para fins de backup ou portabilidade.</p>
                        <a href="../acoes/exportar.php" class="btn-acao btn-info" download
                            style="margin-top: 10px;"><i class="fa-solid fa-file-export"></i> Exportar Dados</a>
                    </div>

                    <div class="coluna-acao-dados divisoria-vertical">
                        <h5>⚠️ Limpeza Absoluta</h5>
                        <p>Restaure o motor de de-para para o estado original de fábrica. Esta ação desfaz todas as
                            associações ativas permanentemente.</p>
                        <button class="btn-acao btn-perigo"
                            style="margin-top: 10px;"
                            onclick="abrirModalReset()"><i class="fa-solid fa-trash-can"></i> Zerar Parâmetros</button>
                    </div>
                </div>
            </section>

            <section class="card-dados">
                <div class="card-header-tabela">
                    <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
                        <div>
                            <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;" for="filtro-grupo">Tipo de Material (Grupo)</label>
                            <select id="filtro-grupo" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 120px;">
                                <option value="TODOS">Todos</option>
                                <option value="VAZIO">-- Sem Grupo --</option>
                                <?php foreach ($grupos as $g): ?>
                                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['nome_grupo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;" for="filtro-compra">Tipo de Compra</label>
                            <select id="filtro-compra" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 120px;">
                                <option value="TODOS">Todos</option>
                                <option value="VAZIO">-- Não Definido --</option>
                                <?php foreach ($tiposCompra as $tc): ?>
                                    <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['nome_tipo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;" for="filtro-padronizacao">Padronização</label>
                            <select id="filtro-padronizacao" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 120px;">
                                <option value="TODOS">Todas</option>
                                <option value="VAZIO">-- Não Definido --</option>
                                <?php foreach ($padronizacoes as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome_padrao']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn-acao btn-neutro" style="padding: 6px 12px; font-size: 0.8rem;" onclick="limparFiltros()"><i class="fa-solid fa-broom"></i> Limpar</button>
                    </div>

                    <div class="busca-container">
                        <input type="text" id="filtro-busca" placeholder="Buscar por código ou descrição...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <span id="reg-visiveis" style="display:none;"><?php echo count($materiais); ?></span>
                </div>

                <div class="table-responsive">
                    <table class="tabela-hgestor" id="tabela-vinculos">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="chk-marcar-todos"></th>
                                <th class="ordenavel" onclick="ordenarTabela(1, 'num')">Código Tasy ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(2, 'text')">Descrição Original ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(3, 'select')">Tipo Material (Grupo) ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(4, 'select')">Tipo de Compra ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(5, 'select')">Padronização ▲▼</th>
                            </tr>
                        </thead>
                        <tbody id="corpo-tabela"></tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <div class="paginacao-container" style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px; padding: 10px 0;">
                    <button type="button" id="btn-pag-anterior" class="btn-acao btn-neutro" style="padding: 6px 12px; font-size: 0.85rem;"><i class="fa-solid fa-chevron-left"></i> Anterior</button>
                    <span id="indicador-pagina" style="font-size: 0.88rem; font-weight: 500; color: #4b5563;">Página 1 de 1</span>
                    <button type="button" id="btn-pag-proxima" class="btn-acao btn-neutro" style="padding: 6px 12px; font-size: 0.85rem;">Próxima <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </section>
        </main>
    </div>

    <div id="barra-lote" class="barra-lote">
        <div><strong id="contador-selecionados">0</strong> itens selecionados</div>
        <div class="lote-acoes">
            <select id="lote-grupo" class="select-param" style="min-width:140px; flex:1;">
                <option value="">-- Alterar Grupo para --</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['nome_grupo']); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="lote-compra" class="select-param" style="min-width:140px; flex:1;">
                <option value="">-- Alterar Compra para --</option>
                <?php foreach ($tiposCompra as $tc): ?>
                    <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['nome_tipo']); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="lote-padronizacao" class="select-param" style="min-width:140px; flex:1;">
                <option value="">-- Padronização para --</option>
                <?php foreach ($padronizacoes as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome_padrao']); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-acao btn-sucesso" style="padding:8px 14px;" onclick="executarAlteracaoEmLote()"><i class="fa-solid fa-bolt"></i>
                Aplicar</button>
            <button class="btn-acao btn-neutro" style="padding:8px 14px;"
                onclick="desmarcarTodos()"><i class="fa-solid fa-xmark"></i> Cancelar</button>
        </div>
    </div>

    <footer class="footer-horus">
        <p>&copy; <?php echo date('Y'); ?> <span>HorusDEV</span>. Todos os direitos reservados.</p>
        <p>Desenvolvido com excelência por <strong>Dândilo Silva</strong></p>
        <div class="footer-links">
            <a href="https://www.linkedin.com/in/d%C3%A2ndilo-silva-6a83a1152" target="_blank">🔗 LinkedIn</a>
            <a href="https://github.com/Dandilo-zz" target="_blank">💻 GitHub</a>
            <a href="https://instagram.com/dandilos" target="_blank">📸 Instagram</a>
        </div>
        <p><a href="politicas.php" style="font-weight: 600; color: #3b82f6; margin-right: 15px;">Privacidade e Termos de
                uso</a></p>
    </footer>

    <dialog id="modal-recurso">
        <h3 id="modal-titulo" style="color:#1e3a8a; margin-bottom:15px;">Gerenciar</h3>
        <form action="../acoes/recursos_gerenciar.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" id="modal-tipo" name="tipo" value="">
            <input type="hidden" id="modal-id" name="id" value="">
            <div class="form-group">
                <label id="modal-label-nome">Nome</label>
                <input type="text" id="modal-nome" name="nome" required style="width:100%;">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn-acao btn-neutro"
                    onclick="document.getElementById('modal-recurso').close()"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
                <button type="submit" class="btn-acao btn-sucesso"
                    style="padding:10px 20px;"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </dialog>

    <dialog id="modal-reset-seguro">
        <h3 style="color:#ef4444; margin-bottom:12px;">⚠️ Ação Irreversível</h3>
        <p style="font-size:0.9rem; color:#4b5563; margin-bottom:15px; line-height:1.4;">
            Esta ação irá <strong>apagar permanentemente</strong> todos os vínculos de Grupo, Tipo de Compra e
            Padronização de todos os seus materiais.
        </p>
        <div class="form-group">
            <label for="senha-reset" style="font-weight:600; color:#1f2937;">Confirme sua senha de acesso:</label>
            <input type="password" id="senha-reset" placeholder="Sua senha atual" style="width:100%; margin-top:5px;">
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:25px;">
            <button type="button" class="btn-acao btn-neutro"
                onclick="document.getElementById('modal-reset-seguro').close()"><i class="fa-solid fa-xmark"></i> Cancelar</button>
            <button type="button" class="btn-acao btn-perigo"
                style="padding:10px 20px;"
                onclick="executarResetTotal()"><i class="fa-solid fa-trash-can"></i> Confirmar Reset</button>
        </div>
    </dialog>

    <form id="form-deletar-escondido" action="../acoes/recursos_gerenciar.php" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <input type="hidden" name="acao" value="deletar">
        <input type="hidden" id="del-tipo" name="tipo" value="">
        <input type="hidden" id="del-id" name="id" value="">
    </form>
    <dialog id="modal-confirmar-deletar"
        style="border:none; border-radius:10px; padding:0; max-width:380px; width:100%; box-shadow:0 8px 32px rgba(0,0,0,0.12);"
        onclick="event.target===this&&this.close()">
        <div style="padding:24px;">
            <h3 style="color:#dc2626; margin:0 0 10px;">⚠️ Remover Item</h3>
            <p style="color:#6b7280; font-size:0.88rem; line-height:1.5; margin:0 0 20px;">
                Remover este item? Os vínculos associados voltarão para "Não Definido".
            </p>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button onclick="document.getElementById('modal-confirmar-deletar').close()" class="btn-acao btn-neutro"
                    style="padding:8px 16px;">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button onclick="executarDeletarRecurso()" class="btn-acao btn-perigo"
                    style="padding:8px 16px;">
                    <i class="fa-solid fa-trash-can"></i> Remover
                </button>
            </div>
        </div>
    </dialog>
    <script>
        let paginaAtual = 1;
        const itensPorPagina = 50;
        let materiaisFiltrados = [];
        const selecionadosLote = new Set();

        const mapaGrupos = {};
        const opcoesGrupo = [{ id: '', nome: '-- Sem Grupo --' }];
        const mapaCompra = {};
        const mapaPadrao = {};

        function inicializarMapeamentos() {
            GRUPOS_DATA.forEach(g => {
                mapaGrupos[g.id] = g.nome_grupo;
                opcoesGrupo.push({ id: String(g.id), nome: g.nome_grupo });
            });
            COMPRA_DATA.forEach(tc => {
                mapaCompra[tc.id] = tc.nome_tipo;
            });
            PADRAO_DATA.forEach(p => {
                mapaPadrao[p.id] = p.nome_padrao;
            });
        }

        const inputBusca = document.getElementById('filtro-busca');
        const selectGrupo = document.getElementById('filtro-grupo');
        const selectCompra = document.getElementById('filtro-compra');
        const selectPadronizacao = document.getElementById('filtro-padronizacao');
        const spanVisiveis = document.getElementById('reg-visiveis');
        const chkTodos = document.getElementById('chk-marcar-todos');
        const barraLote = document.getElementById('barra-lote');
        const txtContador = document.getElementById('contador-selecionados');

        function renderPagina(pagina) {
            paginaAtual = pagina;
            const tbody = document.querySelector('#tabela-vinculos tbody');
            tbody.innerHTML = '';

            const totalItens = materiaisFiltrados.length;
            const totalPaginas = Math.ceil(totalItens / itensPorPagina) || 1;

            if (paginaAtual < 1) paginaAtual = 1;
            if (paginaAtual > totalPaginas) paginaAtual = totalPaginas;

            document.getElementById('btn-pag-anterior').disabled = (paginaAtual === 1);
            document.getElementById('btn-pag-proxima').disabled = (paginaAtual === totalPaginas);
            document.getElementById('indicador-pagina').innerText = `Página ${paginaAtual} de ${totalPaginas}`;

            if (totalItens === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="txt-centro msg-vazia">Nenhum material encontrado.</td></tr>';
                return;
            }

            const inicio = (paginaAtual - 1) * itensPorPagina;
            const fim = Math.min(inicio + itensPorPagina, totalItens);
            const itensPagina = materiaisFiltrados.slice(inicio, fim);

            const fragment = document.createDocumentFragment();

            itensPagina.forEach(mat => {
                const tr = document.createElement('tr');
                tr.setAttribute('data-id', mat.id_material);
                
                const estaMarcado = selecionadosLote.has(String(mat.id_material));
                if (estaMarcado) {
                    tr.classList.add('linha-selecionada');
                }

                // Checkbox
                const tdCheck = document.createElement('td');
                const chk = document.createElement('input');
                chk.type = 'checkbox';
                chk.className = 'chk-material-item';
                chk.value = mat.id_material;
                chk.checked = estaMarcado;
                tdCheck.appendChild(chk);
                tr.appendChild(tdCheck);

                // Código
                const tdCod = document.createElement('td');
                const spanCod = document.createElement('span');
                spanCod.className = 'txt-mutado';
                spanCod.textContent = mat.id_material;
                tdCod.appendChild(spanCod);
                tr.appendChild(tdCod);

                // Descrição
                const tdDesc = document.createElement('td');
                const strongDesc = document.createElement('strong');
                strongDesc.textContent = mat.descricao;
                tdDesc.appendChild(strongDesc);
                tr.appendChild(tdDesc);

                // Grupo
                const tdGrupo = document.createElement('td');
                tdGrupo.setAttribute('data-valor-atual', mat.id_grupo || '');
                
                const selectGrupoEl = document.createElement('select');
                selectGrupoEl.className = 'select-param';
                selectGrupoEl.setAttribute('data-tipo', 'grupo');
                selectGrupoEl.style.display = 'none';
                
                const optGrupoVazio = document.createElement('option');
                optGrupoVazio.value = '';
                optGrupoVazio.textContent = '-- Sem Grupo --';
                selectGrupoEl.appendChild(optGrupoVazio);
                
                GRUPOS_DATA.forEach(g => {
                    const opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = g.nome_grupo;
                    if (g.id == mat.id_grupo) opt.selected = true;
                    selectGrupoEl.appendChild(opt);
                });
                tdGrupo.appendChild(selectGrupoEl);

                const nomeGrupoAtual = mapaGrupos[mat.id_grupo] || '-- Sem Grupo --';
                const wrapperGrupo = document.createElement('div');
                wrapperGrupo.className = 'select-wrapper';
                wrapperGrupo.innerHTML = `
                    <button type="button" class="select-searchable btn-grupo-display">${nomeGrupoAtual.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")}</button>
                    <div class="select-dropdown">
                        <input type="text" class="select-dropdown-busca" placeholder="🔍 Buscar grupo...">
                        <div class="select-dropdown-lista"></div>
                    </div>
                `;
                tdGrupo.appendChild(wrapperGrupo);
                tr.appendChild(tdGrupo);

                // Compra
                const tdCompra = document.createElement('td');
                tdCompra.className = 'coluna-compra';
                tdCompra.setAttribute('data-valor-atual', mat.id_tipo_compra || '');
                
                const selectCompraEl = document.createElement('select');
                selectCompraEl.className = 'select-param';
                selectCompraEl.setAttribute('data-tipo', 'compra');
                
                const optCompraVazio = document.createElement('option');
                optCompraVazio.value = '';
                optCompraVazio.textContent = '-- Não Definido --';
                selectCompraEl.appendChild(optCompraVazio);

                COMPRA_DATA.forEach(tc => {
                    const opt = document.createElement('option');
                    opt.value = tc.id;
                    opt.textContent = tc.nome_tipo;
                    if (tc.id == mat.id_tipo_compra) opt.selected = true;
                    selectCompraEl.appendChild(opt);
                });
                tdCompra.appendChild(selectCompraEl);
                tr.appendChild(tdCompra);

                // Padronização
                const tdPadrao = document.createElement('td');
                tdPadrao.className = 'coluna-padrao';
                tdPadrao.setAttribute('data-valor-atual', mat.id_padronizacao || '');

                const selectPadraoEl = document.createElement('select');
                selectPadraoEl.className = 'select-param';
                selectPadraoEl.setAttribute('data-tipo', 'padronizacao');

                const optPadraoVazio = document.createElement('option');
                optPadraoVazio.value = '';
                optPadraoVazio.textContent = '-- Não Definido --';
                selectPadraoEl.appendChild(optPadraoVazio);

                PADRAO_DATA.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nome_padrao;
                    if (p.id == mat.id_padronizacao) opt.selected = true;
                    selectPadraoEl.appendChild(opt);
                });
                tdPadrao.appendChild(selectPadraoEl);
                tr.appendChild(tdPadrao);

                fragment.appendChild(tr);
            });

            tbody.appendChild(fragment);

            const inputsVisiveis = document.querySelectorAll('#tabela-vinculos tbody .chk-material-item');
            if (inputsVisiveis.length > 0) {
                const todosMarcados = Array.from(inputsVisiveis).every(chk => chk.checked);
                chkTodos.checked = todosMarcados;
            } else {
                chkTodos.checked = false;
            }
        }

        function aplicarFiltros() {
            const busca = inputBusca.value.toLowerCase();
            const fGrupo = selectGrupo.value;
            const fCompra = selectCompra.value;
            const fPadronizacao = selectPadronizacao.value;

            materiaisFiltrados = MATERIAIS_DATA.filter(mat => {
                const textoBusca = (
                    (mat.id_material || '') + ' ' +
                    (mat.descricao || '')
                ).toLowerCase();

                const vGrupo = mat.id_grupo || '';
                const vCompra = mat.id_tipo_compra || '';
                const vPadronizacao = mat.id_padronizacao || '';

                const atendeBusca = textoBusca.includes(busca);
                const atendeGrupo = (fGrupo === 'TODOS') || (fGrupo === 'VAZIO' && vGrupo === '') || (fGrupo == vGrupo);
                const atendeCompra = (fCompra === 'TODOS') || (fCompra === 'VAZIO' && vCompra === '') || (fCompra == vCompra);
                const atendePadronizacao = (fPadronizacao === 'TODOS') || (fPadronizacao === 'VAZIO' && vPadronizacao === '') || (fPadronizacao == vPadronizacao);

                return atendeBusca && atendeGrupo && atendeCompra && atendePadronizacao;
            });

            spanVisiveis.innerText = materiaisFiltrados.length;
            renderPagina(1);
        }

        document.querySelector('#tabela-vinculos tbody').addEventListener('click', function (e) {
            if (e.target.tagName === 'SELECT' || e.target.type === 'checkbox' || e.target.closest('.select-wrapper')) return;

            const linha = e.target.closest('tr');
            if (!linha || linha.querySelector('.msg-vazia')) return;

            const checkboxInterno = linha.querySelector('.chk-material-item');
            if (checkboxInterno) {
                checkboxInterno.checked = !checkboxInterno.checked;
                const id = String(checkboxInterno.value);
                if (checkboxInterno.checked) {
                    selecionadosLote.add(id);
                    linha.classList.add('linha-selecionada');
                } else {
                    selecionadosLote.delete(id);
                    linha.classList.remove('linha-selecionada');
                }
                atualizarPainelLote();
            }
        });

        let _debounceF;
        inputBusca.addEventListener('keyup', () => {
            clearTimeout(_debounceF);
            _debounceF = setTimeout(aplicarFiltros, 180);
        });
        selectGrupo.addEventListener('change', aplicarFiltros);
        selectCompra.addEventListener('change', aplicarFiltros);
        selectPadronizacao.addEventListener('change', aplicarFiltros);

        function limparFiltros() {
            inputBusca.value = '';
            selectGrupo.value = 'TODOS';
            selectCompra.value = 'TODOS';
            selectPadronizacao.value = 'TODOS';
            aplicarFiltros();
        }

        function abrirDropdownGrupo(wrapper, selectReal) {
            const dropdown = wrapper.querySelector('.select-dropdown');
            const lista = wrapper.querySelector('.select-dropdown-lista');
            const busca = wrapper.querySelector('.select-dropdown-busca');
            const valorAtual = String(selectReal.value);

            function renderLista(filtro = '') {
                lista.innerHTML = '';
                opcoesGrupo
                    .filter(op => op.nome.toLowerCase().includes(filtro.toLowerCase()))
                    .forEach(op => {
                        const item = document.createElement('div');
                        item.className = 'select-dropdown-item' + (op.id === valorAtual ? ' ativo' : '');
                        item.textContent = op.nome;
                        item.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            selectReal.value = op.id;
                            selectReal.dispatchEvent(new Event('change'));
                            wrapper.querySelector('.btn-grupo-display').textContent = op.nome;
                            dropdown.classList.remove('aberto');
                        });
                        lista.appendChild(item);
                    });
            }

            renderLista();
            dropdown.classList.add('aberto');
            busca.value = '';
            busca.focus();
            busca.addEventListener('input', () => renderLista(busca.value));
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.select-wrapper')) {
                document.querySelectorAll('.select-dropdown.aberto').forEach(d => d.classList.remove('aberto'));
            }
        });

        document.querySelector('#tabela-vinculos tbody').addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-grupo-display');
            if (!btn) return;
            e.stopPropagation();
            const wrapper = btn.closest('.select-wrapper');
            const dropdown = wrapper.querySelector('.select-dropdown');
            const selectReal = wrapper.closest('td').querySelector('select.select-param');
            document.querySelectorAll('.select-dropdown.aberto').forEach(d => {
                if (d !== dropdown) d.classList.remove('aberto');
            });
            if (dropdown.classList.contains('aberto')) {
                dropdown.classList.remove('aberto');
            } else {
                abrirDropdownGrupo(wrapper, selectReal);
            }
        });

        let direcoesOrdenacao = {};
        function ordenarTabela(colunaIndex, tipo) {
            if (materiaisFiltrados.length === 0) return;

            const direcaoAtual = direcoesOrdenacao[colunaIndex] === 'asc' ? 'desc' : 'asc';
            direcoesOrdenacao = { [colunaIndex]: direcaoAtual };

            materiaisFiltrados.sort((a, b) => {
                let valA, valB;

                if (colunaIndex === 1) {
                    valA = parseInt(a.id_material) || 0;
                    valB = parseInt(b.id_material) || 0;
                } else if (colunaIndex === 2) {
                    valA = a.descricao.toLowerCase();
                    valB = b.descricao.toLowerCase();
                } else if (colunaIndex === 3) {
                    valA = (mapaGrupos[a.id_grupo] || '-- Sem Grupo --').toLowerCase();
                    valB = (mapaGrupos[b.id_grupo] || '-- Sem Grupo --').toLowerCase();
                } else if (colunaIndex === 4) {
                    valA = (mapaCompra[a.id_tipo_compra] || '-- Não Definido --').toLowerCase();
                    valB = (mapaCompra[b.id_tipo_compra] || '-- Não Definido --').toLowerCase();
                } else if (colunaIndex === 5) {
                    valA = (mapaPadrao[a.id_padronizacao] || '-- Não Definido --').toLowerCase();
                    valB = (mapaPadrao[b.id_padronizacao] || '-- Não Definido --').toLowerCase();
                }

                if (valA < valB) return direcaoAtual === 'asc' ? -1 : 1;
                if (valA > valB) return direcaoAtual === 'asc' ? 1 : -1;
                return 0;
            });

            renderPagina(paginaAtual);
        }

        function abrirModalRecurso(tipo, id = '', nome = '') {
            document.getElementById('modal-tipo').value = tipo;
            document.getElementById('modal-id').value = id;
            document.getElementById('modal-nome').value = nome;
            const txtTipo = tipo === 'grupo' ? 'Tipo de Material (Grupo)' : (tipo === 'compra' ? 'Tipo de Compra' : 'Padronização');
            document.getElementById('modal-titulo').innerText = id ? 'Editar ' + txtTipo : 'Criar Novo ' + txtTipo;
            document.getElementById('modal-recurso').showModal();
        }

        function deletarRecurso(tipo, id) {
            document.getElementById('del-tipo').value = tipo;
            document.getElementById('del-id').value = id;
            document.getElementById('modal-confirmar-deletar').showModal();
        }

        function executarDeletarRecurso() {
            document.getElementById('modal-confirmar-deletar').close();
            document.getElementById('form-deletar-escondido').submit();
        }

        chkTodos.addEventListener('change', function () {
            const inputsVisiveis = document.querySelectorAll('#tabela-vinculos tbody .chk-material-item');
            inputsVisiveis.forEach(chk => {
                chk.checked = chkTodos.checked;
                const tr = chk.closest('tr');
                const id = String(chk.value);
                tr.classList.toggle('linha-selecionada', chkTodos.checked);
                if (chkTodos.checked) {
                    selecionadosLote.add(id);
                } else {
                    selecionadosLote.delete(id);
                }
            });
            atualizarPainelLote();
        });

        document.querySelector('#tabela-vinculos tbody').addEventListener('change', function (e) {
            if (e.target.classList.contains('chk-material-item')) {
                const id = String(e.target.value);
                const tr = e.target.closest('tr');
                if (e.target.checked) {
                    selecionadosLote.add(id);
                    tr.classList.add('linha-selecionada');
                } else {
                    selecionadosLote.delete(id);
                    tr.classList.remove('linha-selecionada');
                }
                atualizarPainelLote();
            }
        });

        function atualizarPainelLote() {
            const total = selecionadosLote.size;
            txtContador.innerText = total;
            if (total > 0) barraLote.classList.add('ativa');
            else { barraLote.classList.remove('ativa'); chkTodos.checked = false; }
        }

        function desmarcarTodos() {
            selecionadosLote.clear();
            const inputsVisiveis = document.querySelectorAll('#tabela-vinculos tbody .chk-material-item');
            inputsVisiveis.forEach(chk => {
                chk.checked = false;
                chk.closest('tr').classList.remove('linha-selecionada');
            });
            chkTodos.checked = false;
            atualizarPainelLote();
        }

        function executarAlteracaoEmLote() {
            const ids = Array.from(selecionadosLote);
            const idGrupo = document.getElementById('lote-grupo').value;
            const idCompra = document.getElementById('lote-compra').value;
            const idPadronizacao = document.getElementById('lote-padronizacao').value;

            if (ids.length === 0) {
                lancarAlerta('Nenhum item selecionado.', 'alerta');
                return;
            }

            if (!idGrupo && !idCompra && !idPadronizacao) {
                lancarAlerta('Selecione ao menos um parâmetro para atualizar o lote.', 'alerta');
                return;
            }

            const promessas = [];
            if (idGrupo) promessas.push(fazerRequisicaoLote(ids, 'grupo', idGrupo));
            if (idCompra) promessas.push(fazerRequisicaoLote(ids, 'compra', idCompra));
            if (idPadronizacao) promessas.push(fazerRequisicaoLote(ids, 'padronizacao', idPadronizacao));

            Promise.all(promessas).then(() => {
                lancarAlerta('Propriedades atualizadas em lote com sucesso!', 'sucesso');
                setTimeout(() => window.location.reload(), 1200);
            }).catch(() => {
                lancarAlerta('Erro crítico ao processar atualização em lote.', 'erro');
            });
        }

        function fazerRequisicaoLote(ids_materiais, tipo_parametro, id_destino) {
            return fetch('../acoes/salvar_vinculo_lote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids_materiais, tipo_parametro, id_destino, csrf_token: window.csrfToken })
            }).then(res => { if (!res.ok) throw new Error(); return res.json(); });
        }

        function abrirModalReset() {
            document.getElementById('senha-reset').value = '';
            document.getElementById('modal-reset-seguro').showModal();
        }

        function executarResetTotal() {
            const senha = document.getElementById('senha-reset').value;
            if (!senha) {
                lancarAlerta('Você precisa digitar sua senha para prosseguir.', 'alerta');
                return;
            }

            document.getElementById('modal-reset-seguro').close();

            dispararCarregamentoVisivel("Limpando registros...", function () {
                fetch('../acoes/resetar_parametros.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ senha: senha, csrf_token: window.csrfToken })
                })
                    .then(res => {
                        if (res.status === 401) throw new Error('Senha incorreta.');
                        if (!res.ok) throw new Error('Erro no servidor.');
                        return res.json();
                    })
                    .then(data => {
                        lancarAlerta(data.mensagem, 'sucesso');
                        setTimeout(() => window.location.reload(), 1500);
                    })
                    .catch(err => {
                        lancarAlerta(err.message || 'Falha ao processar requisição de reset.', 'erro');
                    });
            });
        }

        document.querySelector('#tabela-vinculos tbody').addEventListener('change', function (e) {
            const select = e.target;
            if (select.classList.contains('select-param') && !select.id.startsWith('lote-') && !select.id.startsWith('filtro-')) {
                const celula = select.closest('td');
                const linha = select.closest('tr');
                const idMaterial = linha.getAttribute('data-id');
                const tipoParametro = select.getAttribute('data-tipo');
                const idDestino = select.value;

                select.style.borderColor = '#f59e0b';
                fetch('../acoes/salvar_vinculo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_material: idMaterial, tipo_parametro: tipoParametro, id_destino: idDestino, csrf_token: window.csrfToken })
                })
                    .then(res => { if (!res.ok) throw new Error(); return res.json(); })
                    .then(() => {
                        select.style.borderColor = '#10b981';
                        celula.setAttribute('data-valor-atual', idDestino);
                        lancarAlerta('Parametrização individual salva!', 'sucesso');
                        setTimeout(() => select.style.borderColor = '', 800);
                        
                        const item = MATERIAIS_DATA.find(m => String(m.id_material) === String(idMaterial));
                        if (item) {
                            if (tipoParametro === 'grupo') item.id_grupo = idDestino;
                            else if (tipoParametro === 'compra') item.id_tipo_compra = idDestino;
                            else if (tipoParametro === 'padronizacao') item.id_padronizacao = idDestino;
                        }
                    })
                    .catch(() => {
                        select.style.borderColor = '#ef4444';
                        lancarAlerta('Falha ao salvar parametrização.', 'erro');
                    });
            }
        });

        function lancarAlerta(mensagem, tipo = 'info', tempoExibicao = 4000) {
            const container = document.getElementById('container-toasts-global');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `alerta-toast toast-${tipo}`;

            let icone = 'ℹ️';
            if (tipo === 'sucesso') icone = '✅';
            if (tipo === 'erro') icone = '❌';
            if (tipo === 'alerta') icone = '⚠️';

            toast.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <span>${icone}</span>
                    <span>${mensagem}</span>
                </div>
                <button class="toast-fechar" onclick="this.parentElement.remove()">✕</button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(() => toast.remove(), 300);
            }, tempoExibicao);
        }

        window.addEventListener('DOMContentLoaded', () => {
            inicializarMapeamentos();
            materiaisFiltrados = [...MATERIAIS_DATA];
            renderPagina(1);

            document.getElementById('btn-pag-anterior').addEventListener('click', () => {
                if (paginaAtual > 1) renderPagina(paginaAtual - 1);
            });

            document.getElementById('btn-pag-proxima').addEventListener('click', () => {
                const totalPaginas = Math.ceil(materiaisFiltrados.length / itensPorPagina) || 1;
                if (paginaAtual < totalPaginas) renderPagina(paginaAtual + 1);
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

        function toggleCard(id) {
            const corpo = document.getElementById('corpo-' + id);
            const btn = document.getElementById('btn-toggle-' + id);
            const preview = document.getElementById('preview-' + id);
            const recolhido = corpo.classList.toggle('recolhido');
            btn.textContent = recolhido ? '▼' : '▲';
            if (preview) preview.style.display = recolhido ? 'flex' : 'none';
            localStorage.setItem('card-' + id + '-recolhido', recolhido ? '1' : '0');
        }

        function dispararCarregamentoVisivel(mensagem = "Carregando dados...", callback = null) {
            const overlay = document.getElementById('loading-global');
            const linha = document.getElementById('loading-linha');
            const txtPct = document.getElementById('loading-pct');

            document.getElementById('loading-mensagem-texto').innerText = mensagem || "Carregando dados...";

            if (!overlay || !linha || !txtPct) return;

            overlay.classList.add('ativo');

            const tempoTotal = Math.floor(Math.random() * (3000 - 1000 + 1)) + 1000;

            let progresso = 0;
            const intervaloTempo = 30;
            const passosTotais = tempoTotal / intervaloTempo;
            const incremento = 100 / passosTotais;

            const temporizador = setInterval(function () {
                progresso += incremento;

                if (progresso >= 100) {
                    progresso = 100;
                    clearInterval(temporizador);
                    if (typeof callback === 'function') callback();
                }

                linha.style.width = progresso + '%';
                txtPct.innerText = Math.floor(progresso) + '%';
            }, intervaloTempo);
        }

        document.getElementById('form-importar-json').addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;

            dispararCarregamentoVisivel("Analisando e assimilando árvore de dados...");

            setTimeout(function () {
                form.submit();
            }, 2800);
        });
    </script>
    <script>
    const MATERIAIS_DATA = <?php echo json_encode($materiais, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
    const GRUPOS_DATA    = <?php echo json_encode($grupos,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
    const COMPRA_DATA    = <?php echo json_encode($tiposCompra, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
    const PADRAO_DATA    = <?php echo json_encode($padronizacoes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
    </script>
</body>

</html>