<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HGestor - Termos e Privacidade</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon-16x16.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo filemtime('../css/estilos.css'); ?>">
    <style>
        .politicas-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            line-height: 1.6;
            color: #374151;
        }
        .politicas-container h2 {
            color: #1e3a8a;
            margin-top: 25px;
            margin-bottom: 12px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 6px;
        }
        .politicas-container h3 {
            color: #1f2937;
            margin-top: 18px;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        .politicas-container p {
            margin-bottom: 12px;
            text-align: justify;
        }
        .politicas-container ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        .politicas-container li {
            margin-bottom: 4px;
        }
        .meta-data {
            font-style: italic;
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 20px;
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
            <section class="politicas-container">
                <h1>Termos de Uso</h1>
                <div class="meta-data">Última atualização: <?php echo date('d/m/Y'); ?></div>

                <h3>1. Objeto</h3>
                <p>Esta aplicação é um software de código aberto destinado à análise, processamento e consolidação de dados operacionais relacionados à gestão de estoque, materiais, medicamentos, compras, consumo, inventário e indicadores administrativos provenientes de sistemas de gestão hospitalar, incluindo o sistema Tasy.</p>
                <p>A ferramenta realiza cálculos, geração de relatórios, indicadores e análises com base nos dados fornecidos pelos usuários.</p>

                <h3>2. Aceitação dos Termos</h3>
                <p>Ao acessar ou utilizar esta aplicação, o usuário declara ter lido, compreendido e concordado com estes Termos de Uso e com a Política de Privacidade.</p>

                <h3>3. Finalidade da Ferramenta</h3>
                <p>A aplicação foi desenvolvida exclusivamente para apoiar atividades administrativas e operacionais relacionadas à gestão de estoque e suprimentos. A ferramenta não se destina ao uso assistencial, diagnóstico, terapêutico ou clínico, nem deve ser utilizada como instrumento para tomada de decisões médicas.</p>

                <h3>4. Uso Permitido</h3>
                <p>É permitido utilizar a aplicação para processamento de informações relacionadas a:</p>
                <ul>
                    <li>Materiais e medicamentos;</li>
                    <li>Quantidades e movimentações de estoque;</li>
                    <li>Consumo e reposição de itens;</li>
                    <li>Inventários;</li>
                    <li>Indicadores logísticos;</li>
                    <li>Custos e informações operacionais associadas à gestão de suprimentos.</li>
                </ul>

                <h3>5. Uso Proibido</h3>
                <p>Não é permitido utilizar a aplicação para coletar, armazenar, processar ou compartilhar:</p>
                <ul>
                    <li>Dados de pacientes;</li>
                    <li>Prontuários eletrônicos;</li>
                    <li>Diagnósticos;</li>
                    <li>Prescrições individualizadas;</li>
                    <li>Resultados de exames;</li>
                    <li>Dados biométricos;</li>
                    <li>Informações clínicas identificáveis;</li>
                    <li>Dados pessoais sensíveis definidos pela Lei nº 13.709/2018 (LGPD).</li>
                </ul>
                <p>Caso tais informações sejam inseridas na aplicação, a responsabilidade será integralmente da instituição e dos usuários envolvidos.</p>

                <h3>6. Responsabilidades do Usuário e da Instituição</h3>
                <p>O usuário e a instituição responsável comprometem-se a utilizar apenas dados compatíveis com a finalidade da ferramenta, garantir a legitimidade do acesso aos dados processados, observar as políticas internas de segurança da informação, cumprir a legislação aplicável (incluindo a LGPD) e validar os resultados gerados antes de utilizá-los em processos operacionais ou administrativos.</p>

                <h3>7. Limitação de Responsabilidade</h3>
                <p>A aplicação é fornecida "como está", sem garantias de disponibilidade contínua, adequação a finalidades específicas ou ausência de erros. Os desenvolvedores não se responsabilizam por erros nos dados de origem, utilização inadequada da ferramenta, inclusão indevida de dados pessoais, decisões tomadas com base nos relatórios ou perdas operacionais decorrentes da utilização da aplicação.</p>

                <h3>8. Código Aberto</h3>
                <p>A aplicação é disponibilizada como software de código aberto, sujeita aos termos da licença adotada pelo projeto.</p>

                <h3>9. Alterações dos Termos</h3>
                <p>Os presentes Termos de Uso poderão ser modificados a qualquer momento. A continuidade da utilização da ferramenta após a publicação de alterações caracterizará a aceitação da nova versão.</p>

                <h3>10. Contato</h3>
                <p>Dúvidas, sugestões ou comunicações relacionadas a estes Termos poderão ser encaminhadas para:</p>
                <p>
                    Responsável: <strong>Dândilo Silva (HorusDEV)</strong><br>
                    Projeto: <strong>HGestor</strong>
                </p>

                <h2>Política de Privacidade</h2>
                <div class="meta-data">Última atualização: <?php echo date('d/m/Y'); ?></div>

                <h3>1. Objeto</h3>
                <p>Esta Política de Privacidade descreve como a aplicação trata as informações utilizadas durante seu funcionamento.</p>

                <h3>2. Natureza dos Dados Processados</h3>
                <p>A aplicação foi desenvolvida para processar exclusivamente dados operacionais relacionados à gestão de estoque e suprimentos, incluindo códigos de produtos, descrições de materiais, quantidades, custos e movimentações de inventário.</p>

                <h3>3. Dados Pessoais e Dados Sensíveis</h3>
                <p>A aplicação não possui como finalidade a coleta, armazenamento, compartilhamento ou tratamento de dados pessoais ou dados pessoais sensíveis. A ferramenta não foi projetada para processar informações de pacientes, profissionais de saúde, prontuários ou diagnósticos. Caso tais informações sejam inseridas pelo usuário, a responsabilidade pela conformidade legal será exclusivamente da instituição de saúde e dos usuários responsáveis.</p>

                <h3>4. Compartilhamento de Informações</h3>
                <p>A aplicação não realiza compartilhamento intencional de dados com terceiros. Eventuais integrações ou exportações realizadas por decisão da instituição usuária ocorrerão sob sua exclusiva responsabilidade.</p>

                <h3>5. Armazenamento de Dados</h3>
                <p>Os dados processados permanecem localmente sob controle da instituição ou do ambiente em que a aplicação estiver instalada (banco de dados local). A ferramenta não possui finalidade comercial, publicitária ou de criação de perfis de comportamento.</p>

                <h3>6. Segurança da Informação</h3>
                <p>A aplicação adota medidas técnicas padrão para a proteção das informações estruturadas. Contudo, a instituição usuária permanece responsável pela gestão de acessos do servidor local, infraestrutura física, políticas de backup e segurança da rede aplicáveis ao seu próprio ambiente.</p>

                <h3>7. Conformidade com a LGPD</h3>
                <p>A ferramenta foi concebida para operar com dados operacionais de estoque e não possui como finalidade o tratamento de dados pessoais. A observância da Lei Geral de Proteção de Dados (Lei nº 13.709/2018) é responsabilidade da instituição usuária no contexto de sua utilização.</p>

                <h3>8. Direitos e Solicitações</h3>
                <p>Solicitações relacionadas à privacidade e proteção de dados devem ser encaminhadas diretamente à administração responsável pelo ambiente em que a aplicação local está hospedada.</p>
            </section>
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
        <p><a href="politicas.php" style="font-weight: 600; color: #3b82f6; margin-right: 15px;">Privacidade e Termos de uso</a></p>
    </footer>
    <script>
        function lancarAlerta(mensagem, tipo = 'info', tempoExibicao = 4000) {
            const container = document.getElementById('container-toasts-global');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `alerta-toast toast-${tipo}`;

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