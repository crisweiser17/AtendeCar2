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
        
        // NOVA ESTRATÉGIA: Extrair diretamente das imagens com ALT
        $veiculos = $this->extrairVeiculosPorImagem($html, $baseUrl);
        
        // Se não encontrou, tentar estratégia genérica
        if (empty($veiculos)) {
            $veiculos = $this->extrairGenerico($html, $baseUrl);
        }
        
        // Remover duplicados baseado no nome único
        $veiculos = $this->removerDuplicados($veiculos);
        
        return $veiculos;
    }
    
    /**
     * NOVA ESTRATÉGIA: Extração baseada nas imagens com ALT
     */
    private function extrairVeiculosPorImagem($html, $baseUrl) {
        $veiculos = [];
        
        // Buscar todas as imagens com marcas no ALT
        $padraoImagem = '/<img[^>]*alt="([^"]*(?:FIAT|HONDA|CHEVROLET|FORD|VOLKSWAGEN|HYUNDAI|TOYOTA|NISSAN|RENAULT|CHERY)[^"]*)"[^>]*>/i';
        
        if (preg_match_all($padraoImagem, $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $match) {
                $nomeCompleto = trim($match[0]);
                $posicao = $match[1];
                
                // Extrair contexto ao redor da imagem (2000 caracteres antes e depois)
                $inicio = max(0, $posicao - 2000);
                $contexto = substr($html, $inicio, 4000);
                
                // Criar veículo base
                $veiculo = [
                    'nome' => $nomeCompleto,
                    'marca' => '',
                    'modelo' => '',
                    'preco' => 0,
                    'ano' => null,
                    'km' => null,
                    'cambio' => '',
                    'cor' => '',
                    'combustivel' => '',
                    'link' => '',
                    'foto' => ''
                ];
                
                // Extrair marca e modelo do nome
                $marcaModelo = $this->extrairMarcaModeloDoNome($nomeCompleto);
                if ($marcaModelo) {
                    $veiculo['marca'] = $marcaModelo['marca'];
                    $veiculo['modelo'] = $marcaModelo['modelo'];
                }
                
                // Buscar link no contexto
                $veiculo['link'] = $this->buscarLinkNoContexto($contexto, $baseUrl);
                
                // Buscar preço no contexto
                $veiculo['preco'] = $this->buscarPrecoNoContexto($contexto);
                
                // Extrair outras informações do contexto
                $outrasInfos = $this->extrairInfosTexto($contexto);
                $veiculo = array_merge($veiculo, $outrasInfos);
                
                // Buscar foto no contexto
                $veiculo['foto'] = $this->buscarFotoNoContexto($contexto, $baseUrl);
                
                // Validar se o veículo tem dados mínimos
                if (!empty($veiculo['nome']) && !empty($veiculo['marca'])) {
                    $veiculos[] = $veiculo;
                }
            }
        }
        
        return $veiculos;
    }
    
    /**
     * Busca link no contexto ao redor da imagem
     */
    private function buscarLinkNoContexto($contexto, $baseUrl) {
        // Padrões para diferentes tipos de links
        $padroes = [
            '/href="([^"]*\/comprar\/[^"]*)"/i',           // Links /comprar/
            '/href="([^"]*\/veiculo\/[^"]*)"/i',           // Links /veiculo/
            '/href="([^"]*\/anuncio\/[^"]*)"/i',           // Links /anuncio/
            '/href="([^"]*\/detalhes\/[^"]*)"/i',          // Links /detalhes/
            '/href="([^"]*carrosp\.com\.br[^"]*)"/i'       // Qualquer link do carrosp
        ];
        
        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $contexto, $matches)) {
                return $this->normalizarURL($matches[1], $baseUrl);
            }
        }
        
        // Se não encontrou link específico, gerar um link genérico
        return $baseUrl;
    }
    
    /**
     * Busca preço no contexto ao redor da imagem
     */
    private function buscarPrecoNoContexto($contexto) {
        // Padrões específicos para preços
        $padroes = [
            '/R\$\s*([0-9]{2,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',  // R$ 78.990,00
            '/([0-9]{2,3}\.[0-9]{3})/i',                            // 78.990
            '/Valor[^0-9]*([0-9]{2,3}(?:\.[0-9]{3})*)/i',          // Valor ... 78.990
            '/Pre[çc]o[^0-9]*([0-9]{2,3}(?:\.[0-9]{3})*)/i'       // Preço ... 78.990
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
     * Busca foto no contexto ao redor da imagem
     */
    private function buscarFotoNoContexto($contexto, $baseUrl) {
        if (preg_match('/<img[^>]*src="([^"]*)"[^>]*>/i', $contexto, $matches)) {
            return $this->normalizarURL($matches[1], $baseUrl);
        }
        
        return '';
    }
    
    /**
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
     * Extrai dados de um card individual
     */
    private function extrairDadosCard($card, $xpath, $baseUrl) {
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
     * Remove duplicados baseado no nome único
     */
    private function removerDuplicados($veiculos) {
        $nomesVistos = [];
        $veiculosUnicos = [];
        
        foreach ($veiculos as $veiculo) {
            $chave = $veiculo['link'] ?: md5($veiculo['nome'] . $veiculo['marca'] . $veiculo['preco']);
            
            if (!in_array($chave, $nomesVistos)) {
                $nomesVistos[] = $chave;
                $veiculosUnicos[] = $veiculo;
            }
        }
        
        return $veiculosUnicos;
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
            WHERE cliente_id = ? AND (link = ? OR (nome = ? AND marca = ?))
        ");
        
        // Preparar statement para inserção
        $stmtInsert = $this->pdo->prepare("
            INSERT INTO veiculos (cliente_id, nome, marca, modelo, preco, ano, km, cambio, cor, combustivel, link, foto, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
        ");
        
        // Preparar statement para atualização (reativar)
        $stmtUpdate = $this->pdo->prepare("
            UPDATE veiculos SET
                nome = ?, marca = ?, modelo = ?, preco = ?, ano = ?, km = ?, cambio = ?, cor = ?,
                combustivel = ?, foto = ?, ativo = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE cliente_id = ? AND (link = ? OR (nome = ? AND marca = ?))
        ");
        
        $inseridos = 0;
        $atualizados = 0;
        
        foreach ($veiculos as $veiculo) {
            try {
                // Verificar se já existe um veículo com este link ou nome+marca
                $stmtCheck->execute([
                    $clienteId, 
                    $veiculo['link'], 
                    $veiculo['nome'], 
                    $veiculo['marca']
                ]);
                $existente = $stmtCheck->fetch();
                
                if ($existente) {
                    // Atualizar veículo existente
                    $stmtUpdate->execute([
                        $veiculo['nome'],
                        $veiculo['marca'],
                        $veiculo['modelo'],
                        $veiculo['preco'],
                        $veiculo['ano'],
                        $veiculo['km'],
                        $veiculo['cambio'],
                        $veiculo['cor'],
                        $veiculo['combustivel'],
                        $veiculo['foto'],
                        $clienteId,
                        $veiculo['link'],
                        $veiculo['nome'],
                        $veiculo['marca']
                    ]);
                    $atualizados++;
                } else {
                    // Inserir novo veículo
                    $stmtInsert->execute([
                        $clienteId,
                        $veiculo['nome'],
                        $veiculo['marca'],
                        $veiculo['modelo'],
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