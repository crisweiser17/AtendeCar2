<?php
// Script de teste para debug do envio de WhatsApp
// Use: php test_debug_whatsapp.php

// Configuração de teste
$test_number = "5519998989999"; // Número de teste
$test_message = "Teste de mensagem via Evolution API";
$test_instance = "CrisBrasil";
$test_token = "seu_token_aqui"; // Substitua pelo token real

// URL da API
$webhookUrl = "https://evolution-evolution-api.zhtcom.easypanel.host/message/sendText/" . $test_instance;

// Payload
$payload = [
    'number' => $test_number,
    'text' => $test_message,
    'options' => [
        'delay' => 1200,
        'presence' => 'composing'
    ]
];

echo "=== TESTE DE ENVIO WHATSAPP ===\n";
echo "URL: $webhookUrl\n";
echo "Número: $test_number\n";
echo "Mensagem: $test_message\n";
echo "Instância: $test_instance\n";
echo "Token: " . substr($test_token, 0, 10) . "...\n\n";

echo "Payload JSON:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Configurar cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $webhookUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'apikey: ' . $test_token
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_VERBOSE => true
]);

echo "=== EXECUTANDO REQUISIÇÃO ===\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
echo "Error: $error\n";

if ($httpCode >= 200 && $httpCode < 300) {
    echo "\n✅ SUCESSO: Mensagem enviada com sucesso!\n";
} else {
    echo "\n❌ ERRO: Falha ao enviar mensagem (HTTP $httpCode)\n";
}
?>