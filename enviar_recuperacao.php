<?php
// Endpoint para enviar email de recuperação de senha
session_start();
require_once 'config/database.php';
require_once 'config/email_curl.php';

header('Content-Type: application/json');

// Carregar configurações SMTP
$smtp_config = [];
if (file_exists('config/smtp_config.json')) {
    $smtp_config = json_decode(file_get_contents('config/smtp_config.json'), true);
}

if ($_POST) {
    $email = $_POST['email'] ?? '';
    
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email é obrigatório']);
        exit();
    }
    
    // Verificar se o email existe
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Email não encontrado']);
        exit();
    }
    
    // Verificar se SMTP está configurado
    if (empty($smtp_config['smtp_username']) || empty($smtp_config['smtp_password'])) {
        echo json_encode(['success' => false, 'message' => 'SMTP não configurado. Configure as credenciais em Configurações > SMTP']);
        exit();
    }
    
    // Verificar se cURL está disponível
    if (!function_exists('curl_init')) {
        echo json_encode(['success' => false, 'message' => 'cURL não está disponível no servidor']);
        exit();
    }
    
    // Gerar token único
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Salvar token no banco
    try {
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
        
        // Inserir novo token
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires_at]);
        
        // Enviar email real com cURL
        $result = enviarEmailRecuperacaoCurl($email, $token, $smtp_config);
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>