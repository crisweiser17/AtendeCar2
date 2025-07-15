<?php
require_once 'importador_estoque_corrigido.php';

echo "🚀 TESTANDO NOVA ESTRATÉGIA DE IMPORTAÇÃO\n";
echo "=========================================\n\n";

try {
    $importador = new ImportadorEstoque();
    
    // Parâmetros para teste
    $clienteId = 1;
    $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    echo "📡 Iniciando importação com nova estratégia...\n";
    echo "🔗 URL: $url\n";
    echo "👤 Cliente ID: $clienteId\n\n";
    
    $resultado = $importador->importarEstoque($clienteId, $url);
    
    echo "📊 RESULTADO DA IMPORTAÇÃO:\n";
    echo "==========================\n";
    echo "✅ Status: " . ($resultado['sucesso'] ? 'SUCESSO' : 'ERRO') . "\n";
    
    if ($resultado['sucesso']) {
        echo "📈 Total encontrados: " . $resultado['total_encontrados'] . "\n";
        echo "💾 Total inseridos: " . $resultado['total_inseridos'] . "\n";
        echo "📝 Mensagem: " . $resultado['mensagem'] . "\n";
        
        if ($resultado['total_inseridos'] > 0) {
            echo "\n🎉 SUCESSO! Nova estratégia funcionou!\n";
            
            // Mostrar alguns exemplos dos veículos inseridos
            echo "\n🚗 EXEMPLOS DE VEÍCULOS IMPORTADOS:\n";
            echo "==================================\n";
            
            // Conectar ao banco para mostrar alguns exemplos
            require_once 'config/database.php';
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            
            $stmt = $pdo->query("SELECT nome, marca, modelo, preco, ano FROM veiculos WHERE cliente_id = $clienteId AND ativo = TRUE ORDER BY id DESC LIMIT 5");
            $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($veiculos as $i => $veiculo) {
                echo ($i + 1) . ". " . $veiculo['nome'] . "\n";
                echo "   Marca: " . $veiculo['marca'] . "\n";
                echo "   Modelo: " . $veiculo['modelo'] . "\n";
                echo "   Preço: R$ " . number_format($veiculo['preco'], 2, ',', '.') . "\n";
                echo "   Ano: " . ($veiculo['ano'] ?: 'N/A') . "\n\n";
            }
        } else {
            echo "\n⚠️ Veículos encontrados mas não inseridos. Verificar validação.\n";
        }
    } else {
        echo "❌ Erro: " . ($resultado['erro'] ?? 'Erro não especificado') . "\n";
    }
    
} catch (Exception $e) {
    echo "\n💥 EXCEÇÃO CAPTURADA:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}

echo "\n🏁 Teste finalizado.\n";
?>