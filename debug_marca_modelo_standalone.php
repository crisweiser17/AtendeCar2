<?php
/**
 * Debug Standalone - Marca/Modelo CarrosP
 * Sem dependência de banco de dados
 */

class DebugMarcaModelelo {
    private $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    public function executar() {
        echo "=== DEBUG: Extração Marca/Modelo - CarrosP ===\n";
        echo "URL: {$this->url}\n";
        echo "Data: " . date('d/m/Y H:i:s') . "\n\n";
        
        // Obter HTML
        $html = $this->obterHTML();
        if (!$html) {
            echo "❌ Erro ao obter HTML - usando dados simulados\n";
            $this->testarComDadosSimulados();
            return;
        }
        
        echo "✓ HTML obtido (" . strlen($html) . " bytes)\n\n";
        
        // Salvar para análise
        file_put_contents('debug_carrosp.html', $html);
        echo "✓ HTML salvo em: debug_carrosp.html\n\n";
        
        // Analisar estrutura
        $this->analisarEstrutura($html);
        
        // Testar estratégias
        $this->testarEstrategias($html);
    }
    
    private function obterHTML() {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200) ? $content : false;
    }
    
    private function analisarEstrutura($html) {
        echo "--- ANÁLISE DA ESTRUTURA ---\n";
        
        // 1. Links de veículos
        preg_match_all('/href="(\/comprar\/[^"]+)"/i', $html, $links);
        echo "✓ Links encontrados: " . count($links[1]) . "\n";
        
        if (count($links[1]) > 0) {
            echo "Exemplos:\n";
            foreach (array_slice($links[1], 0, 3) as $link) {
                echo "  - $link\n";
                $this->analisarLink($link);
            }
        }
        
        // 2. Buscar marcas
        $marcas = ['FIAT', 'HONDA', 'CHEVROLET', 'FORD', 'VOLKSWAGEN', 'HYUNDAI'];
        foreach ($marcas as $marca) {
            $count = substr_count(strtoupper($html), $marca);
            if ($count > 0) {
                echo "✓ '$marca' encontrada $count vezes\n";
            }
        }
        
        echo "\n";
    }
    
    private function analisarLink($link) {
        // Extrair estrutura do link
        $partes = explode('/', trim($link, '/'));
        if (count($partes) >= 4) {
            echo "    Tipo: " . ($partes[1] ?? 'N/A') . "\n";
            echo "    Marca: " . ucfirst($partes[2] ?? 'N/A') . "\n";
            echo "    Modelo: " . ucfirst(str_replace('-', ' ', $partes[3] ?? 'N/A')) . "\n";
        }
    }
    
    private function testarEstrategias($html) {
        echo "--- TESTE DE ESTRATÉGIAS ---\n\n";
        
        // Estratégia 1: Extração via link estruturado
        echo "1. EXTRAÇÃO VIA ESTRUTURA DO LINK:\n";
        $this->estrategiaLink($html);
        
        // Estratégia 2: Regex para alt de imagem
        echo "\n2. EXTRAÇÃO VIA ALT DE IMAGEM:\n";
        $this->estrategiaAlt($html);
        
        // Estratégia 3: Busca por padrões de texto
        echo "\n3. EXTRAÇÃO VIA PADRÕES DE TEXTO:\n";
        $this->estrategiaTexto($html);
        
        // Estratégia 4: DOM parsing
        echo "\n4. EXTRAÇÃO VIA DOM:\n";
        $this->estrategiaDOM($html);
    }
    
    private function estrategiaLink($html) {
        // Padrão: /comprar/tipo/marca/modelo/versao/ano/id/
        preg_match_all('/href="(\/comprar\/([^\/]+)\/([^\/]+)\/([^\/]+)\/[^"]*)"/', $html, $matches, PREG_SET_ORDER);
        
        echo "Links estruturados encontrados: " . count($matches) . "\n";
        
        foreach (array_slice($matches, 0, 5) as $i => $match) {
            $tipo = $match[2];
            $marca = ucfirst($match[3]);
            $modelo = ucfirst(str_replace('-', ' ', $match[4]));
            
            echo "  " . ($i + 1) . ". $marca $modelo ($tipo)\n";
            echo "     Link: " . $match[1] . "\n";
        }
    }
    
    private function estrategiaAlt($html) {
        // Buscar alt de imagens que contenham marcas
        $pattern = '/alt="([^"]*(?:FIAT|HONDA|CHEVROLET|FORD|VOLKSWAGEN|HYUNDAI|TOYOTA|NISSAN|RENAULT|CHERY)[^"]*)"/i';
        
        if (preg_match_all($pattern, $html, $matches)) {
            echo "Imagens com marcas encontradas: " . count($matches[1]) . "\n";
            
            foreach (array_slice($matches[1], 0, 5) as $i => $alt) {
                echo "  " . ($i + 1) . ". $alt\n";
                
                // Tentar extrair marca/modelo
                $resultado = $this->extrairMarcaModelo($alt);
                if ($resultado) {
                    echo "     → Marca: {$resultado['marca']}, Modelo: {$resultado['modelo']}\n";
                }
            }
        } else {
            echo "Nenhuma imagem com marca encontrada\n";
        }
    }
    
    private function estrategiaTexto($html) {
        // Buscar padrões de marca seguida de modelo
        $pattern = '/\b(FIAT|HONDA|CHEVROLET|FORD|VOLKSWAGEN|HYUNDAI|TOYOTA|NISSAN|RENAULT|CHERY)\s+([A-Z][A-Za-z\s]+?)(?=\s+\d|\s*<|\s*$)/i';
        
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            echo "Padrões marca+modelo encontrados: " . count($matches) . "\n";
            
            foreach (array_slice($matches, 0, 5) as $i => $match) {
                $marca = strtoupper($match[1]);
                $modelo = trim($match[2]);
                
                echo "  " . ($i + 1) . ". $marca $modelo\n";
            }
        } else {
            echo "Nenhum padrão marca+modelo encontrado\n";
        }
    }
    
    private function estrategiaDOM($html) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Buscar links de veículos
        $links = $xpath->query("//a[contains(@href, '/comprar/')]");
        echo "Links via XPath: " . $links->length . "\n";
        
        $processados = 0;
        for ($i = 0; $i < min(5, $links->length); $i++) {
            $link = $links->item($i);
            $href = $link->getAttribute('href');
            
            // Buscar imagem dentro do link
            $imgs = $xpath->query(".//img", $link);
            if ($imgs->length > 0) {
                $img = $imgs->item(0);
                $alt = $img->getAttribute('alt');
                
                if (!empty($alt)) {
                    echo "  " . ($processados + 1) . ". Alt: $alt\n";
                    echo "     Link: $href\n";
                    
                    $resultado = $this->extrairMarcaModelo($alt);
                    if ($resultado) {
                        echo "     → {$resultado['marca']} {$resultado['modelo']}\n";
                    }
                    
                    $processados++;
                }
            }
        }
        
        if ($processados === 0) {
            echo "Nenhum link com imagem/alt processado\n";
        }
    }
    
    private function extrairMarcaModelo($texto) {
        $marcas = [
            'FIAT', 'HONDA', 'CHEVROLET', 'FORD', 'VOLKSWAGEN', 'HYUNDAI', 
            'TOYOTA', 'NISSAN', 'RENAULT', 'CHERY', 'PEUGEOT', 'CITROËN'
        ];
        
        foreach ($marcas as $marca) {
            if (stripos($texto, $marca) !== false) {
                // Tentar extrair modelo após a marca
                $pattern = '/' . $marca . '\s+([A-Za-z\s\-]+?)(?=\s+\d|\s*$|$)/i';
                
                if (preg_match($pattern, $texto, $matches)) {
                    return [
                        'marca' => $marca,
                        'modelo' => trim($matches[1])
                    ];
                }
                
                // Fallback: pegar tudo após a marca
                $pos = stripos($texto, $marca);
                $modelo = trim(substr($texto, $pos + strlen($marca)));
                
                // Limpar números/versões
                $modelo = preg_replace('/\s+\d+\.\d+.*$/', '', $modelo);
                $modelo = preg_replace('/\s+\d{4}.*$/', '', $modelo);
                
                if (!empty($modelo)) {
                    return [
                        'marca' => $marca,
                        'modelo' => $modelo
                    ];
                }
            }
        }
        
        return null;
    }
    
    private function testarComDadosSimulados() {
        echo "\n--- TESTE COM DADOS SIMULADOS ---\n";
        
        $htmlSimulado = '
        <a href="/comprar/hatch/fiat/argo/1.0-4p-flex-firefly-drive/2025/7377176/">
            <img src="foto.jpg" alt="FIAT Argo 1.0 4P FLEX FIREFLY DRIVE">
        </a>
        <a href="/comprar/sedan/honda/civic/2.0-16v-4p-exl-flex-automatico-cvt/2019/7325947/">
            <img src="foto2.jpg" alt="HONDA Civic 2.0 16V 4P EXL FLEX AUTOMÁTICO CVT">
        </a>
        ';
        
        echo "Testando com HTML simulado...\n\n";
        $this->testarEstrategias($htmlSimulado);
    }
}

// Executar
$debug = new DebugMarcaModelelo();
$debug->executar();
?>