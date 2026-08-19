<?php
require_once '../config/conexao.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

if (!isset($_SESSION['user']) || (int) $_SESSION['user']['is_admin'] !== 1) {
    header("Location: estoque");
    exit;
}

$idUsuarioLogado = (int) $_SESSION['user']['id'];
$acaoAdmin = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_admin'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validarCsrf($csrfToken)) {
        header("Location: admin?erro=" . urlencode("Requisição inválida (CSRF)."));
        exit;
    }
    $acaoAdmin = $_POST['acao_admin'];

    try {
        if ($acaoAdmin === 'limpar_logs') {
            $pdo->exec("TRUNCATE TABLE sistema_logs");
            header('Content-Type: application/json');
            echo json_encode(['sucesso' => true]);
            exit;
        }

        if ($acaoAdmin === 'alternar_status') {
            $idUser = (int) $_POST['id_usuario'];
            $novoStatus = (int) $_POST['novo_status'];

            $stmt = $pdo->prepare("UPDATE usuarios SET status_acesso = :status WHERE id = :id AND is_admin != 1");
            $stmt->execute(['status' => $novoStatus, 'id' => $idUser]);
            
            registrarLog($pdo, 'admin_alterar_status', 'Status do usuário ID ' . $idUser . ' alterado para ' . $novoStatus, 'danger', $idUsuarioLogado, $_SESSION['user']['login']);

            header("Location: admin?sucesso=" . urlencode("Status do usuário updated!"));
            exit;
        }

        if ($acaoAdmin === 'deletar_usuario') {
            $idUser = (int) $_POST['id_usuario'];
            if ($idUser <= 0) {
                header("Location: admin?erro=" . urlencode("ID de usuário inválido para exclusão."));
                exit;
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id AND is_admin != 1");
            $stmt->execute(['id' => $idUser]);

            $pdo->commit();
            
            registrarLog($pdo, 'admin_deletar_usuario', 'Usuário ID ' . $idUser . ' deletado.', 'danger', $idUsuarioLogado, $_SESSION['user']['login']);

            header("Location: admin?sucesso=" . urlencode("Usuário e todos os seus dados vinculados foram removidos!"));
            exit;
        }

        if ($acaoAdmin === 'novo_estoque') {
            $nomeEstoque = trim($_POST['nome_estoque'] ?? '');
            if (!empty($nomeEstoque)) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO estoque_nomes (nome_estoque) VALUES (:nome)");
                $stmt->execute(['nome' => $nomeEstoque]);
                header("Location: admin?sucesso=" . urlencode("Novo estoque registrado com sucesso!"));
                exit;
            }
        }

        if ($acaoAdmin === 'deletar_estoque') {
            $idEstoque = (int) $_POST['id_estoque'];
            $stmt = $pdo->prepare("DELETE FROM estoque_nomes WHERE id = :id");
            $stmt->execute(['id' => $idEstoque]);
            header("Location: admin?sucesso=" . urlencode("Estoque removido da listagem."));
            exit;
        }

        if ($acaoAdmin === 'salvar_alerta') {
            $idAlerta = isset($_POST['id_alerta']) ? (int) $_POST['id_alerta'] : 0;
            $mensagem = trim($_POST['mensagem'] ?? '');
            $tipo = $_POST['tipo'] ?? 'info';

            if (!empty($mensagem)) {
                if ($idAlerta > 0) {
                    $stmt = $pdo->prepare("UPDATE admin_alertas SET mensagem = :msg, tipo = :tipo WHERE id = :id");
                    $stmt->execute(['msg' => $mensagem, 'tipo' => $tipo, 'id' => $idAlerta]);
                    header("Location: admin?sucesso=" . urlencode("Alerta atualizado com sucesso!"));
                } else {
                    $stmt = $pdo->prepare("INSERT INTO admin_alertas (mensagem, tipo) VALUES (:msg, :tipo)");
                    $stmt->execute(['msg' => $mensagem, 'tipo' => $tipo]);
                    header("Location: admin?sucesso=" . urlencode("Alerta global publicado!"));
                }
                exit;
            }
        }

        if ($acaoAdmin === 'deletar_alerta') {
            $idAlerta = (int) $_POST['id_alerta'];
            $stmt = $pdo->prepare("DELETE FROM admin_alertas WHERE id = :id");
            $stmt->execute(['id' => $idAlerta]);
            header("Location: admin?sucesso=" . urlencode("Alerta removido do sistema."));
            exit;
        }

        if ($acaoAdmin === 'alternar_admin') {
            $idUser = (int) $_POST['id_usuario'];
            $novoAdmin = (int) $_POST['novo_admin'];

            $stmt = $pdo->prepare("UPDATE usuarios SET is_admin = :is_admin WHERE id = :id");
            $stmt->execute(['is_admin' => $novoAdmin, 'id' => $idUser]);
            header("Location: admin?sucesso=" . urlencode("Permissão do usuário updated!"));
            exit;
        }

        if ($acaoAdmin === 'config_aprovacao') {
            $autoAprovar = (int) $_POST['auto_aprovar'];

            $stmt = $pdo->prepare("INSERT INTO sistema_config (chave, valor) VALUES ('aprovacao_automatica', :valor_ins) ON DUPLICATE KEY UPDATE valor = :valor_upd");
            $stmt->execute(['valor_ins' => $autoAprovar, 'valor_upd' => $autoAprovar]);
            header("Location: admin?sucesso=" . urlencode("Diretriz de novos cadastros atualizada!"));
            exit;
        }
    } catch (Exception $e) {
        if ($acaoAdmin === 'deletar_usuario' && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro no painel admin (Ação: $acaoAdmin): " . $e->getMessage());
        header("Location: admin?erro=" . urlencode("Erro ao processar solicitação."));
        exit;
    }
}

$stmtUsuarios = $pdo->prepare("SELECT id, login, estoque_nome, status_acesso, is_admin FROM usuarios WHERE id != :id ORDER BY login ASC");
$stmtUsuarios->execute(['id' => $idUsuarioLogado]);
$usuarios = $stmtUsuarios->fetchAll();
$estoques = $pdo->query("SELECT * FROM estoque_nomes ORDER BY nome_estoque ASC")->fetchAll();
$alertas = $pdo->query("SELECT * FROM admin_alertas ORDER BY criado_em DESC")->fetchAll();
$configAprovacao = $pdo->query("SELECT valor FROM sistema_config WHERE chave = 'aprovacao_automatica'")->fetch();
$aprovacaoAutomatica = $configAprovacao ? (int) $configAprovacao['valor'] : 1;
$logs = $pdo->query("
    SELECT * FROM sistema_logs 
    ORDER BY criado_em DESC 
    LIMIT 300
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HGestor - Painel Administrativo</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon-16x16.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo filemtime('../css/estilos.css'); ?>">
    <style>
        .grid-admin {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        @media (max-width: 900px) {
            .grid-admin {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 768px) {
            .card-dados {
                padding: 15px;
                width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }

            .tabela-hgestor .btn-acao {
                width: auto;
            }

            .txt-direita div {
                flex-wrap: wrap !important;
            }
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: #d1d5db;
            border-radius: 28px;
            cursor: pointer;
            transition: background .25s;
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            left: 4px;
            top: 4px;
            background: #fff;
            border-radius: 50%;
            transition: transform .25s;
        }

        .toggle-switch input:checked+.toggle-slider {
            background: #10b981;
        }

        .toggle-switch input:checked+.toggle-slider::before {
            transform: translateX(24px);
        }
    </style>
</head>

<body>
    <?php include 'componente_header.php'; ?>
    <div id="container-toasts-global" class="container-toasts"></div>

    <div class="dashboard-container">
        <main class="conteudo-principal">
            <h2>🔧 Painel de Controle Administrativo</h2>
            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 10px;">
            <div class="grid-admin">
                <section class="card-dados">
                    <h3>⚙️ Diretriz de Cadastros</h3>
                    <p class="txt-mutado" style="margin-bottom: 15px;">Defina como a entrada de novos operadores se
                        comportará.</p>
                    <div
                        style="margin-bottom: 25px; background: #f8fafc; padding: 10px; border-radius: 4px; border: 1px solid #e2e8f0;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-size:0.9rem; font-weight:600;">Aprovação Automática</span>
                                <p class="txt-mutado" style="margin:2px 0 0; font-size:0.78rem;"
                                    id="txt-estado-aprovacao">
                                    <?php echo $aprovacaoAutomatica ? '🟢 Ativada — acesso imediato' : '🟡 Retida — requer liberação'; ?>
                                </p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="toggle-aprovacao" <?php echo $aprovacaoAutomatica ? 'checked' : ''; ?> onchange="salvarAprovacao(this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <h3>👥 Governança de Usuários</h3>
                    <div class="table-responsive">
                        <table class="tabela-hgestor">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Ambiente Alocado</th>
                                    <th class="txt-centro">Acesso</th>
                                    <th class="txt-direita">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="4" class="txt-centro msg-vazia">Nenhum usuário comum registrado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($u['login']); ?></strong>
                                            </td>
                                            <td><span class="ambiente-tag"
                                                    style="background:#1e3a8a;"><?php echo htmlspecialchars($u['estoque_nome']); ?></span>
                                            </td>
                                            <td class="txt-centro">
                                                <?php if ((int) $u['status_acesso'] === 1): ?>
                                                    <span class="badge-cobertura cob-ok">Liberado</span>
                                                <?php else: ?>
                                                    <span class="badge-cobertura cob-critica">Bloqueado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="txt-direita">
                                                <div style="display:flex; gap:8px; justify-content:flex-end;">
                                                    <form method="POST" action="admin">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                        <input type="hidden" name="acao_admin" value="alternar_admin">
                                                        <input type="hidden" name="id_usuario" value="<?php echo $u['id']; ?>">
                                                        <input type="hidden" name="novo_admin"
                                                            value="<?php echo (int) $u['is_admin'] === 1 ? '0' : '1'; ?>">
                                                        <button type="submit" class="btn-acao <?php echo (int) $u['is_admin'] === 1 ? 'btn-neutro' : 'btn-info'; ?>"
                                                            style="padding: 5px 10px; font-size:0.8rem;">
                                                            <?php echo (int) $u['is_admin'] === 1 ? '<i class="fa-solid fa-user"></i> Tornar Comum' : '<i class="fa-solid fa-key"></i> Tornar Admin'; ?>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="admin">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                        <input type="hidden" name="acao_admin" value="alternar_status">
                                                        <input type="hidden" name="id_usuario" value="<?php echo $u['id']; ?>">
                                                        <input type="hidden" name="novo_status"
                                                            value="<?php echo (int) $u['status_acesso'] === 1 ? '0' : '1'; ?>">
                                                        <button type="submit" class="btn-acao <?php echo (int) $u['status_acesso'] === 1 ? 'btn-neutro' : 'btn-sucesso'; ?>"
                                                            style="padding: 5px 10px; font-size:0.8rem;">
                                                            <?php echo (int) $u['status_acesso'] === 1 ? '<i class="fa-solid fa-lock"></i> Bloquear' : '<i class="fa-solid fa-lock-open"></i> Liberar'; ?>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn-acao btn-perigo"
                                                         style="padding: 5px 10px; font-size:0.8rem;"
                                                         onclick="abrirModalExclusao(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['login']); ?>')">
                                                         <i class="fa-solid fa-trash-can"></i> Excluir
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
                <section class="card-dados">
                    <h3>🏢 Estoques Autorizados</h3>
                    <p class="txt-mutado" style="margin-bottom: 15px;">Defina a lista fixa de filiais disponíveis para
                        novos registros.</p>

                    <form method="POST" action="admin" style="display:flex; gap:10px; margin-bottom: 20px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="acao_admin" value="novo_estoque">
                        <input type="text" name="nome_estoque" placeholder="Ex: Farmácia Satélite" required
                            style="padding: 8px; border: 1px solid #cbd5e1; border-radius:4px; flex:1; font-size:0.85rem;">
                        <button type="submit" class="btn-acao btn-sucesso" style="padding:8px 12px;"><i class="fa-solid fa-plus"></i></button>
                    </form>

                    <div class="table-responsive">
                        <table class="tabela-hgestor">
                            <thead>
                                <tr>
                                    <th>Nome do Estabelecimento</th>
                                    <th class="txt-direita">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($estoques)): ?>
                                    <tr>
                                        <td colspan="2" class="txt-centro msg-vazia">Nenhum estoque pré-definido.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($estoques as $e): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($e['nome_estoque']); ?></strong></td>
                                            <td class="txt-direita">
                                                <button type="button"
                                                    style="background:none; border:none; color:#ef4444; cursor:pointer; font-size: 1rem;"
                                                    onclick="abrirModalEstoque(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars($e['nome_estoque']); ?>')">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <section class="card-dados" style="margin-top: 20px;">
                <h3>📢 Central de Avisos e Alertas Globais</h3>
                <p class="txt-mutado">Envie comunicados em tempo real para o header de todos os usuários operacionais.
                </p>

                <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 15px;">
                    <form method="POST" action="admin" id="form-alertas-admin"
                        style="background: #f8fafc; padding: 15px; border-radius:6px; border: 1px solid #e2e8f0;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="acao_admin" value="salvar_alerta">
                        <input type="hidden" name="id_alerta" id="alerta-id" value="">

                        <div class="form-group">
                            <label for="alerta-msg" style="font-weight: 600;">Mensagem do Comunicado</label>
                            <textarea name="mensagem" id="alerta-msg" rows="3" required
                                placeholder="Digite o aviso para os usuários..."
                                style="width:100%; padding:8px; border-radius:4px; border:1px solid #cbd5e1; font-family:inherit; resize:none;"></textarea>
                        </div>

                        <div class="form-group" style="margin: 10px 0;">
                            <label style="font-weight: 600;">Estilo do Card</label>
                            <input type="hidden" name="tipo" id="alerta-tipo" value="">
                            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                                <button type="button" class="chip-tipo" data-valor="info" onclick="selecionarTipo(this)">🔵 Informativo</button>
                                <button type="button" class="chip-tipo" data-valor="alerta" onclick="selecionarTipo(this)">🟡 Atenção</button>
                                <button type="button" class="chip-tipo" data-valor="erro" onclick="selecionarTipo(this)">🔴 Urgente</button>
                                <button type="button" class="chip-tipo" data-valor="sucesso" onclick="selecionarTipo(this)">🟢 Sucesso</button>
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; margin-top:15px;">
                            <button type="submit" id="btn-salvar-alerta" class="btn-acao btn-sucesso"
                                style="padding: 8px 20px;" onclick="return validarTipo()"><i class="fa-solid fa-bullhorn"></i> Publicar Aviso</button>
                            <button type="button" id="btn-cancelar-edicao" class="btn-acao btn-neutro" onclick="resetarFormAlerta()"
                                style="display:none; padding:8px 20px;"><i class="fa-solid fa-xmark"></i> Cancelar</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="tabela-hgestor">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Aviso</th>
                                    <th class="txt-direita">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alertas)): ?>
                                    <tr>
                                        <td colspan="3" class="txt-centro msg-vazia">Nenhum alerta global ativo no momento.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alertas as $al): ?>
                                        <tr>
                                            <td>
                                                <span class="badge-cobertura" style="background: <?php
                                                echo $al['tipo'] === 'erro' ? '#ef4444' : ($al['tipo'] === 'alerta' ? '#f59e0b' : ($al['tipo'] === 'sucesso' ? '#10b981' : '#3b82f6'));
                                                ?>; color:#fff;">
                                                    <?php echo strtoupper($al['tipo']); ?>
                                                </span>
                                            </td>
                                            <td style="max-width: 300px; white-space: normal; font-size:0.9rem;">
                                                <?php echo htmlspecialchars($al['mensagem']); ?>
                                            </td>
                                            <td class="txt-direita">
                                                <div style="display:flex; gap:8px; justify-content:flex-end;">
                                                    <button type="button" class="btn-acao btn-info"
                                                        style="padding: 3px 8px; font-size:0.75rem;"
                                                        onclick="prepararEdicaoAlerta(<?php echo $al['id']; ?>, '<?php echo addslashes(htmlspecialchars($al['mensagem'])); ?>', '<?php echo $al['tipo']; ?>')">
                                                        <i class="fa-solid fa-pen"></i> Editar
                                                    </button>
                                                    <button type="button" class="btn-acao btn-perigo"
                                                        style="padding: 3px 8px; font-size:0.75rem;"
                                                        onclick="abrirModalDeletarAlerta(<?php echo $al['id']; ?>)">
                                                        <i class="fa-solid fa-trash-can"></i> Deletar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
            
            <section class="card-dados" style="margin-top: 20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3>📋 Registro de Logs do Sistema</h3>
                        <p class="txt-mutado">Últimos 300 eventos registrados</p>
                        <p class="txt-mutado">Eventos que são registrados: (login_sucesso, login_falha, usuario_bloqueado, login_bloqueado, cadastro_realizado, cadastro_senha_fraca, cadastro_estoque_invalido, cadastro_login_duplicado, upload_csv, upload_csv_erro, reset_estoque, reset_parametros, inventario_encerrado, inventario_deletado, logout, admin_alterar_status, admin_deletar_usuario).</p>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <select id="filtro-nivel-log" class="select-param" style="font-size:0.8rem; padding:5px 8px;">
                            <option value="TODOS">Todos os níveis</option>
                            <option value="info">🟢 Info</option>
                            <option value="warn">🟡 Warn</option>
                            <option value="danger">🔴 Danger</option>
                        </select>
                        <button class="btn-acao btn-perigo" style="padding:6px 12px; font-size:0.8rem;"
                            onclick="abrirModalLimparLogs()">
                            <i class="fa-solid fa-trash-can"></i> Limpar Logs
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="tabela-hgestor" id="tabela-logs">
                        <thead>
                            <tr>
                                <th style="width:120px;">Usuário</th>
                                <th style="width:120px;">IP</th>
                                <th style="width:140px;">Data/Hora</th>
                                <th style="width:80px;" class="txt-centro">Nível</th>
                                <th style="width:120px;">Ação</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-logs"></tbody>
                    </table>
                </div>
                <div style="text-align:center; margin-top:12px;">
                    <button id="btn-carregar-mais-logs" class="btn-acao btn-neutro"
                        style="padding:7px 20px; font-size:0.82rem; display:none;"
                        onclick="renderLogs(logsFiltroAtual, false)">
                        <i class="fa-solid fa-chevron-down"></i> Carregar mais
                    </button>
                </div>
            </section>
        </main>
    </div>
    <div id="modal-confirmacao-sistema" class="modal-param"
        style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div class="modal-conteudo"
            style="background: #fff; padding: 25px; border-radius: 8px; width: 90%; max-width: 450px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h3 id="modal-titulo" style="margin-top: 0; color: #1e293b;">⚠️ Confirmação</h3>
            <p id="modal-mensagem" style="color: #64748b; font-size: 0.95rem; margin: 15px 0 25px 0; line-height: 1.5;">
            </p>

            <form id="form-modal-sistema" method="POST" action="admin">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="acao_admin" id="modal-campo-acao" value="">
                <input type="hidden" name="id_usuario" id="modal-campo-user" value="">
                <input type="hidden" name="id_estoque" id="modal-campo-estoque" value="">

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-acao btn-neutro" onclick="fecharModalSistema()"
                        style="padding: 8px 16px;">
                        <i class="fa-solid fa-xmark"></i> Cancelar
                    </button>
                    <button type="submit" id="modal-btn-confirmar" class="btn-acao btn-perigo"
                        style="padding: 8px 16px;">
                        <i class="fa-solid fa-trash-can"></i> Confirmar Exclusão
                    </button>
                </div>
            </form>
        </div>
    </div>

    <dialog id="modal-limpar-logs" onclick="event.target===this&&this.close()">
        <h3 style="color:#dc2626; margin-bottom:10px;">🗑️ Limpar Todos os Logs</h3>
        <p style="color:#6b7280; font-size:0.88rem; margin-bottom:20px; line-height:1.5;">
            Remove permanentemente todos os registros de log. Ação irreversível.
        </p>
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button onclick="document.getElementById('modal-limpar-logs').close()"
                class="btn-acao btn-neutro" style="padding:8px 16px;">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button onclick="confirmarLimparLogs()"
                class="btn-acao btn-perigo" style="padding:8px 16px;">
                <i class="fa-solid fa-trash-can"></i> Confirmar
            </button>
        </div>
    </dialog>

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
        function lancarAlerta(mensagem, tipo = 'info', tempoExibicao = 4000) {
            const container = document.getElementById('container-toasts-global');
            if (!container) return;
            container.querySelectorAll('.alerta-toast').forEach(t => t.remove());
            const toast = document.createElement('div');
            toast.className = `alerta-toast toast-${tipo}`;
            let icone = 'ℹ️';
            if (tipo === 'sucesso') icone = '✅';
            if (tipo === 'erro') icone = '❌';
            toast.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <span>${icone}</span><span>${mensagem}</span>
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
        function abrirModalExclusao(idUsuario, nomeUsuario) {
            document.getElementById('modal-titulo').innerText = '⚠️ Excluir Usuário Integralmente';
            document.getElementById('modal-mensagem').innerHTML = `ATENÇÃO: Isso apagará permanentemente o usuário <strong>${nomeUsuario}</strong> e TODAS as suas parametrizações, vínculos e históricos de estoque no HGestor. Esta ação não poderá ser desfeita.`;
            document.getElementById('modal-campo-acao').value = 'deletar_usuario';
            document.getElementById('modal-campo-user').value = idUsuario;
            document.getElementById('modal-campo-estoque').value = '';
            document.getElementById('modal-btn-confirmar').style.background = '#ef4444';
            document.getElementById('modal-btn-confirmar').innerText = 'Sim, Excluir Tudo';
            document.getElementById('modal-confirmacao-sistema').style.display = 'flex';
        }

        function abrirModalEstoque(idEstoque, nomeEstoque) {
            document.getElementById('modal-titulo').innerText = '⚠️ Remover Estoque da Lista';
            document.getElementById('modal-mensagem').innerHTML = `Tem certeza que deseja remover o estoque <strong>${nomeEstoque}</strong> da listagem padrão de novos cadastros?`;
            document.getElementById('modal-campo-acao').value = 'deletar_estoque';
            document.getElementById('modal-campo-estoque').value = idEstoque;
            document.getElementById('modal-campo-user').value = '';
            document.getElementById('modal-btn-confirmar').style.background = '#f59e0b';
            document.getElementById('modal-btn-confirmar').innerText = 'Remover Lista';
            document.getElementById('modal-confirmacao-sistema').style.display = 'flex';
        }
        function abrirModalDeletarAlerta(idAlerta) {
            document.getElementById('modal-titulo').innerText = '⚠️ Remover Alerta Global';
            document.getElementById('modal-mensagem').innerHTML = `Deseja remover permanentemente este alerta do sistema? Ele sumirá imediatamente do cabeçalho de todos os usuários.`;
            document.getElementById('modal-campo-acao').value = 'deletar_alerta';
            document.getElementById('modal-campo-estoque').value = '';
            document.getElementById('modal-campo-user').value = '';

            let inputAlerta = document.getElementById('modal-campo-alerta');
            if (!inputAlerta) {
                inputAlerta = document.createElement('input');
                inputAlerta.type = 'hidden';
                inputAlerta.name = 'id_alerta';
                inputAlerta.id = 'modal-campo-alerta';
                document.getElementById('form-modal-sistema').appendChild(inputAlerta);
            }
            inputAlerta.value = idAlerta;

            document.getElementById('modal-btn-confirmar').style.background = '#ef4444';
            document.getElementById('modal-btn-confirmar').innerText = 'Sim, Deletar Alerta';
            document.getElementById('modal-confirmacao-sistema').style.display = 'flex';
        }
        function fecharModalSistema() {
            document.getElementById('modal-confirmacao-sistema').style.display = 'none';
            const inputAlerta = document.getElementById('modal-campo-alerta');
            if (inputAlerta) inputAlerta.value = '';
        }
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('sucesso')) {
                document.querySelectorAll('.alerta-toast').forEach(t => t.remove());
                lancarAlerta(urlParams.get('sucesso'), 'sucesso');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            if (urlParams.has('erro')) {
                document.querySelectorAll('.alerta-toast').forEach(t => t.remove());
                lancarAlerta(urlParams.get('erro'), 'erro');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            renderLogs();
        });
        function selecionarTipo(btn) {
            const ativo = btn.classList.contains('chip-ativo');
            document.querySelectorAll('.chip-tipo').forEach(c => c.classList.remove('chip-ativo'));
            if (!ativo) {
                btn.classList.add('chip-ativo');
                document.getElementById('alerta-tipo').value = btn.dataset.valor;
            } else {
                document.getElementById('alerta-tipo').value = '';
            }
        }
        function validarTipo() {
            if (!document.getElementById('alerta-tipo').value) {
                lancarAlerta('Selecione um estilo antes de publicar.', 'alerta');
                return false;
            }
            return true;
        }
        function prepararEdicaoAlerta(id, mensagem, tipo) {
            document.getElementById('alerta-id').value = id;
            document.getElementById('alerta-msg').value = mensagem;
            document.querySelectorAll('.chip-tipo').forEach(c => c.classList.remove('chip-ativo'));
            const chip = document.querySelector(`.chip-tipo[data-valor="${tipo}"]`);
            if (chip) { chip.classList.add('chip-ativo'); document.getElementById('alerta-tipo').value = tipo; }
            document.getElementById('btn-salvar-alerta').innerHTML = '<i class="fa-solid fa-bullhorn"></i> Atualizar Aviso';
            document.getElementById('btn-cancelar-edicao').style.display = 'inline-block';
        }
        function resetarFormAlerta() {
            document.getElementById('form-alertas-admin').reset();
            document.getElementById('alerta-id').value = '';
            document.getElementById('alerta-tipo').value = '';
            document.querySelectorAll('.chip-tipo').forEach(c => c.classList.remove('chip-ativo'));
            document.getElementById('btn-salvar-alerta').innerHTML = '<i class="fa-solid fa-bullhorn"></i> Publicar Aviso';
            document.getElementById('btn-cancelar-edicao').style.display = 'none';
        }

        const LOGS_DATA = <?php echo json_encode($logs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;

        const LOGS_PAGE_SIZE = 10;
        let logsOffset = 0;
        let logsFiltroAtual = 'TODOS';

        function renderLogs(filtroNivel = 'TODOS', reset = true) {
            const tbody = document.getElementById('tbody-logs');
            const btnCarregar = document.getElementById('btn-carregar-mais-logs');
            if (!tbody) return;

            if (reset) {
                logsOffset = 0;
                logsFiltroAtual = filtroNivel;
                tbody.innerHTML = '';
            }

            const filtrados = logsFiltroAtual === 'TODOS'
                ? LOGS_DATA
                : LOGS_DATA.filter(l => l.nivel === logsFiltroAtual);

            if (!filtrados.length && logsOffset === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="txt-centro msg-vazia">Nenhum log encontrado.</td></tr>';
                if (btnCarregar) btnCarregar.style.display = 'none';
                return;
            }

            const badges = {
                info:   '<span class="badge-cobertura badge-log-info">🟢 info</span>',
                warn:   '<span class="badge-cobertura badge-log-warn">🟡 warn</span>',
                danger: '<span class="badge-cobertura badge-log-danger">🔴 danger</span>'
            };

            const slice = filtrados.slice(logsOffset, logsOffset + LOGS_PAGE_SIZE);
            logsOffset += slice.length;

            tbody.insertAdjacentHTML('beforeend', slice.map(l => `
                <tr>
                    <td style="font-size:0.82rem; font-weight:600;">${l.login || '—'}</td>
                    <td style="font-size:0.78rem; color:#6b7280;">${l.ip || '—'}</td>
                    <td style="font-size:0.78rem; color:#6b7280; white-space:nowrap;">
                        ${new Date(l.criado_em.replace(/-/g, "/")).toLocaleString('pt-BR')}
                    </td>
                    <td class="txt-centro">${badges[l.nivel] || l.nivel}</td>
                    <td><code style="font-size:0.78rem; background:#f1f5f9; padding:2px 6px; border-radius:4px;">${l.acao}</code></td>
                    <td style="font-size:0.85rem;">${l.descricao || '—'}</td>
                </tr>
            `).join(''));

            if (btnCarregar) {
                btnCarregar.style.display = logsOffset < filtrados.length ? 'block' : 'none';
            }
        }

        document.getElementById('filtro-nivel-log').addEventListener('change', function () {
            renderLogs(this.value, true);
        });

        function abrirModalLimparLogs() {
            document.getElementById('modal-limpar-logs').showModal();
        }

        async function confirmarLimparLogs() {
            document.getElementById('modal-limpar-logs').close();
            try {
                const fd = new FormData();
                fd.append('csrf_token', window.csrfToken);
                fd.append('acao_admin', 'limpar_logs');
                const res = await fetch('admin.php', { method: 'POST', body: fd });
                if (!res.ok) throw new Error();
                lancarAlerta('Logs apagados com sucesso.', 'sucesso');
                setTimeout(() => location.reload(), 1200);
            } catch {
                lancarAlerta('Erro ao limpar logs.', 'erro');
            }
        }
    </script>
    <script>
        function salvarAprovacao(ativo) {
            const label = document.getElementById('txt-estado-aprovacao');
            label.textContent = ativo ? '🟢 Ativada — acesso imediato' : '🟡 Retida — requer liberação';

            const fd = new FormData();
            fd.append('csrf_token', window.csrfToken);
            fd.append('acao_admin', 'config_aprovacao');
            fd.append('auto_aprovar', ativo ? '1' : '0');

            fetch('admin', { method: 'POST', body: fd })
                .then(r => { if (!r.ok) throw new Error(); })
                .catch(() => {
                    label.textContent = '❌ Erro ao salvar';
                    document.getElementById('toggle-aprovacao').checked = !ativo;
                });
        }
    </script>
</body>

</html>