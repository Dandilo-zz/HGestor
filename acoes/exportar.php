<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acesso negado.');
}

$idUsuario = (int) $_SESSION['user']['id'];

try {
    $sql = "
        SELECT 
            cm.id_material,
            cg.nome_grupo,
            ctc.nome_tipo AS nome_tipo_compra,
            cp.nome_padrao
        FROM config_materiais cm
        LEFT JOIN config_grupos cg ON cm.id_grupo = cg.id
        LEFT JOIN config_tipos_compra ctc ON cm.id_tipo_compra = ctc.id
        LEFT JOIN config_padronizacoes cp ON cm.id_padronizacao = cp.id
        WHERE cm.id_usuario = :id_usuario
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_usuario' => $idUsuario]);
    $dicionario = $stmt->fetchAll();

    $payload = [
        'gerado_por' => 'HorusDEV HGestor',
        'data_exportacao' => date('Y-m-d H:i:s'),
        'total_itens' => count($dicionario),
        'dicionario_parametros' => $dicionario
    ];

    $jsonContent = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=hgestor_parametros_' . date('dmY') . '.json');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    echo $jsonContent;
    exit;

} catch (\PDOException $e) {
    error_log("Erro ao exportar configurações: " . $e->getMessage());
    exit('Erro crítico ao exportar configurações locais.');
}