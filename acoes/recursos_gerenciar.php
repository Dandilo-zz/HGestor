<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../modulos/parametros?erro=" . urlencode("Acesso não autorizado."));
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    header("Location: ../modulos/parametros?erro=" . urlencode("Requisição inválida (CSRF)."));
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];
$acao      = isset($_POST['acao']) ? trim($_POST['acao']) : '';
$tipo      = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';

if ($tipo === 'compra') {
    $tabela     = 'config_tipos_compra';
    $colunaNome = 'nome_tipo';
} elseif ($tipo === 'padronizacao') {
    $tabela     = 'config_padronizacoes';
    $colunaNome = 'nome_padrao';
} else {
    $tabela     = 'config_grupos';
    $colunaNome = 'nome_grupo';
}

if ($acao === 'salvar') {
    $id   = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';

    if (empty($nome)) {
        header("Location: ../modulos/parametros?erro=" . urlencode("O nome digitado não pode ser vazio."));
        exit;
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE $tabela SET $colunaNome = :nome WHERE id = :id AND id_usuario = :uid");
            $stmt->execute(['nome' => $nome, 'id' => $id, 'uid' => $idUsuario]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO $tabela ($colunaNome, id_usuario) VALUES (:nome, :uid)");
            $stmt->execute(['nome' => $nome, 'uid' => $idUsuario]);
        }
        header("Location: ../modulos/parametros?sucesso=" . urlencode("Registro salvo com sucesso!"));
        exit;
    } catch (\PDOException $e) {
        header("Location: ../modulos/parametros?erro=" . urlencode("Erro ao salvar: Nome duplicado ou inválido."));
        exit;
    }
}

if ($acao === 'deletar') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    if (!$id) {
        header("Location: ../modulos/parametros?erro=" . urlencode("ID inválido para exclusão."));
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM $tabela WHERE id = :id AND id_usuario = :uid");
        $stmt->execute(['id' => $id, 'uid' => $idUsuario]);
        header("Location: ../modulos/parametros?sucesso=" . urlencode("Registro removido com sucesso!"));
        exit;
    } catch (\PDOException $e) {
        header("Location: ../modulos/parametros?erro=" . urlencode("Erro ao deletar o registro solicitado do banco de dados."));
        exit;
    }
}

header("Location: ../modulos/parametros");
exit;