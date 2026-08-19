<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: login");
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];

$stmtParams = $pdo->prepare("SELECT id, eixo, valor FROM endereco_params WHERE id_usuario=:uid ORDER BY eixo, valor ASC");
$stmtParams->execute(['uid' => $idUsuario]);
$params = $stmtParams->fetchAll(PDO::FETCH_ASSOC);

$stmtMat = $pdo->prepare("
    SELECT te.id_material, te.descricao, te.protheus, te.saldo,
           em.x, em.y, em.z
    FROM tasy_estoque te
    LEFT JOIN endereco_materiais em ON te.id_material = em.id_material AND em.id_usuario = :uid_em
    WHERE te.id_usuario = :uid_te
    ORDER BY te.descricao ASC
");
$stmtMat->execute(['uid_em' => $idUsuario, 'uid_te' => $idUsuario]);
$materiais = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

$paramsPorEixo = ['x' => [], 'y' => [], 'z' => []];
foreach ($params as $p) {
    $paramsPorEixo[$p['eixo']][] = ['id' => $p['id'], 'valor' => $p['valor']];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HGestor - Endereçamento de Estoque</title>
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
                        <span>📍 Eixo X (Rua)</span>
                        <button class="btn-acao btn-sucesso" style="padding:4px 8px; font-size:0.8rem;" onclick="abrirModalParam('x')">
                            <i class="fa-solid fa-plus"></i> Novo
                        </button>
                    </h4>
                    <div class="lista-tags" id="tags-eixo-x"></div>
                </div>

                <div class="box-recurso">
                    <h4>
                        <span>📍 Eixo Y (Prateleira)</span>
                        <button class="btn-acao btn-sucesso" style="padding:4px 8px; font-size:0.8rem;" onclick="abrirModalParam('y')">
                            <i class="fa-solid fa-plus"></i> Novo
                        </button>
                    </h4>
                    <div class="lista-tags" id="tags-eixo-y"></div>
                </div>

                <div class="box-recurso">
                    <h4>
                        <span>📍 Eixo Z (Nível)</span>
                        <button class="btn-acao btn-sucesso" style="padding:4px 8px; font-size:0.8rem;" onclick="abrirModalParam('z')">
                            <i class="fa-solid fa-plus"></i> Novo
                        </button>
                    </h4>
                    <div class="lista-tags" id="tags-eixo-z"></div>
                </div>
            </section>

            <section class="painel-governanca" style="background: white; border: 1px solid #cbd5e1; border-radius: 8px; padding: 24px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
                <h3 style="color: #1e3a8a; margin-bottom: 4px;">⚙️ Ações e Governança de Endereços</h3>
                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">Gerencie as parametrizações e exportações do seu mapa de endereçamento físico.</p>
                <div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: flex-start; align-items: center;">
                    <button class="btn-acao btn-sucesso" onclick="salvarGeral()"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</button>
                    <a href="../acoes/exportar_enderecos.php" target="_blank" class="btn-acao btn-info" style="text-decoration:none;"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
                    <button class="btn-acao btn-neutro" onclick="exportarPDF()"><i class="fa-solid fa-file-pdf"></i> Exportar PDF (Imprimir)</button>
                    <button class="btn-acao btn-perigo" onclick="abrirModalReset()"><i class="fa-solid fa-trash-can"></i> Limpar Endereços</button>
                </div>
            </section>

            <section class="card-dados" style="background: white; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; margin-bottom: 80px;">
                <div class="card-header-tabela" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                    <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
                        <div>
                            <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;" for="filtro-x">Filtro X (Rua)</label>
                            <select id="filtro-x" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 120px;"></select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;" for="filtro-y">Filtro Y (Prateleira)</label>
                            <select id="filtro-y" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 120px;"></select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; color:#6b7280; margin-bottom:4px;" for="filtro-z">Filtro Z (Nível)</label>
                            <select id="filtro-z" class="select-param" style="padding: 5px 8px; font-size: 0.8rem; width: auto; min-width: 120px;"></select>
                        </div>
                        <button class="btn-acao btn-neutro" style="padding: 6px 12px; font-size: 0.8rem;" onclick="limparFiltros()"><i class="fa-solid fa-broom"></i> Limpar</button>
                    </div>

                    <div class="busca-container" style="position: relative;">
                        <input type="text" id="filtro-busca" placeholder="Buscar código ou descrição..." style="padding: 6px 12px; padding-right: 30px; font-size: 0.85rem; border: 1px solid #cbd5e1; border-radius: 6px; width: 250px;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 0.85rem;"></i>
                    </div>
                    <span id="reg-visiveis" style="display:none;">0</span>
                </div>

                <div class="table-responsive">
                    <table class="tabela-hgestor" id="tabela-vinculos" style="width:100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="chk-marcar-todos"></th>
                                <th class="ordenavel" onclick="ordenarTabela(1, 'num')" style="width: 110px;">Código Tasy ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(2, 'text')" style="width: 110px;">Protheus ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(3, 'text')">Descrição Original ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(4, 'num')" style="width: 80px;">Saldo ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(5, 'select')" style="width: 140px;">X (Rua) ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(6, 'select')" style="width: 140px;">Y (Prateleira) ▲▼</th>
                                <th class="ordenavel" onclick="ordenarTabela(7, 'select')" style="width: 140px;">Z (Nível) ▲▼</th>
                            </tr>
                        </thead>
                        <tbody id="corpo-tabela"></tbody>
                    </table>
                </div>

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
            <select id="lote-x" class="select-param" style="min-width:140px; flex:1;"></select>
            <select id="lote-y" class="select-param" style="min-width:140px; flex:1;"></select>
            <select id="lote-z" class="select-param" style="min-width:140px; flex:1;"></select>
            <button class="btn-acao btn-sucesso" style="padding:8px 14px;" onclick="executarAlteracaoEmLote()"><i class="fa-solid fa-bolt"></i> Aplicar</button>
            <button class="btn-acao btn-neutro" style="padding:8px 14px;" onclick="desmarcarTodos()"><i class="fa-solid fa-xmark"></i> Cancelar</button>
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
        <p><a href="politicas" style="font-weight: 600; color: #3b82f6; margin-right: 15px;">Privacidade e Termos de uso</a></p>
    </footer>

    <dialog id="modal-param">
        <h3 id="modal-param-titulo" style="color:#1e3a8a; margin-bottom:15px;">Gerenciar Coordenada</h3>
        <form id="form-param">
            <input type="hidden" id="modal-param-id" value="">
            <input type="hidden" id="modal-param-eixo" value="">
            <div class="form-group">
                <label style="font-weight:600; color:#1f2937;">Coordenada (ex: A-01, 10, N2)</label>
                <input type="text" id="modal-param-valor" required style="width:100%; margin-top:5px;" maxlength="50">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn-acao btn-neutro" onclick="document.getElementById('modal-param').close()"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
                <button type="submit" class="btn-acao btn-sucesso" style="padding:10px 20px;"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </dialog>

    <dialog id="modal-confirmar-deletar-param">
        <div style="padding:10px;">
            <h3 style="color:#dc2626; margin:0 0 10px;">⚠️ Remover Coordenada</h3>
            <p style="color:#6b7280; font-size:0.88rem; line-height:1.5; margin:0 0 20px;">
                Deseja realmente remover esta coordenada? Os materiais vinculados a ela voltarão a ficar Sem Endereço.
            </p>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('modal-confirmar-deletar-param').close()" class="btn-acao btn-neutro">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="button" onclick="executarDeletarParam()" class="btn-acao btn-perigo">
                    <i class="fa-solid fa-trash-can"></i> Remover
                </button>
            </div>
        </div>
    </dialog>

    <dialog id="modal-reset" onclick="event.target===this&&this.close()">
        <h3 style="color:#ef4444; margin-bottom:12px;">⚠️ Ação Irreversível</h3>
        <p style="font-size:0.9rem; color:#4b5563; margin-bottom:15px; line-height:1.4;">
            Esta ação irá <strong>apagar permanentemente</strong> todos os endereços vinculados aos seus materiais.
        </p>
        <div class="form-group">
            <label for="senha-reset" style="font-weight:600; color:#1f2937;">Confirme sua senha de acesso:</label>
            <input type="password" id="senha-reset" placeholder="Sua senha atual" style="width:100%; margin-top:5px;">
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:25px;">
            <button class="btn-acao btn-neutro" onclick="document.getElementById('modal-reset').close()">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button class="btn-acao btn-perigo" style="padding:10px 20px;" onclick="executarReset()">
                <i class="fa-solid fa-trash-can"></i> Confirmar
            </button>
        </div>
    </dialog>
    <dialog id="modal-opcoes-pdf" onclick="event.target===this&&this.close()">
        <h3 style="color:#1e3a8a; margin-bottom:15px;">Opções de Exportação PDF</h3>
        <p style="font-size:0.9rem; color:#4b5563; margin-bottom:20px; line-height:1.4;">
            Selecione se deseja incluir a coluna de saldo atual dos materiais no relatório gerado.
        </p>
        <div style="display:flex; flex-direction:column; gap:10px; margin-top:20px;">
            <button class="btn-acao btn-sucesso" onclick="gerarPDF(true)" style="padding:10px;"><i class="fa-solid fa-list-ol"></i> Imprimir COM Saldo</button>
            <button class="btn-acao btn-info" onclick="gerarPDF(false)" style="padding:10px;"><i class="fa-solid fa-eye-slash"></i> Imprimir SEM Saldo</button>
            <button class="btn-acao btn-neutro" onclick="document.getElementById('modal-opcoes-pdf').close()" style="padding:10px; margin-top:5px;"><i class="fa-solid fa-xmark"></i> Cancelar</button>
        </div>
    </dialog>

    <script>
        const MATERIAIS_DATA = <?php echo json_encode($materiais, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
        const PARAMS_EIXO   = <?php echo json_encode($paramsPorEixo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
        const ESTOQUE_NOME  = <?php echo json_encode($_SESSION['user']['estoque_nome'] ?? 'Geral', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
        
        let paginaAtual = 1;
        const itensPorPagina = 50;
        let materiaisFiltrados = [];
        const selecionadosLote = new Set();

        const inputBusca = document.getElementById('filtro-busca');
        const selectFiltroX = document.getElementById('filtro-x');
        const selectFiltroY = document.getElementById('filtro-y');
        const selectFiltroZ = document.getElementById('filtro-z');
        const spanVisiveis = document.getElementById('reg-visiveis');
        const chkTodos = document.getElementById('chk-marcar-todos');
        const barraLote = document.getElementById('barra-lote');
        const txtContador = document.getElementById('contador-selecionados');

        function renderTags() {
            ['x', 'y', 'z'].forEach(eixo => {
                const container = document.getElementById(`tags-eixo-${eixo}`);
                container.innerHTML = '';
                const lista = PARAMS_EIXO[eixo] || [];
                if (lista.length === 0) {
                    container.innerHTML = '<span style="font-size:0.8rem; color:#9ca3af; font-style:italic;">Nenhuma coordenada cadastrada</span>';
                    return;
                }
                lista.forEach(p => {
                    const tag = document.createElement('div');
                    tag.className = 'tag-item';
                    tag.innerHTML = `
                        <span>${p.valor.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")}</span>
                        <button class="tag-btn tag-btn-edit" onclick="abrirModalParam('${eixo}', ${p.id}, '${p.valor.replace(/'/g, "\\'")}')">✏️</button>
                        <button class="tag-btn tag-btn-del" onclick="confirmarDeletarParam(${p.id}, '${eixo}')">✕</button>
                    `;
                    container.appendChild(tag);
                });
            });
        }

        function repovoarSelectsEixo() {
            ['x', 'y', 'z'].forEach(eixo => {
                const selectFiltro = document.getElementById(`filtro-${eixo}`);
                const valorFiltroAtual = selectFiltro.value;
                selectFiltro.innerHTML = `
                    <option value="TODOS">Todos</option>
                    <option value="VAZIO">-- Sem Endereço --</option>
                `;
                const opcoes = PARAMS_EIXO[eixo] || [];
                opcoes.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.valor;
                    opt.textContent = p.valor;
                    if (p.valor === valorFiltroAtual) opt.selected = true;
                    selectFiltro.appendChild(opt);
                });

                const selectLote = document.getElementById(`lote-${eixo}`);
                const valorLoteAtual = selectLote.value;
                const labelEixo = eixo === 'x' ? 'Rua' : (eixo === 'y' ? 'Prateleira' : 'Nível');
                selectLote.innerHTML = `<option value="">-- Manter ${labelEixo} --</option>`;
                opcoes.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.valor;
                    opt.textContent = p.valor;
                    if (p.valor === valorLoteAtual) opt.selected = true;
                    selectLote.appendChild(opt);
                });
            });
        }

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
                tbody.innerHTML = '<tr><td colspan="8" class="txt-centro msg-vazia">Nenhum material encontrado.</td></tr>';
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

                const tdCheck = document.createElement('td');
                const chk = document.createElement('input');
                chk.type = 'checkbox';
                chk.className = 'chk-material-item';
                chk.value = mat.id_material;
                chk.checked = estaMarcado;
                tdCheck.appendChild(chk);
                tr.appendChild(tdCheck);

                const tdCod = document.createElement('td');
                const spanCod = document.createElement('span');
                spanCod.className = 'txt-mutado';
                spanCod.textContent = mat.id_material;
                tdCod.appendChild(spanCod);
                tr.appendChild(tdCod);

                const tdProtheus = document.createElement('td');
                const spanPro = document.createElement('span');
                spanPro.className = 'txt-mutado';
                spanPro.textContent = mat.protheus || '-';
                tdProtheus.appendChild(spanPro);
                tr.appendChild(tdProtheus);

                const tdDesc = document.createElement('td');
                const strongDesc = document.createElement('strong');
                strongDesc.textContent = mat.descricao;
                tdDesc.appendChild(strongDesc);
                tr.appendChild(tdDesc);

                const tdSaldo = document.createElement('td');
                tdSaldo.textContent = mat.saldo;
                tr.appendChild(tdSaldo);

                ['x', 'y', 'z'].forEach(eixo => {
                    const td = document.createElement('td');
                    td.setAttribute('data-valor-atual', mat[eixo] || '');
                    
                    const select = document.createElement('select');
                    select.className = 'select-param';
                    select.setAttribute('data-tipo', eixo);
                    select.setAttribute('data-id-material', mat.id_material);
                    
                    const optVazio = document.createElement('option');
                    optVazio.value = '';
                    optVazio.textContent = '-- Sem Endereço --';
                    select.appendChild(optVazio);
                    
                    const opcoes = PARAMS_EIXO[eixo] || [];
                    opcoes.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.valor;
                        opt.textContent = p.valor;
                        if (mat[eixo] == p.valor) opt.selected = true;
                        select.appendChild(opt);
                    });
                    
                    td.appendChild(select);
                    tr.appendChild(td);
                });

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
            const fX = selectFiltroX.value;
            const fY = selectFiltroY.value;
            const fZ = selectFiltroZ.value;

            materiaisFiltrados = MATERIAIS_DATA.filter(mat => {
                const textoBusca = (
                    (mat.id_material || '') + ' ' +
                    (mat.descricao || '') + ' ' +
                    (mat.protheus || '')
                ).toLowerCase();

                const vX = mat.x || '';
                const vY = mat.y || '';
                const vZ = mat.z || '';

                const atendeBusca = textoBusca.includes(busca);
                const atendeX = (fX === 'TODOS') || (fX === 'VAZIO' && vX === '') || (fX === vX);
                const atendeY = (fY === 'TODOS') || (fY === 'VAZIO' && vY === '') || (fY === vY);
                const atendeZ = (fZ === 'TODOS') || (fZ === 'VAZIO' && vZ === '') || (fZ === vZ);

                return atendeBusca && atendeX && atendeY && atendeZ;
            });

            spanVisiveis.innerText = materiaisFiltrados.length;
            renderPagina(1);
        }

        let _debounceF;
        inputBusca.addEventListener('keyup', () => {
            clearTimeout(_debounceF);
            _debounceF = setTimeout(aplicarFiltros, 180);
        });
        selectFiltroX.addEventListener('change', aplicarFiltros);
        selectFiltroY.addEventListener('change', aplicarFiltros);
        selectFiltroZ.addEventListener('change', aplicarFiltros);

        function limparFiltros() {
            inputBusca.value = '';
            selectFiltroX.value = 'TODOS';
            selectFiltroY.value = 'TODOS';
            selectFiltroZ.value = 'TODOS';
            aplicarFiltros();
        }

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
                    valA = (a.protheus || '').toLowerCase();
                    valB = (b.protheus || '').toLowerCase();
                } else if (colunaIndex === 3) {
                    valA = a.descricao.toLowerCase();
                    valB = b.descricao.toLowerCase();
                } else if (colunaIndex === 4) {
                    valA = parseFloat(a.saldo) || 0;
                    valB = parseFloat(b.saldo) || 0;
                } else if (colunaIndex === 5) {
                    valA = (a.x || '').toLowerCase();
                    valB = (b.x || '').toLowerCase();
                } else if (colunaIndex === 6) {
                    valA = (a.y || '').toLowerCase();
                    valB = (b.y || '').toLowerCase();
                } else if (colunaIndex === 7) {
                    valA = (a.z || '').toLowerCase();
                    valB = (b.z || '').toLowerCase();
                }

                if (valA < valB) return direcaoAtual === 'asc' ? -1 : 1;
                if (valA > valB) return direcaoAtual === 'asc' ? 1 : -1;
                return 0;
            });

            renderPagina(paginaAtual);
        }

        function abrirModalParam(eixo, id = '', valor = '') {
            document.getElementById('modal-param-eixo').value = eixo;
            document.getElementById('modal-param-id').value = id;
            document.getElementById('modal-param-valor').value = valor;
            const eixoNome = eixo.toUpperCase();
            document.getElementById('modal-param-titulo').innerText = id ? `Editar Coordenada no Eixo ${eixoNome}` : `Adicionar Nova Coordenada no Eixo ${eixoNome}`;
            document.getElementById('modal-param').showModal();
        }

        function confirmarDeletarParam(id, eixo) {
            const dialog = document.getElementById('modal-confirmar-deletar-param');
            dialog.setAttribute('data-id', id);
            dialog.setAttribute('data-eixo', eixo);
            dialog.showModal();
        }

        function executarDeletarParam() {
            const dialog = document.getElementById('modal-confirmar-deletar-param');
            const id = dialog.getAttribute('data-id');
            const eixo = dialog.getAttribute('data-eixo');
            dialog.close();

            fetch('../acoes/salvar_params_endereco.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'deletar', id: id, csrf_token: window.csrfToken })
            })
            .then(res => res.json().then(data => ({ status: res.status, body: data })))
            .then(res => {
                if (res.status !== 200) {
                    throw new Error(res.body.erro || 'Falha ao deletar parâmetro.');
                }
                lancarAlerta('Coordenada removida com sucesso!', 'sucesso');
                PARAMS_EIXO[eixo] = PARAMS_EIXO[eixo].filter(p => p.id != id);
                renderTags();
                repovoarSelectsEixo();
                aplicarFiltros();
            })
            .catch(err => {
                lancarAlerta(err.message, 'erro');
            });
        }

        document.getElementById('form-param').addEventListener('submit', function(e) {
            e.preventDefault();
            const id = document.getElementById('modal-param-id').value;
            const eixo = document.getElementById('modal-param-eixo').value;
            const valor = document.getElementById('modal-param-valor').value;
            const acao = id ? 'editar' : 'adicionar';

            fetch('../acoes/salvar_params_endereco.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao, id, eixo, valor, csrf_token: window.csrfToken })
            })
            .then(res => res.json().then(data => ({ status: res.status, body: data })))
            .then(res => {
                if (res.status !== 200) {
                    throw new Error(res.body.erro || 'Erro ao salvar parâmetro.');
                }
                lancarAlerta(id ? 'Coordenada atualizada!' : 'Coordenada cadastrada!', 'sucesso');
                document.getElementById('modal-param').close();

                if (id) {
                    const p = PARAMS_EIXO[eixo].find(x => x.id == id);
                    if (p) {
                        const valorAntigo = p.valor;
                        p.valor = valor;
                        
                        MATERIAIS_DATA.forEach(mat => {
                            if (mat[eixo] === valorAntigo) {
                                mat[eixo] = valor;
                            }
                        });
                    }
                } else {
                    PARAMS_EIXO[eixo].push({ id: res.body.id, valor: valor });
                    PARAMS_EIXO[eixo].sort((a, b) => a.valor.localeCompare(b.valor));
                }

                renderTags();
                repovoarSelectsEixo();
                aplicarFiltros();
            })
            .catch(err => {
                lancarAlerta(err.message, 'erro');
            });
        });

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

        document.querySelector('#tabela-vinculos tbody').addEventListener('click', function (e) {
            if (e.target.tagName === 'SELECT' || e.target.type === 'checkbox') return;

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

        function atualizarPainelLote() {
            const total = selecionadosLote.size;
            txtContador.innerText = total;
            if (total > 0) {
                barraLote.classList.add('ativa');
            } else {
                barraLote.classList.remove('ativa');
                chkTodos.checked = false;
            }
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
            const valX = document.getElementById('lote-x').value;
            const valY = document.getElementById('lote-y').value;
            const valZ = document.getElementById('lote-z').value;

            if (ids.length === 0) {
                lancarAlerta('Nenhum item selecionado.', 'alerta');
                return;
            }

            if (valX === '' && valY === '' && valZ === '') {
                lancarAlerta('Selecione ao menos um parâmetro para atualizar o lote.', 'alerta');
                return;
            }

            const body = {
                acao: 'lote',
                ids_materiais: ids,
                csrf_token: window.csrfToken
            };
            if (valX !== '') body.x = valX;
            if (valY !== '') body.y = valY;
            if (valZ !== '') body.z = valZ;

            dispararCarregamentoVisivel("Aplicando alteração em lote...", function() {
                fetch('../acoes/salvar_endereco.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                })
                .then(res => { if (!res.ok) throw new Error(); return res.json(); })
                .then(() => {
                    lancarAlerta('Parâmetros em lote atualizados com sucesso!', 'sucesso');
                    ids.forEach(id => {
                        const m = MATERIAIS_DATA.find(x => String(x.id_material) === String(id));
                        if (m) {
                            if (valX !== '') m.x = valX;
                            if (valY !== '') m.y = valY;
                            if (valZ !== '') m.z = valZ;
                        }
                    });
                    desmarcarTodos();
                    aplicarFiltros();
                })
                .catch(() => {
                    lancarAlerta('Falha ao processar alteração em lote.', 'erro');
                });
            });
        }

        function salvarGeral() {
            const linhas = document.querySelectorAll('#tabela-vinculos tbody tr');
            const materiais = [];
            
            linhas.forEach(tr => {
                const idMaterial = tr.getAttribute('data-id');
                if (!idMaterial || tr.querySelector('.msg-vazia')) return;
                
                const selectX = tr.querySelector('select[data-tipo="x"]');
                const selectY = tr.querySelector('select[data-tipo="y"]');
                const selectZ = tr.querySelector('select[data-tipo="z"]');
                
                if (selectX && selectY && selectZ) {
                    materiais.push({
                        id_material: idMaterial,
                        x: selectX.value,
                        y: selectY.value,
                        z: selectZ.value
                    });
                }
            });

            if (materiais.length === 0) {
                lancarAlerta('Nenhum material editável no momento.', 'alerta');
                return;
            }

            dispararCarregamentoVisivel("Salvando alterações...", function() {
                fetch('../acoes/salvar_endereco.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'lote', materiais: materiais, csrf_token: window.csrfToken })
                })
                .then(res => { if (!res.ok) throw new Error(); return res.json(); })
                .then(() => {
                    lancarAlerta('Todas as alterações visíveis foram salvas com sucesso!', 'sucesso');
                    materiais.forEach(item => {
                        const m = MATERIAIS_DATA.find(x => String(x.id_material) === String(item.id_material));
                        if (m) {
                            m.x = item.x;
                            m.y = item.y;
                            m.z = item.z;
                        }
                    });
                    aplicarFiltros();
                })
                .catch(() => {
                    lancarAlerta('Falha ao processar salvamento geral.', 'erro');
                });
            });
        }

        function abrirModalReset() {
            document.getElementById('senha-reset').value = '';
            document.getElementById('modal-reset').showModal();
        }

        function executarReset() {
            const senha = document.getElementById('senha-reset').value;
            if (!senha) {
                lancarAlerta('Você precisa digitar sua senha para prosseguir.', 'alerta');
                return;
            }

            document.getElementById('modal-reset').close();

            dispararCarregamentoVisivel("Limpando endereços...", function () {
                fetch('../acoes/resetar_enderecos.php', {
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
                    MATERIAIS_DATA.forEach(m => {
                        m.x = null;
                        m.y = null;
                        m.z = null;
                    });
                    desmarcarTodos();
                    aplicarFiltros();
                })
                .catch(err => {
                    lancarAlerta(err.message || 'Falha ao processar requisição de reset.', 'erro');
                });
            });
        }

        function exportarPDF() {
            const itensEnderecados = MATERIAIS_DATA.filter(m => m.x || m.y || m.z);
            if (itensEnderecados.length === 0) {
                lancarAlerta('Não há materiais endereçados para exportar.', 'alerta');
                return;
            }
            document.getElementById('modal-opcoes-pdf').showModal();
        }

        function gerarPDF(incluirSaldo) {
            document.getElementById('modal-opcoes-pdf').close();
            const itensEnderecados = MATERIAIS_DATA.filter(m => m.x || m.y || m.z);
            const dataGeracao = new Date().toLocaleString('pt-BR');
            const fmt = n => Number(n).toLocaleString('pt-BR');
            
            const linhasHtml = itensEnderecados.map((item, i) => `
            <tr style="background:${i % 2 === 0 ? '#f8fafc' : '#ffffff'}">
                <td>${item.id_material}</td>
                <td>${item.protheus || '—'}</td>
                <td><strong>${item.descricao}</strong></td>
                ${incluirSaldo ? `<td class="num">${fmt(item.saldo)}</td>` : ''}
                <td>${item.x || '—'}</td>
                <td>${item.y || '—'}</td>
                <td>${item.z || '—'}</td>
            </tr>`).join('');

            const html = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Mapa de Endereçamento - HGestor</title>
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
            <div style="font-size:13px;font-weight:bold;">MAPA DE ENDEREÇAMENTO</div>
            <div style="margin-top:4px;">Gerado em: ${dataGeracao}</div>
        </div>
    </div>

    <div class="infos">
        <div class="info-box">
            <div class="info-label">Estoque / Setor</div>
            <div class="info-valor">${ESTOQUE_NOME}</div>
        </div>
    </div>

    <div class="resumo">
        Total de itens endereçados: <strong>${itensEnderecados.length}</strong>
    </div>

    <div class="tabela-wrap">
        <table>
            <thead>
                <tr>
                    <th>Cód. Tasy</th>
                    <th>Cód. Protheus</th>
                    <th>Descrição</th>
                    ${incluirSaldo ? '<th class="num">Saldo</th>' : ''}
                    <th>X (Rua)</th>
                    <th>Y (Prateleira)</th>
                    <th>Z (Nível)</th>
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

        document.querySelector('#tabela-vinculos tbody').addEventListener('change', function (e) {
            const select = e.target;
            if (select.classList.contains('select-param') && !select.id.startsWith('lote-') && !select.id.startsWith('filtro-')) {
                const celula = select.closest('td');
                const linha = select.closest('tr');
                const idMaterial = select.getAttribute('data-id-material');
                const eixo = select.getAttribute('data-tipo');
                const valor = select.value;

                select.style.borderColor = '#f59e0b';
                fetch('../acoes/salvar_endereco.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'individual', id_material: idMaterial, eixo: eixo, valor: valor, csrf_token: window.csrfToken })
                })
                .then(res => { if (!res.ok) throw new Error(); return res.json(); })
                .then(() => {
                    select.style.borderColor = '#10b981';
                    celula.setAttribute('data-valor-atual', valor);
                    lancarAlerta('Endereço salvo com sucesso!', 'sucesso');
                    setTimeout(() => select.style.borderColor = '', 800);
                    
                    const item = MATERIAIS_DATA.find(m => String(m.id_material) === String(idMaterial));
                    if (item) {
                        item[eixo] = valor;
                    }
                })
                .catch(() => {
                    select.style.borderColor = '#ef4444';
                    lancarAlerta('Falha ao salvar endereço.', 'erro');
                });
            }
        });

        function lancarAlerta(mensagem, tipo = 'info', tempoExibicao = 4000) {
            const container = document.getElementById('container-toasts-global');
            if (!container) return;

            container.innerHTML = '';
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

        function dispararCarregamentoVisivel(mensagem = "Carregando dados...", callback = null) {
            const overlay = document.getElementById('loading-global');
            const linha = document.getElementById('loading-linha');
            const txtPct = document.getElementById('loading-pct');

            document.getElementById('loading-mensagem-texto').innerText = mensagem;

            if (!overlay || !linha || !txtPct) return;

            overlay.classList.add('ativo');

            const tempoTotal = Math.floor(Math.random() * (2000 - 800 + 1)) + 800;

            let progresso = 0;
            const intervaloTempo = 30;
            const passosTotais = tempoTotal / intervaloTempo;
            const incremento = 100 / passosTotais;

            const temporizador = setInterval(function () {
                progresso += incremento;

                if (progresso >= 100) {
                    progresso = 100;
                    clearInterval(temporizador);
                    overlay.classList.remove('ativo');
                    if (typeof callback === 'function') callback();
                }

                linha.style.width = progresso + '%';
                txtPct.innerText = Math.floor(progresso) + '%';
            }, intervaloTempo);
        }

        window.addEventListener('DOMContentLoaded', () => {
            renderTags();
            repovoarSelectsEixo();
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
    </script>
</body>
</html>
