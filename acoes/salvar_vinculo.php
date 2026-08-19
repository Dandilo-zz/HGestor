<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['erro' => 'Acesso não autorizado.']);
    exit;
}

$idUsuario = (int) $_SESSION['user']['id'];

$input = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? '';
if (!validarCsrf($csrfToken)) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['erro' => 'Requisição inválida (CSRF).']);
    exit;
}

$id_material    = isset($input['id_material'])    ? trim($input['id_material'])    : '';
$tipo_parametro = isset($input['tipo_parametro']) ? trim($input['tipo_parametro']) : '';
$id_destino     = isset($input['id_destino']) && $input['id_destino'] !== '' ? (int)$input['id_destino'] : null;

if (empty($id_material) || empty($tipo_parametro)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['erro' => 'Dados incompletos.']);
    exit;
}

$coluna = '';
if ($tipo_parametro === 'grupo')        $coluna = 'id_grupo';
elseif ($tipo_parametro === 'compra')   $coluna = 'id_tipo_compra';
elseif ($tipo_parametro === 'padronizacao') $coluna = 'id_padronizacao';

if (empty($coluna)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['erro' => 'Parâmetro inválido.']);
    exit;
}

try {
    $sql = "INSERT INTO config_materiais (id_material, id_usuario, $coluna) 
            VALUES (:id_material, :id_usuario, :id_destino) 
            ON DUPLICATE KEY UPDATE $coluna = :id_destino_update";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id_material'        => $id_material,
        'id_usuario'         => $idUsuario,
        'id_destino'         => $id_destino,
        'id_destino_update'  => $id_destino,
    ]);

    echo json_encode(['sucesso' => true]);
    exit;

} catch (\PDOException $e) {
    error_log("Erro em salvar_vinculo.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['erro' => 'Erro interno ao salvar no banco local.']);
    exit;
}