# HGestor - Sistema de Gestão de Estoque e Almoxarifado

O **HGestor** é uma solução web desenvolvida para otimizar, controlar e simplificar os fluxos operacionais de estoques e almoxarifados.

---

## 📌 Motivação e Origem do Projeto

Durante anos de atuação prática como almoxarife operando sistemas corporativos de grande porte (como o sistema **Tasy**), vivenciei de perto os desafios diários da rotina de armazenagem:
- Dificuldade no controle dinâmico e visual do endereçamento físico dos itens.
- Lentidão e risco de divergências em processos de inventário e contagem.
- Falta de ferramentas ágeis e intuitivas voltadas diretamente para a realidade do operador de almoxarifado.

Como estudante na área de desenvolvimento de software, decidi unir a experiência prática de chão de almoxarifado ao conhecimento em programação para construir uma ferramenta que resolvesse essas dores de forma direta, performática e objetiva.

O **HGestor** nasceu para facilitar o dia a dia operacional, aumentar a acurácia dos estoques e servir como uma base sólida e extensível. O projeto é aberto à comunidade: sinta-se à vontade para utilizar, sugerir melhorias, reportar problemas ou contribuir com código.

---

## 🎯 Intenções e Objetivos do Sistema

- **Agilidade Operacional:** Interface rápida e focada em produtividade, reduzindo cliques e tempos de resposta.
- **Acurácia e Redução de Erros:** Mapeamento minucioso de endereçamento físico e conferência por bipagem.
- **Rastreabilidade e Governança:** Registro e auditoria detalhada de ações críticas em tempo real.
- **Flexibilidade na Integração de Dados:** Importação e exportação ágil em formatos universais (CSV, XLS, XLSX).
- **Simplicidade de Implantação:** Arquitetura leve baseada em PHP/MySQL sobre Apache, sem dependências externas complexas.

---

## 🚀 Módulos e Funcionalidades

### 1. 📦 Controle e Gestão de Estoque (`modulos/estoque.php`)
- **Painel de Saldos e Posições:** Visão consolidada de itens, lotes, validades e quantidades disponíveis.
- **Alertas Operacionais:** Identificação de itens com estoque crítico, zerado ou com data de validade próxima ao vencimento.
- **Filtros e Buscas Dinâmicas:** Localização rápida por código, descrição, lote ou localização física.
- **Exportação de Dados:** Geração de relatórios operacionais em planilhas para conferências externas.

### 2. 📍 Endereçamento Físico (`modulos/enderecamento.php`)
- **Mapeamento Estrutural:** Cadastro e organização física por Rua, Prédio/Módulo, Nível/Andar e Vão/Posição.
- **Vínculo de Itens:** Associação individual ou em lote de produtos e lotes a endereços específicos.
- **Gestão de Capacidade:** Definição de parâmetros físicos de ocupação e otimização de rotas de *picking*.
- **Controle de Parâmetros de Endereço:** Ajuste de dimensões, prioridades e regras de alocação.

### 3. 📝 Pré-Inventário e Inventário (`modulos/pre_inventario.php`)
- **Ciclos de Contagem:** Criação e acompanhamento de inventários gerais ou rotativos.
- **Bipagem em Tempo Real:** Suporte a leitores de código de barras para conferência rápida de itens e lotes.
- **Importação/Exportação de Dados:** Processamento de planilhas de contagem e estoque nos formatos CSV, XLS e XLSX (utilizando SimpleXLS/SimpleXLSX).
- **Análise de Divergências:** Identificação imediata de sobras e faltas antes da consolidação.
- **Encerramento e Histórico:** Finalização segura com gravação de histórico e possibilidade de cancelamento/exclusão auditada.

### 4. 🛒 Gestão de Compras (`modulos/compras.php`)
- **Acompanhamento de Pedidos:** Registro e monitoramento do status de requisições e pedidos de compra.
- **Previsão de Reposição:** Apoio à tomada de decisão de compra com base nos níveis de estoque e parâmetros de consumo.

### 5. ⚙️ Parametrização do Sistema (`modulos/parametros.php`)
- **Regras de Estoque:** Definição de estoques mínimos, estoques de segurança e pontos de pedido.
- **Configurações Globais:** Ajustes operacionais do almoxarifado e parametrização de fluxos do sistema.
- **Opções de Manutenção:** Rotinas controladas para reset de parâmetros e sincronização de dados.

### 6. 🛡️ Painel Administrativo e Governança (`modulos/admin.php`)
- **Gestão de Usuários:** Cadastro, edição, ativação/desativação e redefinição de senhas.
- **Controle de Acesso Baseado em Papéis (RBAC):** Definição de grupos de usuários e permissões específicas por recurso/módulo.
- **Auditoria e Logs de Sistema:** Monitoramento dos últimos 300 eventos com classificação de severidade (🟢 `Info`, 🟡 `Warn`, 🔴 `Danger`).
  - Eventos monitorados: `login_sucesso`, `login_falha`, `usuario_bloqueado`, `login_bloqueado`, `cadastro_realizado`, `cadastro_senha_fraca`, `cadastro_estoque_invalido`, `cadastro_login_duplicado`, `upload_csv`, `upload_csv_erro`, `reset_estoque`, `reset_parametros`, `inventario_encerrado`, `inventario_deletado`, `logout`, `admin_alterar_status`, `admin_deletar_usuario`.
- **Filtros e Limpeza de Logs:** Filtragem por nível de criticidade e rotina de expurgo controlado.

### 7. 🔐 Autenticação e Segurança (`modulos/login.php`, `acoes/autenticar.php`)
- **Proteção contra Brute Force:** Bloqueio temporário de contas após tentativas consecutivas de login inválido.
- **Gerenciamento de Sessão:** Isolamento de sessões de usuário, suporte a auto-login controlado e encerramento seguro (`logout.php`).
- **Políticas e Conformidade (`modulos/politicas.php`):** Termos de uso, boas práticas de segurança e diretrizes operacionais.

---

## 🛠️ Tecnologias Utilizadas

- **Linguagem Backend:** PHP 8+
- **Banco de Dados:** MySQL / MariaDB (com extensão PDO)
- **Frontend:** HTML5, CSS3 estruturado (`css/estilos.css`), JavaScript (Vanilla / Fetch API)
- **Manipulação de Planilhas:** `SimpleXLS` e `SimpleXLSX`
- **Servidor Web:** Apache com suporte a `mod_rewrite` (`.htaccess`)
- **Ícones:** Font Awesome 6

---

## 📂 Estrutura de Diretórios

```text
hgestor/
├── .htaccess                   # Regras de reescrita de URL e segurança Apache
├── index.php                   # Ponto de entrada / roteador da aplicação
├── README.md                   # Documentação detalhada do projeto
├── acoes/                      # Processamentos de backend e endpoints assíncronos
│   ├── autenticar.php
│   ├── exportar.php
│   ├── importar.php
│   ├── salvar_bipagem.php
│   ├── salvar_endereco.php
│   ├── libs/                   # Bibliotecas auxiliares (SimpleXLS, SimpleXLSX)
│   └── ...
├── assets/                     # Imagens, logotipos e ícones do sistema
│   └── img/
├── config/                     # Configurações de ambiente e conexão
│   └── conexao.php
├── css/                        # Folhas de estilo da aplicação
│   └── estilos.css
└── modulos/                    # Interfaces e visualizações dos módulos
    ├── admin.php
    ├── compras.php
    ├── componente_header.php
    ├── enderecamento.php
    ├── estoque.php
    ├── login.php
    ├── parametros.php
    ├── politicas.php
    └── pre_inventario.php
```

---

## 💻 Instalação e Execução Local

### Pré-requisitos
- Ambiente com PHP 8.0+ e MySQL (ex.: **XAMPP**, **WampServer** ou **Docker**).
- Módulo `mod_rewrite` ativado no Apache.
- Extensões PHP ativas: `pdo_mysql`, `zip`, `xml`, `mbstring`.

### Passo a Passo

1. **Clonar o repositório:**
   ```bash
   git clone https://github.com/Dandilo-zz/HGestor.git
   ```

2. **Mover para o diretório web do servidor:**
   - No XAMPP (Windows): coloque a pasta em `C:\xampp\htdocs\hgestor`

3. **Configurar o Banco de Dados:**
   - Crie a base de dados no MySQL/MariaDB.
   - Configure as credenciais de acesso no arquivo [config/conexao.php](config/conexao.php):
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'nome_do_banco');
     define('DB_USER', 'usuario');
     define('DB_PASS', 'senha');
     ```

4. **Acessar no navegador:**
   ```text
   http://localhost/hgestor/
   ```

---

## 🤝 Contribuições

Contribuições de qualquer natureza são muito bem-vindas! Se você deseja colaborar:

1. Faça um **Fork** do projeto.
2. Crie uma branch para sua funcionalidade ou correção:
   ```bash
   git checkout -b feature/minha-melhoria
   ```
3. Commit suas alterações seguindo o padrão [Conventional Commits](https://www.conventionalcommits.org/):
   ```bash
   git commit -m "feat: adicionar suporte a exportação em PDF"
   ```
4. Envie sua branch para o repositório remoto:
   ```bash
   git push origin feature/minha-melhoria
   ```
5. Abra um **Pull Request**.

---

## 📄 Licença

Este projeto está disponível sob a licença [MIT](LICENSE), permitindo livre uso, modificação e distribuição.
