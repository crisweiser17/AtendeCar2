<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'gerais';

// Processar formulário de configurações SMTP
if ($_POST && isset($_POST['smtp_config'])) {
    $smtp_host = $_POST['smtp_host'] ?? '';
    $smtp_port = $_POST['smtp_port'] ?? '587';
    $smtp_username = $_POST['smtp_username'] ?? '';
    $smtp_password = $_POST['smtp_password'] ?? '';
    $smtp_from_email = $_POST['smtp_from_email'] ?? '';
    $smtp_from_name = $_POST['smtp_from_name'] ?? 'AtendeCar';
    $smtp_security = $_POST['smtp_security'] ?? 'tls';
    
    // Salvar configurações (simulação - em produção salvar em arquivo ou banco)
    $config_data = [
        'smtp_host' => $smtp_host,
        'smtp_port' => $smtp_port,
        'smtp_username' => $smtp_username,
        'smtp_password' => $smtp_password,
        'smtp_from_email' => $smtp_from_email,
        'smtp_from_name' => $smtp_from_name,
        'smtp_security' => $smtp_security
    ];
    
    // Simular salvamento em arquivo
    file_put_contents('config/smtp_config.json', json_encode($config_data, JSON_PRETTY_PRINT));
    $message = 'Configurações SMTP salvas com sucesso!';
}

// Carregar configurações existentes
$smtp_config = [];
if (file_exists('config/smtp_config.json')) {
    $smtp_config = json_decode(file_get_contents('config/smtp_config.json'), true);
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-blue-600 hover:text-blue-500 mr-4">← Dashboard</a>
                    <h1 class="text-xl font-semibold text-gray-900">Configurações do Sistema</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">Bem-vindo, <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            
            <!-- Mensagens -->
            <?php if ($message): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative mb-4">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Abas de navegação -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <a href="?tab=gerais" 
                       class="<?php echo $active_tab === 'gerais' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Configurações Gerais
                    </a>
                    <a href="?tab=smtp" 
                       class="<?php echo $active_tab === 'smtp' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Configurações SMTP
                    </a>
                </nav>
            </div>

            <!-- Conteúdo das abas -->
            <div class="mt-6">
                <?php if ($active_tab === 'gerais'): ?>
                    <!-- Aba Configurações Gerais -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Configurações Gerais</h2>
                        
                        <div class="space-y-6">
                            <!-- Sincronização de Estoque -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Sincronização de Estoque</h3>
                                <p class="text-sm text-gray-600 mb-4">
                                    Execute a sincronização manual do estoque com os portais de veículos.
                                </p>
                                <a href="cron_sincronizar_estoque.php" target="_blank" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Rodar Sincronização de Estoque
                                </a>
                            </div>

                            <!-- Informações do Sistema -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Informações do Sistema</h3>
                                <div class="space-y-2 text-sm">
                                    <p><strong>Versão:</strong> 1.0</p>
                                    <p><strong>Última atualização:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                                    <p><strong>Timezone:</strong> America/Sao_Paulo</p>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($active_tab === 'smtp'): ?>
                    <!-- Aba Configurações SMTP -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Configurações SMTP</h2>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="smtp_config" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Host SMTP -->
                                <div>
                                    <label for="smtp_host" class="block text-sm font-medium text-gray-700">
                                        Servidor SMTP
                                    </label>
                                    <input type="text" id="smtp_host" name="smtp_host" 
                                           value="<?php echo htmlspecialchars($smtp_config['smtp_host'] ?? ''); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           placeholder="smtp.gmail.com">
                                </div>

                                <!-- Porta SMTP -->
                                <div>
                                    <label for="smtp_port" class="block text-sm font-medium text-gray-700">
                                        Porta
                                    </label>
                                    <input type="number" id="smtp_port" name="smtp_port" 
                                           value="<?php echo htmlspecialchars($smtp_config['smtp_port'] ?? '587'); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           placeholder="587">
                                </div>

                                <!-- Segurança -->
                                <div>
                                    <label for="smtp_security" class="block text-sm font-medium text-gray-700">
                                        Segurança
                                    </label>
                                    <select id="smtp_security" name="smtp_security" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        <option value="tls" <?php echo ($smtp_config['smtp_security'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($smtp_config['smtp_security'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo ($smtp_config['smtp_security'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>Nenhuma</option>
                                    </select>
                                </div>

                                <!-- Email de envio -->
                                <div>
                                    <label for="smtp_from_email" class="block text-sm font-medium text-gray-700">
                                        Email de Envio
                                    </label>
                                    <input type="email" id="smtp_from_email" name="smtp_from_email" 
                                           value="<?php echo htmlspecialchars($smtp_config['smtp_from_email'] ?? ''); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           placeholder="seu@email.com">
                                </div>

                                <!-- Nome de exibição -->
                                <div>
                                    <label for="smtp_from_name" class="block text-sm font-medium text-gray-700">
                                        Nome de Exibição
                                    </label>
                                    <input type="text" id="smtp_from_name" name="smtp_from_name" 
                                           value="<?php echo htmlspecialchars($smtp_config['smtp_from_name'] ?? 'AtendeCar'); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           placeholder="AtendeCar">
                                </div>

                                <!-- Usuário SMTP -->
                                <div>
                                    <label for="smtp_username" class="block text-sm font-medium text-gray-700">
                                        Usuário SMTP
                                    </label>
                                    <input type="text" id="smtp_username" name="smtp_username" 
                                           value="<?php echo htmlspecialchars($smtp_config['smtp_username'] ?? ''); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           placeholder="seu@email.com">
                                </div>

                                <!-- Senha SMTP -->
                                <div>
                                    <label for="smtp_password" class="block text-sm font-medium text-gray-700">
                                        Senha SMTP
                                    </label>
                                    <input type="password" id="smtp_password" name="smtp_password" 
                                           value="<?php echo htmlspecialchars($smtp_config['smtp_password'] ?? ''); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           placeholder="Senha do email">
                                </div>
                            </div>

                            <!-- Informações de ajuda -->
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                <h4 class="text-sm font-medium text-blue-800 mb-2">Informações de Configuração</h4>
                                <div class="text-sm text-blue-700 space-y-1">
                                    <p><strong>Gmail:</strong> smtp.gmail.com, porta 587 (TLS) ou 465 (SSL)</p>
                                    <p><strong>Outlook:</strong> smtp-mail.outlook.com, porta 587</p>
                                    <p><strong>Para Gmail:</strong> Ative "Acesso a app menos seguro" ou use senha de app</p>
                                </div>
                            </div>

                            <div>
                                <button type="submit" 
                                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Salvar Configurações SMTP
                                </button>
                            </div>
                        </form>

                        <!-- Teste de SMTP -->
                        <div class="mt-6 border-t pt-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Testar Configuração SMTP</h4>
                            <form method="POST" action="testar_smtp.php" target="_blank">
                                <div class="flex space-x-4">
                                    <input type="email" name="test_email" 
                                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                           placeholder="Email para teste" required>
                                    <button type="submit" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        Testar SMTP
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>