<?php
// Script de teste real para debug do hotlead_alert.php
// Use: php test_hotlead_real.php

require_once 'config/database.php';

// Configuração de teste
$client_id = 1; // ID do cliente EMJ Motors
$lead_name = "Cris Weiser7\"";
$lead_number = "5519998989999";

echo "=== TESTE REAL HOTLEAD ALERT ===\n";
echo "Client ID: $client_id\n";
echo "Lead Name: $lead_name\n";
echo "Lead Number: $lead_number\n\n";

try {
    // Conectar ao banco
    $pdo = getConnection();
    
    // Buscar dados do cliente
    $stmt = $pdo->prepare("SELECT id, nome_loja, alertas_whatsapp, nome_instancia_whatsapp, token_evo_api FROM clientes WHERE id = ?");
    $stmt->execute([$client_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        echo "❌ Cliente não encontrado\n";
        exit;
    }
    
    echo "Cliente encontrado: {$cliente['nome_loja']}\n";
    echo "Instância: {$cliente['nome_instancia_whatsapp']}\n";
    echo "Token: " . substr($cliente['token_evo_api'], 0, 10) . "...\n";
    
    // Processar números
    $alertas_whatsapp = json_decode($cliente['alertas_whatsapp'] ?? '[]', true);
    echo "\nNúmeros configurados:\n";
    print_r($alertas_whatsapp);
    
    // Testar formatação de números
    foreach ($alertas_whatsapp as $numero_original) {
        echo "\n--- Testando número: $numero_original ---\n";
        
        // Aplicar a mesma formatação do hotlead_alert.php
        $numeroLimpo = preg_replace('/[^0-9]/', '', $numero_original);
        
        // Adicionar código do país
        if (strlen($numeroLimpo) === 11 && substr($numeroLimpo, 0, 2) !== '55') {
            $numero_formatado = '55' . $numeroLimpo;
        } elseif (strlen($numeroLimpo) === 10 && substr($numeroLimpo, 0, 2) !== '55') {
            $numero_formatado = '5519' . $numeroLimpo;
        } elseif (substr($numeroLimpo, 0, 2) === '55') {
            if (substr($numeroLimpo, 0, 4) === '5555') {
                $numero_formatado = substr($numeroLimpo, 2);
            } else {
                $numero_formatado = $numeroLimpo;
            }
        } else {
            $numero_formatado = '55' . $numeroLimpo;
        }
        
        echo "Original: $numero_original\n";
        echo "Formatado: $numero_formatado\n";
        
        // Testar requisição
        $webhookUrl = "https://evolution-evolution-api.zhtcom.easypanel.host/message/sendText/" . $cliente['nome_instancia_whatsapp'];
        
        $mensagem = "AtendeCar identificou um lead qualificado. Nome = " . $lead_name . " e numero = " . $lead_number;
        
        $payload = [
            'number' => $numero_formatado,
            'text' => $mensagem,
            'options' => [
                'delay' => 1200,
                'presence' => 'composing'
            ]
        ];
        
        echo "URL: $webhookUrl\n";
        echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        
        // Fazer requisição
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'apikey: ' . $cliente['token_evo_api']
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "HTTP Code: $httpCode\n";
        echo "Response: $response\n";
        echo "Error: $error\n";
        
        if ($httpCode >= 200 && $httpCode < 300) {
            echo "✅ SUCESSO!\n";
        } else {
            echo "❌ ERRO (HTTP $httpCode)\n";
        }
        
        echo str_repeat("-", 50) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>