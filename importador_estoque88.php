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
     * Importa estoque de um cliente específico
     */
    public function importarEstoque($clienteId, $url) {
        try {
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
     * Extração específica para CarrosP.com.br
     */
    private function extrairCarrosPEspecifico($html, $baseUrl) {
        $veiculos = [];
        $linksUnicos = [];
        
        // Padrão específico para links de veículos do CarrosP
        $padraoLink = '/href="(\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLink, $html, $matchesLinks);
        
        foreach ($matchesLinks[1] as $linkRelativo) {
            $linkCompleto = $this->normalizarURL($linkRelativo, $baseUrl);
            
            // Evitar duplicados pelo link
            if (in_array($linkCompleto, $linksUnicos)) {
                continue;
            }
            $linksUnicos[] = $linkCompleto;
            
            // Extrair dados específicos para este link
            $veiculo = $this->extrairDadosVeiculoCarrosP($html, $linkCompleto, $baseUrl);
            if ($veiculo && !empty($veiculo['nome']) && !empty($veiculo['link'])) {
                $veiculos[] = $veiculo;
            }
        }
        
        return $veiculos;
    }
    
    /**
     * Extrai dados de um veículo específico do CarrosP
     */
    private function extrairDadosVeiculoCarrosP($html, $linkVeiculo, $baseUrl) {
        $veiculo = [
            'nome' => '',
            'preco' => 0,
            'ano' => null,
            'km' => null,
            'cambio' => '',
            'cor' => '',
            'combustivel' => '',
            'link' => $linkVeiculo,
            'foto' => ''
        ];
        
        // Escapar o link para usar em regex
        $linkEscapado = preg_quote($linkVeiculo, '/');
        
        // Buscar o bloco HTML que contém este link específico
        $padraoBloco = '/(<[^>]*href="[^"]*' . preg_quote(basename($linkVeiculo), '/') . '[^"]*"[^>]*>.*?<\/[^>]+>)/is';
        
        // Tentar encontrar um bloco maior que contenha as informações
        $padraoContainer = '/(<div[^>]*>(?:[^<]|<(?!\/div>))*?href="[^"]*' . preg_quote(basename($linkVeiculo), '/') . '[^"]*"(?:[^<]|<(?!\/div>))*?<\/div>)/is';
        
        if (preg_match($padraoContainer, $html, $matches)) {
            $blocoHtml = $matches[1];
        } else {
            // Fallback: buscar em um contexto maior
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
            }
        }
        
        // Extrair preço usando XPath específico
        $veiculo['preco'] = $this->extrairPrecoComXPath($html, $linkVeiculo);
        
        // Se não encontrou com XPath, tentar estratégias antigas
        if ($veiculo['preco'] == 0) {
            $veiculo['preco'] = $this->extrairPrecoCarrosP($blocoHtml);
        }
        
        // Extrair imagem
        if (preg_match('/<img[^>]*src="([^"]*)"[^>]*>/i', $blocoHtml, $matches)) {
            $veiculo['foto'] = $this->normalizarURL($matches[1], $baseUrl);
        }
        
        // Extrair outras informações do texto
        $textoLimpo = strip_tags($blocoHtml);
        $veiculo = array_merge($veiculo, $this->extrairInfosTexto($textoLimpo));
        
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
            if ($veiculo && !empty($veiculo['nome'])) {
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
                $chave = md5($veiculo['nome'] . $veiculo['preco'] . $veiculo['ano']);
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
            $veiculo['nome'] = trim($titulo->item(0)->textContent);
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
            if ($veiculo && !empty($veiculo['nome'])) {
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
            $veiculo['nome'] = trim(strip_tags($matches[1]));
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
     * Extrai informações do texto (ano, km, câmbio, etc.)
     */
    private function extrairInfosTexto($texto) {
        $info = [];
        
        // Ano
        if (preg_match('/\b(19|20)\d{2}\b/', $texto, $matches)) {
            $info['ano'] = (int)$matches[0];
        }
        
        // Quilometragem
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:km|quilômetros?)/i', $texto, $matches)) {
            $info['km'] = (int)str_replace('.', '', $matches[1]);
        }
        
        // Câmbio
        if (preg_match('/\b(manual|automático|automática|cvt|automatizado)\b/i', $texto, $matches)) {
            $info['cambio'] = ucfirst(strtolower($matches[1]));
        }
        
        // Combustível
        if (preg_match('/\b(flex|gasolina|álcool|diesel|híbrido|elétrico)\b/i', $texto, $matches)) {
            $info['combustivel'] = ucfirst(strtolower($matches[1]));
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
        // Estratégia 1: Padrão específico do CarrosP - <span class="rs">R$</span> 78.990
        $padroes = [
            '/<span[^>]*class="rs"[^>]*>R\$<\/span>\s*([0-9]{2,3}(?:\.[0-9]{3})*)/i', // <span class="rs">R$</span> 78.990
            '/R\$<\/span>\s*([0-9]{2,3}(?:\.[0-9]{3})*)/i',                          // R$</span> 78.990
            '/>\s*([0-9]{2,3}\.[0-9]{3})\s*<\/p>/i',                                 // > 78.990</p>
            '/([0-9]{2,3}\.[0-9]{3})/i',                                             // 78.990 (simples)
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
        
        // Estratégia 2: Buscar preços no contexto específico do card-info
        if (preg_match_all('/card-info[^>]*>.*?([0-9]{2,3}\.[0-9]{3})/is', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $preco = $this->extrairPreco($match);
                if ($preco >= 5000 && $preco <= 500000) {
                    return $preco;
                }
            }
        }
        
        // Estratégia 3: Buscar qualquer número no formato XXX.XXX
        if (preg_match_all('/([0-9]{2,3}\.[0-9]{3})/', $html, $matches)) {
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
     * Desativa veículos antigos do cliente
     */
    private function desativarVeiculosAntigos($clienteId) {
        $stmt = $this->pdo->prepare("UPDATE veiculos SET ativo = FALSE WHERE cliente_id = ?");
        $stmt->execute([$clienteId]);
    }
    
    /**
     * Insere novos veículos no banco (com controle de duplicados)
     */
    private function inserirVeiculos($clienteId, $veiculos) {
        // Preparar statement para verificar se já existe
        $stmtCheck = $this->pdo->prepare("
            SELECT id FROM veiculos
            WHERE cliente_id = ? AND link = ?
        ");
        
        // Preparar statement para inserção
        $stmtInsert = $this->pdo->prepare("
            INSERT INTO veiculos (cliente_id, nome, preco, ano, km, cambio, cor, combustivel, link, foto, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
        ");
        
        // Preparar statement para atualização (reativar)
        $stmtUpdate = $this->pdo->prepare("
            UPDATE veiculos SET
                nome = ?, preco = ?, ano = ?, km = ?, cambio = ?, cor = ?,
                combustivel = ?, foto = ?, ativo = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE cliente_id = ? AND link = ?
        ");
        
        $inseridos = 0;
        $atualizados = 0;
        
        foreach ($veiculos as $veiculo) {
            try {
                // Verificar se já existe um veículo com este link
                $stmtCheck->execute([$clienteId, $veiculo['link']]);
                $existente = $stmtCheck->fetch();
                
                if ($existente) {
                    // Atualizar veículo existente
                    $stmtUpdate->execute([
                        $veiculo['nome'],
                        $veiculo['preco'],
                        $veiculo['ano'],
                        $veiculo['km'],
                        $veiculo['cambio'],
                        $veiculo['cor'],
                        $veiculo['combustivel'],
                        $veiculo['foto'],
                        $clienteId,
                        $veiculo['link']
                    ]);
                    $atualizados++;
                } else {
                    // Inserir novo veículo
                    $stmtInsert->execute([
                        $clienteId,
                        $veiculo['nome'],
                        $veiculo['preco'],
                        $veiculo['ano'],
                        $veiculo['km'],
                        $veiculo['cambio'],
                        $veiculo['cor'],
                        $veiculo['combustivel'],
                        $veiculo['link'],
                        $veiculo['foto']
                    ]);
                    $inseridos++;
                }
            } catch (Exception $e) {
                // Log do erro, mas continua com os outros
                error_log("Erro ao processar veículo: " . $e->getMessage());
            }
        }
        
        return $inseridos;
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
     * Estatísticas do estoque
     */
    public function estatisticasEstoque($clienteId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN ativo = TRUE THEN 1 END) as ativos,
                AVG(preco) as preco_medio,
                MIN(preco) as preco_min,
                MAX(preco) as preco_max,
                MAX(updated_at) as ultima_atualizacao
            FROM veiculos 
            WHERE cliente_id = ?
        ");
        
        $stmt->execute([$clienteId]);
        return $stmt->fetch();
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