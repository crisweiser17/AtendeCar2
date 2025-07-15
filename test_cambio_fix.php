<?php
require_once 'importador_estoque.php';

echo "=== TESTE DE CORREÇÃO DO CÂMBIO ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// Usar reflexão para acessar método privado
$reflection = new ReflectionClass($importador);
$extrairCombustivelCambio = $reflection->getMethod('extrairCombustivelCambioVersao');
$extrairCombustivelCambio->setAccessible(true);

// Testar com os casos que estavam falhando
$versoesTeste = [
    'FIAT ARGO 1.0 FIREFLY DRIVE Flex Manual',
    'HONDA CITY SEDAN 1.5 16V 4P DX FLEX AUTOMÁTICO',
    'HONDA CIVIC 2.0 16V 4P EXL FLEX AUTOMÁTICO CVT',
    'HYUNDAI CRETA 1.6 16V 4P FLEX SMART AUTOMÁTICO',
    'CHEVROLET CRUZE SEDAN 1.8 16V 4P LTZ ECOTEC FLEX AUTOMÁTICO',
    'RENAULT DUSTER 1.6 16V 4P FLEX DYNAMIQUE AUTOMÁTICO CVT',
    'VOLKSWAGEN GOL 1.6 Manual',
    'TOYOTA COROLLA XEI 2.0 Automatico',
];

foreach ($versoesTeste as $index => $versao) {
    echo "Teste " . ($index + 1) . ":\n";
    echo "Versão: $versao\n";
    
    $info = $extrairCombustivelCambio->invoke($importador, $versao);
    
    echo "  Combustível: " . ($info['combustivel'] ?: 'Não identificado') . "\n";
    echo "  Câmbio: " . $info['cambio'] . "\n";
    
    // Verificar se o câmbio está correto
    $cambioEsperado = '';
    if (stripos($versao, 'CVT') !== false) {
        $cambioEsperado = 'Automático CVT';
    } elseif (stripos($versao, 'Automatico') !== false || stripos($versao, 'Automático') !== false) {
        $cambioEsperado = 'Automático';
    } else {
        $cambioEsperado = 'Manual';
    }
    
    $correto = ($info['cambio'] === $cambioEsperado);
    echo "  Esperado: $cambioEsperado\n";
    echo "  Status: " . ($correto ? "✅ CORRETO" : "❌ INCORRETO") . "\n";
    echo "\n";
}

echo "=== TESTE CONCLUÍDO ===\n";
?>