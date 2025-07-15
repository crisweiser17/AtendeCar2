<?php
// Configuração de email com cURL - Versão corrigida
// Este arquivo substitui o uso de PHPMailer por cURL puro

class EmailCurl {
    private $host;
    private $port;
    private $username;
    private $password;
    private $from;
    private $fromName;
    
    public function __construct($host = null, $port = null, $username = null, $password = null) {
        // Usar configurações do arquivo JSON se não fornecidas
        $config = $this->loadConfig();
        
        $this->host = $host ?? $config['host'];
        $this->port = $port ?? $config['port'];
        $this->username = $username ?? $config['username'];
        $this->password = $password ?? $config['password'];
        $this->from = $config['from'] ?? 'noreply@atendecar.com.br';
        $this->fromName = $config['from_name'] ?? 'AtendeCar';
    }
    
    private function loadConfig() {
        $configFile = 'config/smtp_config.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            return $config ?: [];
        }
        return [];
    }
    
    public function send($to, $subject, $message, $toName = '') {
        // Criar diretório de logs se não existir
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        // Registrar tentativa
        $log = date('Y-m-d H:i:s') . " - Tentando enviar email para: $to - Assunto: $subject\n";
        file_put_contents('logs/email_curl.log', $log, FILE_APPEND);
        
        // Tentar com fsockopen (mais confiável)
        return $this->sendViaSocket($to, $subject, $message, $toName);
    }
    
    private function sendViaSocket($to, $subject, $message, $toName = '') {
        $fp = @fsockopen($this->host, $this->port, $errno, $errstr, 30);
        
        if (!$fp) {
            $error = "Erro de conexão SMTP: $errstr ($errno)";
            $this->logError($error);
            return ['success' => false, 'error' => $error];
        }
        
        // Ler resposta inicial
        $response = fgets($fp, 512);
        if (substr($response, 0, 3) != '220') {
            fclose($fp);
            $error = "Resposta SMTP inválida: $response";
            $this->logError($error);
            return ['success' => false, 'error' => $error];
        }
        
        // Comandos SMTP
        $commands = [
            "EHLO localhost\r\n",
            "AUTH LOGIN\r\n",
            base64_encode($this->username) . "\r\n",
            base64_encode($this->password) . "\r\n",
            "MAIL FROM:<{$this->from}>\r\n",
            "RCPT TO:<{$to}>\r\n",
            "DATA\r\n"
        ];
        
        $expected = ['250', '334', '334', '235', '250', '250', '354'];
        
        foreach ($commands as $i => $command) {
            fputs($fp, $command);
            $response = fgets($fp, 512);
            
            if (!isset($expected[$i]) || substr($response, 0, 3) != $expected[$i]) {
                fclose($fp);
                $error = "Erro SMTP no comando $i: $response";
                $this->logError($error);
                return ['success' => false, 'error' => $error];
            }
        }
        
        // Enviar corpo do email
        $email_body = "From: {$this->fromName} <{$this->from}>\r\n";
        $email_body .= "To: " . ($toName ? "$toName <$to>" : $to) . "\r\n";
        $email_body .= "Subject: $subject\r\n";
        $email_body .= "Content-Type: text/plain; charset=utf-8\r\n";
        $email_body .= "MIME-Version: 1.0\r\n";
        $email_body .= "\r\n";
        $email_body .= $message;
        $email_body .= "\r\n.\r\n";
        
        fputs($fp, $email_body);
        $response = fgets($fp, 512);
        
        // Enviar QUIT
        fputs($fp, "QUIT\r\n");
        fclose($fp);
        
        if (substr($response, 0, 3) == '250') {
            $log = date('Y-m-d H:i:s') . " - Email enviado com sucesso para: $to\n";
            file_put_contents('logs/email_curl.log', $log, FILE_APPEND);
            return ['success' => true, 'message' => 'Email enviado com sucesso'];
        } else {
            $error = "Erro ao enviar email: $response";
            $this->logError($error);
            return ['success' => false, 'error' => $error];
        }
    }
    
    private function logError($error) {
        $log = date('Y-m-d H:i:s') . " - ERRO: $error\n";
        file_put_contents('logs/email_curl.log', $log, FILE_APPEND);
    }
    
    public function testConnection() {
        $fp = @fsockopen($this->host, $this->port, $errno, $errstr, 10);
        
        if (!$fp) {
            return [
                'success' => false,
                'error' => "Não foi possível conectar ao servidor SMTP: $errstr ($errno)"
            ];
        }
        
        $response = fgets($fp, 512);
        fclose($fp);
        
        if (substr($response, 0, 3) == '220') {
            return [
                'success' => true,
                'message' => 'Conexão SMTP estabelecida com sucesso'
            ];
        } else {
            return [
                'success' => false,
                'error' => "Resposta inesperada do servidor: $response"
            ];
        }
    }
}

// Função auxiliar para uso simples
function enviarEmail($to, $subject, $message, $toName = '') {
    $email = new EmailCurl();
    return $email->send($to, $subject, $message, $toName);
}

// Função específica para email de recuperação
function enviarEmailRecuperacaoCurl($email, $token, $smtp_config) {
    $subject = "Recuperação de Senha - AtendeCar";
    
    $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/redefinir_senha.php?token=" . $token;
    
    $message = "Olá,\n\n";
    $message .= "Você solicitou a recuperação de senha para sua conta no AtendeCar.\n\n";
    $message .= "Clique no link abaixo para redefinir sua senha:\n";
    $message .= $reset_link . "\n\n";
    $message .= "Este link expirará em 1 hora.\n\n";
    $message .= "Se você não solicitou esta recuperação, ignore este email.\n\n";
    $message .= "AtendeCar - Sistema de Gestão de Leads";
    
    $emailObj = new EmailCurl(
        $smtp_config['host'],
        $smtp_config['port'],
        $smtp_config['username'],
        $smtp_config['password']
    );
    
    return $emailObj->send($email, $subject, $message);
}

// Função para teste de SMTP
function enviarEmailCurl($to, $subject, $message, $smtp_config) {
    $email = new EmailCurl(
        $smtp_config['host'],
        $smtp_config['port'],
        $smtp_config['username'],
        $smtp_config['password']
    );
    return $email->send($to, $subject, $message);
}
?>