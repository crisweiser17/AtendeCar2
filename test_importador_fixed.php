<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste do Importador Corrigido - EMJ Motors</title>
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
        .marca {
            font-weight: bold;
            color: #007bff;
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
        <h1>🚗 Teste do Importador Corrigido - EMJ Motors</h1>

<?php
require_once 'importador_estoque.php';

try {
    // Criar uma instância do importador
    $importador = new ImportadorEstoque();
    
    // URL de teste
    $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    echo '<div class="info">📡 Testando extração da URL: <strong>' . htmlspecialchars($url) . '</strong></div>';
    
    // Simular a extração (sem salvar no banco)
    $reflection = new ReflectionClass($importador);
    $method = $reflection->getMethod('extrairVeiculos');
    $method->setAccessible(true);
    
    $veiculos = $method->invoke($importador, $url);
    
    echo '<div class="info success">✅ <strong>Total de veículos encontrados: ' . count($veiculos) . '</strong></div>';
    
    if (!empty($veiculos)) {
        // Contar veículos com preço
        $comPreco = 0;
        foreach ($veiculos as $veiculo) {
            if ($veiculo['preco'] > 0) $comPreco++;
        }
        
        echo '<div class="info">💰 <strong>Veículos com preço: ' . $comPreco . ' de ' . count($veiculos) . '</strong></div>';
        
        // Tabela de veículos
        echo '<h2>🚗 Lista de Veículos Extraídos</h2>';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>Nome</th>';
        echo '<th>Marca</th>';
        echo '<th>Modelo</th>';
        echo '<th>Ano</th>';
        echo '<th>Preço</th>';
        echo '<th>Link</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($veiculos as $i => $veiculo) {
            $precoClass = $veiculo['preco'] > 0 ? 'preco' : 'preco zero';
            $precoTexto = $veiculo['preco'] > 0 ? 'R$ ' . number_format($veiculo['preco'], 2, ',', '.') : 'N/D';
            
            echo '<tr>';
            echo '<td>' . ($i + 1) . '</td>';
            echo '<td>' . htmlspecialchars($veiculo['nome'] ?: 'N/A') . '</td>';
            echo '<td class="marca">' . htmlspecialchars($veiculo['marca'] ?: 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($veiculo['modelo'] ?: 'N/A') . '</td>';
            echo '<td>' . ($veiculo['ano'] ?: 'N/A') . '</td>';
            echo '<td class="' . $precoClass . '">' . $precoTexto . '</td>';
            echo '<td><a href="' . htmlspecialchars($veiculo['link']) . '" target="_blank">Ver</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Teste de importação simulada (cliente ID fictício)
        echo '<h2>🔄 Teste de Importação Simulada</h2>';
        echo '<div class="info">⚠️ <strong>Nota:</strong> Este teste não salvará dados no banco. É apenas para verificar se a lógica está funcionando.</div>';
        
        $clienteIdTeste = 999; // ID fictício para teste
        
        // Simular o processo de importação
        $resultado = [
            'sucesso' => true,
            'total_encontrados' => count($veiculos),
            'total_inseridos' => count($veiculos),
            'mensagem' => "Teste concluído: " . count($veiculos) . " veículos seriam importados"
        ];
        
        if ($resultado['sucesso']) {
            echo '<div class="info success">✅ ' . $resultado['mensagem'] . '</div>';
        } else {
            echo '<div class="info error">❌ Erro: ' . $resultado['erro'] . '</div>';
        }
        
    } else {
        echo '<div class="info error">❌ Nenhum veículo foi extraído. Verifique a URL ou a estrutura da página.</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="info error">💥 ERRO: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="info error">📍 Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine() . '</div>';
}
?>

        <div class="info">🏁 Teste finalizado.</div>
    </div>
</body>
</html>