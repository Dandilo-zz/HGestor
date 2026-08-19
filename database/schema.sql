-- ==========================================================
-- HGestor - Script de Instalação do Banco de Dados
-- Compatibilidade: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `hgestor` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hgestor`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- 1. Tabela: sistema_config
-- Armazena configurações globais do sistema
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sistema_config` (
    `chave` VARCHAR(100) NOT NULL,
    `valor` TEXT NOT NULL,
    PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Tabela: estoque_nomes
-- Lista de almoxarifados / estoques disponíveis no sistema
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estoque_nomes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_estoque` VARCHAR(100) NOT NULL UNIQUE,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Tabela: usuarios
-- Cadastro de operadores e administradores
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `login` VARCHAR(100) NOT NULL UNIQUE,
    `senha` VARCHAR(255) NOT NULL,
    `estoque_nome` VARCHAR(100) NOT NULL,
    `status_acesso` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo/Liberado, 0 = Bloqueado/Pendente',
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Administrador, 0 = Operador',
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Tabela: login_tentativas
-- Controle de tentativas de login e proteção contra força bruta
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_tentativas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `login` VARCHAR(100) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_login_ip_data` (`login`, `ip`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. Tabela: sistema_logs
-- Auditoria detalhada de eventos e ações críticas do sistema
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sistema_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_usuario` INT NULL,
    `login` VARCHAR(100) NULL,
    `acao` VARCHAR(100) NOT NULL,
    `descricao` TEXT NULL,
    `nivel` VARCHAR(20) NOT NULL DEFAULT 'info',
    `ip` VARCHAR(45) NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_logs_criado_em` (`criado_em`),
    INDEX `idx_logs_nivel` (`nivel`),
    INDEX `idx_logs_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. Tabela: admin_alertas
-- Avisos e comunicados globais aos usuários
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_alertas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mensagem` TEXT NOT NULL,
    `tipo` VARCHAR(20) NOT NULL DEFAULT 'info',
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. Tabela: alerta_leituras
-- Registro de alertas marcados como lidos por usuário
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alerta_leituras` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_alerta` INT NOT NULL,
    `id_usuario` INT NOT NULL,
    `lido_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_alerta_usuario` (`id_alerta`, `id_usuario`),
    CONSTRAINT `fk_leitura_alerta` FOREIGN KEY (`id_alerta`) REFERENCES `admin_alertas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_leitura_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. Tabelas de Parâmetros e Classificações (por Usuário)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `config_grupos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_grupo` VARCHAR(100) NOT NULL,
    `id_usuario` INT NOT NULL,
    INDEX `idx_grupo_usr` (`id_usuario`),
    CONSTRAINT `fk_grupo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `config_tipos_compra` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_tipo` VARCHAR(100) NOT NULL,
    `id_usuario` INT NOT NULL,
    INDEX `idx_tipo_usr` (`id_usuario`),
    CONSTRAINT `fk_tipo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `config_padronizacoes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_padrao` VARCHAR(100) NOT NULL,
    `id_usuario` INT NOT NULL,
    INDEX `idx_padr_usr` (`id_usuario`),
    CONSTRAINT `fk_padr_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `config_materiais` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_material` VARCHAR(100) NOT NULL,
    `id_usuario` INT NOT NULL,
    `id_grupo` INT NULL,
    `id_tipo_compra` INT NULL,
    `id_padronizacao` INT NULL,
    UNIQUE KEY `unique_mat_usuario` (`id_material`, `id_usuario`),
    INDEX `idx_mat_usuario` (`id_usuario`),
    CONSTRAINT `fk_cm_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 9. Tabelas de Endereçamento Físico (por Usuário)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `endereco_params` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_usuario` INT NOT NULL,
    `eixo` ENUM('x', 'y', 'z') NOT NULL,
    `valor` VARCHAR(50) NOT NULL,
    INDEX `idx_end_param_usr` (`id_usuario`, `eixo`),
    CONSTRAINT `fk_end_param_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `endereco_materiais` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_usuario` INT NOT NULL,
    `id_material` VARCHAR(100) NOT NULL,
    `x` VARCHAR(50) NULL,
    `y` VARCHAR(50) NULL,
    `z` VARCHAR(50) NULL,
    UNIQUE KEY `unique_end_mat_user` (`id_usuario`, `id_material`),
    INDEX `idx_end_mat_user` (`id_usuario`),
    CONSTRAINT `fk_end_mat_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 10. Tabela: tasy_estoque
-- Snapshot de produtos e saldos importados
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasy_estoque` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_material` VARCHAR(100) NOT NULL,
    `id_usuario` INT NOT NULL,
    `descricao` VARCHAR(255) NOT NULL,
    `protheus` VARCHAR(100) NULL,
    `saldo` INT NOT NULL DEFAULT 0,
    `consumo` INT NOT NULL DEFAULT 0,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tasy_mat_usr` (`id_material`, `id_usuario`),
    INDEX `idx_tasy_usr` (`id_usuario`),
    CONSTRAINT `fk_tasy_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 11. Tabelas de Pedidos de Compras
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pedidos_compra` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_usuario` INT NOT NULL,
    `numero_fluig` VARCHAR(100) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ativo',
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ped_usuario` (`id_usuario`),
    CONSTRAINT `fk_ped_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pedidos_compra_itens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_pedido` INT NOT NULL,
    `id_material` VARCHAR(100) NOT NULL,
    `descricao` VARCHAR(255) NOT NULL,
    `protheus` VARCHAR(100) NULL,
    `saldo_atual` INT NOT NULL DEFAULT 0,
    `consumo_mensal` INT NOT NULL DEFAULT 0,
    `sugestao_compra` INT NOT NULL DEFAULT 0,
    `quantidade_solicitada` INT NOT NULL DEFAULT 0,
    INDEX `idx_ped_item_pedido` (`id_pedido`),
    CONSTRAINT `fk_ped_item_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_compra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 12. Tabelas de Pré-Inventário, Bipagens e Itens
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pre_inventario` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_usuario` INT NOT NULL,
    `descricao` VARCHAR(255) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'ativo, encerrado, cancelado',
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `encerrado_em` DATETIME NULL,
    INDEX `idx_inv_usuario` (`id_usuario`),
    CONSTRAINT `fk_inv_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pre_inventario_itens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_inventario` INT NOT NULL,
    `cd_material` VARCHAR(100) NOT NULL,
    `ds_material` VARCHAR(255) NOT NULL,
    `cd_sistema_ant` VARCHAR(100) NULL,
    `qt_estoque_sistema` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `quantidade_bipada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `data_ultima_bipagem` DATETIME NULL,
    UNIQUE KEY `unique_inv_material` (`id_inventario`, `cd_material`),
    INDEX `idx_inv_item` (`id_inventario`),
    CONSTRAINT `fk_inv_item_inventario` FOREIGN KEY (`id_inventario`) REFERENCES `pre_inventario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pre_inventario_barcodes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_item` INT NOT NULL,
    `ds_barras` VARCHAR(100) NOT NULL,
    `ds_lote` VARCHAR(100) NULL,
    `dt_validade` VARCHAR(50) NULL,
    `seq_lote` VARCHAR(50) NULL,
    INDEX `idx_barcodes_item` (`id_item`),
    INDEX `idx_barcodes_ds` (`ds_barras`),
    CONSTRAINT `fk_bc_item` FOREIGN KEY (`id_item`) REFERENCES `pre_inventario_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pre_inventario_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_item` INT NOT NULL,
    `id_usuario` INT NOT NULL,
    `tipo_alteracao` VARCHAR(50) NOT NULL DEFAULT 'bipagem',
    `ds_barras_lida` VARCHAR(100) NULL,
    `valor_anterior` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `valor_novo` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `qtd_incremento` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inv_log_item` (`id_item`),
    INDEX `idx_inv_log_usuario` (`id_usuario`),
    CONSTRAINT `fk_inv_log_item` FOREIGN KEY (`id_item`) REFERENCES `pre_inventario_itens` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_log_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------
-- 13. Carga Inicial de Dados (Seed)
-- ----------------------------------------------------------

-- Configuração padrão: autoaprovação de novos cadastros ativa (1)
INSERT INTO `sistema_config` (`chave`, `valor`) 
VALUES ('aprovacao_automatica', '1')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

-- Estoques/Almoxarifados padrões
INSERT IGNORE INTO `estoque_nomes` (`nome_estoque`) VALUES
('Almoxarifado Central'),
('Farmácia Central'),
('Almoxarifado Geral');

-- Usuário Administrador Padrão (Login: admin / Senha: admin_password_change_me / Hash padrão abaixo para 'admin12345')
-- Senha de fábrica: admin12345
INSERT IGNORE INTO `usuarios` (`id`, `login`, `senha`, `estoque_nome`, `status_acesso`, `is_admin`) VALUES
(1, 'admin', '$2y$10$tJ05rQZcQ83N4Qz1wP8DfuZ3R2k0kG7K1E6eL7mC7z0x3J6Q2M6vW', 'Almoxarifado Central', 1, 1);
