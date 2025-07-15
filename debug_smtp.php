<?php
// Debug simples para identificar erro 500
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug SMTP - Identificando Erro 500</h1>\n";

try {
    echo "<h2>1. Verificações básicas:</h2>\n";
    echo "<pre>";
    echo "PHP Version: " . phpversion() . "\n";
    echo "cURL disponível: " . (function_exists('curl_init') ? 'Sim' : 'Não') . "\n";
    echo "JSON disponível: " . (function_exists('json_decode') ? 'Sim' : 'Não') . "\n";
    echo "</pre>";

    echo "<h2>2. Testando arquivo de configuração:</h2>\n";
    echo "<pre>";
    
    if (file_exists('config/smtp_config.json')) {
        echo "Arquivo config/smtp_config.json existe: Sim\n";
        $config_content = file_get_contents('config/smtp_config.json');
        echo "Conteúdo do arquivo:\n" . htmlspecialchars($config_content) . "\n";
        
        $smtp_config = json_decode($config_content, true);
        if ($smtp_config) {
            echo "JSON válido: Sim\n";
            echo "Host: " . ($smtp_config['smtp_host'] ?? 'não definido') . "\n";
            echo "Port: " . ($smtp_config['smtp_port'] ?? 'não definido') . "\n";
            echo "Username: " . ($smtp_config['smtp_username'] ?? 'não definido') . "\n";
        } else {
            echo "JSON válido: Não - Erro: " . json_last_error_msg() . "\n";
        }
    } else {
        echo "Arquivo config/smtp_config.json existe: Não\n";
    }
    echo "</pre>";

    echo "<h2>3. Testando cURL básico:</h2>\n";
    echo "<pre>";
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        if ($ch) {
            echo "cURL init: Sucesso\n";
            curl_setopt($ch, CURLOPT_URL, 'https://httpbin.org/get');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $result = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                echo "cURL teste: Erro - " . $error . "\n";
            } else {
                echo "cURL teste: Sucesso (conexão HTTP funciona)\n";
            }
        } else {
            echo "cURL init: Falhou\n";
        }
    } else {
        echo "cURL não disponível\n";
    }
    echo "</pre>";

    echo "<h2>4. Testando conexão SMTP simples:</h2>\n";
    echo "<pre>";
    
    if (function_exists('curl_init') && file_exists('config/smtp_config.json')) {
        $smtp_config = json_decode(file_get_contents('config/smtp_config.json'), true);
        
        if ($smtp_config) {
            $host = $smtp_config['smtp_host'];
            $port = $smtp_config['smtp_port'];
            $username = $smtp_config['smtp_username'];
            $password = $smtp_config['smtp_password'];
            
            echo "Testando conexão com $host:$port...\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "smtp://$host:$port");
            curl_setopt($ch, CURLOPT_CONNECT_ONLY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $result = curl_exec($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            if ($error) {
                echo "Conexão SMTP: Erro - " . $error . "\n";
            } else {
                echo "Conexão SMTP: Sucesso\n";
                echo "Response code: " . $info['response_code'] . "\n";
            }
        }
    }
    echo "</pre>";

    echo "<h2>5. Verificando diretórios:</h2>\n";
    echo "<pre>";
    echo "Diretório atual: " . getcwd() . "\n";
    echo "Diretório config existe: " . (is_dir('config') ? 'Sim' : 'Não') . "\n";
    echo "Diretório logs existe: " . (is_dir('logs') ? 'Sim' : 'Não') . "\n";
    
    if (!is_dir('logs')) {
        echo "Criando diretório logs...\n";
        mkdir('logs', 0755, true);
        echo "Diretório logs criado: " . (is_dir('logs') ? 'Sim' : 'Não') . "\n";
    }
    echo "</pre>";

} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Erro capturado:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
} catch (Error $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Erro fatal capturado:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<p>Debug concluído. Se ainda houver erro 500, verifique os logs do servidor web.</p>";
?>