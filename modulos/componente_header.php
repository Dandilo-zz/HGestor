<?php
if (!isset($pdo)) {
    require_once '../config/conexao.php';
}
if (isset($_SESSION['user']['id'])) {
    $idUserLogado = (int) $_SESSION['user']['id'];
    $stmtAlertasAtivos = $pdo->prepare("
        SELECT a.* FROM admin_alertas a
        WHERE NOT EXISTS (
            SELECT 1 FROM alerta_leituras al
             WHERE al.id_alerta = a.id
               AND al.id_usuario = :id_user
        )
        ORDER BY a.criado_em DESC
    ");
    $stmtAlertasAtivos->execute(['id_user' => $idUserLogado]);
    $alertasNaoLidos = $stmtAlertasAtivos->fetchAll();
    $estoquesModal = $pdo->query("SELECT nome_estoque FROM estoque_nomes ORDER BY nome_estoque ASC")->fetchAll();
} else {
    $alertasNaoLidos = [];
    $estoquesModal = [];
}

$pagina_atual = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
<script>window.csrfToken = "<?php echo $_SESSION['csrf_token'] ?? ''; ?>";</script>
<header class="navbar">
    <div class="logo-container">
        <a href="estoque" class="logo-link">
            <img src="../assets/img/LOGO.png" alt="HGestor" class="logo-img">
        </a>
    </div>
    <button class="btn-sanduiche" onclick="toggleMenuSanduiche()" aria-label="Menu">
        <i class="fa-solid fa-bars" id="icon-sanduiche"></i>
    </button>
    <nav class="navbar-menu">
        <a href="estoque"
            class="nav-link <?php echo $pagina_atual == 'estoque.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
        <a href="compras" class="nav-link <?php echo $pagina_atual == 'compras.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-cart-shopping"></i> Pedido de Compras
        </a>
        <a href="pre_inventario"
            class="nav-link <?php echo $pagina_atual == 'pre_inventario.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-clipboard-list"></i> Pré-Inventário
        </a>
        <a href="enderecamento" class="nav-link <?php echo $pagina_atual == 'enderecamento.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-location-dot"></i> Endereçamento
        </a>
        <a href="parametros"
            class="nav-link <?php echo $pagina_atual == 'parametros.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-sliders"></i> Parâmetros
        </a>        
        <?php if (isset($_SESSION['user']['is_admin']) && (int) $_SESSION['user']['is_admin'] === 1): ?>
            <a href="admin"
                class="nav-link <?php echo $pagina_atual == 'admin.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-shield-halved"></i> Painel ADM
            </a>
        <?php endif; ?>
    </nav>

    <div class="user-menu">
        <button class="user-name" onclick="abrirModalPerfil()"
            style="background:none; border:none; cursor:pointer; font:inherit; color:inherit; display:flex; align-items:center; gap:6px; padding:4px 8px; border-radius:6px; transition:background 0.15s;"
            onmouseover="this.style.background='rgba(0,0,0,0.06)'" onmouseout="this.style.background='none'">
            <i class="fa-solid fa-circle-user"></i>
            <?php echo isset($_SESSION['user']['login']) ? htmlspecialchars($_SESSION['user']['login']) : 'Usuário'; ?>
        </button>
        <div class="notif-container" id="notifContainer">
            <button class="notif-bell-btn" onclick="toggleDropdownNotif(event)">
                <i class="fa-regular fa-bell"></i>
                <span class="notif-badge" id="notifBadge" <?php echo count($alertasNaoLidos) == 0 ? 'style="display:none;"' : ''; ?>>
                    <?php echo count($alertasNaoLidos); ?>
                </span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-dropdown-header">
                    <span>Alertas Administrativos</span>
                </div>
                <div class="notif-dropdown-content">
                    <?php if (count($alertasNaoLidos) == 0): ?>
                        <div class="notif-item-vazio">Nenhum alerta novo</div>
                    <?php else: ?>
                        <?php foreach ($alertasNaoLidos as $alerta): ?>
                            <div class="notif-item nt-<?php echo htmlspecialchars($alerta['tipo']); ?>"
                                id="notif-item-<?php echo $alerta['id']; ?>">
                                <p class="notif-texto"><?php echo htmlspecialchars($alerta['mensagem']); ?></p>
                                <button class="btn-notif-ciente" onclick="marcarCiente(<?php echo $alerta['id']; ?>, event)">
                                    Entendido
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <a href="../acoes/logout.php" class="btn-logout">Sair</a>
    </div>
</header>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="fecharSidebar()"></div>
<nav class="sidebar" id="sidebarMobile">
    <div class="sidebar-header">
        <span>Menu</span>
        <button onclick="fecharSidebar()" class="sidebar-fechar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <a href="estoque" class="sidebar-link <?php echo $pagina_atual == 'estoque.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard
    </a>
    <a href="compras" class="sidebar-link <?php echo $pagina_atual == 'compras.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-cart-shopping"></i> Pedido de Compras
    </a>
    <a href="parametros" class="sidebar-link <?php echo $pagina_atual == 'parametros.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-sliders"></i> Parâmetros
    </a>
    <a href="pre_inventario" class="sidebar-link <?php echo $pagina_atual == 'pre_inventario.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-clipboard-list"></i> Pré-Inventário
    </a>
    <a href="enderecamento" class="sidebar-link <?php echo $pagina_atual == 'enderecamento.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-location-dot"></i> Endereços
    </a>
    <?php if (isset($_SESSION['user']['is_admin']) && (int) $_SESSION['user']['is_admin'] === 1): ?>
    <a href="admin" class="sidebar-link <?php echo $pagina_atual == 'admin.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-shield-halved"></i> Painel ADM
    </a>
    <?php endif; ?>
    <div class="sidebar-footer">
        <button onclick="abrirModalPerfil()" class="sidebar-link" style="background:none;border:none;width:100%;text-align:left;cursor:pointer;">
            <i class="fa-solid fa-circle-user"></i>
            <?php echo isset($_SESSION['user']['login']) ? htmlspecialchars($_SESSION['user']['login']) : 'Usuário'; ?>
        </button>
        <a href="../acoes/logout.php" class="sidebar-link sidebar-sair">
            <i class="fa-solid fa-right-from-bracket"></i> Sair
        </a>
    </div>
</nav>

<script>
    function marcarCiente(idAlerta, event) {
        if (event) event.stopPropagation();

        fetch('../acoes/marcar_alerta_lido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_alerta=' + idAlerta
        })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    const elemento = document.getElementById('notif-item-' + idAlerta);
                    if (elemento) {
                        elemento.style.transition = 'opacity 0.25s ease, max-height 0.35s ease, padding 0.35s ease, margin 0.35s ease';
                        elemento.style.overflow = 'hidden';
                        elemento.style.maxHeight = elemento.scrollHeight + 'px';
                        requestAnimationFrame(() => {
                            elemento.style.opacity = '0';
                            elemento.style.maxHeight = '0';
                            elemento.style.padding = '0';
                            elemento.style.margin = '0';
                        });
                        setTimeout(() => {
                            elemento.remove();

                            const badge = document.getElementById('notifBadge');
                            if (badge) {
                                let atual = parseInt(badge.innerText) - 1;
                                if (atual <= 0) {
                                    badge.style.display = 'none';
                                    document.querySelector('.notif-dropdown-content').innerHTML = '<div class="notif-item-vazio">Nenhum alerta novo</div>';
                                } else {
                                    badge.innerText = atual;
                                }
                            }
                        }, 300);
                    }
                } else {
                    lancarAlerta('Erro ao marcar como lido: ' + data.erro, 'erro');
                }
            })
            .catch(err => console.error('Erro:', err));
    }
</script>
<script>
    function toggleDropdownNotif(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notifDropdown');
        dropdown.classList.toggle('active');
    }

    function toggleMenuSanduiche() {
        const sidebar = document.getElementById('sidebarMobile');
        const overlay = document.getElementById('sidebarOverlay');
        const icon = document.getElementById('icon-sanduiche');
        const aberto = sidebar.classList.toggle('ativo');
        overlay.classList.toggle('ativo', aberto);
        icon.className = aberto ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
    }

    function fecharSidebar() {
        const sidebar = document.getElementById('sidebarMobile');
        const overlay = document.getElementById('sidebarOverlay');
        const icon = document.getElementById('icon-sanduiche');
        sidebar.classList.remove('ativo');
        overlay.classList.remove('ativo');
        icon.className = 'fa-solid fa-bars';
    }

    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('notifDropdown');
        if (dropdown) dropdown.classList.remove('active');

        const sidebar = document.getElementById('sidebarMobile');
        const btn = document.querySelector('.btn-sanduiche');
        if (sidebar && sidebar.classList.contains('ativo') && !sidebar.contains(e.target) && btn && !btn.contains(e.target)) {
            fecharSidebar();
        }
    });
</script>
<div class="page-overlay" id="pageOverlay">
    <div class="spinner-card">
        <div class="spinner-wrap">
            <div class="spinner-ring"></div>
            <div class="spinner-ring-2"></div>
            <div class="spinner-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <rect x="9" y="13" width="6" height="8" rx="1" />
                    <path d="M9 9h.01M12 9h.01M15 9h.01" />
                </svg>
            </div>
        </div>
        <div class="spinner-label">Carregando<span
                class="spinner-dots"><span>.</span><span>.</span><span>.</span></span></div>
    </div>
</div>
<dialog id="modal-perfil"
    style="border:none; border-radius:12px; padding:0; width:100%; max-width:420px; box-shadow:0 8px 32px rgba(0,0,0,0.12);"
    onclick="event.target===this&&this.close()">
    <div style="padding:24px;">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <div style="font-size:1rem; font-weight:600; color:#1e3a8a; display:flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['user']['login'] ?? 'Usuário'); ?>
                </div>
                <div style="font-size:0.78rem; color:#9ca3af; margin-top:2px;">
                    HGestor &nbsp;·&nbsp; v1.0
                </div>
                <div style="font-size:0.75rem; color:#cbd5e1; margin-top:1px;">
                    Estoque: <?php echo htmlspecialchars($_SESSION['user']['estoque_nome'] ?? ''); ?>
                </div>
            </div>
            <button onclick="document.getElementById('modal-perfil').close()"
                style="background:none; border:none; cursor:pointer; font-size:1.1rem; color:#9ca3af; padding:4px;">✕</button>
        </div>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin-bottom:20px;">

        <div style="display:flex; gap:8px; margin-bottom:20px;">
            <button class="perfil-aba ativa" onclick="trocarAba('senha')" id="aba-senha"
                style="flex:1; padding:7px; border-radius:6px; border:1px solid #e5e7eb; background:#1e3a8a; color:#fff; font-size:0.82rem; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fa-solid fa-key"></i>
                Trocar Senha</button>
            <button class="perfil-aba" onclick="trocarAba('estoque')" id="aba-estoque"
                style="flex:1; padding:7px; border-radius:6px; border:1px solid #e5e7eb; background:#f9fafb; color:#374151; font-size:0.82rem; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fa-solid fa-warehouse"></i>
                Trocar Estoque</button>
        </div>

        <div id="painel-senha">
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="font-size:0.82rem; font-weight:600; color:#374151;">Senha atual</label>
                    <input type="password" id="perfil-senha-atual" placeholder="••••••••"
                        style="width:100%; margin-top:4px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.88rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.82rem; font-weight:600; color:#374151;">Nova senha</label>
                    <input type="password" id="perfil-nova-senha" placeholder="••••••••"
                        style="width:100%; margin-top:4px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.88rem; box-sizing:border-box;">
                </div>
                <button onclick="salvarPerfil('trocar_senha')" class="btn-acao btn-sucesso"
                    style="margin-top:4px; padding:9px; width: 100%;"><i class="fa-solid fa-key"></i> Salvar Nova Senha</button>
            </div>
        </div>

        <div id="painel-estoque" style="display:none;">
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="font-size:0.82rem; font-weight:600; color:#374151;">Novo estoque</label>
                    <select id="perfil-estoque"
                        style="width:100%; margin-top:4px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.88rem; box-sizing:border-box;">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($estoquesModal as $em): ?>
                            <?php
                            $nomeEstoque = htmlspecialchars($em['nome_estoque']);
                            $selecionado = ($_SESSION['user']['estoque_nome'] ?? '') === $em['nome_estoque'] ? 'selected' : '';
                            echo "<option value=\"{$nomeEstoque}\" {$selecionado}>{$nomeEstoque}</option>";
                            ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.82rem; font-weight:600; color:#374151;">Confirme sua senha</label>
                    <input type="password" id="perfil-senha-estoque" placeholder="••••••••"
                        style="width:100%; margin-top:4px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.88rem; box-sizing:border-box;">
                </div>
                <button onclick="salvarPerfil('trocar_estoque')" class="btn-acao btn-sucesso"
                    style="margin-top:4px; padding:9px; width: 100%;"><i class="fa-solid fa-warehouse"></i> Confirmar Troca de Estoque</button>
            </div>
        </div>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:20px 0 16px;">
        <a href="mailto:dandilomg@gmail.com"
            style="display:flex; align-items:center; gap:8px; font-size:0.82rem; color:#6b7280; text-decoration:none;"
            onmouseover="this.style.color='#1e3a8a'" onmouseout="this.style.color='#6b7280'">
            <i class="fa-regular fa-envelope"></i> <span>Fale com o desenvolvedor</span>
        </a>
    </div>
</dialog>

<script>
    function abrirModalPerfil() {
        document.getElementById('perfil-senha-atual').value = '';
        document.getElementById('perfil-nova-senha').value = '';
        document.getElementById('perfil-senha-estoque').value = '';
        trocarAba('senha');
        document.getElementById('modal-perfil').showModal();
    }

    function trocarAba(aba) {
        const paineis = { senha: 'painel-senha', estoque: 'painel-estoque' };
        const abas = { senha: 'aba-senha', estoque: 'aba-estoque' };
        Object.keys(paineis).forEach(k => {
            document.getElementById(paineis[k]).style.display = k === aba ? 'block' : 'none';
            const btn = document.getElementById(abas[k]);
            btn.style.background = k === aba ? '#1e3a8a' : '#f9fafb';
            btn.style.color = k === aba ? '#fff' : '#374151';
        });
    }

    function salvarPerfil(acao) {
        const senhaAtual = acao === 'trocar_senha'
            ? document.getElementById('perfil-senha-atual').value
            : document.getElementById('perfil-senha-estoque').value;

        if (!senhaAtual) {
            lancarAlerta('Informe sua senha atual.', 'alerta');
            return;
        }

        const payload = { acao, senha_atual: senhaAtual, csrf_token: window.csrfToken };

        if (acao === 'trocar_senha') {
            const nova = document.getElementById('perfil-nova-senha').value;
            if (!nova) { lancarAlerta('Informe a nova senha.', 'alerta'); return; }
            payload.nova_senha = nova;
        }

        if (acao === 'trocar_estoque') {
            const estoque = document.getElementById('perfil-estoque').value;
            if (!estoque) { lancarAlerta('Selecione um estoque.', 'alerta'); return; }
            payload.estoque_nome = estoque;
        }

        document.getElementById('modal-perfil').close();
        document.getElementById('pageOverlay').classList.add('active');

        fetch('../acoes/atualizar_perfil.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => {
                if (res.status === 401) throw new Error('Senha atual incorreta.');
                if (!res.ok) throw new Error('Erro no servidor.');
                return res.json();
            })
            .then(data => {
                document.getElementById('pageOverlay').classList.remove('active');
                lancarAlerta(data.mensagem, 'sucesso');
                if (acao === 'trocar_estoque') setTimeout(() => window.location.reload(), 1200);
            })
            .catch(err => {
                document.getElementById('pageOverlay').classList.remove('active');
                lancarAlerta(err.message, 'erro');
            });
    }
</script>
<script>
    (function () {
        const overlay = document.getElementById('pageOverlay');

        document.addEventListener('click', function (e) {
            const link = e.target.closest('a[href]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript') || link.target === '_blank' || link.hasAttribute('download')) return;
            e.preventDefault();
            overlay.classList.add('active');
            setTimeout(() => { window.location.href = href; }, 300);
        });

        window.addEventListener('pageshow', function (e) {
            if (e.persisted) overlay.classList.remove('active');
        });
    })();
</script>