<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || (int)$_SESSION['user']['is_admin'] !== 1) {
    header("Location: ../modulos/parametros?erro=" . urlencode("Acesso não autorizado."));
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    header("Location: ../modulos/parametros?erro=" . urlencode("Requisição inválida (CSRF)."));
    exit;
}

$acao = isset($_POST['acao']) ? trim($_POST['acao']) : '';

if ($acao === 'salvar') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $nome_grupo = isset($_POST['nome_grupo']) ? trim($_POST['nome_grupo']) : '';

    if (empty($nome_grupo)) {
        header("Location: ../modulos/parametros?erro=" . urlencode("O nome do grupo não pode ser vazio."));
        exit;
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE config_grupos SET nome_grupo = :nome WHERE id = :id");
            $stmt->execute(['nome' => $nome_grupo, 'id' => $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO config_grupos (nome_grupo) VALUES (:nome)");
            $stmt->execute(['nome' => $nome_grupo]);
        }
        
        header("Location: ../modulos/parametros?sucesso=" . urlencode("Grupo salvo com sucesso!"));
        exit;
    } catch (\PDOException $e) {
        header("Location: ../modulos/parametros?erro=" . urlencode("Erro ao salvar grupo: Nome já existente ou duplicado."));
        exit;
    }
}

if ($acao === 'deletar') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if (!$id) {
        header("Location: ../modulos/parametros?erro=" . urlencode("ID do grupo inválido para exclusão."));
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM config_grupos WHERE id = :id");
        $stmt->execute(['id' => $id]);

        header("Location: ../modulos/parametros?sucesso=" . urlencode("Grupo removido com sucesso!"));
        exit;
    } catch (\PDOException $e) {
        header("Location: ../modulos/parametros?erro=" . urlencode("Erro ao deletar grupo do banco de dados."));
        exit;
    }
}

header("Location: ../modulos/parametros");
exit;