<?php
/**
 * Script de sincronização diária do estoque
 * Este arquivo deve ser executado via cron job diariamente
 *
 * Exemplo de configuração no crontab:
 * 0 5 * * * /usr/bin/php /caminho/para/projeto/cron_sincronizar_estoque.php
 * (Executa todos os dias às 5:00 da manhã)
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
            // Limpar registros antigos da fila (mais de 7 dias)
            $this->limparFilaAntiga();
            
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
    public function buscarClientesParaSincronizar() {
        $stmt = $this->pdo->prepare("
            SELECT id, nome_loja, url_estoque, ultima_sincronizacao
            FROM clientes
            WHERE status = 'ativo'
            AND url_estoque IS NOT NULL
            AND url_estoque != ''
            AND url_estoque != 'https://'
            AND url_estoque LIKE '%carrosp.com.br%'
            ORDER BY COALESCE(ultima_sincronizacao, '1970-01-01') ASC
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
                
                // Se o link não está mais no HTML, deletar o veículo
                if (strpos($htmlAtual, $linkId) === false) {
                    $stmtDeletar = $this->pdo->prepare("
                        DELETE FROM veiculos WHERE id = ?
                    ");
                    $stmtDeletar->execute([$veiculo['id']]);
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
     * Limpa registros antigos da fila de sincronização (mais de 7 dias)
     */
    private function limparFilaAntiga() {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM sync_queue
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND status IN ('completed', 'failed')
            ");
            $stmt->execute();
            
            $removidos = $stmt->rowCount();
            if ($removidos > 0) {
                $this->log("Removidos {$removidos} registros antigos da fila de sincronização");
            }
            
        } catch (Exception $e) {
            $this->log("Erro ao limpar fila antiga: " . $e->getMessage());
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
if (php_sapi_name() === 'cli' || (isset($_GET['exec']) && $_GET['exec'] === 'sync') || (isset($_GET['cron']) && $_GET['cron'] === 'true')) {
    $sincronizador = new SincronizadorEstoque();
    
    // Buscar clientes para exibir resumo quando cron=true
    $clientes = $sincronizador->buscarClientesParaSincronizar();
    $totalClientes = count($clientes);
    
    // Exibir resumo visual quando executado com cron=true via navegador
    if (isset($_GET['cron']) && $_GET['cron'] === 'true') {
        echo "<!DOCTYPE html>\n";
        echo "<html lang=\"pt-BR\">\n";
        echo "<head>\n";
        echo "<meta charset=\"UTF-8\">\n";
        echo "<title>Sincronização de Estoque - AtendeCar</title>\n";
        echo "<style>\n";
        echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }\n";
        echo ".header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }\n";
        echo ".client-list { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
        echo ".client-item { padding: 15px; margin: 10px 0; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #667eea; }\n";
        echo ".client-name { font-weight: bold; color: #333; }\n";
        echo ".client-url { color: #666; font-size: 0.9em; }\n";
        echo ".success { color: #28a745; font-weight: bold; }\n";
        echo "</style>\n";
        echo "</head>\n";
        echo "<body>\n";
        echo "<div class=\"header\">\n";
        echo "<h1>✅ Sincronização de Estoque</h1>\n";
        echo "<p>Atualizando o estoque para <strong>{$totalClientes} lojistas</strong></p>\n";
        echo "</div>\n";
        
        if ($totalClientes > 0) {
            echo "<div class=\"client-list\">\n";
            echo "<h3>Lista de Lojistas:</h3>\n";
            foreach ($clientes as $cliente) {
                echo "<div class=\"client-item\">\n";
                echo "<div class=\"client-name\">" . htmlspecialchars($cliente['nome_loja']) . "</div>\n";
                echo "<div class=\"client-url\">" . htmlspecialchars($cliente['url_estoque']) . "</div>\n";
                echo "</div>\n";
            }
            echo "</div>\n";
        }
        
        echo "<div class=\"success\">\n";
        echo "<p>🔄 Iniciando sincronização dos {$totalClientes} lojistas...</p>\n";
        echo "</div>\n";
        
        // Forçar envio do buffer
        ob_implicit_flush(true);
        ob_end_flush();
    }
    
    // Limpar logs antigos primeiro
    $sincronizador->limparLogsAntigos();
    
    // Executar sincronização
    $sincronizador->executarSincronizacao();
    
    if (php_sapi_name() === 'cli') {
        echo "\nSincronização concluída. Verifique o arquivo de log para detalhes.\n";
    } elseif (isset($_GET['cron']) && $_GET['cron'] === 'true') {
        echo "<div class=\"success\">\n";
        echo "<p>✅ Sincronização concluída com sucesso!</p>\n";
        echo "</div>\n";
        echo "</body>\n";
        echo "</html>\n";
    } else {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Sincronização executada com sucesso']);
    }
} else {
    // Interface web para execução via navegador
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sincronização de Estoque - AtendeCar</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                border-radius: 10px;
                margin-bottom: 30px;
                text-align: center;
            }
            .client-list {
                background: white;
                border-radius: 10px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .client-item {
                padding: 15px;
                margin: 10px 0;
                background: #f8f9fa;
                border-radius: 5px;
                border-left: 4px solid #667eea;
            }
            .client-name {
                font-weight: bold;
                color: #333;
            }
            .client-url {
                color: #666;
                font-size: 0.9em;
            }
            .sync-button {
                background: #28a745;
                color: white;
                border: none;
                padding: 15px 40px;
                font-size: 1.2em;
                border-radius: 50px;
                cursor: pointer;
                transition: all 0.3s;
                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            }
            .sync-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            }
            .actions {
                text-align: center;
                margin: 30px 0;
            }
            .note {
                background: #e7f3ff;
                border: 1px solid #b3d9ff;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🔄 Sincronização de Estoque</h1>
            <p>Sistema de sincronização diária de estoque dos clientes</p>
        </div>

        <div class="note">
            <strong>💡 Dica:</strong> Você pode executar a sincronização automaticamente via URL:
            <br>
            <code>https://atendecar.net/sistema/cron_sincronizar_estoque.php?cron=true</code>
        </div>

        <div class="client-list">
            <h2>Clientes para Sincronizar</h2>
            <?php
            $sincronizador = new SincronizadorEstoque();
            $clientes = $sincronizador->buscarClientesParaSincronizar();
            
            if (empty($clientes)) {
                echo '<p>Nenhum cliente encontrado para sincronização.</p>';
            } else {
                echo '<p><strong>Total de clientes: ' . count($clientes) . '</strong></p>';
                
                foreach ($clientes as $cliente) {
                    echo '<div class="client-item">';
                    echo '<div class="client-name">' . htmlspecialchars($cliente['nome_loja']) . '</div>';
                    echo '<div class="client-url">' . htmlspecialchars($cliente['url_estoque']) . '</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <div class="actions">
            <button class="sync-button" onclick="window.location.href='?cron=true'">
                🚀 Executar Sincronização Agora
            </button>
        </div>
    </body>
    </html>
    <?php
}
?>
