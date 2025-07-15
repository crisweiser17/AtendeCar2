<?php
/**
 * Debug para identificar problema na extração de marca/modelo
 * CarrosP.com.br - EMJ Motors
 */

require_once 'config/database.php';

class DebugMarcaModelo {
    private $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    public function debugExtracao() {
        echo "=== DEBUG: Extração Marca/Modelo - CarrosP ===\n";
        echo "URL: {$this->url}\n";
        echo "Data: " . date('d/m/Y H:i:s') . "\n\n";
        
        // 1. Obter HTML da página
        $html = $this->obterHTML();
        if (!$html) {
            echo "❌ Erro ao obter HTML\n";
            return false;
        }
        
        echo "✓ HTML obtido (" . strlen($html) . " bytes)\n\n";
        
        // 2. Salvar HTML para análise
        file_put_contents('debug_html_carrosp.html', $html);
        echo "✓ HTML salvo em: debug_html_carrosp.html\n\n";
        
        // 3. Analisar estrutura dos veículos
        $this->analisarEstrutura($html);
        
        // 4. Testar diferentes estratégias de extração
        $this->testarEstrategias($html);
        
        return true;
    }
    
    private function obterHTML() {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
            ]
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200) ? $content : false;
    }
    
    private function analisarEstrutura($html) {
        echo "--- ANÁLISE DA ESTRUTURA ---\n";
        
        // 1. Buscar links de veículos
        preg_match_all('/href="(\/comprar\/[^"]+)"/i', $html, $links);
        echo "✓ Links de veículos encontrados: " . count($links[1]) . "\n";
        
        if (count($links[1]) > 0) {
            echo "Exemplos de links:\n";
            for ($i = 0; $i < min(3, count($links[1])); $i++) {
                echo "  - " . $links[1][$i] . "\n";
            }
        }
        
        // 2. Buscar padrões de marca/modelo
        $marcas = ['FIAT', 'HONDA', 'CHEVROLET', 'FORD', 'VOLKSWAGEN', 'HYUNDAI', 'TOYOTA', 'NISSAN', 'RENAULT'];
        
        foreach ($marcas as $marca) {
            $count = substr_count(strtoupper($html), $marca);
            if ($count > 0) {
                echo "✓ Marca '$marca' encontrada $count vezes\n";
            }
        }
        
        // 3. Analisar estrutura DOM
        $this->analisarDOM($html);
        
        echo "\n";
    }
    
    private function analisarDOM($html) {
        echo "\n--- ANÁLISE DOM ---\n";
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Buscar diferentes seletores possíveis
        $seletores = [
            "//a[contains(@href, '/comprar/')]",
            "//div[contains(@class, 'card')]",
            "//div[contains(@class, 'vehicle')]",
            "//div[contains(@class, 'item')]",
            "//*[contains(@class, 'title')]",
            "//h1 | //h2 | //h3 | //h4",
            "//*[contains(text(), 'FIAT') or contains(text(), 'HONDA')]"
        ];
        
        foreach ($seletores as $seletor) {
            $nodes = $xpath->query($seletor);
            echo "Seletor '$seletor': " . $nodes->length . " elementos\n";
            
            if ($nodes->length > 0 && $nodes->length < 50) {
                for ($i = 0; $i < min(2, $nodes->length); $i++) {
                    $texto = trim($nodes->item($i)->textContent);
                    if (strlen($texto) > 0 && strlen($texto) < 200) {
                        echo "  Exemplo: " . substr($texto, 0, 100) . "\n";
                    }
                }
            }
        }
    }
    
    private function testarEstrategias($html) {
        echo "--- TESTE DE ESTRATÉGIAS ---\n";
        
        // Estratégia 1: Regex para links + extração do link
        echo "\n1. Extração via estrutura do link:\n";
        $this->testarExtracaoLink($html);
        
        // Estratégia 2: DOM + XPath
        echo "\n2. Extração via DOM/XPath:\n";
        $this->testarExtracaoDOM($html);
        
        // Estratégia 3: Regex específica para CarrosP
        echo "\n3. Extração via Regex específica:\n";
        $this->testarExtracaoRegex($html);
        
        // Estratégia 4: Análise de blocos HTML
        echo "\n4. Extração via blocos HTML:\n";
        $this->testarExtracaoBlocos($html);
    }
    
    private function testarExtracaoLink($html) {
        preg_match_all('/href="(\/comprar\/[^"]+)"/i', $html, $matches);
        
        foreach (array_slice($matches[1], 0, 3) as $link) {
            echo "Link: $link\n";
            
            // Extrair marca/modelo do link
            $partes = explode('/', trim($link, '/'));
            if (count($partes) >= 4) {
                $tipo = $partes[1] ?? '';
                $marca = ucfirst($partes[2] ?? '');
                $modelo = ucfirst(str_replace('-', ' ', $partes[3] ?? ''));
                
                echo "  Tipo: $tipo\n";
                echo "  Marca: $marca\n";
                echo "  Modelo: $modelo\n";
                echo "  Nome completo: $marca $modelo\n";
            }
            echo "\n";
        }
    }
    
    private function testarExtracaoDOM($html) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Buscar links de veículos
        $links = $xpath->query("//a[contains(@href, '/comprar/')]");
        
        echo "Links encontrados via DOM: " . $links->length . "\n";
        
        for ($i = 0; $i < min(3, $links->length); $i++) {
            $link = $links->item($i);
            $href = $link->getAttribute('href');
            
            echo "Link $i: $href\n";
            
            // Buscar texto dentro do link
            $texto = trim($link->textContent);
            echo "  Texto do link: " . substr($texto, 0, 100) . "\n";
            
            // Buscar imagem alt
            $img = $xpath->query(".//img", $link)->item(0);
            if ($img) {
                $alt = $img->getAttribute('alt');
                echo "  Alt da imagem: $alt\n";
            }
            
            // Buscar elementos próximos
            $parent = $link->parentNode;
            if ($parent) {
                $textoParent = trim($parent->textContent);
                echo "  Texto do parent: " . substr($textoParent, 0, 100) . "\n";
            }
            
            echo "\n";
        }
    }
    
    private function testarExtracaoRegex($html) {
        // Padrões específicos para CarrosP
        $padroes = [
            // Padrão 1: Marca seguida de modelo em maiúsculas
            '/\b(FIAT|HONDA|CHEVROLET|FORD|VOLKSWAGEN|HYUNDAI|TOYOTA|NISSAN|RENAULT|CHERY)\s+([A-Z][A-Za-z\s]+?)(?=\s+\d|\s*<|\s*$)/i',
            
            // Padrão 2: Em tags alt de imagem
            '/alt="([^"]*(?:FIAT|HONDA|CHEVROLET|FORD|VOLKSWAGEN|HYUNDAI|TOYOTA|NISSAN|RENAULT|CHERY)[^"]*)"/i',
            
            // Padrão 3: Em títulos/headers
            '/<h[1-6][^>]*>([^<]*(?:FIAT|HONDA|CHEVROLET|FORD|VOLKSWAGEN|HYUNDAI|TOYOTA|NISSAN|RENAULT|CHERY)[^<]*)<\/h[1-6]>/i'
        ];
        
        foreach ($padroes as $i => $padrao) {
            echo "Padrão " . ($i + 1) . ":\n";
            
            if (preg_match_all($padrao, $html, $matches, PREG_SET_ORDER)) {
                echo "  Encontradas " . count($matches) . " ocorrências\n";
                
                foreach (array_slice($matches, 0, 3) as $match) {
                    echo "  - " . trim($match[1]) . "\n";
                }
            } else {
                echo "  Nenhuma ocorrência encontrada\n";
            }
            echo "\n";
        }
    }
    
    private function testarExtracaoBlocos($html) {
        // Buscar blocos que contêm links de veículos
        preg_match_all('/(<[^>]*>.*?href="\/comprar\/[^"]*".*?<\/[^>]*>)/is', $html, $blocos);
        
        echo "Blocos com links encontrados: " . count($blocos[1]) . "\n";
        
        foreach (array_slice($blocos[1], 0, 2) as $i => $bloco) {
            echo "\nBloco " . ($i + 1) . ":\n";
            echo "HTML: " . substr($bloco, 0, 200) . "...\n";
            
            $texto = strip_tags($bloco);
            echo "Texto: " . substr($texto, 0, 100) . "\n";
            
            // Buscar marca no texto
            $marcas = ['FIAT', 'HONDA', 'CHEVROLET', 'FORD', 'VOLKSWAGEN', 'HYUNDAI', 'TOYOTA', 'NISSAN', 'RENAULT', 'CHERY'];
            
            foreach ($marcas as $marca) {
                if (stripos($texto, $marca) !== false) {
                    echo "  ✓ Marca encontrada: $marca\n";
                    
                    // Tentar extrair modelo
                    if (preg_match('/' . $marca . '\s+([A-Za-z\s]+?)(?=\s+\d|\s*$)/i', $texto, $match)) {
                        echo "  ✓ Modelo: " . trim($match[1]) . "\n";
                    }
                    break;
                }
            }
        }
    }
}

// Executar debug
$debug = new DebugMarcaModelo();
$debug->debugExtracao();
?>