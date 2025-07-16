<?php
// Teste para verificar se o envio de email de recuperação está funcionando
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste de Envio de Email de Recuperação</h1>\n";

// Carregar configurações SMTP
$smtp_config = [];
if (file_exists('config/smtp_config.json')) {
    $smtp_config = json_decode(file_get_contents('config/smtp_config.json'), true);
    echo "<h2>Configurações SMTP:</h2>\n";
    echo "<pre>";
    print_r($smtp_config);
    echo "</pre>";
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "❌ Arquivo config/smtp_config.json não encontrado";
    echo "</div>";
    exit();
}

// Testar conexão SMTP
require_once 'config/email_curl.php';

// Mapear configurações como no enviar_recuperacao.php
$smtp_mapped = [
    'host' => $smtp_config['smtp_host'] ?? '',
    'port' => $smtp_config['smtp_port'] ?? '',
    'username' => $smtp_config['smtp_username'] ?? '',
    'password' => $smtp_config['smtp_password'] ?? ''
];

echo "<h2>Configurações Mapeadas:</h2>\n";
echo "<pre>";
print_r($smtp_mapped);
echo "</pre>";

$email = new EmailCurl(
    $smtp_mapped['host'],
    $smtp_mapped['port'],
    $smtp_mapped['username'],
    $smtp_mapped['password']
);

echo "<h2>Testando Conexão SMTP...</h2>\n";
$testResult = $email->testConnection();

if ($testResult['success']) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "✅ " . $testResult['message'];
    echo "</div>";
    
    // Testar envio real
    echo "<h2>Testando Envio de Email...</h2>\n";
    $testEmail = "teste@example.com";
    $testSubject = "Teste de Recuperação - AtendeCar";
    $testMessage = "Este é um teste de email de recuperação de senha.\n\nSe você recebeu este email, a configuração está funcionando corretamente.";
    
    $result = $email->send($testEmail, $testSubject, $testMessage);
    
    if ($result['success']) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
        echo "✅ Email de teste enviado com sucesso!";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "❌ Erro ao enviar email: " . htmlspecialchars($result['error']);
        echo "</div>";
    }
    
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "❌ " . $testResult['message'];
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='recuperar_senha.php'>Voltar para Recuperação de Senha</a></p>";
?>