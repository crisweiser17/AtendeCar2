<?php
// Configuração de email simplificada para Mailtrap
function enviarEmail($para, $assunto, $mensagem, $smtp_config = null) {
    if (!$smtp_config) {
        $smtp_config = json_decode(file_get_contents('config/smtp_config.json'), true);
    }
    
    // Configurações para Mailtrap
    $headers = [
        'From' => $smtp_config['smtp_from_email'],
        'Reply-To' => $smtp_config['smtp_from_email'],
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    // Montar headers
    $headers_str = '';
    foreach ($headers as $key => $value) {
        $headers_str .= $key . ': ' . $value . "\r\n";
    }
    
    // Enviar email
    $enviado = mail($para, $assunto, $mensagem, $headers_str);
    
    if ($enviado) {
        return ['success' => true, 'message' => 'Email enviado com sucesso'];
    } else {
        return ['success' => false, 'message' => 'Erro ao enviar email'];
    }
}

function enviarEmailRecuperacao($email, $token, $smtp_config = null) {
    $reset_link = "https://atendecar.net/sistema/redefinir_senha.php?token=" . $token;
    
    $assunto = "Recuperação de Senha - AtendeCar";
    $mensagem = "
    <html>
    <body>
        <h2>Recuperação de Senha</h2>
        <p>Você solicitou a recuperação de senha para sua conta no AtendeCar.</p>
        <p>Clique no link abaixo para redefinir sua senha:</p>
        <p><a href='{$reset_link}' style='background-color: #3B82F6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Redefinir Senha</a></p>
        <p>Este link expira em 1 hora.</p>
        <p>Se você não solicitou esta recuperação, ignore este email.</p>
    </body>
    </html>
    ";
    
    return enviarEmail($email, $assunto, $mensagem, $smtp_config);
}
?>