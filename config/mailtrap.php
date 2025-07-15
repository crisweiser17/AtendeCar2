<?php
// Configuração específica para Mailtrap
function configurarMailtrap() {
    return [
        'smtp_host' => 'sandbox.smtp.mailtrap.io',
        'smtp_port' => 587,
        'smtp_username' => '7d5be16b754a10',
        'smtp_password' => 'ca82ecd42df904',
        'smtp_from_email' => 'noreply@atendecar.net',
        'smtp_from_name' => 'AtendeCar',
        'smtp_security' => 'tls'
    ];
}

function enviarEmailMailtrap($para, $assunto, $mensagem) {
    $config = configurarMailtrap();
    
    // Configuração do php.ini para Mailtrap
    ini_set('SMTP', $config['smtp_host']);
    ini_set('smtp_port', $config['smtp_port']);
    ini_set('sendmail_from', $config['smtp_from_email']);
    
    // Headers para Mailtrap
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $config['smtp_from_name'] . ' <' . $config['smtp_from_email'] . '>',
        'Reply-To: ' . $config['smtp_from_email'],
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $headers_str = implode("\r\n", $headers);
    
    // Enviar email
    $enviado = mail($para, $assunto, $mensagem, $headers_str, 
        '-f ' . $config['smtp_from_email'] . 
        ' -S ' . $config['smtp_host'] . ':' . $config['smtp_port'] .
        ' -au ' . $config['smtp_username'] . 
        ' -ap ' . $config['smtp_password']);
    
    if ($enviado) {
        return ['success' => true, 'message' => 'Email enviado com sucesso para Mailtrap'];
    } else {
        return ['success' => false, 'message' => 'Erro ao enviar email para Mailtrap'];
    }
}

function enviarEmailRecuperacaoMailtrap($email, $token) {
    $reset_link = "http://localhost:8000/redefinir_senha.php?token=" . $token;
    
    $assunto = "Recuperação de Senha - AtendeCar";
    $mensagem = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #3B82F6;'>Recuperação de Senha</h2>
            <p>Olá,</p>
            <p>Você solicitou a recuperação de senha para sua conta no AtendeCar.</p>
            <p>Clique no botão abaixo para redefinir sua senha:</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='{$reset_link}' style='background-color: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Redefinir Senha</a>
            </p>
            <p><strong>Este link expira em 1 hora.</strong></p>
            <p>Se você não solicitou esta recuperação, ignore este email.</p>
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
            <p style='font-size: 12px; color: #666;'>AtendeCar - Sistema de Gestão de Leads</p>
        </div>
    </body>
    </html>
    ";
    
    return enviarEmailMailtrap($email, $assunto, $mensagem);
}
?>