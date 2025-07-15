<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

require_once 'config/database.php';

class ImportadorEstoque {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getConnection();
    }
    
    /**
     * Importa estoque de um cliente específico - VERSÃO ESTÁVEL
     */
    public function importarEstoque($clienteId, $url) {
        try {
            // Definir timeout para evitar travamento
            set_time_limit(120); // 2 minutos
            
            // Validar URL
            if (!$this->validarURL($url)) {
                throw new Exception('URL inválida para CarrosP.com.br');
            }
            
            // Extrair dados da página
            $veiculos = $this->extrairVeiculos($url);
            
            if (empty($veiculos)) {
                throw new Exception('Nenhum veículo encontrado na URL fornecida');
            }
            
            // Desativar veículos antigos
            $this->desativarVeiculosAntigos($clienteId);
            
            // Inserir novos veículos
            $inseridos = $this->inserirVeiculos($clienteId, $veiculos);
            
            return [
                'sucesso' => true,
                'total_encontrados' => count($veiculos),
                'total_inseridos' => $inseridos,
                'mensagem' => "Importação concluída: {$inseridos} veículos importados"
            ];
            
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Valida se a URL é do CarrosP
     */
    private function validarURL($url) {
        return strpos($url, 'carrosp.com.br') !== false;
    }
    
    /**
     * Extrai veículos da página usando cURL e regex
     */
    private function extrairVeiculos($url) {
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
        
        // Se não encontrou, tentar estratégia genérica
        if (empty($veiculos)) {
            $veiculos = $this->extrairGenerico($html, $baseUrl);
        }
        
        // Remover duplicados baseado no link único
        $veiculos = $this->removerDuplicados($veiculos);
        
        return $veiculos;
    }
    
    /**
     * Extração específica para CarrosP.com.br - VERSÃO CORRIGIDA COM KM
     */
    private function extrairCarrosPEspecifico($html, $baseUrl) {
        $veiculos = [];
        $linksUnicos = [];
        
        // NOVA ESTRATÉGIA: Extrair todas as KMs primeiro
        $todasKms = $this->extrairTodasKmsDoHtml($html);
        
        // ESTRATÉGIA 1: Buscar links absolutos (funciona para marca/modelo)
        $padraoLinkAbsoluto = '/href="(https:\/\/carrosp\.com\.br\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLinkAbsoluto, $html, $matchesAbsolutos);
        
        // ESTRATÉGIA 2: Buscar links relativos (funciona para preços)
        $padraoLinkRelativo = '/href="(\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLinkRelativo, $html, $matchesRelativos);
        
        // Processar links absolutos primeiro
        $indiceKm = 0;
        foreach ($matchesAbsolutos[1] as $linkCompleto) {
            if (in_array($linkCompleto, $linksUnicos)) {
                continue;
            }
            $linksUnicos[] = $linkCompleto;
            
            $veiculo = $this->extrairDadosDoLink($html, $linkCompleto, $baseUrl);
            if ($veiculo && !empty($veiculo['nome'])) {
                // Associar KM pela ordem
                if (isset($todasKms[$indiceKm])) {
                    $veiculo['km'] = $todasKms[$indiceKm];
                }
                $veiculos[] = $veiculo;
                $indiceKm++;
            }
        }
        
        // Se não encontrou links absolutos, tentar relativos
        if (empty($veiculos)) {
            $indiceKm = 0;
            foreach ($matchesRelativos[1] as $linkRelativo) {
                $linkCompleto = $this->normalizarURL($linkRelativo, $baseUrl);
                
                if (in_array($linkCompleto, $linksUnicos)) {
                    continue;
                }
                $linksUnicos[] = $linkCompleto;
                
                $veiculo = $this->extrairDadosVeiculoCarrosP($html, $linkCompleto, $baseUrl);
                if ($veiculo && !empty($veiculo['nome'])) {
                    // Associar KM pela ordem
                    if (isset($todasKms[$indiceKm])) {
                        $veiculo['km'] = $todasKms[$indiceKm];
                    }
                    $veiculos[] = $veiculo;
                    $indiceKm++;
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
        
        // BUSCAR PREÇO usando múltiplas estratégias
        $veiculo['preco'] = $this->extrairPrecoMultiplasEstrategias($html, $linkVeiculo);
        
        // BUSCAR FOTO próxima ao link
        $linkBase = basename($linkVeiculo);
        $padroesFoto = [
            '/<img[^>]*src="([^"]*)"[^>]*alt="[^"]*' . preg_quote($linkBase, '/') . '[^"]*"/i',
            '/href="[^"]*' . preg_quote($linkBase, '/') . '[^"]*"[^>]*>.*?<img[^>]*src="([^"]*)"/is',
            '/<img[^>]*src="([^"]*)"[^>]*>.*?href="[^"]*' . preg_quote($linkBase, '/') . '[^"]*"/is',
            '/<img[^>]*src="([^"]*)"[^>]*>/i'
        ];
        
        foreach ($padroesFoto as $padraoFoto) {
            if (preg_match($padraoFoto, $html, $fotoMatch)) {
                $urlFoto = trim($fotoMatch[1]);
                if (!empty($urlFoto) && $urlFoto !== '#' && strpos($urlFoto, 'data:') !== 0) {
                    $veiculo['foto'] = $this->normalizarURL($urlFoto, $baseUrl);
                    break;
                }
            }
        }
        
        // KM será associada pela nova estratégia no método extrairCarrosPEspecifico
        
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
        
        // Buscar o bloco HTML que contém este link específico - VERSÃO MELHORADA
        $linkBase = basename($linkVeiculo);
        $blocoHtml = $this->buscarContextoDoLink($html, $linkBase);
        
        if (empty($blocoHtml)) {
            // Fallback: buscar por contexto mais amplo
            $posicaoLink = strpos($html, $linkBase);
            if ($posicaoLink !== false) {
                $inicio = max(0, $posicaoLink - 1500);
                $blocoHtml = substr($html, $inicio, 3000);
            } else {
                return null;
            }
        }
        
        // Extrair foto PRIMEIRO (mais próxima do link)
        $linkBase = basename($linkVeiculo);
        $padroesFoto = [
            '/<img[^>]*src="([^"]*)"[^>]*alt="[^"]*' . preg_quote($linkBase, '/') . '[^"]*"/i',
            '/href="[^"]*' . preg_quote($linkBase, '/') . '[^"]*"[^>]*>.*?<img[^>]*src="([^"]*)"/is',
            '/<img[^>]*src="([^"]*)"[^>]*>.*?href="[^"]*' . preg_quote($linkBase, '/') . '[^"]*"/is',
            '/<img[^>]*src="([^"]*)"[^>]*>/i'
        ];
        
        foreach ($padroesFoto as $padraoFoto) {
            if (preg_match($padraoFoto, $blocoHtml, $fotoMatch)) {
                $urlFoto = trim($fotoMatch[1]);
                if (!empty($urlFoto) && $urlFoto !== '#' && strpos($urlFoto, 'data:') !== 0) {
                    $veiculo['foto'] = $this->normalizarURL($urlFoto, $baseUrl);
                    break;
                }
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
        
        // Extrair informações específicas do CarrosP (câmbio, combustível, km) - BLOCO ESPECÍFICO
        if (preg_match('/<p[^>]*class="[^"]*info-geral[^"]*"[^>]*>([^<]+)<\/p>/i', $blocoHtml, $infoMatch)) {
            $infoTexto = trim($infoMatch[1]);
            $outrasInfos = $this->extrairInfosTexto($infoTexto);
            $veiculo = array_merge($veiculo, $outrasInfos);
        } else {
            // Fallback: buscar em todo o bloco HTML específico deste veículo
            $outrasInfos = $this->extrairInfosTexto($blocoHtml);
            $veiculo = array_merge($veiculo, $outrasInfos);
        }
        
        return $veiculo;
    }
    
    /**
     * Estratégia genérica de extração
     */
    private function extrairGenerico($html, $baseUrl) {
        $veiculos = [];
        
        // Criar DOMDocument para parsing
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Buscar cards de veículos
        $cards = $xpath->query("//div[contains(@class, 'card') or contains(@class, 'vehicle') or contains(@class, 'item')]");
        
        foreach ($cards as $card) {
            $veiculo = $this->extrairDadosCard($card, $xpath, $baseUrl);
            if ($veiculo && (!empty($veiculo['nome']) || !empty($veiculo['marca']))) {
                $veiculos[] = $veiculo;
            }
        }
        
        return $veiculos;
    }
    
    /**
     * Remove duplicados baseado no link único
     */
    private function removerDuplicados($veiculos) {
        $linksVistos = [];
        $veiculosUnicos = [];
        
        foreach ($veiculos as $veiculo) {
            $link = $veiculo['link'];
            
            // Se não tem link, usar nome como chave
            if (empty($link)) {
                $chave = md5(($veiculo['nome'] ?: '') . ($veiculo['marca'] ?: '') . $veiculo['preco'] . $veiculo['ano']);
            } else {
                $chave = $link;
            }
            
            if (!in_array($chave, $linksVistos)) {
                $linksVistos[] = $chave;
                $veiculosUnicos[] = $veiculo;
            }
        }
        
        return $veiculosUnicos;
    }
    
    /**
     * Extrai dados de um card individual
     */
    private function extrairDadosCard($card, $xpath, $baseUrl) {
        $veiculo = [
            'nome' => '',
            'preco' => 0,
            'ano' => null,
            'km' => null,
            'cambio' => '',
            'cor' => '',
            'combustivel' => '',
            'link' => '',
            'foto' => ''
        ];
        
        // Extrair nome/título
        $titulo = $xpath->query(".//h1 | .//h2 | .//h3 | .//h4 | .//a[contains(@class, 'title')] | .//*[contains(@class, 'title')] | .//*[contains(@class, 'name')]", $card);
        if ($titulo->length > 0) {
            $veiculo['versao'] = trim($titulo->item(0)->textContent);
        }
        
        // Extrair preço
        $preco = $xpath->query(".//*[contains(@class, 'price') or contains(@class, 'valor') or contains(text(), 'R$')]", $card);
        if ($preco->length > 0) {
            $precoTexto = $preco->item(0)->textContent;
            $veiculo['preco'] = $this->extrairPreco($precoTexto);
        }
        
        // Extrair link
        $link = $xpath->query(".//a[@href]", $card);
        if ($link->length > 0) {
            $href = $link->item(0)->getAttribute('href');
            $veiculo['link'] = $this->normalizarURL($href, $baseUrl);
        }
        
        // Extrair imagem
        $img = $xpath->query(".//img[@src]", $card);
        if ($img->length > 0) {
            $src = $img->item(0)->getAttribute('src');
            $veiculo['foto'] = $this->normalizarURL($src, $baseUrl);
        }
        
        // Extrair outras informações do texto
        $textoCompleto = $card->textContent;
        $veiculo = array_merge($veiculo, $this->extrairInfosTexto($textoCompleto));
        
        return $veiculo;
    }
    
    /**
     * Extração usando regex como fallback
     */
    private function extrairComRegex($html, $baseUrl) {
        $veiculos = [];
        
        // Padrões regex para diferentes elementos
        $padroes = [
            'cards' => '/<div[^>]*class="[^"]*(?:card|vehicle|item)[^"]*"[^>]*>(.*?)<\/div>/is',
            'links' => '/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is',
            'precos' => '/R\$\s*([0-9.,]+)/i',
            'anos' => '/\b(19|20)\d{2}\b/',
            'km' => '/(\d+(?:\.\d+)?)\s*(?:km|quilômetros?)/i'
        ];
        
        // Buscar por estruturas de veículos
        preg_match_all($padroes['cards'], $html, $matches);
        
        foreach ($matches[1] as $cardHtml) {
            $veiculo = $this->processarCardRegex($cardHtml, $baseUrl);
            if ($veiculo && (!empty($veiculo['nome']) || !empty($veiculo['marca']))) {
                $veiculos[] = $veiculo;
            }
        }
        
        return $veiculos;
    }
    
    /**
     * Processa um card usando regex
     */
    private function processarCardRegex($html, $baseUrl) {
        $veiculo = [
            'nome' => '',
            'preco' => 0,
            'ano' => null,
            'km' => null,
            'cambio' => '',
            'cor' => '',
            'combustivel' => '',
            'link' => '',
            'foto' => ''
        ];
        
        // Extrair título
        if (preg_match('/<(?:h[1-6]|title)[^>]*>(.*?)<\/(?:h[1-6]|title)>/is', $html, $matches)) {
            $veiculo['versao'] = trim(strip_tags($matches[1]));
        }
        
        // Extrair preço
        if (preg_match('/R\$\s*([0-9.,]+)/i', $html, $matches)) {
            $veiculo['preco'] = $this->extrairPreco($matches[0]);
        }
        
        // Extrair link
        if (preg_match('/<a[^>]*href="([^"]*)"[^>]*>/i', $html, $matches)) {
            $veiculo['link'] = $this->normalizarURL($matches[1], $baseUrl);
        }
        
        // Extrair imagem
        if (preg_match('/<img[^>]*src="([^"]*)"[^>]*>/i', $html, $matches)) {
            $veiculo['foto'] = $this->normalizarURL($matches[1], $baseUrl);
        }
        
        // Extrair outras informações
        $veiculo = array_merge($veiculo, $this->extrairInfosTexto(strip_tags($html)));
        
        return $veiculo;
    }
    
    /**
     * Extrai apenas KM do texto - VERSÃO CORRIGIDA PARA CARROSP
     */
    private function extrairInfosTexto($texto) {
        $info = [];
        
        // PADRÃO ESPECÍFICO CARROSP: "Aut. | Flex | 132.326 KM" ou "Aut. CVT | Flex | 67.887 KM"
        // Buscar por padrão: qualquer_coisa | qualquer_coisa | NÚMERO KM
        if (preg_match('/[^|]+\|\s*[^|]+\|\s*([\d.,]+)\s*KM/i', $texto, $matches)) {
            // Extrair a quilometragem (3º elemento após os separadores |)
            $kmTexto = trim($matches[1]);
            $km = str_replace(['.', ','], '', $kmTexto);
            if (is_numeric($km)) {
                $info['km'] = (int)$km;
            }
        } else {
            // FALLBACK: Padrões genéricos para KM
            
            // Ano
            if (preg_match('/\b(19|20)\d{2}\b/', $texto, $matches)) {
                $info['ano'] = (int)$matches[0];
            }
            
            // Quilometragem genérica
            if (preg_match('/([\d.,]+)\s*(?:km|quilômetros?)/i', $texto, $matches)) {
                $km = str_replace(['.', ','], '', $matches[1]);
                if (is_numeric($km)) {
                    $info['km'] = (int)$km;
                }
            }
        }
        
        // Cor (palavras comuns de cores)
        $cores = ['branco', 'preto', 'prata', 'cinza', 'azul', 'vermelho', 'verde', 'amarelo', 'dourado', 'bege'];
        foreach ($cores as $cor) {
            if (preg_match('/\b' . $cor . '\b/i', $texto)) {
                $info['cor'] = ucfirst($cor);
                break;
            }
        }
        
        return $info;
    }
    
    /**
     * NOVO MÉTODO: extrairMarcaModeloDoNome
     * Extrai marca e modelo de um nome completo
     */
    private function extrairMarcaModeloDoNome($nomeCompleto) {
        $marcas = [
            'FIAT', 'HONDA', 'CHEVROLET', 'FORD', 'VOLKSWAGEN', 'HYUNDAI',
            'TOYOTA', 'NISSAN', 'RENAULT', 'CHERY', 'PEUGEOT', 'CITROËN'
        ];
        
        foreach ($marcas as $marca) {
            if (stripos($nomeCompleto, $marca) !== false) {
                // Extrair modelo após a marca
                $pattern = '/' . $marca . '\s+([A-Za-z\s\-0-9]+?)(?=\s+\d{1,2}\.\d|\s+\d{4}|\s*$)/i';
                
                if (preg_match($pattern, $nomeCompleto, $matches)) {
                    $modelo = trim($matches[1]);
                    
                    // Limpar versões/especificações do modelo
                    $modelo = preg_replace('/\s+\d+\.\d+.*$/', '', $modelo);
                    $modelo = preg_replace('/\s+\d{1,2}V.*$/', '', $modelo);
                    $modelo = preg_replace('/\s+4P.*$/', '', $modelo);
                    
                    return [
                        'marca' => $marca,
                        'modelo' => $modelo,
                        'nome' => $marca . ' ' . $modelo
                    ];
                }
                
                // Fallback: pegar tudo após a marca até encontrar número
                $pos = stripos($nomeCompleto, $marca);
                $resto = trim(substr($nomeCompleto, $pos + strlen($marca)));
                
                if (preg_match('/^([A-Za-z\s\-]+?)(?=\s+\d|\s*$)/', $resto, $matches)) {
                    $modelo = trim($matches[1]);
                    
                    if (!empty($modelo)) {
                        return [
                            'marca' => $marca,
                            'modelo' => $modelo,
                            'nome' => $marca . ' ' . $modelo
                        ];
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * NOVO MÉTODO: extrairMarcaModeloDoLink
     * Extrai marca e modelo da estrutura do link
     */
    private function extrairMarcaModeloDoLink($link) {
        // Padrão: /comprar/tipo/marca/modelo/versao/ano/id/
        if (preg_match('/\/comprar\/([^\/]+)\/([^\/]+)\/([^\/]+)\//', $link, $matches)) {
            $tipo = $matches[1];
            $marcaSlug = $matches[2];
            $modeloSlug = $matches[3];
            
            // Usar slugs como estão, apenas formatando
            $marca = strtoupper(str_replace('-', ' ', $marcaSlug));
            $modelo = ucwords(str_replace('-', ' ', $modeloSlug));
            
            return [
                'marca' => $marca,
                'modelo' => $modelo,
                'nome' => $marca . ' ' . $modelo,
                'tipo' => ucfirst($tipo)
            ];
        }
        
        return null;
    }
    
    /**
     * NOVO MÉTODO: Extrai combustível e câmbio da versão do veículo
     */
    private function extrairCombustivelCambioVersao($versao) {
        $info = [
            'combustivel' => '',
            'cambio' => 'Manual' // Default
        ];
        
        // Extrair combustível
        if (stripos($versao, 'Flex') !== false) {
            $info['combustivel'] = 'Flex';
        } elseif (stripos($versao, 'Gasolina') !== false) {
            $info['combustivel'] = 'Gasolina';
        } elseif (stripos($versao, 'Diesel') !== false) {
            $info['combustivel'] = 'Diesel';
        }
        
        // Extrair câmbio - ORDEM IMPORTANTE: CVT primeiro, depois Automático
        if (stripos($versao, 'CVT') !== false) {
            $info['cambio'] = 'Automático CVT';
        } elseif (stripos($versao, 'Automatico') !== false || stripos($versao, 'Automático') !== false) {
            $info['cambio'] = 'Automático';
        } elseif (stripos($versao, 'Manual') !== false) {
            $info['cambio'] = 'Manual';
        }
        // Se não encontrar nenhum, mantém o default 'Manual'
        
        return $info;
    }
    
    /**
     * NOVO MÉTODO: buscarContextoDoLink - VERSÃO MELHORADA
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
     * NOVO MÉTODO: Extrai KM do JSON-LD estruturado
     */
    private function extrairKmDoJsonLD($html, $linkBase) {
        // Buscar por JSON-LD que contenha o ID do veículo
        if (preg_match('/"vehicleIdentificationNumber":"[^"]*' . preg_quote($linkBase, '/') . '"[^}]*"mileageFromOdometer":\{"@type":"QuantitativeValue","value":"(\d+)"/i', $html, $matches)) {
            return (int)$matches[1];
        }
        
        return null;
    }
    
    /**
     * NOVO MÉTODO: Extrai todas as KMs do HTML na ordem que aparecem
     */
    private function extrairTodasKmsDoHtml($html) {
        $kms = [];
        
        // Buscar padrão CarrosP: "Man. | Flex | 43.302 KM"
        if (preg_match_all('/([A-Za-z]+\.?)\s*\|\s*([A-Za-z]+)\s*\|\s*([\d.,]+)\s*KM/i', $html, $matches)) {
            foreach ($matches[3] as $kmTexto) {
                $km = str_replace(['.', ','], '', trim($kmTexto));
                if (is_numeric($km)) {
                    $kms[] = (int)$km;
                }
            }
        }
        
        return $kms;
    }
    
    /**
     * NOVO MÉTODO: Extrai KM por proximidade no HTML
     */
    private function extrairKmPorProximidade($html, $linkBase) {
        // Buscar todas as posições onde o ID do veículo aparece
        $posicoes = [];
        $offset = 0;
        while (($pos = strpos($html, $linkBase, $offset)) !== false) {
            $posicoes[] = $pos;
            $offset = $pos + 1;
        }
        
        // Para cada posição, buscar por padrões de KM próximos
        foreach ($posicoes as $pos) {
            // Buscar em um contexto de 1000 caracteres ao redor
            $inicio = max(0, $pos - 500);
            $contexto = substr($html, $inicio, 1000);
            
            // Buscar padrão CarrosP: "Man. | Flex | 43.302 KM"
            if (preg_match('/([A-Za-z]+\.?)\s*\|\s*([A-Za-z]+)\s*\|\s*([\d.,]+)\s*KM/i', $contexto, $matches)) {
                $km = str_replace(['.', ','], '', trim($matches[3]));
                if (is_numeric($km)) {
                    return (int)$km;
                }
            }
        }
        
        return null;
    }
    
    /**
     * MÉTODO MELHORADO: extrairPrecoMelhorado
     */
    private function extrairPrecoMelhorado($contexto) {
        // Padrões específicos para CarrosP
        $padroes = [
            '/R\$\s*([0-9]{2,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',  // R$ 78.990,00
            '/([0-9]{2,3}\.[0-9]{3})/i',                            // 78.990
            '/Valor[^0-9]*([0-9]{2,3}(?:\.[0-9]{3})*)/i'          // Valor ... 78.990
        ];
        
        foreach ($padroes as $padrao) {
            if (preg_match_all($padrao, $contexto, $matches)) {
                foreach ($matches[1] as $match) {
                    $preco = $this->extrairPreco($match);
                    // Validar se é um preço realista para veículo
                    if ($preco >= 5000 && $preco <= 500000) {
                        return $preco;
                    }
                }
            }
        }
        
        return 0;
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
     * Extrai preço usando XPath específico do CarrosP
     */
    private function extrairPrecoComXPath($html, $linkVeiculo) {
        try {
            // Criar DOMDocument
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Buscar por elementos que contêm "Valor" seguido de "R$"
            $precoNodes = $xpath->query('//text()[contains(., "Valor")]/following-sibling::text()[contains(., "R$")] | //text()[contains(., "Valor")]/parent::*/following-sibling::*/text()[contains(., "R$")]');
            
            foreach ($precoNodes as $node) {
                $precoTexto = trim($node->textContent);
                if (preg_match('/R\$\s*([0-9.,]+)/', $precoTexto, $matches)) {
                    $preco = $this->extrairPreco($matches[1]);
                    if ($preco >= 5000 && $preco <= 500000) {
                        return $preco;
                    }
                }
            }
            
            // Buscar diretamente por texto "R$ valor" em qualquer lugar
            $precoGenerico = $xpath->query('//text()[contains(., "R$")]');
            
            foreach ($precoGenerico as $node) {
                $precoTexto = trim($node->textContent);
                if (preg_match('/R\$\s*([0-9]{2,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i', $precoTexto, $matches)) {
                    $preco = $this->extrairPreco($matches[1]);
                    if ($preco >= 5000 && $preco <= 500000) {
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
     * Extrai preço específico do CarrosP com múltiplas estratégias
     */
    private function extrairPrecoCarrosP($html) {
        // Estratégia 1: Buscar diretamente por números no formato XXX.XXX (mais simples e eficaz)
        if (preg_match_all('/([0-9]{2,3}\.[0-9]{3})/', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $preco = $this->extrairPreco($match);
                if ($preco >= 5000 && $preco <= 500000) {
                    return $preco;
                }
            }
        }
        
        // Estratégia 2: Padrão específico do CarrosP - <span class="rs">R$</span> 78.990
        $padroes = [
            '/<span[^>]*class="rs"[^>]*>R\$<\/span>\s*([0-9]{2,3}(?:\.[0-9]{3})*)/i', // <span class="rs">R$</span> 78.990
            '/R\$<\/span>\s*([0-9]{2,3}(?:\.[0-9]{3})*)/i',                          // R$</span> 78.990
            '/>\s*([0-9]{2,3}\.[0-9]{3})\s*<\/p>/i',                                 // > 78.990</p>
        ];
        
        foreach ($padroes as $padrao) {
            if (preg_match_all($padrao, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    $preco = $this->extrairPreco($match);
                    if ($preco >= 5000 && $preco <= 500000) {
                        return $preco;
                    }
                }
            }
        }
        
        // Estratégia 3: Buscar preços no contexto específico do card-info
        if (preg_match_all('/card-info[^>]*>.*?([0-9]{2,3}\.[0-9]{3})/is', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $preco = $this->extrairPreco($match);
                if ($preco >= 5000 && $preco <= 500000) {
                    return $preco;
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Extrai valor numérico do preço
     */
    private function extrairPreco($texto) {
        // Remover tudo exceto números, pontos e vírgulas
        $numero = preg_replace('/[^\d,.]/', '', $texto);
        
        // Se tem vírgula, assumir que é separador decimal brasileiro
        if (strpos($numero, ',') !== false) {
            // Se tem ponto antes da vírgula, o ponto é separador de milhares
            if (strpos($numero, '.') !== false && strpos($numero, '.') < strpos($numero, ',')) {
                $numero = str_replace('.', '', $numero); // Remove separador de milhares
                $numero = str_replace(',', '.', $numero); // Vírgula vira ponto decimal
            } else {
                // Apenas vírgula, trocar por ponto
                $numero = str_replace(',', '.', $numero);
            }
        } else if (substr_count($numero, '.') == 1) {
            // Um ponto apenas - pode ser decimal ou separador de milhares
            $partes = explode('.', $numero);
            if (strlen($partes[1]) == 3 && strlen($partes[0]) <= 3) {
                // Formato XXX.XXX = separador de milhares, não decimal
                $numero = $partes[0] . $partes[1]; // Remove o ponto
            }
            // Se não, mantém como decimal
        } else if (substr_count($numero, '.') > 1) {
            // Múltiplos pontos = separadores de milhares
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
    
    /**
     * Remove veículos antigos do cliente
     */
    private function desativarVeiculosAntigos($clienteId) {
        $stmt = $this->pdo->prepare("DELETE FROM veiculos WHERE cliente_id = ?");
        $stmt->execute([$clienteId]);
    }
    
    /**
     * Insere novos veículos no banco (com controle de duplicados) - VERSÃO CORRIGIDA PARA ESTRUTURA REAL
     */
    private function inserirVeiculos($clienteId, $veiculos) {
        // Preparar statement para verificar se já existe
        $stmtCheck = $this->pdo->prepare("
            SELECT id FROM veiculos
            WHERE cliente_id = ? AND link = ?
        ");
        
        // Preparar statement para inserção (usando campos corretos da tabela)
        $stmtInsert = $this->pdo->prepare("
            INSERT INTO veiculos (cliente_id, versao, marca_modelo, preco, ano, km, cambio, cor, combustivel, link, foto, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
        ");
        
        // Preparar statement para atualização (reativar)
        $stmtUpdate = $this->pdo->prepare("
            UPDATE veiculos SET
                versao = ?, marca_modelo = ?, preco = ?, ano = ?, km = ?, cambio = ?, cor = ?,
                combustivel = ?, foto = ?, ativo = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE cliente_id = ? AND link = ?
        ");
        
        $inseridos = 0;
        $atualizados = 0;
        
        foreach ($veiculos as $veiculo) {
            try {
                // Garantir que o nome/versão não esteja vazio
                $versao = !empty($veiculo['nome']) ? $veiculo['nome'] :
                         (!empty($veiculo['marca']) && !empty($veiculo['modelo']) ?
                          $veiculo['marca'] . ' ' . $veiculo['modelo'] : 'Veículo sem nome');
                
                // Criar marca_modelo
                $marcaModelo = '';
                if (!empty($veiculo['marca']) && !empty($veiculo['modelo'])) {
                    $marcaModelo = $veiculo['marca'] . ' ' . $veiculo['modelo'];
                } elseif (!empty($veiculo['marca'])) {
                    $marcaModelo = $veiculo['marca'];
                } elseif (!empty($veiculo['modelo'])) {
                    $marcaModelo = $veiculo['modelo'];
                }
                
                // Formatar preço como string
                $precoStr = '';
                if (!empty($veiculo['preco']) && $veiculo['preco'] > 0) {
                    $precoStr = 'R$ ' . number_format($veiculo['preco'], 2, ',', '.');
                }
                
                // NOVA ESTRATÉGIA: Extrair combustível e câmbio da versão
                $infosVersao = $this->extrairCombustivelCambioVersao($versao);
                $combustivel = $infosVersao['combustivel'];
                $cambio = $infosVersao['cambio'];
                
                // Garantir que KM seja um valor válido
                $kmValue = isset($veiculo['km']) && is_numeric($veiculo['km']) && $veiculo['km'] > 0 ? (int)$veiculo['km'] : null;
                
                // Garantir que o link não esteja vazio
                if (empty($veiculo['link'])) {
                    error_log("Veículo sem link ignorado: " . $versao);
                    continue;
                }
                
                // Verificar se já existe um veículo com este link
                $stmtCheck->execute([$clienteId, $veiculo['link']]);
                $existente = $stmtCheck->fetch();
                
                if ($existente) {
                    // Atualizar veículo existente
                    $stmtUpdate->execute([
                        $versao,
                        $marcaModelo,
                        $precoStr,
                        $veiculo['ano'] ?: null,
                        $kmValue,
                        $cambio, // Usar valor extraído da versão
                        $veiculo['cor'] ?: '',
                        $combustivel, // Usar valor extraído da versão
                        $veiculo['foto'] ?: '',
                        $clienteId,
                        $veiculo['link']
                    ]);
                    $atualizados++;
                } else {
                    // Inserir novo veículo
                    $stmtInsert->execute([
                        $clienteId,
                        $versao,
                        $marcaModelo,
                        $precoStr,
                        $veiculo['ano'] ?: null,
                        $kmValue,
                        $cambio, // Usar valor extraído da versão
                        $veiculo['cor'] ?: '',
                        $combustivel, // Usar valor extraído da versão
                        $veiculo['link'],
                        $veiculo['foto'] ?: ''
                    ]);
                    $inseridos++;
                }
            } catch (Exception $e) {
                // Log do erro com mais detalhes
                error_log("Erro ao processar veículo '{$versao}': " . $e->getMessage());
                error_log("Dados do veículo: " . json_encode($veiculo));
            }
        }
        
        // Atualizar timestamp de última sincronização do cliente
        try {
            $stmtUpdateCliente = $this->pdo->prepare("
                UPDATE clientes SET ultima_sincronizacao = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmtUpdateCliente->execute([$clienteId]);
        } catch (Exception $e) {
            error_log("Erro ao atualizar timestamp do cliente: " . $e->getMessage());
        }
        
        return $inseridos + $atualizados;
    }
    
    /**
     * Busca veículos de um cliente
     */
    public function buscarVeiculosCliente($clienteId, $apenasAtivos = true) {
        $sql = "SELECT * FROM veiculos WHERE cliente_id = ?";
        $params = [$clienteId];
        
        if ($apenasAtivos) {
            $sql .= " AND ativo = TRUE";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Estatísticas do estoque - CORRIGIDO PARA NOVA ESTRUTURA
     */
    public function estatisticasEstoque($clienteId) {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN ativo = TRUE THEN 1 END) as ativos,
                MAX(updated_at) as ultima_atualizacao
            FROM veiculos
            WHERE cliente_id = ?
        ");
        
        $stmt->execute([$clienteId]);
        $stats = $stmt->fetch();
        
        // Para preços, vamos calcular manualmente já que agora são strings
        $stmt = $this->pdo->prepare("
            SELECT preco
            FROM veiculos
            WHERE cliente_id = ? AND ativo = TRUE AND preco IS NOT NULL AND preco != ''
        ");
        $stmt->execute([$clienteId]);
        $precos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $precosNumericos = [];
        foreach ($precos as $preco) {
            // Extrair valor numérico do formato "R$ 78.990,00"
            if (preg_match('/R\$\s*([0-9.,]+)/', $preco, $matches)) {
                $numero = str_replace(['.', ','], ['', '.'], $matches[1]);
                if (is_numeric($numero)) {
                    $precosNumericos[] = (float)$numero;
                }
            }
        }
        
        if (!empty($precosNumericos)) {
            $stats['preco_medio'] = array_sum($precosNumericos) / count($precosNumericos);
            $stats['preco_min'] = min($precosNumericos);
            $stats['preco_max'] = max($precosNumericos);
        } else {
            $stats['preco_medio'] = 0;
            $stats['preco_min'] = 0;
            $stats['preco_max'] = 0;
        }
        
        return $stats;
    }
}

// Função para uso via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado']);
        exit;
    }
    
    $importador = new ImportadorEstoque();
    
    switch ($_POST['action']) {
        case 'importar':
            $clienteId = $_POST['cliente_id'] ?? null;
            $url = $_POST['url'] ?? '';
            
            if (!$clienteId || !$url) {
                echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros obrigatórios não fornecidos']);
                exit;
            }
            
            $resultado = $importador->importarEstoque($clienteId, $url);
            echo json_encode($resultado);
            break;
            
        case 'listar':
            $clienteId = $_POST['cliente_id'] ?? null;
            if (!$clienteId) {
                echo json_encode(['sucesso' => false, 'erro' => 'Cliente ID obrigatório']);
                exit;
            }
            
            $veiculos = $importador->buscarVeiculosCliente($clienteId);
            echo json_encode(['sucesso' => true, 'veiculos' => $veiculos]);
            break;
            
        case 'estatisticas':
            $clienteId = $_POST['cliente_id'] ?? null;
            if (!$clienteId) {
                echo json_encode(['sucesso' => false, 'erro' => 'Cliente ID obrigatório']);
                exit;
            }
            
            $stats = $importador->estatisticasEstoque($clienteId);
            echo json_encode(['sucesso' => true, 'estatisticas' => $stats]);
            break;
    }
    exit;
}
?>
    