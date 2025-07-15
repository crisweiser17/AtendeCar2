-- SQL para adicionar coluna veiculo na tabela leads
-- Execute este comando no seu servidor MySQL

ALTER TABLE `leads` 
ADD COLUMN `veiculo` VARCHAR(255) DEFAULT NULL 
COMMENT 'Nome do veículo de interesse do lead';

-- Índice para buscas por veículo
ALTER TABLE `leads` ADD INDEX `idx_veiculo` (`veiculo`);

-- Atualizar comentários da tabela
ALTER TABLE `leads` 
    MODIFY `veiculo` VARCHAR(255) COMMENT 'Nome/modelo do veículo que o lead está interessado';

-- Exemplo de uso:
-- INSERT INTO leads (client_id, lead_number, lead_name, is_hot_lead, veiculo) 
-- VALUES (1, '5519991234567', 'João Silva', 0, 'Gol 2023');

-- Atualizar leads existentes (opcional)
-- UPDATE leads SET veiculo = 'Não especificado' WHERE veiculo IS NULL;