<?php
require_once 'importador_estoque.php';

echo "=== DEBUG DA INSERÇÃO DE KM NO BANCO ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// Usar reflexão para acessar método privado inserirVeiculos
$reflection = new ReflectionClass($importador);
$inserirVeiculos = $reflection->getMethod('inserirVeiculos');
$inserirVeiculos->setAccessible(true);

// Simular dados de veículos com KM
$veiculosTeste = [
    [
        'nome' => 'FIAT ARGO DRIVE 1.0 Flex Manual',
        'marca' => 'FIAT',
        'modelo' => 'ARGO',
        'preco' => 78990,
        'ano' => 2020,
        'km' => 43302, // KM extraída do scraper
        'cambio' => '',
        'cor' => '',
        'combustivel' => '',
        'link' => 'https://carrosp.com.br/comprar/carro/fiat/argo/drive-1-0/2020/123456',
        'foto' => ''
    ],
    [
        'nome' => 'HONDA CIVIC EXL 2.0 Flex Automático CVT',
        'marca' => 'HONDA',
        'modelo' => 'CIVIC',
        'preco' => 111990,
        'ano' => 2019,
        'km' => 67887, // KM extraída do scraper
        'cambio' => '',
        'cor' => '',
        'combustivel' => '',
        'link' => 'https://carrosp.com.br/comprar/carro/honda/civic/exl-2-0/2019/789012',
        'foto' => ''
    ]
];

echo "Dados dos veículos ANTES do processamento:\n\n";

foreach ($veiculosTeste as $index => $veiculo) {
    echo "Veículo " . ($index + 1) . ":\n";
    echo "  Nome: " . $veiculo['nome'] . "\n";
    echo "  KM: " . ($veiculo['km'] ? number_format($veiculo['km'], 0, ',', '.') . ' km' : 'NULL') . "\n";
    echo "  Link: " . $veiculo['link'] . "\n";
    echo "\n";
}

echo "=== SIMULAÇÃO DO PROCESSAMENTO NO inserirVeiculos ===\n\n";

// Simular o processamento que acontece dentro do método inserirVeiculos
foreach ($veiculosTeste as $index => $veiculo) {
    echo "=== PROCESSANDO VEÍCULO " . ($index + 1) . " ===\n";
    
    // Garantir que o nome/versão não esteja vazio
    $versao = !empty($veiculo['nome']) ? $veiculo['nome'] :
             (!empty($veiculo['marca']) && !empty($veiculo['modelo']) ?
              $veiculo['marca'] . ' ' . $veiculo['modelo'] : 'Veículo sem nome');
    
    // Criar marca_modelo
    $marcaModelo = '';
    if (!empty($veiculo['marca']) && !empty($veiculo['modelo'])) {
        $marcaModelo = $veiculo['marca'] . ' ' . $veiculo['modelo'];
    } elseif (!empty($veiculo['marca'])) {
        $marcaModelo = $veiculo['marca'];
    } elseif (!empty($veiculo['modelo'])) {
        $marcaModelo = $veiculo['modelo'];
    }
    
    // Formatar preço como string
    $precoStr = '';
    if (!empty($veiculo['preco']) && $veiculo['preco'] > 0) {
        $precoStr = 'R$ ' . number_format($veiculo['preco'], 2, ',', '.');
    }
    
    // NOVA ESTRATÉGIA: Extrair combustível e câmbio da versão
    $extrairCombustivelCambio = $reflection->getMethod('extrairCombustivelCambioVersao');
    $extrairCombustivelCambio->setAccessible(true);
    $infosVersao = $extrairCombustivelCambio->invoke($importador, $versao);
    $combustivel = $infosVersao['combustivel'];
    $cambio = $infosVersao['cambio'];
    
    echo "Dados processados para inserção:\n";
    echo "  versao: $versao\n";
    echo "  marca_modelo: $marcaModelo\n";
    echo "  preco: $precoStr\n";
    echo "  ano: " . ($veiculo['ano'] ?: 'NULL') . "\n";
    echo "  km: " . ($veiculo['km'] ?: 'NULL') . "\n";
    echo "  cambio: $cambio\n";
    echo "  cor: " . ($veiculo['cor'] ?: "''") . "\n";
    echo "  combustivel: $combustivel\n";
    echo "  link: " . $veiculo['link'] . "\n";
    echo "  foto: " . ($veiculo['foto'] ?: "''") . "\n";
    
    // Verificar se KM está presente
    if (isset($veiculo['km']) && $veiculo['km'] > 0) {
        echo "  ✅ KM presente: " . number_format($veiculo['km'], 0, ',', '.') . " km\n";
    } else {
        echo "  ❌ KM ausente ou zero\n";
    }
    
    // Simular o SQL que seria executado
    echo "\nSQL que seria executado:\n";
    echo "INSERT INTO veiculos (cliente_id, versao, marca_modelo, preco, ano, km, cambio, cor, combustivel, link, foto, ativo)\n";
    echo "VALUES (1, '$versao', '$marcaModelo', '$precoStr', " . ($veiculo['ano'] ?: 'NULL') . ", " . ($veiculo['km'] ?: 'NULL') . ", '$cambio', '" . ($veiculo['cor'] ?: '') . "', '$combustivel', '" . $veiculo['link'] . "', '" . ($veiculo['foto'] ?: '') . "', TRUE)\n";
    
    echo "\n" . str_repeat("-", 80) . "\n\n";
}

echo "=== VERIFICAÇÃO FINAL ===\n";
echo "Se a KM não está sendo salva no banco, pode ser:\n";
echo "1. ❌ Problema na query SQL\n";
echo "2. ❌ Problema na estrutura da tabela\n";
echo "3. ❌ Problema na conexão com o banco\n";
echo "4. ❌ KM sendo sobrescrita por NULL em algum lugar\n";

echo "\n=== DEBUG CONCLUÍDO ===\n";
?>