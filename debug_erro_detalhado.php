<?php
require_once 'importador_estoque.php';

echo "🔍 DEBUG DETALHADO DO ERRO\n";
echo "==========================\n\n";

try {
    $importador = new ImportadorEstoque();
    
    // Parâmetros para teste
    $clienteId = 1;
    $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    echo "📡 Testando importação...\n";
    echo "🔗 URL: $url\n";
    echo "👤 Cliente ID: $clienteId\n\n";
    
    $resultado = $importador->importarEstoque($clienteId, $url);
    
    echo "📊 RESULTADO COMPLETO:\n";
    echo "=====================\n";
    print_r($resultado);
    
    if (!$resultado['sucesso']) {
        echo "\n❌ ERRO DETECTADO:\n";
        echo "Mensagem: " . ($resultado['erro'] ?? 'Erro não especificado') . "\n";
        
        // Vamos tentar acessar a URL diretamente para ver se há problema de conectividade
        echo "\n🌐 TESTANDO CONECTIVIDADE:\n";
        echo "=========================\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "HTTP Code: $httpCode\n";
        echo "cURL Error: " . ($error ?: 'Nenhum') . "\n";
        echo "HTML Length: " . strlen($html) . " bytes\n";
        
        if ($httpCode === 200 && $html) {
            echo "✅ Conectividade OK - HTML recebido\n";
            
            // Vamos verificar se há veículos no HTML
            echo "\n🔍 VERIFICANDO CONTEÚDO HTML:\n";
            echo "============================\n";
            
            // Contar links /comprar/
            $countLinks = preg_match_all('/href="(\/comprar\/[^"]+)"/i', $html, $matches);
            echo "Links /comprar/ encontrados: $countLinks\n";
            
            if ($countLinks > 0) {
                echo "Primeiros 3 links:\n";
                for ($i = 0; $i < min(3, count($matches[1])); $i++) {
                    echo "  " . ($i + 1) . ". " . $matches[1][$i] . "\n";
                }
            }
            
            // Contar imagens com ALT contendo marcas
            $countImgs = preg_match_all('/<img[^>]*alt="([^"]*(?:FIAT|HONDA|CHEVROLET|FORD|VOLKSWAGEN|HYUNDAI|TOYOTA|NISSAN|RENAULT|CHERY)[^"]*)"[^>]*>/i', $html, $imgMatches);
            echo "Imagens com marcas no ALT: $countImgs\n";
            
            if ($countImgs > 0) {
                echo "Primeiras 3 imagens:\n";
                for ($i = 0; $i < min(3, count($imgMatches[1])); $i++) {
                    echo "  " . ($i + 1) . ". " . $imgMatches[1][$i] . "\n";
                }
            }
        } else {
            echo "❌ Problema de conectividade\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n💥 EXCEÇÃO CAPTURADA:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Debug finalizado.\n";
?>