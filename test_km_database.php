<?php
require_once 'importador_estoque.php';

echo "=== TESTE DE KM NO BANCO DE DADOS ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// Usar reflexão para acessar métodos privados
$reflection = new ReflectionClass($importador);
$extrairDadosDoLink = $reflection->getMethod('extrairDadosDoLink');
$extrairDadosDoLink->setAccessible(true);

// Simular HTML com dados de KM
$htmlSimulado = '
<div class="vehicle-card">
    <a href="/comprar/carro/fiat/argo/drive-1-0/2020/123456">FIAT ARGO DRIVE</a>
    <p class="text-muted d-table-cell info-geral">Man. | Flex | 43.302 KM</p>
    <span class="price">R$ 78.990</span>
</div>
<div class="vehicle-card">
    <a href="/comprar/carro/honda/civic/exl-2-0/2019/789012">HONDA CIVIC EXL</a>
    <p class="text-muted d-table-cell info-geral">Aut. CVT | Flex | 67.887 KM</p>
    <span class="price">R$ 111.990</span>
</div>
';

$baseUrl = 'https://carrosp.com.br';

// Testar links específicos
$linksTest = [
    'https://carrosp.com.br/comprar/carro/fiat/argo/drive-1-0/2020/123456',
    'https://carrosp.com.br/comprar/carro/honda/civic/exl-2-0/2019/789012'
];

echo "Testando extração de dados completos:\n\n";

foreach ($linksTest as $index => $link) {
    echo "=== TESTE " . ($index + 1) . " ===\n";
    echo "Link: $link\n";
    
    try {
        $veiculo = $extrairDadosDoLink->invoke($importador, $htmlSimulado, $link, $baseUrl);
        
        echo "Dados extraídos:\n";
        echo "  Nome: " . ($veiculo['nome'] ?: 'N/A') . "\n";
        echo "  Marca: " . ($veiculo['marca'] ?: 'N/A') . "\n";
        echo "  Modelo: " . ($veiculo['modelo'] ?: 'N/A') . "\n";
        echo "  Ano: " . ($veiculo['ano'] ?: 'N/A') . "\n";
        echo "  KM: " . ($veiculo['km'] ? number_format($veiculo['km'], 0, ',', '.') . ' km' : 'N/A') . "\n";
        echo "  Câmbio: " . ($veiculo['cambio'] ?: 'N/A') . "\n";
        echo "  Combustível: " . ($veiculo['combustivel'] ?: 'N/A') . "\n";
        echo "  Preço: " . ($veiculo['preco'] ? 'R$ ' . number_format($veiculo['preco'], 2, ',', '.') : 'N/A') . "\n";
        
        // Verificar se KM foi extraída
        if (isset($veiculo['km']) && $veiculo['km'] > 0) {
            echo "  ✅ KM extraída com sucesso!\n";
        } else {
            echo "  ❌ KM não foi extraída\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ ERRO: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== TESTE DE INSERÇÃO NO BANCO (SIMULADO) ===\n\n";

// Simular dados de veículo com KM
$veiculoTeste = [
    'nome' => 'FIAT ARGO DRIVE 1.0 Flex Manual',
    'marca' => 'FIAT',
    'modelo' => 'ARGO',
    'preco' => 78990,
    'ano' => 2020,
    'km' => 43302, // KM extraída
    'cambio' => '',
    'cor' => '',
    'combustivel' => '',
    'link' => 'https://carrosp.com.br/comprar/carro/fiat/argo/drive-1-0/2020/123456',
    'foto' => ''
];

echo "Dados do veículo para inserção:\n";
echo "  Nome: " . $veiculoTeste['nome'] . "\n";
echo "  KM: " . ($veiculoTeste['km'] ? number_format($veiculoTeste['km'], 0, ',', '.') . ' km' : 'N/A') . "\n";

// Simular o processo de inserção (sem realmente inserir no banco)
$versao = $veiculoTeste['nome'];

// Usar reflexão para testar extração de combustível/câmbio
$extrairCombustivelCambio = $reflection->getMethod('extrairCombustivelCambioVersao');
$extrairCombustivelCambio->setAccessible(true);
$infosVersao = $extrairCombustivelCambio->invoke($importador, $versao);

echo "\nDados que seriam inseridos no banco:\n";
echo "  versao: $versao\n";
echo "  km: " . ($veiculoTeste['km'] ?: 'NULL') . "\n";
echo "  cambio: " . $infosVersao['cambio'] . " (da versão)\n";
echo "  combustivel: " . ($infosVersao['combustivel'] ?: 'N/A') . " (da versão)\n";

// Verificar se todos os dados estão presentes
$dadosCompletos = !empty($versao) && 
                  !empty($veiculoTeste['km']) && 
                  !empty($infosVersao['cambio']);

echo "\nStatus da inserção: " . ($dadosCompletos ? "✅ DADOS COMPLETOS" : "❌ DADOS INCOMPLETOS") . "\n";

echo "\n=== TESTE CONCLUÍDO ===\n";
?>