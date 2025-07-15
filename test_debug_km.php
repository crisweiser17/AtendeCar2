<?php
// Teste sem conexão com banco para debug da KM
echo "=== TESTE DEBUG KM SEM BANCO ===\n\n";

// Simular a classe ImportadorEstoque apenas com os métodos de extração
class ImportadorEstoqueDebug {
    
    /**
     * Extrai veículos da página usando cURL e regex
     */
    public function extrairVeiculos($url) {
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
            throw new Exception('Erro ao acessar a página: HTTP ' . $httpCode);
        }
        
        return $this->parseHTML($html, $url);
    }
    
    /**
     * Faz o parse do HTML para extrair dados dos veículos
     */
    private function parseHTML($html, $baseUrl) {
        $veiculos = [];
        
        // Estratégia específica para CarrosP.com.br
        $veiculos = $this->extrairCarrosPEspecifico($html, $baseUrl);
        
        // Remover duplicados baseado no link único
        $veiculos = $this->removerDuplicados($veiculos);
        
        return $veiculos;
    }
    
    /**
     * Extração específica para CarrosP.com.br
     */
    private function extrairCarrosPEspecifico($html, $baseUrl) {
        $veiculos = [];
        $linksUnicos = [];
        
        // ESTRATÉGIA 1: Buscar links absolutos
        $padraoLinkAbsoluto = '/href="(https:\/\/carrosp\.com\.br\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLinkAbsoluto, $html, $matchesAbsolutos);
        
        echo "Links encontrados: " . count($matchesAbsolutos[1]) . "\n\n";
        
        // Processar apenas os primeiros 3 links para debug
        $contador = 0;
        foreach ($matchesAbsolutos[1] as $linkCompleto) {
            if ($contador >= 3) break; // Limitar para debug
            $contador++;
            
            if (in_array($linkCompleto, $linksUnicos)) {
                continue;
            }
            $linksUnicos[] = $linkCompleto;
            
            echo "=== PROCESSANDO VEÍCULO $contador ===\n";
            $veiculo = $this->extrairDadosDoLink($html, $linkCompleto, $baseUrl);
            if ($veiculo && !empty($veiculo['nome'])) {
                $veiculos[] = $veiculo;
            }
            echo "\n";
        }
        
        return $veiculos;
    }
    
    /**
     * Extrai dados do veículo usando estrutura do link
     */
    private function extrairDadosDoLink($html, $linkVeiculo, $baseUrl) {
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
        
        // Extrair informações específicas do CarrosP (câmbio, combustível, km) - CONTEXTO ESPECÍFICO
        $linkBase = basename($linkVeiculo);
        $contexto = $this->buscarContextoDoLink($html, $linkBase);
        
        // DEBUG TEMPORÁRIO
        echo "DEBUG KM REAL - Link: $linkVeiculo\n";
        echo "DEBUG KM REAL - Contexto encontrado: " . (!empty($contexto) ? "SIM" : "NÃO") . "\n";
        
        if ($contexto) {
            // Buscar info-geral específica neste contexto
            if (preg_match('/<p[^>]*class="[^"]*info-geral[^"]*"[^>]*>([^<]+)<\/p>/i', $contexto, $infoMatch)) {
                $infoTexto = trim($infoMatch[1]);
                echo "DEBUG KM REAL - Info-geral encontrada: $infoTexto\n";
                $outrasInfos = $this->extrairInfosTexto($infoTexto);
                echo "DEBUG KM REAL - KM extraída: " . ($outrasInfos['km'] ?? 'NULL') . "\n";
                $veiculo = array_merge($veiculo, $outrasInfos);
            } else {
                // Fallback: buscar padrão genérico no contexto
                echo "DEBUG KM REAL - Usando fallback no contexto\n";
                $outrasInfos = $this->extrairInfosTexto($contexto);
                echo "DEBUG KM REAL - KM extraída (fallback): " . ($outrasInfos['km'] ?? 'NULL') . "\n";
                $veiculo = array_merge($veiculo, $outrasInfos);
            }
        } else {
            echo "DEBUG KM REAL - Contexto vazio para: $linkVeiculo\n";
        }
        
        // DEBUG FINAL
        echo "DEBUG KM REAL - KM final no veículo: " . ($veiculo['km'] ?? 'NULL') . "\n";
        echo "---\n";
        
        return $veiculo;
    }
    
    /**
     * Buscar contexto do link
     */
    private function buscarContextoDoLink($html, $linkBase) {
        // Estratégia 1: Buscar por div que contenha o link específico
        $padraoDiv = '/<div[^>]*>(?:[^<]|<(?!\/div>))*?' . preg_quote($linkBase, '/') . '(?:[^<]|<(?!\/div>))*?<\/div>/is';
        if (preg_match($padraoDiv, $html, $matches)) {
            return $matches[0];
        }
        
        // Estratégia 2: Buscar por contexto mais amplo ao redor do link
        $pos = strpos($html, $linkBase);
        if ($pos !== false) {
            // Buscar início de div anterior
            $inicioDiv = strrpos(substr($html, 0, $pos), '<div');
            if ($inicioDiv !== false) {
                $inicio = $inicioDiv;
            } else {
                $inicio = max(0, $pos - 1500);
            }
            
            // Buscar fim de div posterior
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
    
    /**
     * Extrai apenas KM do texto
     */
    private function extrairInfosTexto($texto) {
        $info = [];
        
        // PADRÃO ESPECÍFICO CARROSP: "Aut. | Flex | 132.326 KM" ou "Aut. CVT | Flex | 67.887 KM"
        if (preg_match('/[^|]+\|\s*[^|]+\|\s*([\d.,]+)\s*KM/i', $texto, $matches)) {
            // Extrair a quilometragem (3º elemento após os separadores |)
            $kmTexto = trim($matches[1]);
            $km = str_replace(['.', ','], '', $kmTexto);
            if (is_numeric($km)) {
                $info['km'] = (int)$km;
            }
        } else {
            // FALLBACK: Padrões genéricos para KM
            if (preg_match('/([\d.,]+)\s*(?:km|quilômetros?)/i', $texto, $matches)) {
                $km = str_replace(['.', ','], '', $matches[1]);
                if (is_numeric($km)) {
                    $info['km'] = (int)$km;
                }
            }
        }
        
        return $info;
    }
    
    /**
     * Remove duplicados baseado no link único
     */
    private function removerDuplicados($veiculos) {
        $linksVistos = [];
        $veiculosUnicos = [];
        
        foreach ($veiculos as $veiculo) {
            $link = $veiculo['link'];
            
            if (!in_array($link, $linksVistos)) {
                $linksVistos[] = $link;
                $veiculosUnicos[] = $veiculo;
            }
        }
        
        return $veiculosUnicos;
    }
}

// URL de teste
$url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';

echo "Testando URL: $url\n\n";

try {
    $importador = new ImportadorEstoqueDebug();
    
    // Extrair veículos
    $veiculos = $importador->extrairVeiculos($url);
    
    echo "\n=== RESUMO FINAL ===\n";
    echo "Total de veículos processados: " . count($veiculos) . "\n\n";
    
    foreach ($veiculos as $index => $veiculo) {
        echo "Veículo " . ($index + 1) . ":\n";
        echo "  Nome: " . ($veiculo['nome'] ?: 'N/A') . "\n";
        echo "  KM: " . ($veiculo['km'] ? number_format($veiculo['km'], 0, ',', '.') . ' km' : 'NULL') . "\n";
        echo "  Link: " . $veiculo['link'] . "\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "=== TESTE CONCLUÍDO ===\n";
?>