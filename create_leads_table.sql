-- SQL para criar tabela de leads no servidor online
-- Execute este comando no seu servidor MySQL

CREATE TABLE IF NOT EXISTS `leads` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `client_id` int(11) NOT NULL,
    `lead_number` varchar(20) NOT NULL,
    `lead_name` varchar(255) NOT NULL,
    `is_hot_lead` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_client_id` (`client_id`),
    KEY `idx_lead_number` (`lead_number`),
    KEY `idx_hot_lead` (`is_hot_lead`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice composto para buscas rápidas
ALTER TABLE `leads` ADD INDEX `idx_client_lead` (`client_id`, `lead_number`);

-- Comentários para documentação
ALTER TABLE `leads` 
    MODIFY `client_id` int(11) COMMENT 'ID do cliente-lojista',
    MODIFY `lead_number` varchar(20) COMMENT 'Telefone do lead com código do país',
    MODIFY `lead_name` varchar(255) COMMENT 'Nome completo do lead',
    MODIFY `is_hot_lead` tinyint(1) COMMENT '1=Hot Lead, 0=Mensagem recebida normal';

-- Inserir alguns registros de teste (opcional)
-- INSERT INTO `leads` (`client_id`, `lead_number`, `lead_name`, `is_hot_lead`) VALUES
-- (1, '5519991234567', 'João Silva', 1),
-- (1, '5519987654321', 'Maria Santos', 0);