<?php
require_once '../config/conexao.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit('Não autenticado.');
}

$idUsuario = (int) $_SESSION['user']['id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            em.id_material,
            te.protheus,
            te.descricao,
            te.saldo,
            em.x,
            em.y,
            em.z
        FROM endereco_materiais em
        INNER JOIN tasy_estoque te ON em.id_material = te.id_material AND te.id_usuario = :uid_te
        WHERE em.id_usuario = :uid_em
        ORDER BY te.descricao ASC
    ");
    $stmt->execute(['uid_te' => $idUsuario, 'uid_em' => $idUsuario]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'enderecos_' . date('dmY') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // BOM UTF-8
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    
    fputcsv($out, ['ID Material', 'Protheus', 'Descrição', 'Saldo', 'X (Rua)', 'Y (Prateleira)', 'Z (Nível)'], ';');

    foreach ($dados as $linha) {
        fputcsv($out, [
            $linha['id_material'],
            $linha['protheus'],
            $linha['descricao'],
            $linha['saldo'],
            $linha['x'] ?? '',
            $linha['y'] ?? '',
            $linha['z'] ?? ''
        ], ';');
    }
    
    fclose($out);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erro ao exportar endereços.');
}
