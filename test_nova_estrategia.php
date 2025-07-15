<?php
require_once 'importador_estoque.php';

echo "=== TESTE DA NOVA ESTRATÉGIA (COMBUSTÍVEL/CÂMBIO DA VERSÃO + KM DO SCRAPER) ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// Usar reflexão para acessar método privado
$reflection = new ReflectionClass($importador);
$extrairCombustivelCambio = $reflection->getMethod('extrairCombustivelCambioVersao');
$extrairCombustivelCambio->setAccessible(true);

// Testar diferentes versões de veículos
$versoesTeste = [
    'FIAT ARGO DRIVE 1.0 Flex Manual',
    'HONDA CIVIC EXL 2.0 Gasolina Automatico',
    'CHEVROLET ONIX PLUS LT 1.0 Flex Automatico Cvt',
    'FORD KA SE 1.0 Diesel Manual',
    'VOLKSWAGEN GOL 1.6 Manual', // Sem combustível especificado
    'TOYOTA COROLLA XEI 2.0 Automatico', // Sem combustível especificado
];

echo "=== TESTE DE EXTRAÇÃO DE COMBUSTÍVEL E CÂMBIO ===\n\n";

foreach ($versoesTeste as $versao) {
    echo "Versão: $versao\n";
    $info = $extrairCombustivelCambio->invoke($importador, $versao);
    echo "  Combustível: " . ($info['combustivel'] ?: 'Não identificado') . "\n";
    echo "  Câmbio: " . $info['cambio'] . "\n";
    echo "\n";
}

echo "=== TESTE DE EXTRAÇÃO DE KM DO SCRAPER ===\n\n";

// Simular textos que podem vir do scraper
$textosTeste = [
    'Aut. | Flex | 132.326 KM',
    'Man. | Gasolina | 45.890 KM',
    'Automatico | Diesel | 78.123 KM',
    'Manual | Flex | 12.500 KM',
    'Veículo com 89.456 km rodados',
    'Quilometragem: 156.789 quilômetros',
];

$extrairInfos = $reflection->getMethod('extrairInfosTexto');
$extrairInfos->setAccessible(true);

foreach ($textosTeste as $texto) {
    echo "Texto: $texto\n";
    $info = $extrairInfos->invoke($importador, $texto);
    echo "  KM extraído: " . ($info['km'] ? number_format($info['km'], 0, ',', '.') . ' km' : 'Não encontrado') . "\n";
    echo "\n";
}

echo "=== TESTE INTEGRADO ===\n\n";

// Simular um veículo completo
$veiculoTeste = [
    'nome' => 'FIAT ARGO DRIVE 1.0 Flex Manual 2020',
    'marca' => 'FIAT',
    'modelo' => 'ARGO',
    'preco' => 45990,
    'ano' => 2020,
    'link' => 'https://carrosp.com.br/comprar/carro/fiat/argo/drive-1-0/2020/123456',
    'foto' => 'https://example.com/foto.jpg'
];

// Simular texto do scraper para este veículo
$textoScraper = 'Man. | Flex | 43.302 KM';

echo "Veículo de teste: " . $veiculoTeste['nome'] . "\n";
echo "Texto do scraper: $textoScraper\n\n";

// Extrair combustível e câmbio da versão
$infoVersao = $extrairCombustivelCambio->invoke($importador, $veiculoTeste['nome']);
echo "Da versão extraído:\n";
echo "  Combustível: " . $infoVersao['combustivel'] . "\n";
echo "  Câmbio: " . $infoVersao['cambio'] . "\n";

// Extrair KM do scraper
$infoScraper = $extrairInfos->invoke($importador, $textoScraper);
echo "\nDo scraper extraído:\n";
echo "  KM: " . ($infoScraper['km'] ? number_format($infoScraper['km'], 0, ',', '.') . ' km' : 'Não encontrado') . "\n";

echo "\n=== RESULTADO FINAL COMBINADO ===\n";
echo "Nome: " . $veiculoTeste['nome'] . "\n";
echo "Marca: " . $veiculoTeste['marca'] . "\n";
echo "Modelo: " . $veiculoTeste['modelo'] . "\n";
echo "Preço: R$ " . number_format($veiculoTeste['preco'], 2, ',', '.') . "\n";
echo "Ano: " . $veiculoTeste['ano'] . "\n";
echo "Combustível: " . $infoVersao['combustivel'] . " (da versão)\n";
echo "Câmbio: " . $infoVersao['cambio'] . " (da versão)\n";
echo "KM: " . ($infoScraper['km'] ? number_format($infoScraper['km'], 0, ',', '.') . ' km' : 'Não encontrado') . " (do scraper)\n";

echo "\n=== TESTE CONCLUÍDO ===\n";
?>