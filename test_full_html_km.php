<?php
// Teste para buscar KM em todo o HTML
echo "=== TESTE BUSCA KM EM TODO HTML ===\n\n";

class ImportadorFullHtmlDebug {
    
    public function extrairVeiculos($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$html) {
            throw new Exception('Erro ao acessar a página: HTTP ' . $httpCode);
        }
        
        return $this->parseHTML($html, $url);
    }
    
    private function parseHTML($html, $baseUrl) {
        echo "Tamanho total do HTML: " . strlen($html) . " caracteres\n\n";
        
        // Buscar primeiro link
        $padraoLinkAbsoluto = '/href="(https:\/\/carrosp\.com\.br\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLinkAbsoluto, $html, $matchesAbsolutos);
        
        if (!empty($matchesAbsolutos[1])) {
            $linkCompleto = $matchesAbsolutos[1][0]; // Primeiro link
            echo "Link analisado: $linkCompleto\n";
            
            $linkBase = basename($linkCompleto);
            echo "Link base: $linkBase\n\n";
            
            echo "=== BUSCANDO PADRÕES DE KM EM TODO HTML ===\n";
            
            // 1. Buscar padrão específico CarrosP: "Aut. | Flex | 132.326 KM"
            if (preg_match_all('/([A-Za-z]+\.?)\s*\|\s*([A-Za-z]+)\s*\|\s*([\d.,]+)\s*KM/i', $html, $matches)) {
                echo "✅ Padrão CarrosP encontrado (" . count($matches[0]) . " ocorrências):\n";
                for ($i = 0; $i < min(5, count($matches[0])); $i++) {
                    echo "  - " . $matches[0][$i] . "\n";
                }
            } else {
                echo "❌ Padrão CarrosP NÃO encontrado\n";
            }
            
            // 2. Buscar qualquer padrão com KM
            if (preg_match_all('/(\d+(?:\.\d+)*)\s*km/i', $html, $kmMatches)) {
                echo "✅ Padrões genéricos de KM encontrados (" . count($kmMatches[0]) . " ocorrências):\n";
                for ($i = 0; $i < min(10, count($kmMatches[0])); $i++) {
                    echo "  - " . $kmMatches[0][$i] . "\n";
                }
            } else {
                echo "❌ Nenhum padrão genérico de KM encontrado\n";
            }
            
            // 3. Buscar por info-geral em todo HTML
            if (preg_match_all('/<p[^>]*class="[^"]*info-geral[^"]*"[^>]*>([^<]+)<\/p>/i', $html, $infoMatches)) {
                echo "✅ Padrões info-geral encontrados (" . count($infoMatches[0]) . " ocorrências):\n";
                for ($i = 0; $i < min(10, count($infoMatches[0])); $i++) {
                    echo "  - " . $infoMatches[1][$i] . "\n";
                }
            } else {
                echo "❌ Nenhum padrão info-geral encontrado\n";
            }
            
            // 4. Buscar por qualquer coisa com pipe separators
            if (preg_match_all('/[^|<>]{3,}\|[^|<>]{3,}\|[^|<>]{3,}/i', $html, $pipeMatches)) {
                echo "✅ Padrões com | encontrados (" . count($pipeMatches[0]) . " ocorrências):\n";
                for ($i = 0; $i < min(10, count($pipeMatches[0])); $i++) {
                    echo "  - " . trim($pipeMatches[0][$i]) . "\n";
                }
            } else {
                echo "❌ Nenhum padrão com | encontrado\n";
            }
            
            // 5. Buscar especificamente pelo ID do veículo
            echo "\n=== BUSCANDO CONTEXTO ESPECÍFICO DO VEÍCULO $linkBase ===\n";
            
            // Buscar por qualquer ocorrência do ID do veículo
            $posicoes = [];
            $offset = 0;
            while (($pos = strpos($html, $linkBase, $offset)) !== false) {
                $posicoes[] = $pos;
                $offset = $pos + 1;
            }
            
            echo "ID $linkBase encontrado em " . count($posicoes) . " posições\n";
            
            foreach ($posicoes as $index => $pos) {
                echo "\n--- Contexto " . ($index + 1) . " (posição $pos) ---\n";
                $inicio = max(0, $pos - 200);
                $contexto = substr($html, $inicio, 400);
                echo $contexto . "\n";
                
                // Testar padrões neste contexto
                if (preg_match('/([A-Za-z]+\.?)\s*\|\s*([A-Za-z]+)\s*\|\s*([\d.,]+)\s*KM/i', $contexto, $match)) {
                    echo "✅ KM encontrada neste contexto: " . $match[3] . "\n";
                }
            }
        }
    }
}

$url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';

try {
    $importador = new ImportadorFullHtmlDebug();
    $importador->extrairVeiculos($url);
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== TESTE CONCLUÍDO ===\n";
?>