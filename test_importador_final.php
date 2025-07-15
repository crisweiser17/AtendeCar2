<?php
require_once 'importador_estoque.php';

echo "🔧 TESTANDO IMPORTADOR CORRIGIDO\n";
echo "================================\n\n";

try {
    $importador = new ImportadorEstoque();
    
    // Parâmetros para teste
    $clienteId = 1; // ID do cliente EMJ Motors
    $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    echo "📡 Iniciando importação...\n";
    echo "🔗 URL: $url\n";
    echo "👤 Cliente ID: $clienteId\n\n";
    
    $resultado = $importador->importarEstoque($clienteId, $url);
    
    echo "\n📊 RESULTADO DA IMPORTAÇÃO:\n";
    echo "==========================\n";
    echo "✅ Status: " . ($resultado['sucesso'] ? 'SUCESSO' : 'ERRO') . "\n";
    echo "📈 Total encontrados: " . $resultado['total_encontrados'] . "\n";
    echo "💾 Total inseridos: " . $resultado['total_inseridos'] . "\n";
    echo "📝 Mensagem: " . $resultado['mensagem'] . "\n";
    
    if ($resultado['total_inseridos'] > 0) {
        echo "\n🎉 PROBLEMA RESOLVIDO! Veículos estão sendo inseridos corretamente!\n";
        
        // Mostrar alguns exemplos dos veículos inseridos
        echo "\n🚗 EXEMPLOS DE VEÍCULOS IMPORTADOS:\n";
        echo "==================================\n";
        
        // Conectar ao banco para mostrar alguns exemplos
        require_once 'config/database.php';
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        
        $stmt = $pdo->query("SELECT nome, marca, modelo, preco, ano FROM veiculos ORDER BY id DESC LIMIT 5");
        $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($veiculos as $i => $veiculo) {
            echo ($i + 1) . ". " . $veiculo['nome'] . "\n";
            echo "   Marca: " . $veiculo['marca'] . "\n";
            echo "   Modelo: " . $veiculo['modelo'] . "\n";
            echo "   Preço: R$ " . number_format($veiculo['preco'], 2, ',', '.') . "\n";
            echo "   Ano: " . $veiculo['ano'] . "\n\n";
        }
    } else {
        echo "\n❌ AINDA HÁ PROBLEMAS! Nenhum veículo foi inserido.\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERRO DURANTE O TESTE:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}

echo "\n🏁 Teste finalizado.\n";
?>