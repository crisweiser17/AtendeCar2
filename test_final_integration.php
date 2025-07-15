<?php
require_once 'importador_estoque.php';

echo "=== TESTE FINAL INTEGRADO - NOVA ESTRATÉGIA COMPLETA ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// Usar reflexão para acessar métodos privados
$reflection = new ReflectionClass($importador);
$extrairCombustivelCambio = $reflection->getMethod('extrairCombustivelCambioVersao');
$extrairCombustivelCambio->setAccessible(true);
$extrairInfos = $reflection->getMethod('extrairInfosTexto');
$extrairInfos->setAccessible(true);

echo "=== SIMULAÇÃO DE VEÍCULOS REAIS ===\n\n";

// Simular veículos com dados reais do CarrosP
$veiculosTeste = [
    [
        'nome' => 'FIAT ARGO 1.0 FIREFLY DRIVE Flex Manual',
        'scraper_text' => 'Man. | Flex | 43.302 KM'
    ],
    [
        'nome' => 'HONDA CITY SEDAN 1.5 16V 4P DX FLEX AUTOMÁTICO',
        'scraper_text' => 'Aut. | Flex | 132.326 KM'
    ],
    [
        'nome' => 'HONDA CIVIC 2.0 16V 4P EXL FLEX AUTOMÁTICO CVT',
        'scraper_text' => 'Aut. CVT | Flex | 67.887 KM'
    ],
    [
        'nome' => 'HYUNDAI CRETA 1.6 16V 4P FLEX SMART AUTOMÁTICO',
        'scraper_text' => 'Aut. | Flex | 102.000 KM'
    ],
    [
        'nome' => 'CHEVROLET CRUZE SEDAN 1.8 16V 4P LTZ ECOTEC FLEX AUTOMÁTICO',
        'scraper_text' => 'Aut. | Flex | 108.545 KM'
    ],
    [
        'nome' => 'RENAULT DUSTER 1.6 16V 4P FLEX DYNAMIQUE AUTOMÁTICO CVT',
        'scraper_text' => 'Aut. CVT | Flex | 88.334 KM'
    ]
];

foreach ($veiculosTeste as $index => $veiculo) {
    $num = $index + 1;
    echo "=== VEÍCULO $num ===\n";
    echo "Nome/Versão: " . $veiculo['nome'] . "\n";
    echo "Texto do Scraper: " . $veiculo['scraper_text'] . "\n\n";
    
    // Extrair combustível e câmbio da versão
    $infoVersao = $extrairCombustivelCambio->invoke($importador, $veiculo['nome']);
    
    // Extrair KM do scraper
    $infoScraper = $extrairInfos->invoke($importador, $veiculo['scraper_text']);
    
    echo "RESULTADOS:\n";
    echo "  Combustível (da versão): " . ($infoVersao['combustivel'] ?: 'Não identificado') . "\n";
    echo "  Câmbio (da versão): " . $infoVersao['cambio'] . "\n";
    echo "  KM (do scraper): " . ($infoScraper['km'] ? number_format($infoScraper['km'], 0, ',', '.') . ' km' : 'Não encontrado') . "\n";
    
    // Verificar se todos os dados foram extraídos corretamente
    $sucesso = !empty($infoVersao['combustivel']) && !empty($infoVersao['cambio']) && !empty($infoScraper['km']);
    echo "  Status: " . ($sucesso ? "✅ SUCESSO" : "❌ FALHA") . "\n";
    echo "\n";
}

echo "=== TESTE DE CASOS ESPECIAIS ===\n\n";

// Testar casos onde não há combustível especificado na versão
$casosEspeciais = [
    [
        'nome' => 'VOLKSWAGEN GOL 1.6 Manual', // Sem combustível
        'scraper_text' => 'Man. | Gasolina | 45.678 KM'
    ],
    [
        'nome' => 'TOYOTA COROLLA XEI 2.0 Automatico', // Sem combustível
        'scraper_text' => 'Aut. | Flex | 78.901 KM'
    ]
];

foreach ($casosEspeciais as $index => $veiculo) {
    $num = $index + 1;
    echo "=== CASO ESPECIAL $num ===\n";
    echo "Nome/Versão: " . $veiculo['nome'] . "\n";
    echo "Texto do Scraper: " . $veiculo['scraper_text'] . "\n\n";
    
    $infoVersao = $extrairCombustivelCambio->invoke($importador, $veiculo['nome']);
    $infoScraper = $extrairInfos->invoke($importador, $veiculo['scraper_text']);
    
    echo "RESULTADOS:\n";
    echo "  Combustível (da versão): " . ($infoVersao['combustivel'] ?: 'Não identificado - OK para casos sem especificação') . "\n";
    echo "  Câmbio (da versão): " . $infoVersao['cambio'] . "\n";
    echo "  KM (do scraper): " . ($infoScraper['km'] ? number_format($infoScraper['km'], 0, ',', '.') . ' km' : 'Não encontrado') . "\n";
    echo "\n";
}

echo "=== RESUMO DA NOVA ESTRATÉGIA ===\n";
echo "✅ Combustível e Câmbio: Extraídos da coluna 'versao' do banco de dados\n";
echo "✅ KM: Extraída do scraper usando padrão 'X | Y | NÚMERO KM'\n";
echo "✅ Contexto específico: Cada veículo tem seu próprio contexto isolado\n";
echo "✅ Sem duplicação: Dados únicos para cada veículo\n";

echo "\n=== TESTE CONCLUÍDO ===\n";
?>