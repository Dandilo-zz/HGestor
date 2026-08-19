<?php
require_once '../config/conexao.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$acao = $_POST['acao'] ?? '';
$login = trim($_POST['login'] ?? '');
$senha = $_POST['senha'] ?? '';
$estoque_nome = $_POST['estoque_nome'] ?? '';

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = substr($ip, 0, 45);

if ($acao === 'login') {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE login = :login");
    $stmt->execute(['login' => $login]);
    $user = $stmt->fetch();

    if ($user && (int) $user['status_acesso'] === 0) {
        registrarLog($pdo, 'login_bloqueado', 'Tentativa de acesso em conta bloqueada.', 'danger', (int)$user['id'], $user['login']);
        header("Location: ../modulos/login.php?erro=" . urlencode("Seu acesso ainda não foi liberado pelo Administrador. Aguarde a liberação."));
        exit;
    }

    $stmtTentativas = $pdo->prepare("
        SELECT COUNT(*) 
        FROM login_tentativas 
        WHERE login = :login AND ip = :ip AND criado_em >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmtTentativas->execute(['login' => $login, 'ip' => $ip]);
    $tentativas = (int) $stmtTentativas->fetchColumn();

    if ($tentativas >= 6) {
        if ($user) {
            $stmtUp = $pdo->prepare("UPDATE usuarios SET status_acesso = 0 WHERE id = :id");
            $stmtUp->execute(['id' => $user['id']]);
        }
        registrarLog($pdo, 'usuario_bloqueado', 'Usuário bloqueado por excesso de tentativas.', 'danger', $user ? (int)$user['id'] : null, $login);
        header("Location: ../modulos/login.php?erro=" . urlencode("Muitas tentativas. Aguarde 5 minutos."));
        exit;
    }

    if ($tentativas >= 3) {
        header("Location: ../modulos/login.php?erro=" . urlencode("Muitas tentativas. Aguarde 5 minutos."));
        exit;
    }

    if ($user && password_verify($senha, $user['senha'])) {
        $stmtDel = $pdo->prepare("DELETE FROM login_tentativas WHERE login = :login");
        $stmtDel->execute(['login' => $login]);

        registrarLog($pdo, 'login_sucesso', 'Login efetuado com sucesso.', 'info', (int)$user['id'], $user['login']);

        $_SESSION['user'] = $user;
        header("Location: ../modulos/estoque.php");
        exit;
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO login_tentativas (login, ip) VALUES (:login, :ip)");
        $stmtIns->execute(['login' => $login, 'ip' => $ip]);

        registrarLog($pdo, 'login_falha', 'Senha incorreta ou usuário inexistente.', 'warn', $user ? (int)$user['id'] : null, $login);

        header("Location: ../modulos/login.php?erro=" . urlencode("Usuário ou senha incorretos."));
        exit;
    }
}

if ($acao === 'cadastro') {
    if (empty($login) || empty($senha) || empty($estoque_nome)) {
        header("Location: ../modulos/login.php?erro=" . urlencode("Preencha todos os campos obrigatórios."));
        exit;
    }

    if (strlen($senha) < 8) {
        registrarLog($pdo, 'cadastro_senha_fraca', 'Tentativa de cadastro com senha menor de 8 caracteres.', 'warn', null, $login);
        header("Location: ../modulos/login.php?erro=" . urlencode("A senha deve ter no mínimo 8 caracteres."));
        exit;
    }

    $stmtEstoque = $pdo->prepare("SELECT id FROM estoque_nomes WHERE nome_estoque = :nome");
    $stmtEstoque->execute(['nome' => $estoque_nome]);
    if (!$stmtEstoque->fetch()) {
        registrarLog($pdo, 'cadastro_estoque_invalido', 'Tentativa de cadastro com estoque inválido.', 'warn', null, $login);
        header("Location: ../modulos/login.php?erro=" . urlencode("O estoque selecionado é inválido."));
        exit;
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE login = :login");
    $stmtCheck->execute(['login' => $login]);
    if ($stmtCheck->fetch()) {
        registrarLog($pdo, 'cadastro_login_duplicado', 'Tentativa de cadastro com login duplicado.', 'warn', null, $login);
        header("Location: ../modulos/login.php?erro=" . urlencode("Este nome de usuário já está em uso."));
        exit;
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $configAprovacao = $pdo->query("SELECT valor FROM sistema_config WHERE chave = 'aprovacao_automatica'")->fetch();
    $statusInicial = $configAprovacao ? (int) $configAprovacao['valor'] : 1;

    $stmtCad = $pdo->prepare("INSERT INTO usuarios (login, senha, estoque_nome, status_acesso) VALUES (:login, :senha, :estoque, :status)");
    $stmtCad->execute([
        'login' => $login,
        'senha' => $senhaHash,
        'estoque' => $estoque_nome,
        'status' => $statusInicial
    ]);
    
    $novoId = (int)$pdo->lastInsertId();

    if ($statusInicial === 1) {
        $mensagemSucesso = "Cadastro realizado com sucesso! Você já pode acessar o sistema.";
    } else {
        $mensagemSucesso = "Cadastro realizado! Aguarde até que o Administrador libere seu acesso.";
    }

    registrarLog($pdo, 'cadastro_realizado', 'Cadastro realizado com sucesso.', 'info', $novoId, $login);

    header("Location: ../modulos/login.php?sucesso=" . urlencode($mensagemSucesso));
    exit;
}