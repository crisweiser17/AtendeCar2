<?php
echo "🔍 DEBUG: INVESTIGANDO PADRÕES DE LINKS\n";
echo "======================================\n\n";

$url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';

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
    die("Erro ao acessar a página: HTTP $httpCode\n");
}

echo "✅ HTML obtido com sucesso (" . strlen($html) . " bytes)\n\n";

// 1. TESTAR DIFERENTES PADRÕES DE LINKS
echo "🔍 TESTANDO DIFERENTES PADRÕES DE LINKS:\n";
echo "=======================================\n";

$padroes = [
    'Relativo /comprar/' => '/href="(\/comprar\/[^"]+)"/i',
    'Absoluto carrosp.com.br/comprar/' => '/href="([^"]*carrosp\.com\.br\/comprar\/[^"]*)"/i',
    'Qualquer /comprar/' => '/href="([^"]*\/comprar\/[^"]*)"/i',
    'Links com carrosp' => '/href="([^"]*carrosp[^"]*)"/i',
    'Todos os links' => '/href="([^"]+)"/i'
];

foreach ($padroes as $nome => $padrao) {
    $count = preg_match_all($padrao, $html, $matches);
    echo "$nome: $count encontrados\n";
    
    if ($count > 0 && $count <= 5) {
        echo "  Exemplos:\n";
        for ($i = 0; $i < min(3, count($matches[1])); $i++) {
            echo "    " . ($i + 1) . ". " . $matches[1][$i] . "\n";
        }
    } elseif ($count > 5) {
        echo "  Primeiros 3 exemplos:\n";
        for ($i = 0; $i < 3; $i++) {
            echo "    " . ($i + 1) . ". " . $matches[1][$i] . "\n";
        }
    }
    echo "\n";
}

// 2. BUSCAR ESPECIFICAMENTE POR LINKS COM ESTRUTURA ESPERADA
echo "🎯 BUSCANDO LINKS COM ESTRUTURA ESPECÍFICA:\n";
echo "==========================================\n";

// Padrão mais específico baseado na estrutura fornecida
$padraoEspecifico = '/href="([^"]*\/comprar\/[^\/]+\/[^\/]+\/[^\/]+\/[^"]*)"[^>]*>/i';
$countEspecifico = preg_match_all($padraoEspecifico, $html, $matchesEspecifico);

echo "Links com estrutura /comprar/tipo/marca/modelo/: $countEspecifico\n";

if ($countEspecifico > 0) {
    echo "Exemplos encontrados:\n";
    for ($i = 0; $i < min(5, count($matchesEspecifico[1])); $i++) {
        $link = $matchesEspecifico[1][$i];
        echo "  " . ($i + 1) . ". $link\n";
        
        // Tentar extrair partes do link
        if (preg_match('/\/comprar\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)/', $link, $partes)) {
            echo "     Tipo: " . $partes[1] . "\n";
            echo "     Marca: " . $partes[2] . "\n";
            echo "     Modelo: " . $partes[3] . "\n";
            echo "     Versão: " . $partes[4] . "\n";
        }
        echo "\n";
    }
}

// 3. VERIFICAR SE OS LINKS ESTÃO DENTRO DE CONTEXTO ESPECÍFICO
echo "🔍 VERIFICANDO CONTEXTO DOS LINKS:\n";
echo "==================================\n";

// Buscar por divs ou elementos que podem conter os veículos
$padroesDivs = [
    'Divs com class card' => '/<div[^>]*class="[^"]*card[^"]*"[^>]*>(.*?)<\/div>/is',
    'Divs com class vehicle' => '/<div[^>]*class="[^"]*vehicle[^"]*"[^>]*>(.*?)<\/div>/is',
    'Divs com class item' => '/<div[^>]*class="[^"]*item[^"]*"[^>]*>(.*?)<\/div>/is',
    'Links dentro de <a>' => '/<a[^>]*href="([^"]*comprar[^"]*)"[^>]*>/i'
];

foreach ($padroesDivs as $nome => $padrao) {
    $count = preg_match_all($padrao, $html, $matches);
    echo "$nome: $count encontrados\n";
    
    if ($count > 0 && $nome === 'Links dentro de <a>') {
        echo "  Primeiros 3 links:\n";
        for ($i = 0; $i < min(3, count($matches[1])); $i++) {
            echo "    " . ($i + 1) . ". " . $matches[1][$i] . "\n";
        }
    }
    echo "\n";
}

// 4. VERIFICAR SE HÁ JAVASCRIPT GERANDO OS LINKS
echo "⚡ VERIFICANDO JAVASCRIPT:\n";
echo "========================\n";

$jsPatterns = [
    'window.location' => '/window\.location[^;]+/i',
    'href=' => '/href\s*=\s*["\'][^"\']*comprar[^"\']*["\']/i',
    'data-href' => '/data-href\s*=\s*["\'][^"\']*comprar[^"\']*["\']/i'
];

foreach ($jsPatterns as $nome => $padrao) {
    $count = preg_match_all($padrao, $html, $matches);
    echo "$nome: $count encontrados\n";
    
    if ($count > 0) {
        echo "  Exemplos:\n";
        for ($i = 0; $i < min(2, count($matches[0])); $i++) {
            echo "    " . ($i + 1) . ". " . trim($matches[0][$i]) . "\n";
        }
    }
    echo "\n";
}

echo "🏁 Debug finalizado.\n";
?>