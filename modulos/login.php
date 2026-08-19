<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/conexao.php';

if (isset($_SESSION['user'])) {
    header("Location: estoque");
    exit;
}

$estoquesDisponiveis = $pdo->query("SELECT * FROM estoque_nomes ORDER BY nome_estoque ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HGestor - Login</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon-16x16.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo filemtime('../css/estilos.css'); ?>">
</head>

<body class="login-body">
    <div id="container-toasts-global" class="container-toasts"></div>
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
            <div class="spinner-label">Acessando<span
                    class="spinner-dots"><span>.</span><span>.</span><span>.</span></span></div>
        </div>
    </div>
    <div class="login-container">
        <div class="login-header">
            <img src="../assets/img/LOGO.png" alt="HGestor" style="max-height: 120px; margin-bottom: 12px; width: auto; object-fit: contain;">
            <p>Camada de Inteligência para Estoque Tasy</p>
        </div>

        <div id="form-login" class="form-box">
            <h2>Acessar o Sistema</h2>
            <form action="../acoes/autenticar.php" method="POST" id="form-autenticar-login">
                <input type="hidden" name="acao" value="login">

                <div class="form-group">
                    <label for="login_usuario">Usuário (Login)</label>
                    <input type="text" id="login_usuario" name="login" required autocomplete="username">
                </div>



                <div class="form-group">
                    <label for="senha_usuario">Senha</label>
                    <input type="password" id="senha_usuario" name="senha" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn-principal">Entrar</button>
            </form>
            <div class="form-footer">
                <a href="#" onclick="alternarForm(true)">Criar uma conta</a>
                <br><br>
                <a href="#" class="link-orfao">Esqueci a senha</a>
            </div>
        </div>

        <div id="form-cadastro" class="form-box" style="display: none;">
            <h2>Cadastro</h2>
            <form action="../acoes/autenticar.php" method="POST" id="form-cadastrar-usuario">
                <input type="hidden" name="acao" value="cadastro">

                <div class="form-group">
                    <label for="cad_usuario">Defina seu Usuário</label>
                    <input type="text" id="cad_usuario" name="login" required>
                </div>

                <div class="form-group">
                    <label for="cad_senha">Defina sua Senha</label>
                    <input type="password" id="cad_senha" name="senha" required>
                    <div id="forca-senha-wrap" style="margin-top: 8px; display: none;">
                        <div id="forca-barra" style="display: flex; gap: 4px; margin-bottom: 4px;">
                            <div class="seg-forca" id="seg-1"></div>
                            <div class="seg-forca" id="seg-2"></div>
                            <div class="seg-forca" id="seg-3"></div>
                            <div class="seg-forca" id="seg-4"></div>
                        </div>
                        <span id="forca-label" style="font-size: 0.75rem; font-weight: 600;"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="cad_estoque">Nome do seu Estoque</label>
                    <select id="cad_estoque" name="estoque_nome" required class="select-param" style="padding: 10px;">
                        <option value="">-- Selecione o Estoque --</option>
                        <?php foreach ($estoquesDisponiveis as $est): ?>
                            <option value="<?php echo htmlspecialchars($est['nome_estoque']); ?>">
                                <?php echo htmlspecialchars($est['nome_estoque']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-principal btn-cadastro">Registrar e Entrar</button>
            </form>
            <div class="form-footer">
                <a href="#" onclick="alternarForm(false)">Já tenho uma conta. Entrar</a>
            </div>
        </div>
    </div>

    <script>
        function alternarForm(mostrarCadastro) {
            const formLogin = document.getElementById('form-login');
            const formCadastro = document.getElementById('form-cadastro');

            if (mostrarCadastro) {
                formLogin.style.display = 'none';
                formCadastro.style.display = 'block';
            } else {
                formLogin.style.display = 'block';
                formCadastro.style.display = 'none';
            }
        }

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

        let forcaAtual = 0;

        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('sucesso')) {
                lancarAlerta(urlParams.get('sucesso'), 'sucesso');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            if (urlParams.has('erro')) {
                lancarAlerta(urlParams.get('erro'), 'erro');
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            document.querySelector('.link-orfao').addEventListener('click', function (e) {
                e.preventDefault();
                lancarAlerta('A recuperação de senha ainda não está disponível. Contate o administrador do sistema.', 'alerta');
            });

            // Ler cookie hgestor_login
            const cookies = document.cookie.split(';');
            let hgestorLoginVal = '';
            for (let i = 0; i < cookies.length; i++) {
                const parts = cookies[i].split('=');
                if (parts[0].trim() === 'hgestor_login') {
                    hgestorLoginVal = decodeURIComponent(parts[1]);
                    break;
                }
            }
            if (hgestorLoginVal) {
                document.getElementById('login_usuario').value = hgestorLoginVal;
            }
        });

        document.getElementById('form-autenticar-login').addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;
            
            const login = document.getElementById('login_usuario').value;
            document.cookie = "hgestor_login=" + login + "; max-age=2592000; path=/; SameSite=Strict";

            document.getElementById('pageOverlay').classList.add('active');
            setTimeout(() => form.submit(), 300);
        });

        const cadSenha = document.getElementById('cad_senha');
        const forcaWrap = document.getElementById('forca-senha-wrap');
        const seg1 = document.getElementById('seg-1');
        const seg2 = document.getElementById('seg-2');
        const seg3 = document.getElementById('seg-3');
        const seg4 = document.getElementById('seg-4');
        const forcaLabel = document.getElementById('forca-label');

        cadSenha.addEventListener('input', function() {
            const senha = this.value;
            if (!senha) {
                forcaWrap.style.display = 'none';
                forcaAtual = 0;
                return;
            }
            
            forcaWrap.style.display = 'block';
            
            let forca = 0;
            let texto = '';
            let cor = '';
            
            if (senha.length < 8) {
                forca = 1;
                texto = 'Muito fraca';
                cor = '#ef4444';
            } else {
                const temEspecial = /[^a-zA-Z0-9]/.test(senha);
                const temMaiuscula = /[A-Z]/.test(senha);
                
                if (temEspecial) {
                    forca = 4;
                    texto = 'Forte';
                    cor = '#22c55e';
                } else if (temMaiuscula) {
                    forca = 3;
                    texto = 'Média';
                    cor = '#eab308';
                } else {
                    forca = 2;
                    texto = 'Fraca';
                    cor = '#f97316';
                }
            }
            
            forcaAtual = forca;
            forcaLabel.textContent = texto;
            forcaLabel.style.color = cor;
            
            seg1.style.background = (forca >= 1) ? cor : '#e2e8f0';
            seg2.style.background = (forca >= 2) ? cor : '#e2e8f0';
            seg3.style.background = (forca >= 3) ? cor : '#e2e8f0';
            seg4.style.background = (forca >= 4) ? cor : '#e2e8f0';
        });

        document.getElementById('form-cadastrar-usuario').addEventListener('submit', function (e) {
            if (forcaAtual < 2) {
                e.preventDefault();
                lancarAlerta('A senha deve ter no mínimo 8 caracteres.', 'erro');
            }
        });       
    </script>
</body>

</html>