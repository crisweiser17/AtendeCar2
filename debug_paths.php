<?php
echo "<h1>Debug de Paths</h1>\n";
echo "<p>Diretório atual: " . getcwd() . "</p>\n";
echo "<p>__DIR__: " . __DIR__ . "</p>\n";
echo "<p>Arquivo atual: " . __FILE__ . "</p>\n";

echo "<h2>Verificando arquivos:</h2>\n";
$files = [
    'config/email_curl.php',
    'config/database.php',
    'config/smtp_config.json'
];

foreach ($files as $file) {
    echo "<p>$file: " . (file_exists($file) ? "✅ EXISTE" : "❌ NÃO EXISTE") . "</p>\n";
}

echo "<h2>Conteúdo do diretório config:</h2>\n";
if (is_dir('config')) {
    $files = scandir('config');
    echo "<pre>";
    print_r($files);
    echo "</pre>";
}
?>