<?php
// Teste de envio de email com Mailtrap usando cURL - Versão corrigida com SMTP
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste de Email com Mailtrap (cURL SMTP)</h1>\n";

// Configuração do Mailtrap
$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;
$username = 'ce4deba7ca79ee';
$password = 'f3128a3df002ec';
$from = 'from@example.com';
$to = 'to@example.com';

echo "<h2>Configuração:</h2>\n";
echo "<pre>";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Username: $username\n";
echo "From: $from\n";
echo "To: $to\n";
echo "</pre>";

// Criar diretório de logs se não existir
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

// Verificar se cURL está disponível
if (!function_exists('curl_init')) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "❌ cURL não está disponível neste servidor";
    echo "</div>";
    exit();
}

// Função para enviar email via cURL com SMTP
function enviarEmailCurlSMTP($host, $port, $username, $password, $from, $to, $subject, $message) {
    $url = "smtp://$host:$port";
    
    // Montar o email completo
    $email_data = "From: AtendeCar <$from>\r\n";
    $email_data .= "To: Usuario <$to>\r\n";
    $email_data .= "Subject: $subject\r\n";
    $email_data .= "Content-Type: text/plain; charset=utf-8\r\n";
    $email_data .= "MIME-Version: 1.0\r\n";
    $email_data .= "\r\n";
    $email_data .= $message;
    
    // Criar um stream temporário para os dados do email
    $email_stream = fopen('php://temp', 'r+');
    fwrite($email_stream, $email_data);
    rewind($email_stream);
    
    // Inicializar cURL
    $ch = curl_init();
    
    if (!$ch) {
        return [
            'success' => false,
            'error' => 'Falha ao inicializar cURL'
        ];
    }
    
    // Configurações do cURL para SMTP
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_PORT => $port,
        CURLOPT_USERNAME => $username,
        CURLOPT_PASSWORD => $password,
        CURLOPT_MAIL_FROM => $from,
        CURLOPT_MAIL_RCPT => [$to],
        CURLOPT_UPLOAD => true,
        CURLOPT_READDATA => $email_stream,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_VERBOSE => true
    ]);
    
    // Capturar output verbose
    $verbose_handle = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose_handle);
    
    // Executar
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Obter verbose log
    rewind($verbose_handle);
    $verbose_log = stream_get_contents($verbose_handle);
    fclose($verbose_handle);
    
    // Fechar stream
    fclose($email_stream);
    
    curl_close($ch);
    
    return [
        'success' => empty($error),
        'result' => $result,
        'error' => $error,
        'http_code' => $http_code,
        'verbose' => $verbose_log
    ];
}

// Função alternativa usando fsockopen (mais compatível)
function enviarEmailSocket($host, $port, $username, $password, $from, $to, $subject, $message) {
    $fp = fsockopen($host, $port, $errno, $errstr, 30);
    
    if (!$fp) {
        return [
            'success' => false,
            'error' => "Erro de conexão: $errstr ($errno)"
        ];
    }
    
    // Ler resposta inicial
    $response = fgets($fp, 512);
    
    // Enviar comandos SMTP
    $commands = [
        "EHLO localhost\r\n",
        "AUTH LOGIN\r\n",
        base64_encode($username) . "\r\n",
        base64_encode($password) . "\r\n",
        "MAIL FROM:<$from>\r\n",
        "RCPT TO:<$to>\r\n",
        "DATA\r\n",
        "From: AtendeCar <$from>\r\nTo: Usuario <$to>\r\nSubject: $subject\r\n\r\n$message\r\n.\r\n",
        "QUIT\r\n"
    ];
    
    $responses = [];
    foreach ($commands as $command) {
        fputs($fp, $command);
        $responses[] = fgets($fp, 512);
    }
    
    fclose($fp);
    
    return [
        'success' => true,
        'responses' => $responses
    ];
}

// Testar envio
$subject = 'Teste AtendeCar - cURL SMTP';
$message = "Este é um teste de email enviado via cURL SMTP para Mailtrap!\n\n";
$message .= "Se você está vendo este email no Mailtrap, a configuração está funcionando.\n";
$message .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n\n";
$message .= "AtendeCar - Sistema de Gestão de Leads";

echo "<h2>Enviando email via cURL...</h2>\n";

try {
    // Tentar com cURL SMTP
    $resultado = enviarEmailCurlSMTP($host, $port, $username, $password, $from, $to, $subject, $message);
    
    if ($resultado['success']) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "✅ Email enviado com sucesso via cURL SMTP!";
        echo "</div>";
        
        // Log do envio
        $log = date('Y-m-d H:i:s') . " - Email teste enviado via cURL SMTP para Mailtrap - Sucesso\n";
        file_put_contents('logs/test_email_curl.log', $log, FILE_APPEND);
        
    } else {
        echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ cURL SMTP falhou, tentando método alternativo...";
        echo "</div>";
        
        // Tentar com fsockopen
        $resultado_socket = enviarEmailSocket($host, $port, $username, $password, $from, $to, $subject, $message);
        
        if ($resultado_socket['success']) {
            echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "✅ Email enviado com sucesso via Socket SMTP!";
            echo "</div>";
        } else {
            echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "❌ Erro ao enviar email: " . htmlspecialchars($resultado['error']);
            echo "</div>";
        }
        
        // Log do erro
        $log = date('Y-m-d H:i:s') . " - Erro cURL: " . $resultado['error'] . "\n";
        file_put_contents('logs/test_email_curl.log', $log, FILE_APPEND);
    }

    // Mostrar detalhes do debug
    echo "<h2>Debug:</h2>";
    echo "<pre>";
    if (isset($resultado['verbose'])) {
        echo "Verbose Log:\n" . htmlspecialchars($resultado['verbose']) . "\n";
    }
    if (isset($resultado_socket['responses'])) {
        echo "Socket Responses:\n" . print_r($resultado_socket['responses'], true);
    }
    echo "</pre>";

} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "❌ Exceção capturada: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

// Verificações do sistema
echo "<h2>Verificações do Sistema:</h2>";
echo "<pre>";
echo "cURL disponível: " . (function_exists('curl_init') ? 'Sim' : 'Não') . "\n";
echo "fsockopen disponível: " . (function_exists('fsockopen') ? 'Sim' : 'Não') . "\n";
echo "PHP Version: " . phpversion() . "\n";

if (function_exists('curl_version')) {
    $curl_info = curl_version();
    echo "cURL Version: " . $curl_info['version'] . "\n";
    echo "SSL Version: " . $curl_info['ssl_version'] . "\n";
}
echo "</pre>";

echo "<hr>";
echo "<p><a href='debug_smtp.php'>Debug Detalhado</a> | ";
echo "<a href='configuracoes.php'>Voltar para Configurações</a> | ";
echo "<a href='index.php'>Voltar para Dashboard</a></p>";
?>