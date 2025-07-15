<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste do Sistema de Fila e Cron - EMJ Motors</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }
        .success {
            background: #e8f5e8;
            color: #2e7d32;
            border-left-color: #4caf50;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            border-left-color: #f44336;
        }
        .warning {
            background: #fff3e0;
            color: #ef6c00;
            border-left-color: #ff9800;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Teste do Sistema de Fila e Cron Job - EMJ Motors</h1>

<?php
require_once 'config/database.php';

try {
    $pdo = getConnection();
    
    echo '<div class="info">🔍 Verificando estrutura do sistema de fila...</div>';
    
    // Verificar se a tabela sync_queue existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'sync_queue'");
    if ($stmt->rowCount() > 0) {
        echo '<div class="success">✅ Tabela sync_queue encontrada</div>';
        
        // Mostrar estrutura da tabela
        $stmt = $pdo->query("DESCRIBE sync_queue");
        $columns = $stmt->fetchAll();
        
        echo '<h2>📋 Estrutura da Tabela sync_queue</h2>';
        echo '<table>';
        echo '<thead><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr></thead>';
        echo '<tbody>';
        foreach ($columns as $col) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($col['Field']) . '</td>';
            echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
            echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
            echo '<td>' . htmlspecialchars($col['Key']) . '</td>';
            echo '<td>' . htmlspecialchars($col['Default']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
    } else {
        echo '<div class="error">❌ Tabela sync_queue não encontrada</div>';
    }
    
    // Verificar clientes ativos
    echo '<h2>👥 Clientes Ativos para Sincronização</h2>';
    $stmt = $pdo->prepare("
        SELECT id, nome_loja, url_estoque, ultima_sincronizacao, status
        FROM clientes 
        WHERE status = 'ativo' 
        AND url_estoque IS NOT NULL 
        AND url_estoque != '' 
        AND url_estoque != 'https://'
        ORDER BY ultima_sincronizacao ASC NULLS FIRST
    ");
    $stmt->execute();
    $clientes = $stmt->fetchAll();
    
    if ($clientes) {
        echo '<div class="success">✅ Encontrados ' . count($clientes) . ' clientes ativos</div>';
        
        echo '<table>';
        echo '<thead><tr><th>ID</th><th>Nome da Loja</th><th>URL Estoque</th><th>Última Sincronização</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        foreach ($clientes as $cliente) {
            echo '<tr>';
            echo '<td>' . $cliente['id'] . '</td>';
            echo '<td>' . htmlspecialchars($cliente['nome_loja']) . '</td>';
            echo '<td><a href="' . htmlspecialchars($cliente['url_estoque']) . '" target="_blank">Ver</a></td>';
            echo '<td>' . ($cliente['ultima_sincronizacao'] ? date('d/m/Y H:i', strtotime($cliente['ultima_sincronizacao'])) : 'Nunca') . '</td>';
            echo '<td>' . htmlspecialchars($cliente['status']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
    } else {
        echo '<div class="warning">⚠️ Nenhum cliente ativo encontrado para sincronização</div>';
    }
    
    // Verificar itens na fila
    echo '<h2>📋 Itens na Fila de Sincronização</h2>';
    $stmt = $pdo->prepare("
        SELECT sq.*, c.nome_loja 
        FROM sync_queue sq
        LEFT JOIN clientes c ON sq.cliente_id = c.id
        ORDER BY sq.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $filaItems = $stmt->fetchAll();
    
    if ($filaItems) {
        echo '<div class="info">📊 Últimos 20 itens da fila:</div>';
        
        echo '<table>';
        echo '<thead><tr><th>ID</th><th>Cliente</th><th>Status</th><th>Tentativas</th><th>Criado em</th><th>Iniciado em</th><th>Concluído em</th><th>Erro</th></tr></thead>';
        echo '<tbody>';
        foreach ($filaItems as $item) {
            $statusClass = '';
            switch ($item['status']) {
                case 'completed': $statusClass = 'success'; break;
                case 'failed': $statusClass = 'error'; break;
                case 'processing': $statusClass = 'warning'; break;
                default: $statusClass = 'info';
            }
            
            echo '<tr>';
            echo '<td>' . $item['id'] . '</td>';
            echo '<td>' . htmlspecialchars($item['nome_loja'] ?: 'Cliente não encontrado') . '</td>';
            echo '<td><span class="' . $statusClass . '">' . htmlspecialchars($item['status']) . '</span></td>';
            echo '<td>' . $item['attempts'] . '/' . $item['max_attempts'] . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($item['created_at'])) . '</td>';
            echo '<td>' . ($item['started_at'] ? date('d/m/Y H:i', strtotime($item['started_at'])) : '-') . '</td>';
            echo '<td>' . ($item['completed_at'] ? date('d/m/Y H:i', strtotime($item['completed_at'])) : '-') . '</td>';
            echo '<td>' . htmlspecialchars($item['error_message'] ?: '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
    } else {
        echo '<div class="info">ℹ️ Nenhum item na fila de sincronização</div>';
    }
    
    // Estatísticas da fila
    echo '<h2>📊 Estatísticas da Fila (Últimas 24h)</h2>';
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendentes,
            COUNT(CASE WHEN status = 'processing' THEN 1 END) as processando,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as concluidos,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as falhados
        FROM sync_queue
        WHERE created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    if ($stats) {
        echo '<div class="info">';
        echo '<strong>Total:</strong> ' . $stats['total'] . '<br>';
        echo '<strong>Pendentes:</strong> ' . $stats['pendentes'] . '<br>';
        echo '<strong>Processando:</strong> ' . $stats['processando'] . '<br>';
        echo '<strong>Concluídos:</strong> ' . $stats['concluidos'] . '<br>';
        echo '<strong>Falhados:</strong> ' . $stats['falhados'];
        echo '</div>';
    }
    
    // Botões de teste
    echo '<h2>🧪 Testes</h2>';
    echo '<a href="cron_sincronizar_estoque.php?exec=sync" class="btn" target="_blank">🔄 Executar Sincronização Manual</a>';
    echo '<a href="test_database_import.php" class="btn" target="_blank">🗄️ Teste de Importação Direta</a>';
    
} catch (Exception $e) {
    echo '<div class="error">💥 ERRO: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

        <div class="info">🏁 Verificação concluída.</div>
    </div>
</body>
</html>