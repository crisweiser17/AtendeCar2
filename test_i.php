<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Importação - EMJ Motors</title>
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
        <h1>🚗 Teste de Importação - EMJ Motors</h1>

<?php
// Simular a classe ImportadorEstoque sem conexão com banco
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
     * Extrai veículos do HTML
     */
    private function extrairVeiculos($html, $baseUrl) {
        $veiculos = [];
        $linksUnicos = [];
        
        // Regex corrigida para URLs absolutas
        $padraoLink = '/href="(https:\/\/carrosp\.com\.br\/comprar\/[^"]+)"/i';
        preg_match_all($padraoLink, $html, $matchesLinks);
        
        echo '<div class="info">🔍 Links encontrados: <strong>' . count($matchesLinks[1]) . '</strong></div>';
        
        foreach ($matchesLinks[1] as $linkCompleto) {
            // Evitar duplicados
            if (in_array($linkCompleto, $linksUnicos)) {
                continue;
            }
            $linksUnicos[] = $linkCompleto;
            
            // Extrair dados do link
            $veiculo = $this->extrairDadosDoLink($html, $linkCompleto, $baseUrl);
            if ($veiculo && !empty($veiculo['nome'])) {
                $veiculos[] = $veiculo;
            }
        }
        
        return $veiculos;
    }
    
    /**
     * Extrai dados do veículo usando o link
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
        // Padrão: /comprar/tipo/marca/modelo/versao/ano/id/
        if (preg_match('/\/comprar\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/(\d{4})\/(\d+)\/?/', $linkVeiculo, $matches)) {
            $tipo = $matches[1];
            $marcaSlug = $matches[2];
            $modeloSlug = $matches[3];
            $versaoSlug = $matches[4];
            $ano = (int)$matches[5];
            
            // Usar slugs como estão, apenas formatando
            $marca = strtoupper(str_replace('-', ' ', $marcaSlug));
            $modelo = ucwords(str_replace('-', ' ', $modeloSlug));
            $versao = str_replace('-', ' ', $versaoSlug);
            
            $veiculo['marca'] = $marca;
            $veiculo['modelo'] = $modelo;
            $veiculo['ano'] = $ano;
            $veiculo['nome'] = $marca . ' ' . $modelo . ' ' . $versao;
            $veiculo['tipo'] = ucfirst($tipo);
        }
        
        // Buscar preço no contexto
        $contexto = $this->buscarContextoDoLink($html, $linkVeiculo);
        if ($contexto) {
            $preco = $this->buscarPrecoNoContexto($contexto);
            if ($preco > 0) {
                $veiculo['preco'] = $preco;
            }
        }
        
        return $veiculo;
    }
    
    /**
     * Busca contexto ao redor do link
     */
    private function buscarContextoDoLink($html, $link) {
        $pos = strpos($html, $link);
        if ($pos !== false) {
            $inicio = max(0, $pos - 1000);
            $contexto = substr($html, $inicio, 2000);
            return $contexto;
        }
        return '';
    }
    
    /**
     * Busca preço no contexto
     */
    private function buscarPrecoNoContexto($contexto) {
        $padroes = [
            '/R\$\s*([0-9]{2,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i',
            '/([0-9]{2,3}\.[0-9]{3})/i'
        ];
        
        foreach ($padroes as $padrao) {
            if (preg_match_all($padrao, $contexto, $matches)) {
                foreach ($matches[1] as $match) {
                    $preco = $this->extrairPreco($match);
                    if ($preco >= 5000 && $preco <= 500000) {
                        return $preco;
                    }
                }
            }
        }
        
        return 0;
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
}

// Executar teste
try {
    $testador = new TestImportador();
    $url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
    
    $veiculos = $testador->testarExtracao($url);
    
    echo '<div class="info success">📊 <strong>Total de veículos encontrados: ' . count($veiculos) . '</strong></div>';
    
    if (!empty($veiculos)) {
        // Contar por marca para o resumo
        $marcas = [];
        foreach ($veiculos as $veiculo) {
            $marca = $veiculo['marca'];
            $marcas[$marca] = ($marcas[$marca] ?? 0) + 1;
        }
        arsort($marcas);
        
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
            echo '<td class="marca">' . htmlspecialchars($veiculo['marca']) . '</td>';
            echo '<td>' . htmlspecialchars($veiculo['modelo']) . '</td>';
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