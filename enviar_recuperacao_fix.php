<?php
// Endpoint para enviar email de recuperação de senha - Versão corrigida
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Configurações SMTP diretas
$smtp_config = [
    'host' => 'sandbox.smtp.mailtrap.io',
    'port' => 2525,
    'username' => 'ce4deba7ca79ee',
    'password' => 'f3128a3df002ec',
    'from' => 'noreply@atendecar.net',
    'from_name' => 'AtendeCar'
];

header('Content-Type: application/json');

// Função para conectar ao banco de dados
function getConnection() {
    try {
        $host = 'localhost';
        $dbname = 'uzybhpjay_atendecar';
        $username = 'uzybhpjay';
        $password = '7qJb7h7yX9';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro de conexão: " . $e->getMessage());
    }
}

// Função para enviar email via SMTP
function enviarEmailSMTP($to, $subject, $message, $config) {
    $fp = @fsockopen($config['host'], $config['port'], $errno, $errstr, 30);
    
    if (!$fp) {
        return ['success' => false, 'error' => "Erro de conexão SMTP: $errstr ($errno)"];
    }
    
    // Ler resposta inicial
    $response = fgets($fp, 512);
    if (substr($response, 0, 3) != '220') {
        fclose($fp);
        return ['success' => false, 'error' => "Resposta SMTP inválida: $response"];
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
            return ['success' => false, 'error' => "Erro SMTP no comando $i: $response"];
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
        return ['success' => false, 'error' => "Erro ao enviar email: $response"];
    }
}

// Função para enviar email de recuperação
function enviarEmailRecuperacao($email, $token) {
    global $smtp_config;
    
    $subject = "Recuperação de Senha - AtendeCar";
    
    // Detectar protocolo e host
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $reset_link = "$protocol://$host/redefinir_senha.php?token=" . $token;
    
    $message = "Olá,\n\n";
    $message .= "Você solicitou a recuperação de senha para sua conta no AtendeCar.\n\n";
    $message .= "Clique no link abaixo para redefinir sua senha:\n";
    $message .= $reset_link . "\n\n";
    $message .= "Este link expirará em 1 hora.\n\n";
    $message .= "Se você não solicitou esta recuperação, ignore este email.\n\n";
    $message .= "AtendeCar - Sistema de Gestão de Leads";
    
    return enviarEmailSMTP($email, $subject, $message, $smtp_config);
}

// Processar requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        // Enviar email
        $result = enviarEmailRecuperacao($email, $token);
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>