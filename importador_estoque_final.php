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
     * Extrai veículos da página usando cURL
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
     * Parse HTML para extrair dados dos veículos
     */
    private function parseHTML($html, $baseUrl) {
        $veiculos = [];
        
        // ESTRATÉGIA CORRIGIDA: Buscar links absolutos do CarrosP
        $veiculos = $this->extrairCarrosPCorrigido($html, $baseUrl);
        
        // Remover duplicados baseado no link único
        $veiculos = $this->removerDuplicados($veiculos);
        
        return $veiculos;
    }
    
    /**
     * MÉTODO CORRIGIDO: Extração usando links absolutos
     */
    private function extrairCarrosPCorrigido($html, $baseUrl) {
        $veiculos = [];
        $linksUnicos = [];
        
        // REGEX CORRIGIDA: Buscar links absolutos do CarrosP
        $padraoLink = '/href="(https:\/\/carrosp\.com\.br\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLink, $html, $matchesLinks);
        
        foreach ($matchesLinks[1] as $linkCompleto) {
            // Evitar duplicados pelo link
            if (in_array($linkCompleto, $linksUnicos)) {
                continue;
            }
            $linksUnicos[] = $linkCompleto;
            
            // Extrair dados do veículo usando o link e contexto
            $veiculo = $this->extrairDadosVeiculoDoLink($html, $linkCompleto, $baseUrl);
            if ($veiculo && !empty($veiculo['nome']) && !empty($veiculo['marca'])) {
                $veiculos[] = $veiculo;
            }
        }
        
        return $veiculos;
    }
    
    /**
     * NOVO MÉTODO: Extrai dados do veículo usando o link e contexto
     */
    private function extrairDadosVeiculoDoLink($html, $linkVeiculo, $baseUrl) {
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
        
        // ESTRATÉGIA 1: Extrair dados da estrutura do link
        $dadosLink = $this->extrairDadosDoLinkEstrutura($linkVeiculo);
        if ($dadosLink) {
            $veiculo = array_merge($veiculo, $dadosLink);
        }
        
        // ESTRATÉGIA 2: Buscar contexto ao redor do link no HTML
        $contexto = $this->buscarContextoDoLink($html, $linkVeiculo);
        if ($contexto) {
            // Buscar preço no contexto
            $preco = $this->buscarPrecoNoContexto($contexto);
            if ($preco > 0) {
                $veiculo['preco'] = $preco;
            }
            
            // Buscar outras informações no contexto
            $outrasInfos = $this->extrairInfosTexto($contexto);
            $veiculo = array_merge($veiculo, $outrasInfos);
            
            // Buscar foto no contexto
            $foto = $this->buscarFotoNoContexto($contexto, $baseUrl);
            if ($foto) {
                $veiculo['foto'] = $foto;
            }
        }
        
        // ESTRATÉGIA 3: Se não tem nome completo, construir a partir da estrutura
        if (empty($veiculo['nome']) && !empty($veiculo['marca']) && !empty($veiculo['modelo'])) {
            $veiculo['nome'] = $veiculo['marca'] . ' ' . $veiculo['modelo'];
        }
        
        return $veiculo;
    }
    
    /**
     * Extrai dados da estrutura do link
     * Padrão: carrosp.com.br/comprar/$tipo/$marca/$modelo/$versao/$ano/$id/
     */
    private function extrairDadosDoLinkEstrutura($link) {
        // Padrão: /comprar/tipo/marca/modelo/versao/ano/id/
        if (preg_match('/\/comprar\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/(\d{4})\/(\d+)\/?/', $link, $matches)) {
            $tipo = $matches[1];
            $marcaSlug = $matches[2];
            $modeloSlug = $matches[3];
            $versaoSlug = $matches[4];
            $ano = (int)$matches[5];
            $id = $matches[6];
            
            // Converter slugs para nomes legíveis
            $marca = $this->slugParaMarca($marcaSlug);
            $modelo = $this->slugParaModelo($modeloSlug);
            $versao = $this->slugParaVersao($versaoSlug);
            
            // Construir nome completo
            $nomeCompleto = $marca . ' ' . $modelo . ' ' . $versao;
            
            return [
                'nome' => $nomeCompleto,
                'marca' => $marca,
                'modelo' => $modelo,
                'ano' => $ano,
                'tipo' => ucfirst($tipo),
                'versao' => $versao
            ];
        }
        
        return null;
    }
    
    /**
     * Converte slug da marca para nome
     */
    private function slugParaMarca($slug) {
        $mapeamento = [
            'fiat' => 'FIAT',
            'honda' => 'HONDA',
            'chevrolet' => 'CHEVROLET',
            'ford' => 'FORD',
            'volkswagen' => 'VOLKSWAGEN',
            'hyundai' => 'HYUNDAI',
            'toyota' => 'TOYOTA',
            'nissan' => 'NISSAN',
            'renault' => 'RENAULT',
            'chery' => 'CHERY',
            'peugeot' => 'PEUGEOT',
            'citroen' => 'CITROËN'
        ];
        
        return $mapeamento[strtolower($slug)] ?? strtoupper($slug);
    }
    
    /**
     * Converte slug do modelo para nome
     */
    private function slugParaModelo($slug) {
        $modelo = str_replace('-', ' ', $slug);
        $modelo = ucwords(strtolower($modelo));
        
        // Correções específicas
        $correcoes = [
            'City Sedan' => 'City',
            'Focus Hatch' => 'Focus',
            'Ka Hatch' => 'Ka',
            'Onix Hatch' => 'Onix',
            'Polo Hatch' => 'Polo',
            'Hb 20' => 'HB20',
            'Hr V' => 'HR-V',
            'T Cross' => 'T-Cross'
        ];
        
        return $correcoes[$modelo] ?? $modelo;
    }
    
    /**
     * Converte slug da versão para nome
     */
    private function slugParaVersao($slug) {
        $versao = str_replace('-', ' ', $slug);
        $versao = strtoupper($versao);
        
        // Correções específicas
        $correcoes = [
            '4P' => '4P',
            'FLEX' => 'FLEX',
            'AUTOMATICO' => 'AUTOMÁTICO',
            'CVT' => 'CVT'
        ];
        
        foreach ($correcoes as $de => $para) {
            $versao = str_replace($de, $para, $versao);
        }
        
        return $versao;
    }
    
    /**
     * Busca contexto ao redor do link no HTML
     */
    private function buscarContextoDoLink($html, $link) {
        // Buscar posição do link no HTML
        $pos = strpos($html, $link);
        if ($pos !== false) {
            // Extrair contexto de 2000 caracteres ao redor
            $inicio = max(0, $pos - 2000);
            $contexto = substr($html, $inicio, 4000);
            return $contexto;
        }
        
        return '';
    }
    
    /**
     * Busca preço no contexto
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
     * Busca foto no contexto
     */
    private function buscarFotoNoContexto($contexto, $baseUrl) {
        if (preg_match('/<img[^>]*src="([^"]*)"[^>]*>/i', $contexto, $matches)) {
            return $this->normalizarURL($matches[1], $baseUrl);
        }
        
        return '';
    }
    
    /**
     * Remove duplicados baseado no link único
     */
    private function removerDuplicados($veiculos) {
        $linksVistos = [];
        $veiculosUnicos = [];
        
        foreach ($veiculos as $veiculo) {
            $chave = $veiculo['link'];
            
            if (!in_array($chave, $linksVistos)) {
                $linksVistos[] = $chave;
                $veiculosUnicos[] = $veiculo;
            }
        }
        
        return $veiculosUnicos;
    }
    
    /**
     * Extrai informações do texto (km, câmbio, etc.)
     */
    private function extrairInfosTexto($texto) {
        $info = [];
        
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
     * Insere novos veículos no banco
     */
    private function inserirVeiculos($clienteId, $veiculos) {
        // Preparar statement para verificar se já existe
        $stmtCheck = $this->pdo->prepare("
            SELECT id FROM veiculos
            WHERE cliente_id = ? AND link = ?
        ");
        
        // Preparar statement para inserção
        $stmtInsert = $this->pdo->prepare("
            INSERT INTO veiculos (cliente_id, nome, marca, modelo, preco, ano, km, cambio, cor, combustivel, link, foto, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
        ");
        
        // Preparar statement para atualização
        $stmtUpdate = $this->pdo->prepare("
            UPDATE veiculos SET
                nome = ?, marca = ?, modelo = ?, preco = ?, ano = ?, km = ?, cambio = ?, cor = ?,
                combustivel = ?, foto = ?, ativo = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE cliente_id = ? AND link = ?
        ");
        
        $inseridos = 0;
        
        foreach ($veiculos as $veiculo) {
            try {
                // Verificar se já existe
                $stmtCheck->execute([$clienteId, $veiculo['link']]);
                $existente = $stmtCheck->fetch();
                
                if ($existente) {
                    // Atualizar existente
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
                        $veiculo['link']
                    ]);
                } else {
                    // Inserir novo
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