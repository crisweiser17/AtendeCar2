<?php
// Endpoint para enviar email de recuperação de senha - Usando lógica do test_email_curl.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Configuração do Mailtrap (mesma do test_email_curl.php)
$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;
$username = 'ce4deba7ca79ee';
$password = 'f3128a3df002ec';
$from = 'noreply@atendecar.net';

// Função para enviar email via Socket SMTP (copiada do test_email_curl.php)
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

// Função para enviar email via cURL SMTP (copiada do test_email_curl.php)
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

if ($_POST) {
    $email = $_POST['email'] ?? '';
    
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email é obrigatório']);
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit();
    }
    
    try {
        // Verificar se o email existe
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Email não encontrado']);
            exit();
        }
        
        // Criar tabela se não existir
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        )");
        
        // Limpar tokens antigos
        $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW()")->execute();
        
        // Gerar e salvar token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires_at]);
        
        // Montar email de recuperação
        $subject = 'Recuperação de Senha - AtendeCar';
        $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/redefinir_senha.php?token=" . $token;
        
        $message = "Olá,\n\n";
        $message .= "Você solicitou a recuperação de senha para sua conta no AtendeCar.\n\n";
        $message .= "Clique no link abaixo para redefinir sua senha:\n";
        $message .= $reset_link . "\n\n";
        $message .= "Este link expirará em 1 hora.\n\n";
        $message .= "Se você não solicitou esta recuperação, ignore este email.\n\n";
        $message .= "AtendeCar - Sistema de Gestão de Leads";
        
        // Tentar enviar com cURL SMTP primeiro (como no test_email_curl.php)
        $resultado = enviarEmailCurlSMTP($host, $port, $username, $password, $from, $email, $subject, $message);
        
        if ($resultado['success']) {
            echo json_encode(['success' => true, 'message' => 'Email de recuperação enviado com sucesso!']);
        } else {
            // Se cURL falhar, tentar com Socket (como no test_email_curl.php)
            $resultado_socket = enviarEmailSocket($host, $port, $username, $password, $from, $email, $subject, $message);
            
            if ($resultado_socket['success']) {
                echo json_encode(['success' => true, 'message' => 'Email de recuperação enviado com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao enviar email: ' . $resultado['error']]);
            }
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>