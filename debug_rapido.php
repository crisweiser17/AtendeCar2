<?php
/**
 * Debug rápido para identificar problema de inserção
 * Encontra 28 mas não insere nenhum
 */

require_once 'config/database.php';

echo "=== DEBUG RÁPIDO - PROBLEMA DE INSERÇÃO ===\n";
echo "Data: " . date('d/m/Y H:i:s') . "\n\n";

try {
    // 1. Testar conexão
    $pdo = getConnection();
    echo "✓ Conexão com banco OK\n\n";
    
    // 2. Simular extração de um veículo
    $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        echo "❌ Erro ao obter HTML: HTTP $httpCode\n";
        exit;
    }
    
    echo "✓ HTML obtido (" . strlen($html) . " bytes)\n";
    
    // 3. Buscar links de veículos
    preg_match_all('/href="(\/comprar\/[^"]+)"/i', $html, $matches);
    $links = $matches[1];
    
    echo "✓ " . count($links) . " links encontrados\n\n";
    
    if (empty($links)) {
        echo "❌ Nenhum link encontrado!\n";
        exit;
    }
    
    // 4. Analisar primeiro link
    $primeiroLink = 'https://carrosp.com.br' . $links[0];
    echo "Analisando: $primeiroLink\n";
    
    // Buscar ALT da imagem para este link
    $linkBase = basename($links[0]);
    $padraoImagem = '/<img[^>]*alt="([^"]*(?:FIAT|HONDA|CHEVROLET|FORD|VOLKSWAGEN|HYUNDAI|TOYOTA|NISSAN|RENAULT|CHERY)[^"]*)"[^>]*>.*?href="[^"]*' . preg_quote($linkBase, '/') . '[^"]*"/is';
    
    if (preg_match($padraoImagem, $html, $match)) {
        $nomeCompleto = trim($match[1]);
        echo "✓ Nome encontrado: '$nomeCompleto'\n";
        
        // Extrair marca
        $marcas = ['FIAT', 'HONDA', 'CHEVROLET', 'FORD', 'VOLKSWAGEN', 'HYUNDAI', 'TOYOTA', 'NISSAN', 'RENAULT', 'CHERY'];
        $marca = '';
        $modelo = '';
        
        foreach ($marcas as $m) {
            if (stripos($nomeCompleto, $m) !== false) {
                $marca = $m;
                // Extrair modelo
                if (preg_match('/' . $m . '\s+([A-Za-z\s\-0-9]+?)(?=\s+\d{1,2}\.\d|\s+\d{4}|\s*$)/i', $nomeCompleto, $modeloMatch)) {
                    $modelo = trim($modeloMatch[1]);
                    $modelo = preg_replace('/\s+\d+\.\d+.*$/', '', $modelo);
                }
                break;
            }
        }
        
        echo "✓ Marca: '$marca'\n";
        echo "✓ Modelo: '$modelo'\n";
        
        // 5. Testar validação
        $valido = (!empty($nomeCompleto) || !empty($marca)) && !empty($primeiroLink);
        echo "✓ Válido para inserção: " . ($valido ? "SIM" : "NÃO") . "\n";
        
        if (!$valido) {
            echo "❌ PROBLEMA IDENTIFICADO:\n";
            if (empty($nomeCompleto) && empty($marca)) {
                echo "- Nome e marca estão vazios\n";
            }
            if (empty($primeiroLink)) {
                echo "- Link está vazio\n";
            }
        } else {
            // 6. Testar inserção real
            echo "\n6. Testando inserção no banco...\n";
            
            $veiculo = [
                'nome' => $nomeCompleto,
                'preco' => 50000, // Valor teste
                'ano' => 2020,
                'km' => 50000,
                'cambio' => 'Manual',
                'cor' => 'Branco',
                'combustivel' => 'Flex',
                'link' => $primeiroLink,
                'foto' => ''
            ];
            
            // Verificar se já existe
            $stmt = $pdo->prepare("SELECT id FROM veiculos WHERE cliente_id = ? AND link = ?");
            $stmt->execute([1, $veiculo['link']]);
            $existente = $stmt->fetch();
            
            if ($existente) {
                echo "✓ Veículo já existe (ID: {$existente['id']})\n";
                
                // Tentar atualizar
                $stmt = $pdo->prepare("
                    UPDATE veiculos SET
                        nome = ?, preco = ?, ano = ?, km = ?, cambio = ?, cor = ?,
                        combustivel = ?, foto = ?, ativo = TRUE, updated_at = CURRENT_TIMESTAMP
                    WHERE cliente_id = ? AND link = ?
                ");
                
                $resultado = $stmt->execute([
                    $veiculo['nome'],
                    $veiculo['preco'],
                    $veiculo['ano'],
                    $veiculo['km'],
                    $veiculo['cambio'],
                    $veiculo['cor'],
                    $veiculo['combustivel'],
                    $veiculo['foto'],
                    1,
                    $veiculo['link']
                ]);
                
                if ($resultado) {
                    echo "✅ Atualização bem-sucedida!\n";
                } else {
                    echo "❌ Falha na atualização\n";
                    print_r($stmt->errorInfo());
                }
                
            } else {
                echo "✓ Veículo não existe, inserindo...\n";
                
                $stmt = $pdo->prepare("
                    INSERT INTO veiculos (cliente_id, nome, preco, ano, km, cambio, cor, combustivel, link, foto, ativo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                
                $resultado = $stmt->execute([
                    1,
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
                
                if ($resultado) {
                    $novoId = $pdo->lastInsertId();
                    echo "✅ Inserção bem-sucedida! ID: $novoId\n";
                } else {
                    echo "❌ Falha na inserção\n";
                    print_r($stmt->errorInfo());
                }
            }
        }
        
    } else {
        echo "❌ Não encontrou nome via ALT de imagem\n";
        
        // Tentar via estrutura do link
        if (preg_match('/\/comprar\/([^\/]+)\/([^\/]+)\/([^\/]+)\//', $links[0], $linkMatch)) {
            $marca = strtoupper($linkMatch[2]);
            $modelo = ucwords(str_replace('-', ' ', $linkMatch[3]));
            echo "✓ Via link - Marca: '$marca', Modelo: '$modelo'\n";
        } else {
            echo "❌ Também não conseguiu extrair do link\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}

echo "\n=== FIM DO DEBUG ===\n";
?>