<?php
$host    = 'localhost';
$db      = 'hgestor';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
} catch (\PDOException $e) {
    error_log("Erro de conexão PDO: " . $e->getMessage());
    die("Erro ao conectar com o banco de dados.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validarCsrf(?string $token): bool {
    return !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function registrarLog(
    PDO $pdo,
    string $acao,
    string $descricao,
    string $nivel = 'info',
    ?int $idUsuario = null,
    ?string $loginUsuario = null
): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] 
        ?? $_SERVER['REMOTE_ADDR'] 
        ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sistema_logs 
                (id_usuario, login, acao, descricao, nivel, ip)
            VALUES 
                (:id_usuario, :login, :acao, :descricao, :nivel, :ip)
        ");
        $stmt->execute([
            'id_usuario' => $idUsuario,
            'login'      => $loginUsuario,
            'acao'       => $acao,
            'descricao'  => $descricao,
            'nivel'      => $nivel,
            'ip'         => substr($ip, 0, 45)
        ]);
    } catch (\Exception $e) {
        error_log("Falha ao registrar log: " . $e->getMessage());
    }
}