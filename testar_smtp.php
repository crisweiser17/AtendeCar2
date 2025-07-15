<?php
// Teste de configuração SMTP com cURL
session_start();
require_once 'config/database.php';
require_once 'config/email_curl.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Carregar configurações SMTP
$smtp_config = [];
if (file_exists('config/smtp_config.json')) {
    $smtp_config = json_decode(file_get_contents('config/smtp_config.json'), true);
}

if ($_POST && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'] ?? '';
    
    if ($test_email && !empty($smtp_config['smtp_host'])) {
        // Verificar se cURL está disponível
        if (!function_exists('curl_init')) {
            $result = [
                'success' => false,
                'message' => 'cURL não está disponível no servidor'
            ];
        } else {
            try {
                // Enviar email de teste usando cURL
                $assunto = 'Teste SMTP - AtendeCar';
                $mensagem = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Teste de Configuração SMTP</h2>
                    <p>Este é um email de teste enviado pelo sistema AtendeCar.</p>
                    <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                    <p><strong>Configuração:</strong></p>
                    <ul>
                        <li>Host: " . $smtp_config['smtp_host'] . "</li>
                        <li>Port: " . $smtp_config['smtp_port'] . "</li>
                        <li>Username: " . $smtp_config['smtp_username'] . "</li>
                    </ul>
                    <p>Se você está vendo este email, a configuração SMTP está funcionando corretamente!</p>
                </body>
                </html>
                ";
                
                $result = enviarEmailCurl($test_email, $assunto, $mensagem, $smtp_config);
                
            } catch (Exception $e) {
                $result = [
                    'success' => false,
                    'message' => 'Erro ao enviar email: ' . $e->getMessage()
                ];
            }
        }
    } else {
        $result = [
            'success' => false,
            'message' => 'Email de teste ou configuração SMTP não fornecidos'
        ];
    }
    
    // Retornar JSON para AJAX
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// Se não for POST, redirecionar
header('Location: configuracoes.php?tab=smtp');
exit();
?>