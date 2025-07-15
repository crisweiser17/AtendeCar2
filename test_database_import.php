<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Importação no Banco - EMJ Motors</title>
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
        .preco {
            color: #28a745;
            font-weight: bold;
        }
        .preco.zero {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Teste de Importação no Banco de Dados - EMJ Motors</h1>

<?php
require_once 'importador_estoque.php';

try {
    // Criar uma instância do importador
    $importador = new ImportadorEstoque();
    
    // URL de teste
    $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    // Cliente de teste (vamos usar ID 1 se existir, ou criar um temporário)
    $clienteIdTeste = 1;
    
    echo '<div class="info">📡 Testando importação completa da URL: <strong>' . htmlspecialchars($url) . '</strong></div>';
    echo '<div class="info">👤 Cliente ID de teste: <strong>' . $clienteIdTeste . '</strong></div>';
    
    // Verificar se o cliente existe
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, nome_loja FROM clientes WHERE id = ?");
    $stmt->execute([$clienteIdTeste]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        echo '<div class="warning">⚠️ Cliente ID ' . $clienteIdTeste . ' não encontrado. Criando cliente de teste...</div>';
        
        // Criar cliente de teste
        $stmt = $pdo->prepare("
            INSERT INTO clientes (nome_responsavel, email, celular, nome_loja, url_estoque, status)
            VALUES (?, ?, ?, ?, ?, 'ativo')
        ");
        $stmt->execute([
            'Teste EMJ',
            'teste@emjmotors.com',
            '(19) 99999-9999',
            'EMJ Motors - Teste',
            $url
        ]);
        $clienteIdTeste = $pdo->lastInsertId();
        echo '<div class="success">✅ Cliente de teste criado com ID: ' . $clienteIdTeste . '</div>';
    } else {
        echo '<div class="success">✅ Cliente encontrado: ' . htmlspecialchars($cliente['nome_loja']) . '</div>';
    }
    
    // Contar veículos antes da importação
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM veiculos WHERE cliente_id = ? AND ativo = TRUE");
    $stmt->execute([$clienteIdTeste]);
    $veiculosAntes = $stmt->fetchColumn();
    
    echo '<div class="info">📊 Veículos ativos antes da importação: <strong>' . $veiculosAntes . '</strong></div>';
    
    // Executar importação
    echo '<h2>🔄 Executando Importação...</h2>';
    $resultado = $importador->importarEstoque($clienteIdTeste, $url);
    
    if ($resultado['sucesso']) {
        echo '<div class="success">✅ ' . $resultado['mensagem'] . '</div>';
        echo '<div class="info">📈 Total encontrados: <strong>' . $resultado['total_encontrados'] . '</strong></div>';
        echo '<div class="info">💾 Total inseridos/atualizados: <strong>' . $resultado['total_inseridos'] . '</strong></div>';
        
        if (isset($resultado['queue_id'])) {
            echo '<div class="info">🔗 Queue ID: <strong>' . $resultado['queue_id'] . '</strong></div>';
        }
        
        // Contar veículos após a importação
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM veiculos WHERE cliente_id = ? AND ativo = TRUE");
        $stmt->execute([$clienteIdTeste]);
        $veiculosDepois = $stmt->fetchColumn();
        
        echo '<div class="info">📊 Veículos ativos após a importação: <strong>' . $veiculosDepois . '</strong></div>';
        
        // Mostrar alguns veículos importados
        echo '<h2>🚗 Veículos Importados (Últimos 10)</h2>';
        $stmt = $pdo->prepare("
            SELECT versao, marca_modelo, preco, ano, link, created_at
            FROM veiculos
            WHERE cliente_id = ? AND ativo = TRUE
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$clienteIdTeste]);
        $veiculos = $stmt->fetchAll();
        
        if ($veiculos) {
            echo '<table>';
            echo '<thead>';
            echo '<tr><th>Versão</th><th>Marca/Modelo</th><th>Preço</th><th>Ano</th><th>Importado em</th><th>Link</th></tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($veiculos as $veiculo) {
                $precoClass = !empty($veiculo['preco']) ? 'preco' : 'preco zero';
                $precoTexto = !empty($veiculo['preco']) ? $veiculo['preco'] : 'N/D';
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($veiculo['versao']) . '</td>';
                echo '<td>' . htmlspecialchars($veiculo['marca_modelo'] ?: 'N/A') . '</td>';
                echo '<td class="' . $precoClass . '">' . htmlspecialchars($precoTexto) . '</td>';
                echo '<td>' . ($veiculo['ano'] ?: 'N/A') . '</td>';
                echo '<td>' . date('d/m/Y H:i', strtotime($veiculo['created_at'])) . '</td>';
                echo '<td><a href="' . htmlspecialchars($veiculo['link']) . '" target="_blank">Ver</a></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        // Estatísticas do estoque
        echo '<h2>📊 Estatísticas do Estoque</h2>';
        $stats = $importador->estatisticasEstoque($clienteIdTeste);
        
        if ($stats) {
            echo '<div class="info">';
            echo '<strong>Total de veículos:</strong> ' . $stats['total'] . '<br>';
            echo '<strong>Veículos ativos:</strong> ' . $stats['ativos'] . '<br>';
            echo '<strong>Preço médio:</strong> R$ ' . number_format($stats['preco_medio'] ?: 0, 2, ',', '.') . '<br>';
            echo '<strong>Preço mínimo:</strong> R$ ' . number_format($stats['preco_min'] ?: 0, 2, ',', '.') . '<br>';
            echo '<strong>Preço máximo:</strong> R$ ' . number_format($stats['preco_max'] ?: 0, 2, ',', '.') . '<br>';
            echo '<strong>Última atualização:</strong> ' . ($stats['ultima_atualizacao'] ? date('d/m/Y H:i', strtotime($stats['ultima_atualizacao'])) : 'N/A');
            echo '</div>';
        }
        
        // Sistema de fila desabilitado temporariamente para debugging
        echo '<h2>⏳ Sistema de Fila</h2>';
        echo '<div class="info">ℹ️ Sistema de fila temporariamente desabilitado para facilitar o debugging. A importação é executada diretamente.</div>';
        
    } else {
        echo '<div class="error">❌ Erro na importação: ' . htmlspecialchars($resultado['erro']) . '</div>';
        
        if (isset($resultado['queue_id'])) {
            echo '<div class="info">🔗 Queue ID (falhou): <strong>' . $resultado['queue_id'] . '</strong></div>';
        }
    }
    
} catch (Exception $e) {
    echo '<div class="error">💥 ERRO: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="error">📍 Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine() . '</div>';
}
?>

        <div class="info">🏁 Teste finalizado.</div>
    </div>
</body>
</html>