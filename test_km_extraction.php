<?php
require_once 'importador_estoque.php';

echo "=== TESTE DE EXTRAÇÃO DE KM - PADRÃO CARROSP ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// Usar reflexão para acessar método privado
$reflection = new ReflectionClass($importador);
$extrairInfos = $reflection->getMethod('extrairInfosTexto');
$extrairInfos->setAccessible(true);

// Testar com os padrões exatos que vi na página do CarrosP
$textosTeste = [
    'Man. | Flex | 43.302 KM',           // FIAT ARGO
    'Aut. | Flex | 132.326 KM',         // HONDA CITY SEDAN
    'Aut. CVT | Flex | 67.887 KM',      // HONDA CIVIC
    'Aut. | Flex | 102.000 KM',         // HYUNDAI CRETA
    'Aut. | Flex | 108.545 KM',         // CHEVROLET CRUZE SEDAN
    'Aut. CVT | Flex | 88.334 KM',      // RENAULT DUSTER
    'Manual | Gasolina | 25.678 KM',    // Teste adicional
    'Automatico | Diesel | 156.789 KM', // Teste adicional
    '<p class="text-muted d-table-cell info-geral">Aut. | Flex | 188.746 KM</p>', // Com HTML
];

echo "Testando extração de KM com padrões do CarrosP:\n\n";

foreach ($textosTeste as $index => $texto) {
    echo "Teste " . ($index + 1) . ":\n";
    echo "Texto: $texto\n";
    
    $info = $extrairInfos->invoke($importador, $texto);
    
    if (isset($info['km']) && $info['km'] > 0) {
        echo "✅ KM extraído: " . number_format($info['km'], 0, ',', '.') . " km\n";
    } else {
        echo "❌ KM não encontrado\n";
    }
    echo "\n";
}

echo "=== TESTE DE REGEX MANUAL ===\n\n";

// Testar o regex manualmente para debug
$padraoRegex = '/[^|]+\|\s*[^|]+\|\s*([\d.,]+)\s*KM/i';

foreach ($textosTeste as $index => $texto) {
    echo "Teste " . ($index + 1) . " (regex manual):\n";
    echo "Texto: $texto\n";
    
    if (preg_match($padraoRegex, $texto, $matches)) {
        echo "✅ Match encontrado: " . $matches[1] . "\n";
        $km = str_replace(['.', ','], '', trim($matches[1]));
        if (is_numeric($km)) {
            echo "✅ KM processado: " . number_format((int)$km, 0, ',', '.') . " km\n";
        } else {
            echo "❌ Não é numérico: '$km'\n";
        }
    } else {
        echo "❌ Regex não fez match\n";
    }
    echo "\n";
}

echo "=== TESTE CONCLUÍDO ===\n";
?>