<?php
require_once 'importador_estoque.php';

echo "=== TESTE DE EXTRAÇÃO ESPECÍFICA POR CONTEXTO ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// URL de teste do CarrosP
$url = 'https://carrosp.com.br/estoque/loja/revenda-de-veiculos-jd-america-ltda-me';

echo "Testando URL: $url\n\n";

try {
    // Usar reflexão para acessar método privado
    $reflection = new ReflectionClass($importador);
    $extrairVeiculos = $reflection->getMethod('extrairVeiculos');
    $extrairVeiculos->setAccessible(true);
    
    // Extrair veículos
    $veiculos = $extrairVeiculos->invoke($importador, $url);
    
    echo "Total de veículos encontrados: " . count($veiculos) . "\n\n";
    
    // Mostrar detalhes dos primeiros 5 veículos para verificar se os dados são únicos
    $contador = 0;
    foreach ($veiculos as $veiculo) {
        $contador++;
        if ($contador > 5) break;
        
        echo "=== VEÍCULO $contador ===\n";
        echo "Nome: " . ($veiculo['nome'] ?: 'N/A') . "\n";
        echo "Marca: " . ($veiculo['marca'] ?: 'N/A') . "\n";
        echo "Modelo: " . ($veiculo['modelo'] ?: 'N/A') . "\n";
        echo "Preço: R$ " . number_format($veiculo['preco'], 2, ',', '.') . "\n";
        echo "Ano: " . ($veiculo['ano'] ?: 'N/A') . "\n";
        echo "KM: " . ($veiculo['km'] ? number_format($veiculo['km'], 0, ',', '.') . ' km' : 'N/A') . "\n";
        echo "Câmbio: " . ($veiculo['cambio'] ?: 'N/A') . "\n";
        echo "Combustível: " . ($veiculo['combustivel'] ?: 'N/A') . "\n";
        echo "Cor: " . ($veiculo['cor'] ?: 'N/A') . "\n";
        echo "Link: " . substr($veiculo['link'], 0, 80) . "...\n";
        echo "Foto: " . ($veiculo['foto'] ? 'Sim' : 'Não') . "\n";
        echo "\n";
    }
    
    // Verificar se há dados duplicados (problema que estávamos tentando resolver)
    echo "=== VERIFICAÇÃO DE DUPLICAÇÃO DE DADOS ===\n";
    
    $kms = array_filter(array_column($veiculos, 'km'));
    $cambios = array_filter(array_column($veiculos, 'cambio'));
    $combustiveis = array_filter(array_column($veiculos, 'combustivel'));
    
    echo "KMs únicos encontrados: " . count(array_unique($kms)) . " de " . count($kms) . " total\n";
    echo "Câmbios únicos encontrados: " . count(array_unique($cambios)) . " de " . count($cambios) . " total\n";
    echo "Combustíveis únicos encontrados: " . count(array_unique($combustiveis)) . " de " . count($combustiveis) . " total\n";
    
    // Mostrar alguns exemplos de KMs para verificar variação
    if (!empty($kms)) {
        echo "\nExemplos de KMs encontrados:\n";
        $kmsUnicos = array_unique($kms);
        $contador = 0;
        foreach ($kmsUnicos as $km) {
            if ($contador >= 10) break;
            echo "- " . number_format($km, 0, ',', '.') . " km\n";
            $contador++;
        }
    }
    
    // Mostrar alguns exemplos de câmbios para verificar variação
    if (!empty($cambios)) {
        echo "\nExemplos de câmbios encontrados:\n";
        $cambiosUnicos = array_unique($cambios);
        foreach ($cambiosUnicos as $cambio) {
            echo "- $cambio\n";
        }
    }
    
    // Mostrar alguns exemplos de combustíveis para verificar variação
    if (!empty($combustiveis)) {
        echo "\nExemplos de combustíveis encontrados:\n";
        $combustiveisUnicos = array_unique($combustiveis);
        foreach ($combustiveisUnicos as $combustivel) {
            echo "- $combustivel\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TESTE CONCLUÍDO ===\n";
?>