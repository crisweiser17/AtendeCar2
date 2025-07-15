<?php
// Teste para ver o conteúdo do contexto
echo "=== TESTE CONTEÚDO DO CONTEXTO ===\n\n";

class ImportadorContextoDebug {
    
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
        // Buscar primeiro link
        $padraoLinkAbsoluto = '/href="(https:\/\/carrosp\.com\.br\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLinkAbsoluto, $html, $matchesAbsolutos);
        
        if (!empty($matchesAbsolutos[1])) {
            $linkCompleto = $matchesAbsolutos[1][0]; // Primeiro link
            echo "Link analisado: $linkCompleto\n\n";
            
            $linkBase = basename($linkCompleto);
            echo "Link base: $linkBase\n\n";
            
            $contexto = $this->buscarContextoDoLink($html, $linkBase);
            
            if ($contexto) {
                echo "=== CONTEXTO ENCONTRADO ===\n";
                echo "Tamanho: " . strlen($contexto) . " caracteres\n\n";
                echo "Conteúdo:\n";
                echo $contexto . "\n\n";
                
                echo "=== BUSCANDO PADRÕES ===\n";
                
                // Testar padrão info-geral
                if (preg_match('/<p[^>]*class="[^"]*info-geral[^"]*"[^>]*>([^<]+)<\/p>/i', $contexto, $infoMatch)) {
                    echo "✅ Padrão info-geral encontrado: " . $infoMatch[1] . "\n";
                } else {
                    echo "❌ Padrão info-geral NÃO encontrado\n";
                }
                
                // Testar padrão KM genérico
                if (preg_match('/[^|]+\|\s*[^|]+\|\s*([\d.,]+)\s*KM/i', $contexto, $kmMatch)) {
                    echo "✅ Padrão KM genérico encontrado: " . $kmMatch[1] . "\n";
                } else {
                    echo "❌ Padrão KM genérico NÃO encontrado\n";
                }
                
                // Buscar qualquer coisa que pareça com KM
                if (preg_match_all('/(\d+(?:\.\d+)*)\s*km/i', $contexto, $allKm)) {
                    echo "✅ Padrões de KM encontrados: " . implode(', ', $allKm[1]) . "\n";
                } else {
                    echo "❌ Nenhum padrão de KM encontrado\n";
                }
                
                // Buscar por pipe separators
                if (preg_match_all('/[^|]*\|[^|]*\|[^|]*/i', $contexto, $pipes)) {
                    echo "✅ Padrões com | encontrados:\n";
                    foreach ($pipes[0] as $pipe) {
                        echo "  - " . trim($pipe) . "\n";
                    }
                } else {
                    echo "❌ Nenhum padrão com | encontrado\n";
                }
                
            } else {
                echo "❌ Contexto vazio\n";
            }
        }
    }
    
    private function buscarContextoDoLink($html, $linkBase) {
        // Estratégia 1: Buscar por div que contenha o link específico
        $padraoDiv = '/<div[^>]*>(?:[^<]|<(?!\/div>))*?' . preg_quote($linkBase, '/') . '(?:[^<]|<(?!\/div>))*?<\/div>/is';
        if (preg_match($padraoDiv, $html, $matches)) {
            return $matches[0];
        }
        
        // Estratégia 2: Buscar por contexto mais amplo ao redor do link
        $pos = strpos($html, $linkBase);
        if ($pos !== false) {
            $inicioDiv = strrpos(substr($html, 0, $pos), '<div');
            if ($inicioDiv !== false) {
                $inicio = $inicioDiv;
            } else {
                $inicio = max(0, $pos - 1500);
            }
            
            $fimDiv = strpos($html, '</div>', $pos);
            if ($fimDiv !== false) {
                $fim = $fimDiv + 6;
            } else {
                $fim = $pos + 1500;
            }
            
            $contexto = substr($html, $inicio, $fim - $inicio);
            return $contexto;
        }
        
        return '';
    }
}

$url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';

try {
    $importador = new ImportadorContextoDebug();
    $importador->extrairVeiculos($url);
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== TESTE CONCLUÍDO ===\n";
?>