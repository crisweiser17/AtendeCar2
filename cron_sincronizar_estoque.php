<?php
/**
 * Script de sincronização diária do estoque
 * Este arquivo deve ser executado via cron job diariamente
 *
 * Exemplo de configuração no crontab:
 * 0 6 * * * /usr/bin/php /caminho/para/projeto/cron_sincronizar_estoque.php
 * (Executa todos os dias às 6:00 da manhã)
 */

// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

require_once 'config/database.php';
require_once 'importador_estoque.php';

class SincronizadorEstoque {
    private $pdo;
    private $importador;
    private $logFile;
    
    public function __construct() {
        $this->pdo = getConnection();
        $this->importador = new ImportadorEstoque();
        $this->logFile = __DIR__ . '/logs/sincronizacao_' . date('Y-m-d') . '.log';
        
        // Criar diretório de logs se não existir
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Executa a sincronização para todos os clientes ativos
     */
    public function executarSincronizacao() {
        $this->log("=== INICIANDO SINCRONIZAÇÃO DIÁRIA ===");
        $this->log("Data/Hora: " . date('d/m/Y H:i:s'));
        
        try {
            // Buscar todos os clientes ativos com URL de estoque configurada
            $clientes = $this->buscarClientesParaSincronizar();
            
            if (empty($clientes)) {
                $this->log("Nenhum cliente encontrado para sincronização.");
                return;
            }
            
            $this->log("Encontrados " . count($clientes) . " clientes para sincronizar.");
            
            $sucessos = 0;
            $erros = 0;
            
            foreach ($clientes as $cliente) {
                $this->log("\n--- Sincronizando cliente: {$cliente['nome_loja']} (ID: {$cliente['id']}) ---");
                $this->log("URL: {$cliente['url_estoque']}");
                
                try {
                    $resultado = $this->sincronizarCliente($cliente);
                    
                    if ($resultado['sucesso']) {
                        $sucessos++;
                        $this->log("✅ SUCESSO: {$resultado['mensagem']}");
                        
                        // Atualizar última sincronização
                        $this->atualizarUltimaSincronizacao($cliente['id']);
                        
                    } else {
                        $erros++;
                        $this->log("❌ ERRO: {$resultado['erro']}");
                    }
                    
                } catch (Exception $e) {
                    $erros++;
                    $this->log("❌ EXCEÇÃO: " . $e->getMessage());
                }
                
                // Pausa entre clientes para não sobrecarregar o servidor
                sleep(2);
            }
            
            $this->log("\n=== RESUMO DA SINCRONIZAÇÃO ===");
            $this->log("Total de clientes: " . count($clientes));
            $this->log("Sucessos: {$sucessos}");
            $this->log("Erros: {$erros}");
            $this->log("=== FIM DA SINCRONIZAÇÃO ===\n");
            
        } catch (Exception $e) {
            $this->log("ERRO CRÍTICO: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Busca clientes que precisam ser sincronizados
     */
    private function buscarClientesParaSincronizar() {
        $stmt = $this->pdo->prepare("
            SELECT id, nome_loja, url_estoque, ultima_sincronizacao
            FROM clientes
            WHERE status = 'ativo'
            AND url_estoque IS NOT NULL
            AND url_estoque != ''
            AND url_estoque != 'https://'
            ORDER BY ultima_sincronizacao ASC NULLS FIRST
        ");
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Sincroniza o estoque de um cliente específico - USANDO SISTEMA DE FILA
     */
    private function sincronizarCliente($cliente) {
        // Adicionar cliente à fila de sincronização
        $queueId = $this->adicionarClienteNaFila($cliente['id']);
        
        if ($queueId) {
            $this->log("Cliente adicionado à fila com ID: {$queueId}");
            
            // Processar imediatamente (para cron job)
            $resultado = $this->processarItemDaFila($cliente);
            
            if ($resultado['sucesso']) {
                // Limpar veículos que não estão mais no site
                $this->limparVeiculosRemovidosDoSite($cliente['id'], $cliente['url_estoque']);
            }
            
            return $resultado;
        } else {
            return [
                'sucesso' => false,
                'erro' => 'Erro ao adicionar cliente na fila de sincronização'
            ];
        }
    }
    
    /**
     * Adiciona um cliente na fila de sincronização
     */
    private function adicionarClienteNaFila($clienteId, $priority = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sync_queue (cliente_id, status, priority, scheduled_at)
                VALUES (?, 'pending', ?, CURRENT_TIMESTAMP)
            ");
            
            $stmt->execute([$clienteId, $priority]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            $this->log("Erro ao adicionar na fila: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Processa um item da fila
     */
    private function processarItemDaFila($cliente) {
        try {
            // Marcar como processando
            $this->atualizarStatusFila($cliente['id'], 'processing');
            
            // Executar importação
            $resultado = $this->importador->importarEstoque($cliente['id'], $cliente['url_estoque']);
            
            if ($resultado['sucesso']) {
                $this->atualizarStatusFila($cliente['id'], 'completed');
            } else {
                $this->atualizarStatusFila($cliente['id'], 'failed', $resultado['erro']);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->atualizarStatusFila($cliente['id'], 'failed', $e->getMessage());
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualiza o status de um item na fila
     */
    private function atualizarStatusFila($clienteId, $status, $errorMessage = null) {
        try {
            $campos = ['status = ?'];
            $params = [$status];
            
            if ($status === 'processing') {
                $campos[] = 'started_at = CURRENT_TIMESTAMP';
                $campos[] = 'attempts = attempts + 1';
            } elseif ($status === 'completed') {
                $campos[] = 'completed_at = CURRENT_TIMESTAMP';
            } elseif ($status === 'failed') {
                $campos[] = 'error_message = ?';
                $params[] = $errorMessage;
            }
            
            $sql = "UPDATE sync_queue SET " . implode(', ', $campos) . " WHERE cliente_id = ? AND status IN ('pending', 'processing')";
            $params[] = $clienteId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
        } catch (Exception $e) {
            $this->log("Erro ao atualizar status da fila: " . $e->getMessage());
        }
    }
    
    /**
     * Remove veículos que não estão mais disponíveis no site
     */
    private function limparVeiculosRemovidosDoSite($clienteId, $urlEstoque) {
        try {
            // Buscar todos os links dos veículos ativos no banco
            $stmt = $this->pdo->prepare("
                SELECT id, link FROM veiculos 
                WHERE cliente_id = ? AND ativo = TRUE AND link IS NOT NULL AND link != ''
            ");
            $stmt->execute([$clienteId]);
            $veiculosBanco = $stmt->fetchAll();
            
            if (empty($veiculosBanco)) {
                return;
            }
            
            // Buscar HTML atual do site
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $urlEstoque);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $htmlAtual = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$htmlAtual) {
                $this->log("Erro ao verificar veículos removidos: HTTP {$httpCode}");
                return;
            }
            
            $removidos = 0;
            
            // Verificar cada veículo do banco se ainda existe no site
            foreach ($veiculosBanco as $veiculo) {
                $linkId = basename($veiculo['link']);
                
                // Se o link não está mais no HTML, desativar o veículo
                if (strpos($htmlAtual, $linkId) === false) {
                    $stmtDesativar = $this->pdo->prepare("
                        UPDATE veiculos SET ativo = FALSE, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmtDesativar->execute([$veiculo['id']]);
                    $removidos++;
                    
                    $this->log("Veículo removido do site: {$veiculo['link']}");
                }
            }
            
            if ($removidos > 0) {
                $this->log("Total de veículos removidos: {$removidos}");
            }
            
        } catch (Exception $e) {
            $this->log("Erro ao limpar veículos removidos: " . $e->getMessage());
        }
    }
    
    /**
     * Atualiza a data da última sincronização
     */
    private function atualizarUltimaSincronizacao($clienteId) {
        $stmt = $this->pdo->prepare("
            UPDATE clientes SET ultima_sincronizacao = CURRENT_TIMESTAMP WHERE id = ?
        ");
        $stmt->execute([$clienteId]);
    }
    
    /**
     * Registra mensagem no log
     */
    private function log($mensagem) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$mensagem}\n";
        
        // Escrever no arquivo de log
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Também exibir no console se executado via linha de comando
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * Limpa logs antigos (manter apenas últimos 30 dias)
     */
    public function limparLogsAntigos() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            return;
        }
        
        $arquivos = glob($logDir . '/sincronizacao_*.log');
        $dataLimite = strtotime('-30 days');
        
        foreach ($arquivos as $arquivo) {
            if (filemtime($arquivo) < $dataLimite) {
                unlink($arquivo);
                $this->log("Log antigo removido: " . basename($arquivo));
            }
        }
    }
}

// Executar sincronização se chamado diretamente
if (php_sapi_name() === 'cli' || (isset($_GET['exec']) && $_GET['exec'] === 'sync')) {
    $sincronizador = new SincronizadorEstoque();
    
    // Limpar logs antigos primeiro
    $sincronizador->limparLogsAntigos();
    
    // Executar sincronização
    $sincronizador->executarSincronizacao();
    
    if (php_sapi_name() === 'cli') {
        echo "\nSincronização concluída. Verifique o arquivo de log para detalhes.\n";
    } else {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Sincronização executada com sucesso']);
    }
}
?>