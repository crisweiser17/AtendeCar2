<?php
require_once 'importador_estoque.php';

echo "=== TESTE DE IMPORTAÇÃO REAL COM DEBUG ===\n\n";

// Criar instância do importador
$importador = new ImportadorEstoque();

// URL de teste
$url = 'https://carrosp.com.br/piracicaba-sp/emj-motors/';
$clienteId = 1; // ID de teste

echo "Executando importação real...\n";
echo "URL: $url\n";
echo "Cliente ID: $clienteId\n\n";

// Limpar logs anteriores
error_log("=== INÍCIO DO TESTE DE IMPORTAÇÃO REAL ===");

try {
    $resultado = $importador->importarEstoque($clienteId, $url);
    
    echo "Resultado da importação:\n";
    echo "Sucesso: " . ($resultado['sucesso'] ? 'SIM' : 'NÃO') . "\n";
    
    if ($resultado['sucesso']) {
        echo "Total encontrados: " . $resultado['total_encontrados'] . "\n";
        echo "Total inseridos: " . $resultado['total_inseridos'] . "\n";
        echo "Mensagem: " . $resultado['mensagem'] . "\n";
    } else {
        echo "Erro: " . $resultado['erro'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== VERIFICAR OS LOGS DO PHP PARA VER OS DEBUGS ===\n";
echo "Use: tail -f /var/log/php_errors.log\n";
echo "Ou verifique onde estão os logs do PHP no seu sistema\n";

echo "\n=== TESTE CONCLUÍDO ===\n";
?>