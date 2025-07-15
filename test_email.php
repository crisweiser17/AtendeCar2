<?php
// Teste de envio de email com Mailtrap
echo "<h1>Teste de Email com Mailtrap</h1>\n";

// Configuração do Mailtrap
$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;
$username = 'ce4deba7ca79ee';
$password = 'f3128a3df002ec';
$from = 'from@example.com';
$to = 'to@example.com';

// Mensagem de teste
$subject = 'Teste de Email - AtendeCar';
$message = "Este é um teste de email enviado via Mailtrap!\n\n";
$message .= "Se você está vendo este email no Mailtrap, a configuração está funcionando corretamente.\n";
$message .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n\n";
$message .= "AtendeCar - Sistema de Gestão de Leads";

// Headers
$headers = [
    'From: Magic Elves <from@example.com>',
    'Reply-To: from@example.com',
    'X-Mailer: PHP/' . phpversion(),
    'Content-Type: text/plain; charset=UTF-8'
];

// Configuração do php.ini para Mailtrap
ini_set('SMTP', $host);
ini_set('smtp_port', $port);
ini_set('sendmail_from', $from);

// Tentar enviar email
echo "<h2>Configuração:</h2>\n";
echo "<pre>";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Username: $username\n";
echo "From: $from\n";
echo "To: $to\n";
echo "Subject: $subject\n";
echo "</pre>";

echo "<h2>Enviando email...</h2>\n";

// Criar diretório de logs se não existir
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

// Configuração adicional para Mailtrap
$additional_params = "-f$from -S$host:$port -au$username -ap$password";

$enviado = mail($to, $subject, $message, implode("\r\n", $headers), $additional_params);

if ($enviado) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "✅ Email enviado com sucesso para Mailtrap!";
    echo "</div>";
    
    // Log do envio
    $log = date('Y-m-d H:i:s') . " - Email teste enviado para Mailtrap\n";
    file_put_contents('logs/test_email.log', $log, FILE_APPEND);
    
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ol>";
    echo "<li>Acesse: https://mailtrap.io</li>";
    echo "<li>Faça login com suas credenciais</li>";
    echo "<li>Verifique a caixa de entrada do sandbox</li>";
    echo "<li>Você deve ver o email de teste enviado</li>";
    echo "</ol>";
    
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ Erro ao enviar email";
    echo "</div>";
    
    echo "<p><strong>Verificações:</strong></p>";
    echo "<ul>";
    echo "<li>Verifique se o servidor PHP tem permissão para enviar emails</li>";
    echo "<li>Confirme as credenciais do Mailtrap</li>";
    echo "<li>Verifique o log em: logs/test_email.log</li>";
    echo "</ul>";
}

// Informações de debug
echo "<h2>Informações do Sistema:</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "SMTP Port: " . ini_get('smtp_port') . "\n";
echo "Sendmail From: " . ini_get('sendmail_from') . "\n";
echo "</pre>";

// Teste alternativo com curl (se disponível)
echo "<h2>Teste com cURL (opcional):</h2>";
echo "<pre>";
$curl_test = <<<CURL
curl --ssl-reqd \
--url 'smtp://sandbox.smtp.mailtrap.io:2525' \
--user 'ce4deba7ca79ee:f3128a3df002ec' \
--mail-from from@example.com \
--mail-rcpt to@example.com \
--upload-file - <<EOF
From: Magic Elves <from@example.com>
To: Mailtrap Sandbox <to@example.com>
Subject: Teste AtendeCar - Funcionando!
Content-Type: text/plain; charset=utf-8

Parabéns! O sistema de email do AtendeCar está funcionando com Mailtrap.
Este é um teste de integração bem-sucedido.

Data: """ . date('d/m/Y H:i:s') . """
EOF
CURL;

echo "Comando cURL para teste manual:\n";
echo htmlspecialchars($curl_test);
echo "</pre>";

echo "<hr>";
echo "<p><a href='configuracoes.php'>Voltar para Configurações</a> | ";
echo "<a href='index.php'>Voltar para Dashboard</a></p>";
?>