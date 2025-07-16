<?php
// Teste simples de email sem includes externos
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste de Email - Versão Simples</h1>\n";

// Configurações SMTP diretas
$smtp_config = [
    'host' => 'sandbox.smtp.mailtrap.io',
    'port' => 2525,
    'username' => 'ce4deba7ca79ee',
    'password' => 'f3128a3df002ec',
    'from' => 'noreply@atendecar.net',
    'from_name' => 'AtendeCar'
];

echo "<h2>Configurações:</h2>\n";
echo "<pre>";
print_r($smtp_config);
echo "</pre>";

// Função simples para enviar email via SMTP
function enviarEmailSMTP($to, $subject, $message, $config) {
    $fp = @fsockopen($config['host'], $config['port'], $errno, $errstr, 30);
    
    if (!$fp) {
        return ['success' => false, 'error' => "Erro de conexão: $errstr ($errno)"];
    }
    
    // Ler resposta inicial
    $response = fgets($fp, 512);
    if (substr($response, 0, 3) != '220') {
        fclose($fp);
        return ['success' => false, 'error' => "Resposta inválida: $response"];
    }
    
    // Comandos SMTP
    $commands = [
        "EHLO localhost\r\n",
        "AUTH LOGIN\r\n",
        base64_encode($config['username']) . "\r\n",
        base64_encode($config['password']) . "\r\n",
        "MAIL FROM:<{$config['from']}>\r\n",
        "RCPT TO:<{$to}>\r\n",
        "DATA\r\n"
    ];
    
    $expected = ['250', '334', '334', '235', '250', '250', '354'];
    
    foreach ($commands as $i => $command) {
        fputs($fp, $command);
        $response = fgets($fp, 512);
        
        if (!isset($expected[$i]) || substr($response, 0, 3) != $expected[$i]) {
            fclose($fp);
            return ['success' => false, 'error' => "Erro no comando $i: $response"];
        }
    }
    
    // Enviar corpo do email
    $email_body = "From: {$config['from_name']} <{$config['from']}>\r\n";
    $email_body .= "To: $to\r\n";
    $email_body .= "Subject: $subject\r\n";
    $email_body .= "Content-Type: text/plain; charset=utf-8\r\n";
    $email_body .= "MIME-Version: 1.0\r\n";
    $email_body .= "\r\n";
    $email_body .= $message;
    $email_body .= "\r\n.\r\n";
    
    fputs($fp, $email_body);
    $response = fgets($fp, 512);
    
    fputs($fp, "QUIT\r\n");
    fclose($fp);
    
    if (substr($response, 0, 3) == '250') {
        return ['success' => true, 'message' => 'Email enviado com sucesso'];
    } else {
        return ['success' => false, 'error' => "Erro ao enviar: $response"];
    }
}

// Testar conexão
echo "<h2>Testando Conexão SMTP...</h2>\n";
$fp = @fsockopen($smtp_config['host'], $smtp_config['port'], $errno, $errstr, 10);
if ($fp) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "✅ Conexão SMTP estabelecida com sucesso!";
    echo "</div>";
    fclose($fp);
    
    // Testar envio
    echo "<h2>Testando Envio de Email...</h2>\n";
    $result = enviarEmailSMTP('teste@example.com', 'Teste de Recuperação', 'Este é um teste de email de recuperação de senha.', $smtp_config);
    
    if ($result['success']) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
        echo "✅ " . $result['message'];
        echo "</div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "❌ " . $result['error'];
        echo "</div>";
    }
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "❌ Erro de conexão: $errstr ($errno)";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='recuperar_senha.php'>Voltar para Recuperação de Senha</a></p>";
?>