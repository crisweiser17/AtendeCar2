<?php
require_once 'importador_estoque.php';

echo "=== TESTE FINAL DA CORREÇÃO DE KM ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// Usar reflexão para acessar métodos privados
$reflection = new ReflectionClass($importador);
$extrairVeiculos = $reflection->getMethod('extrairVeiculos');
$extrairVeiculos->setAccessible(true);

// URL de teste
$url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';

echo "Testando URL: $url\n\n";

try {
    // Extrair apenas os primeiros 3 veículos para teste
    $veiculos = $extrairVeiculos->invoke($importador, $url);
    
    echo "Total de veículos encontrados: " . count($veiculos) . "\n\n";
    
    // Mostrar detalhes dos primeiros 5 veículos
    $contador = 0;
    foreach ($veiculos as $veiculo) {
        $contador++;
        if ($contador > 5) break;
        
        echo "=== VEÍCULO $contador ===\n";
        echo "Nome: " . ($veiculo['nome'] ?: 'N/A') . "\n";
        echo "Marca: " . ($veiculo['marca'] ?: 'N/A') . "\n";
        echo "Modelo: " . ($veiculo['modelo'] ?: 'N/A') . "\n";
        echo "Ano: " . ($veiculo['ano'] ?: 'N/A') . "\n";
        echo "KM: " . ($veiculo['km'] ? number_format($veiculo['km'], 0, ',', '.') . ' km' : 'NULL') . "\n";
        echo "Link: " . substr($veiculo['link'], 0, 80) . "...\n";
        
        // Verificar se KM foi extraída
        if (isset($veiculo['km']) && $veiculo['km'] > 0) {
            echo "Status KM: ✅ EXTRAÍDA COM SUCESSO\n";
        } else {
            echo "Status KM: ❌ NÃO EXTRAÍDA\n";
        }
        echo "\n";
    }
    
    // Estatísticas finais
    $veiculosComKm = 0;
    $totalKms = [];
    
    foreach ($veiculos as $veiculo) {
        if (isset($veiculo['km']) && $veiculo['km'] > 0) {
            $veiculosComKm++;
            $totalKms[] = $veiculo['km'];
        }
    }
    
    echo "=== ESTATÍSTICAS FINAIS ===\n";
    echo "Total de veículos: " . count($veiculos) . "\n";
    echo "Veículos com KM extraída: $veiculosComKm\n";
    echo "Taxa de sucesso: " . round(($veiculosComKm / count($veiculos)) * 100, 1) . "%\n";
    
    if (!empty($totalKms)) {
        echo "KMs extraídas: " . implode(', ', array_map(function($km) {
            return number_format($km, 0, ',', '.');
        }, array_slice($totalKms, 0, 10))) . "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== TESTE CONCLUÍDO ===\n";
?>