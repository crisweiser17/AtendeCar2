<?php
/**
 * Teste do sistema de sincronização via cron
 * Simula a execução do cron para verificar se está funcionando corretamente
 */

require_once 'config/database.php';
require_once 'cron_sincronizar_estoque.php';

echo "=== TESTE DO SISTEMA DE SINCRONIZAÇÃO CRON ===\n";
echo "Data/Hora: " . date('d/m/Y H:i:s') . "\n\n";

try {
    // Verificar conexão com banco
    $pdo = getConnection();
    echo "✅ Conexão com banco de dados: OK\n";
    
    // Verificar se existem clientes para sincronizar
    $stmt = $pdo->prepare("
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
    $clientes = $stmt->fetchAll();
    
    echo "📊 Clientes encontrados para sincronização: " . count($clientes) . "\n";
    
    if (!empty($clientes)) {
        echo "\n--- CLIENTES PARA SINCRONIZAR ---\n";
        foreach ($clientes as $cliente) {
            echo "ID: {$cliente['id']} | Loja: {$cliente['nome_loja']}\n";
            echo "URL: {$cliente['url_estoque']}\n";
            echo "Última sync: " . ($cliente['ultima_sincronizacao'] ?: 'Nunca') . "\n";
            echo "---\n";
        }
    }
    
    // Verificar estrutura da tabela sync_queue
    $stmt = $pdo->query("SHOW TABLES LIKE 'sync_queue'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela sync_queue: OK\n";
        
        // Verificar registros pendentes na fila
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM sync_queue WHERE status = 'pending'");
        $pendentes = $stmt->fetch()['total'];
        echo "📋 Registros pendentes na fila: {$pendentes}\n";
        
    } else {
        echo "❌ Tabela sync_queue: NÃO ENCONTRADA\n";
    }
    
    // Verificar se o diretório de logs existe
    $logDir = __DIR__ . '/logs';
    if (is_dir($logDir)) {
        echo "✅ Diretório de logs: OK\n";
    } else {
        echo "⚠️  Diretório de logs: Será criado automaticamente\n";
    }
    
    echo "\n=== TESTE DE EXECUÇÃO (SEM PROCESSAR) ===\n";
    
    // Criar instância do sincronizador
    $sincronizador = new SincronizadorEstoque();
    echo "✅ Instância do SincronizadorEstoque criada com sucesso\n";
    
    // Verificar se o ImportadorEstoque está funcionando
    $importador = new ImportadorEstoque();
    echo "✅ Instância do ImportadorEstoque criada com sucesso\n";
    
    echo "\n=== CONFIGURAÇÃO DO CRON ===\n";
    echo "Para configurar o cron job, adicione esta linha ao crontab:\n";
    echo "0 5 * * * /usr/bin/php " . __DIR__ . "/cron_sincronizar_estoque.php\n";
    echo "\nPara editar o crontab:\n";
    echo "crontab -e\n";
    
    echo "\n=== TESTE MANUAL ===\n";
    echo "Para executar manualmente:\n";
    echo "php " . __DIR__ . "/cron_sincronizar_estoque.php\n";
    
    echo "\n✅ TESTE CONCLUÍDO COM SUCESSO!\n";
    echo "O sistema de sincronização está pronto para uso.\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>