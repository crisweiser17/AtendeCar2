<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Importação v2 - EMJ Motors</title>
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
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f8ff;
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
        .resumo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .marca-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            text-align: center;
        }
        .marca-nome {
            font-weight: bold;
            color: #007bff;
            font-size: 16px;
        }
        .marca-count {
            font-size: 24px;
            color: #28a745;
            font-weight: bold;
        }
        .link-btn {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
        }
        .link-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚗 Teste de Importação v2 - EMJ Motors (Com Preços)</h1>

<?php
// Simular a classe ImportadorEstoque sem conexão com banco - VERSÃO COM PREÇOS
class TestImportador {
    
    /**
     * Testa a extração de veículos
     */
    public function testarExtracao($url) {
        echo '<div class="info">📡 Acessando URL: <strong>' . htmlspecialchars($url) . '</strong></div>';
        
        // Configurar cURL
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
            echo '<div class="info error">❌ Erro ao acessar a página: HTTP ' . $httpCode . '</div>';
            return [];
        }
        
        echo '<div class="info success">✅ HTML obtido com sucesso (' . number_format(strlen($html)) . ' bytes)</div>';
        
        return $this->extrairVeiculos($html, $url);
    }
    
    /**
     * Extrai veículos do HTML - USANDO ESTRATÉGIA HÍBRIDA
     */
    private function extrairVeiculos($html, $baseUrl) {
        $veiculos = [];
        $linksUnicos = [];
        
        // ESTRATÉGIA 1: Buscar links absolutos (funciona para marca/modelo)
        $padraoLinkAbsoluto = '/href="(https:\/\/carrosp\.com\.br\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLinkAbsoluto, $html, $matchesAbsolutos);
        
        // ESTRATÉGIA 2: Buscar links relativos (funciona para preços)
        $padraoLinkRelativo = '/href="(\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLinkRelativo, $html, $matchesRelativos);
        
        echo '<div class="info">🔍 Links absolutos encontrados: <strong>' . count($matchesAbsolutos[1]) . '</strong></div>';
        echo '<div class="info">🔍 Links relativos encontrados: <strong>' . count($matchesRelativos[1]) . '</strong></div>';
        
        // Processar links absolutos primeiro
        foreach ($matchesAbsolutos[1] as $linkCompleto) {
            if (in_array($linkCompleto, $linksUnicos)) {
                continue;
            }
            $linksUnicos[] = $linkCompleto;
            
            $veiculo = $this->extrairDadosDoLink($html, $linkCompleto, $baseUrl);
            if ($veiculo && !empty($veiculo['nome'])) {
                $veiculos[] = $veiculo;
            }
        }
        
        // Se não encontrou links absolutos, tentar relativos
        if (empty($veiculos)) {
            foreach ($matchesRelativos[1] as $linkRelativo) {
                $linkCompleto = $this->normalizarURL($linkRelativo, $baseUrl);
                
                if (in_array($linkCompleto, $linksUnicos)) {
                    continue;
                }
                $linksUnicos[] = $linkCompleto;
                
                $veiculo = $this->extrairDadosVeiculoCarrosP($html, $linkCompleto, $baseUrl);
                if ($veiculo && !empty($veiculo['nome'])) {
                    $veiculos[] = $veiculo;
                }
            }
        }
        
        return $veiculos;
    }
    
    /**
     * Extrai dados do veículo usando estrutura do link (para marca/modelo)
     */
    private function extrairDadosDoLink($html, $linkVeiculo, $baseUrl) {
        $veiculo = [
            'nome' => '',
            'marca' => '',
            'modelo' => '',
            'preco' => 0,
            'ano' => null,
            'link' => $linkVeiculo
        ];
        
        // Extrair dados da estrutura do link
        if (preg_match('/\/comprar\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/(\d{4})\/(\d+)\/?/', $linkVeiculo, $matches)) {
            $tipo = $matches[1];
            $marcaSlug = $matches[2];
            $modeloSlug = $matches[3];
            $versaoSlug = $matches[4];
            $ano = (int)$matches[5];
            
            $marca = strtoupper(str_replace('-', ' ', $marcaSlug));
            $modelo = ucwords(str_replace('-', ' ', $modeloSlug));
            $versao = str_replace('-', ' ', $versaoSlug);
            
            $veiculo['marca'] = $marca;
            $veiculo['modelo'] = $modelo;
            $veiculo['ano'] = $ano;
            $veiculo['nome'] = $marca . ' ' . $modelo . ' ' . $versao;
            $veiculo['tipo'] = ucfirst($tipo);
        }
        
        // BUSCAR PREÇO usando múltiplas estratégias
        $veiculo['preco'] = $this->extrairPrecoMultiplasEstrategias($html, $linkVeiculo);
        
        return $veiculo;
    }
    
    /**
     * MÉTODO DO ARQUIVO ANTIGO: Extrai dados de um veículo específico do CarrosP
     */
    private function extrairDadosVeiculoCarrosP($html, $linkVeiculo, $baseUrl) {
        $veiculo = [
            'nome' => '',
            'marca' => '',
            'modelo' => '',
            'preco' => 0,
            'ano' => null,
            'km' => null,
            'cambio' => '',
            'cor' => '',
            'combustivel' => '',
            'link' => $linkVeiculo,
            'foto' => ''
        ];
        
        // Buscar o bloco HTML que contém este link específico
        $padraoContainer = '/(<div[^>]*>(?:[^<]|<(?!\/div>))*?href="[^"]*' . preg_quote(basename($linkVeiculo), '/') . '[^"]*"(?:[^<]|<(?!\/div>))*?<\/div>)/is';
        
        if (preg_match($padraoContainer, $html, $matches)) {
            $blocoHtml = $matches[1];
        } else {
            $posicaoLink = strpos($html, basename($linkVeiculo));
            if ($posicaoLink !== false) {
                $inicio = max(0, $posicaoLink - 2000);
                $blocoHtml = substr($html, $inicio, 4000);
            } else {
                return null;
            }
        }
        
        // Extrair nome/marca/modelo
        if (preg_match('/<(?:h[1-6]|div|span)[^>]*>([^<]*(?:FIAT|FORD|CHEVROLET|HONDA|TOYOTA|VOLKSWAGEN|HYUNDAI|NISSAN|RENAULT|CHERY)[^<]*)<\/(?:h[1-6]|div|span)>/i', $blocoHtml, $matches)) {
            $veiculo['nome'] = trim(strip_tags($matches[1]));
        }
        
        // Se não encontrou nome, tentar extrair do próprio link
        if (empty($veiculo['nome'])) {
            $partesLink = explode('/', $linkVeiculo);
            if (count($partesLink) >= 4) {
                $marca = ucfirst($partesLink[count($partesLink)-4]);
                $modelo = ucfirst(str_replace('-', ' ', $partesLink[count($partesLink)-3]));
                $veiculo['nome'] = $marca . ' ' . $modelo;
                $veiculo['marca'] = strtoupper($marca);
                $veiculo['modelo'] = $modelo;
            }
        }
        
        // Extrair preço usando múltiplas estratégias
        $veiculo['preco'] = $this->extrairPrecoMultiplasEstrategias($html, $linkVeiculo);
        
        return $veiculo;
    }
    
    /**
     * Extrai preço usando múltiplas estratégias combinadas
     */
    private function extrairPrecoMultiplasEstrategias($html, $linkVeiculo) {
        // Extrair ID do veículo do link
        if (preg_match('/\/(\d+)\/?$/', $linkVeiculo, $matches)) {
            $idVeiculo = $matches[1];
            
            // Buscar por div info-list que contenha referência a este veículo
            $padrao = '/<div[^>]*class="[^"]*info-list[^"]*"[^>]*>.*?<\/div>/is';
            if (preg_match_all($padrao, $html, $divMatches)) {
                foreach ($divMatches[0] as $divContent) {
                    // Verificar se esta div contém referência ao ID do veículo
                    if (strpos($divContent, $idVeiculo) !== false) {
                        // Buscar o padrão específico do preço
                        if (preg_match('/<span[^>]*class="[^"]*text-color-1[^"]*"[^>]*>.*?<span[^>]*class="rs"[^>]*>R\$<\/span>\s*([0-9.,]+).*?<\/span>/is', $divContent, $precoMatch)) {
                            $preco = $this->extrairPreco($precoMatch[1]);
                            if ($preco > 0) return $preco;
                        }
                    }
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Busca a div info-list específica para o veículo
     */
    private function buscarDivInfoList($html, $linkVeiculo) {
        // Extrair ID do veículo do link
        if (preg_match('/\/(\d+)\/?$/', $linkVeiculo, $matches)) {
            $idVeiculo = $matches[1];
            
            // Buscar por div info-list que contenha referência a este veículo
            $padrao = '/<div[^>]*class="[^"]*info-list[^"]*"[^>]*>.*?<\/div>/is';
            if (preg_match_all($padrao, $html, $matches)) {
                foreach ($matches[0] as $divContent) {
                    // Verificar se esta div contém referência ao ID do veículo
                    if (strpos($divContent, $idVeiculo) !== false) {
                        // Extrair apenas o span com o preço para debug
                        if (preg_match('/<span[^>]*class="[^"]*text-color-1[^"]*"[^>]*>.*?<span[^>]*class="rs"[^>]*>R\$<\/span>\s*([0-9.,]+).*?<\/span>/is', $divContent, $precoMatch)) {
                            return "PREÇO ENCONTRADO: " . $precoMatch[0] . "\nVALOR EXTRAÍDO: " . $precoMatch[1];
                        } else {
                            return "DIV ENCONTRADA MAS SEM PADRÃO DE PREÇO:\n" . substr($divContent, 0, 300) . "...";
                        }
                    }
                }
            }
        }
        
        return 'Div info-list não encontrada para ID: ' . ($idVeiculo ?? 'N/A');
    }
    
    /**
     * Retorna o conteúdo da div info-list para debug
     */
    public function getDebugInfoList($html, $linkVeiculo) {
        return $this->buscarDivInfoList($html, $linkVeiculo);
    }
    
    /**
     * Acessa a página individual do veículo para buscar o preço
     */
    private function buscarPrecoNaPaginaIndividual($linkVeiculo) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $linkVeiculo);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $htmlIndividual = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $htmlIndividual) {
                // Buscar preço na página individual
                $padroes = [
                    '/R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
                    '/"price":\s*"?([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)"?/i',
                    '/data-price["\']?\s*[:=]\s*["\']?([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
                    '/valor["\']?\s*[:>]\s*["\']?R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
                ];
                
                foreach ($padroes as $padrao) {
                    if (preg_match($padrao, $htmlIndividual, $matches)) {
                        $preco = $this->extrairPreco($matches[1]);
                        if ($preco > 0) {
                            return $preco;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Se der erro, continua sem preço
        }
        
        return 0;
    }
    
    /**
     * Extrai preço usando XPath com contexto específico do veículo
     */
    private function extrairPrecoComXPathEspecifico($html, $linkVeiculo) {
        // Extrair ID do veículo do link
        if (preg_match('/\/(\d+)\/?$/', $linkVeiculo, $matches)) {
            $idVeiculo = $matches[1];
            
            try {
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML($html);
                libxml_clear_errors();
                
                $xpath = new DOMXPath($dom);
                
                // Buscar por elementos que contenham o ID do veículo e tenham preço próximo
                $query = '//text()[contains(., "' . $idVeiculo . '")]/ancestor::*[1]//text()[contains(., "R$")]';
                $precoNodes = $xpath->query($query);
                
                foreach ($precoNodes as $node) {
                    $precoTexto = trim($node->textContent);
                    if (preg_match('/R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i', $precoTexto, $matches)) {
                        $preco = $this->extrairPreco($matches[1]);
                        if ($preco > 0) {
                            return $preco;
                        }
                    }
                }
                
            } catch (Exception $e) {
                // Se der erro no XPath, continua
            }
        }
        
        return 0;
    }
    
    /**
     * Extrai preço com padrões específicos do CarrosP
     */
    private function extrairPrecoEspecificoCarrosP($html, $linkVeiculo) {
        // Buscar por ID do veículo no link
        if (preg_match('/\/(\d+)\/?$/', $linkVeiculo, $matches)) {
            $idVeiculo = $matches[1];
            
            // Buscar contexto específico deste veículo
            $padraoContexto = '/id["\']?\s*[:=]\s*["\']?' . preg_quote($idVeiculo, '/') . '["\']?.*?R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/is';
            if (preg_match($padraoContexto, $html, $matches)) {
                $preco = $this->extrairPreco($matches[1]);
                if ($preco > 0) return $preco;
            }
        }
        
        // Buscar por data-price ou similar
        $padroes = [
            '/data-price["\']?\s*[:=]\s*["\']?([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
            '/price["\']?\s*[:=]\s*["\']?([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
            '/"price":\s*"?([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)"?/i',
        ];
        
        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $html, $matches)) {
                $preco = $this->extrairPreco($matches[1]);
                if ($preco > 0) return $preco;
            }
        }
        
        return 0;
    }
    
    /**
     * Busca preço diretamente no HTML por padrões simples
     */
    private function buscarPrecoDirecto($html, $linkVeiculo) {
        // Buscar por qualquer ocorrência de "R$" seguido de números
        $padroes = [
            '/R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
            '/R\$\s*([0-9]{1,4}(?:,[0-9]{2})?)/i',
            '/([0-9]{2,3}\.[0-9]{3})/i'
        ];
        
        foreach ($padroes as $padrao) {
            if (preg_match_all($padrao, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    $preco = $this->extrairPreco($match);
                    if ($preco > 0) {
                        return $preco;
                    }
                }
            }
        }
        
        return 0;
    }
    
    /**
     * MÉTODO DO ARQUIVO ANTIGO: Extrai preço usando XPath específico do CarrosP
     */
    private function extrairPrecoComXPath($html, $linkVeiculo) {
        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Estratégia 1: Buscar por elementos que contêm "R$" diretamente
            $precoNodes = $xpath->query('//text()[contains(., "R$")]');
            
            foreach ($precoNodes as $node) {
                $precoTexto = trim($node->textContent);
                // Padrões mais amplos para capturar diferentes formatos
                $padroes = [
                    '/R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
                    '/R\$\s*([0-9]{1,4}(?:,[0-9]{2})?)/i',
                    '/R\$\s*([0-9.,]+)/i'
                ];
                
                foreach ($padroes as $padrao) {
                    if (preg_match($padrao, $precoTexto, $matches)) {
                        $preco = $this->extrairPreco($matches[1]);
                        if ($preco > 0) {
                            return $preco;
                        }
                    }
                }
            }
            
            // Estratégia 2: Buscar em elementos específicos (spans, divs com classes de preço)
            $elementosPreco = $xpath->query('//span[contains(@class, "price") or contains(@class, "valor") or contains(@class, "rs")] | //div[contains(@class, "price") or contains(@class, "valor")]');
            
            foreach ($elementosPreco as $elemento) {
                $precoTexto = trim($elemento->textContent);
                if (preg_match('/R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i', $precoTexto, $matches)) {
                    $preco = $this->extrairPreco($matches[1]);
                    if ($preco > 0) {
                        return $preco;
                    }
                }
            }
            
        } catch (Exception $e) {
            // Se der erro no XPath, continua com estratégias antigas
        }
        
        return 0;
    }
    
    /**
     * MÉTODO DO ARQUIVO ANTIGO: Extrai preço específico do CarrosP com múltiplas estratégias
     */
    private function extrairPrecoCarrosP($html) {
        $padroes = [
            // Padrão específico do CarrosP: R$ 78.990
            '/R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
            // Padrão com span class="rs"
            '/<span[^>]*class="rs"[^>]*>R\$<\/span>\s*([0-9]{1,3}(?:\.[0-9]{3})*)/i',
            // Padrão genérico R$ seguido de número
            '/R\$<\/span>\s*([0-9]{1,3}(?:\.[0-9]{3})*)/i',
            // Padrão em parágrafos
            '/>\s*([0-9]{1,3}\.[0-9]{3})\s*<\/p>/i',
            // Padrão mais amplo para números com ponto
            '/([0-9]{1,3}\.[0-9]{3})/i',
            // Padrão para preços menores (sem ponto)
            '/R\$\s*([0-9]{1,4}(?:,[0-9]{2})?)/i',
        ];
        
        foreach ($padroes as $padrao) {
            if (preg_match_all($padrao, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    $preco = $this->extrairPreco($match);
                    if ($preco > 0) {
                        return $preco;
                    }
                }
            }
        }
        
        // Buscar preços no contexto específico do card-info
        if (preg_match_all('/card-info[^>]*>.*?([0-9]{2,3}\.[0-9]{3})/is', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $preco = $this->extrairPreco($match);
                if ($preco > 0) {
                    return $preco;
                }
            }
        }
        
        // Buscar qualquer número no formato XXX.XXX
        if (preg_match_all('/([0-9]{2,3}\.[0-9]{3})/', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $preco = $this->extrairPreco($match);
                if ($preco > 0) {
                    return $preco;
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Busca contexto específico ao redor do link do veículo
     */
    private function buscarContextoDoLink($html, $link) {
        // Tentar buscar pelo ID do veículo no link
        if (preg_match('/\/(\d+)\/?$/', $link, $matches)) {
            $idVeiculo = $matches[1];
            
            // Buscar por contexto que contenha o ID do veículo
            $pos = strpos($html, $idVeiculo);
            if ($pos !== false) {
                $inicio = max(0, $pos - 2000);
                $contexto = substr($html, $inicio, 4000);
                return $contexto;
            }
        }
        
        // Fallback: buscar pelo link completo
        $pos = strpos($html, $link);
        if ($pos !== false) {
            $inicio = max(0, $pos - 2000);
            $contexto = substr($html, $inicio, 4000);
            return $contexto;
        }
        
        // Fallback: buscar por partes do link
        $partesLink = explode('/', $link);
        $ultimaParte = end($partesLink);
        if ($ultimaParte) {
            $pos = strpos($html, $ultimaParte);
            if ($pos !== false) {
                $inicio = max(0, $pos - 2000);
                $contexto = substr($html, $inicio, 4000);
                return $contexto;
            }
        }
        
        return '';
    }
    
    /**
     * Extrai valor numérico do preço
     */
    private function extrairPreco($texto) {
        $numero = preg_replace('/[^\d,.]/', '', $texto);
        
        if (strpos($numero, ',') !== false) {
            if (strpos($numero, '.') !== false && strpos($numero, '.') < strpos($numero, ',')) {
                $numero = str_replace('.', '', $numero);
                $numero = str_replace(',', '.', $numero);
            } else {
                $numero = str_replace(',', '.', $numero);
            }
        } else if (substr_count($numero, '.') == 1) {
            $partes = explode('.', $numero);
            if (strlen($partes[1]) == 3 && strlen($partes[0]) <= 3) {
                $numero = $partes[0] . $partes[1];
            }
        } else if (substr_count($numero, '.') > 1) {
            $numero = str_replace('.', '', $numero);
        }
        
        return (float)$numero;
    }
    
    /**
     * Normaliza URLs relativas para absolutas
     */
    private function normalizarURL($url, $baseUrl) {
        if (empty($url)) return '';
        
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        $parsedBase = parse_url($baseUrl);
        $base = $parsedBase['scheme'] . '://' . $parsedBase['host'];
        
        if ($url[0] === '/') {
            return $base . $url;
        }
        
        return $base . '/' . ltrim($url, '/');
    }
}

// Executar teste
try {
    $testador = new TestImportador();
    $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    // Obter HTML para debug
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $htmlDebug = curl_exec($ch);
    curl_close($ch);
    
    $veiculos = $testador->testarExtracao($url);
    
    echo '<div class="info success">📊 <strong>Total de veículos encontrados: ' . count($veiculos) . '</strong></div>';
    
    if (!empty($veiculos)) {
        // Contar por marca para o resumo
        $marcas = [];
        $comPreco = 0;
        foreach ($veiculos as $veiculo) {
            $marca = $veiculo['marca'] ?: 'SEM MARCA';
            $marcas[$marca] = ($marcas[$marca] ?? 0) + 1;
            if ($veiculo['preco'] > 0) $comPreco++;
        }
        arsort($marcas);
        
        echo '<div class="info">💰 <strong>Veículos com preço: ' . $comPreco . ' de ' . count($veiculos) . '</strong></div>';
        
        // Resumo por marca
        echo '<h2>📋 Resumo por Marca</h2>';
        echo '<div class="resumo">';
        foreach ($marcas as $marca => $count) {
            echo '<div class="marca-card">';
            echo '<div class="marca-nome">' . htmlspecialchars($marca) . '</div>';
            echo '<div class="marca-count">' . $count . '</div>';
            echo '<div>veículos</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Tabela de veículos
        echo '<h2>🚗 Lista Completa de Veículos</h2>';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>Marca</th>';
        echo '<th>Modelo</th>';
        echo '<th>Ano</th>';
        echo '<th>Preço</th>';
        echo '<th>Tipo</th>';
        echo '<th>Nome Completo</th>';
        echo '<th>Link</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($veiculos as $i => $veiculo) {
            $precoClass = $veiculo['preco'] > 0 ? 'preco' : 'preco zero';
            $precoTexto = $veiculo['preco'] > 0 ? 'R$ ' . number_format($veiculo['preco'], 2, ',', '.') : 'N/D';
            
            echo '<tr>';
            echo '<td>' . ($i + 1) . '</td>';
            echo '<td class="marca">' . htmlspecialchars($veiculo['marca'] ?: 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($veiculo['modelo'] ?: 'N/A') . '</td>';
            echo '<td>' . ($veiculo['ano'] ?: 'N/A') . '</td>';
            echo '<td class="' . $precoClass . '">' . $precoTexto . '</td>';
            echo '<td>' . htmlspecialchars($veiculo['tipo'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($veiculo['nome']) . '</td>';
            echo '<td><a href="' . htmlspecialchars($veiculo['link']) . '" target="_blank" class="link-btn">Ver</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
    } else {
        echo '<div class="info error">❌ Nenhum veículo foi extraído.</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="info error">💥 ERRO: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

        <div class="info">🏁 Teste finalizado.</div>
    </div>
</body>
</html>