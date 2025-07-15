<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'uuzybhpjay');
define('DB_USER', 'uuzybhpjay');
define('DB_PASS', 'yVRuFD2nk3');

// Função para conectar ao banco de dados
function getConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Erro na conexão: " . $e->getMessage());
    }
}

$message = '';
$messageType = '';

// Processar formulário
if ($_POST) {
    $nome_responsavel = $_POST['nome_responsavel'] ?? '';
    $email = $_POST['email'] ?? '';
    $celular = $_POST['celular'] ?? '';
    $nome_loja = $_POST['nome_loja'] ?? '';
    $whatsapp_loja = $_POST['whatsapp_loja'] ?? '';
    $telefone_loja = $_POST['telefone_loja'] ?? '';
    $website_loja = $_POST['website_loja'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $endereco_loja = $_POST['endereco_loja'] ?? '';
    $numero_endereco = $_POST['numero_endereco'] ?? '';
    $complemento_endereco = $_POST['complemento_endereco'] ?? '';
    $estoque_medio = $_POST['estoque_medio'] ?? null;
    $vendas_mensais = $_POST['vendas_mensais'] ?? null;
    $tipo_estoque = $_POST['tipo_estoque'] ?? [];
    $segmento_atuacao = $_POST['segmento_atuacao'] ?? [];
    $alertas_whatsapp = array_filter($_POST['alertas_whatsapp'] ?? []);
    $url_estoque = $_POST['url_estoque'] ?? '';
    
    // Validação básica
    if (empty($nome_responsavel) || empty($email) || empty($celular) || empty($nome_loja)) {
        $message = 'Por favor, preencha todos os campos obrigatórios.';
        $messageType = 'error';
    } else {
        try {
            $pdo = getConnection();
            
            $stmt = $pdo->prepare("INSERT INTO clientes (
                nome_responsavel, email, celular, nome_loja, whatsapp_loja, telefone_loja,
                website_loja, cep, endereco_loja, numero_endereco, complemento_endereco, estoque_medio, 
                vendas_mensais, tipo_estoque, segmento_atuacao, alertas_whatsapp, url_estoque,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
            
            $stmt->execute([
                $nome_responsavel, $email, $celular, $nome_loja, $whatsapp_loja, $telefone_loja,
                $website_loja, $cep, $endereco_loja, $numero_endereco, $complemento_endereco, $estoque_medio, 
                $vendas_mensais, json_encode($tipo_estoque), json_encode($segmento_atuacao), 
                json_encode($alertas_whatsapp), $url_estoque
            ]);
            
            $message = 'Cadastro realizado com sucesso! Entraremos em contato em breve.';
            $messageType = 'success';
            $cadastroRealizado = true;
            
            // Limpar formulário após sucesso
            $_POST = [];
            
        } catch (Exception $e) {
            $message = 'Erro ao realizar cadastro: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AtendeCar - Cadastro de Cliente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/preline@2.0.3/dist/preline.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Estilo customizado para inputs */
        input[type="text"],
        input[type="email"],
        input[type="url"],
        input[type="number"],
        textarea,
        select {
            background-color: #deedfc !important;
        }
        
        /* Manter foco com a mesma cor */
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="url"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            background-color: #deedfc !important;
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-center h-16 items-center">
                <h1 class="text-2xl font-bold text-gray-900">AtendeCar</h1>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            
            <?php if (isset($cadastroRealizado) && $cadastroRealizado): ?>
                <!-- Página de sucesso -->
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-4">Cadastro Realizado!</h2>
                    <div class="bg-green-50 border border-green-200 rounded-md p-6 max-w-md mx-auto">
                        <p class="text-lg text-green-700 font-medium">
                            Cadastro realizado com sucesso! Entraremos em contato em breve.
                        </p>
                    </div>
                    <div class="mt-8">
                        <a href="/" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-600 bg-blue-100 hover:bg-blue-200">
                            Voltar ao início
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Formulário de cadastro -->
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-extrabold text-gray-900">Cadastro de Cliente</h2>
                    <p class="mt-2 text-lg text-gray-600">
                        Preencha os dados abaixo para se cadastrar em nosso sistema
                    </p>
                </div>
                
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-8">
                
                <!-- Dados Básicos -->
                <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <div class="md:grid md:grid-cols-3 md:gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Dados Básicos</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Informações básicas do responsável.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <label for="nome_responsavel" class="block text-sm font-medium text-gray-700">
                                        Nome da Pessoa Responsável *
                                    </label>
                                    <input type="text" name="nome_responsavel" id="nome_responsavel" required
                                           value="<?php echo htmlspecialchars($_POST['nome_responsavel'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                                    <input type="email" name="email" id="email" required
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="celular" class="block text-sm font-medium text-gray-700">Celular *</label>
                                    <input type="text" name="celular" id="celular" required
                                           value="<?php echo htmlspecialchars($_POST['celular'] ?? ''); ?>"
                                           onkeyup="formatarTelefone(this)"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dados da Loja -->
                <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <div class="md:grid md:grid-cols-3 md:gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Dados da Loja</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Informações sobre sua loja de veículos.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <label for="nome_loja" class="block text-sm font-medium text-gray-700">
                                        Nome da Loja de Veículos *
                                    </label>
                                    <input type="text" name="nome_loja" id="nome_loja" required
                                           value="<?php echo htmlspecialchars($_POST['nome_loja'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="whatsapp_loja" class="block text-sm font-medium text-gray-700">WhatsApp da Loja</label>
                                    <input type="text" name="whatsapp_loja" id="whatsapp_loja"
                                           value="<?php echo htmlspecialchars($_POST['whatsapp_loja'] ?? ''); ?>"
                                           onkeyup="formatarTelefone(this)"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="telefone_loja" class="block text-sm font-medium text-gray-700">Telefone Fixo da Loja</label>
                                    <input type="text" name="telefone_loja" id="telefone_loja"
                                           value="<?php echo htmlspecialchars($_POST['telefone_loja'] ?? ''); ?>"
                                           onkeyup="formatarTelefone(this)"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label for="website_loja" class="block text-sm font-medium text-gray-700">
                                        Website da Loja
                                    </label>
                                    <input type="url" name="website_loja" id="website_loja"
                                           value="<?php echo htmlspecialchars($_POST['website_loja'] ?? 'https://'); ?>"
                                           placeholder="https://exemplo.com"
                                           onblur="normalizarURL(this)"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label for="cep" class="block text-sm font-medium text-gray-700">CEP</label>
                                    <input type="text" name="cep" id="cep" 
                                           value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>"
                                           placeholder="00000-000"
                                           onkeyup="if(this.value.replace(/\D/g, '').length === 8) buscarCEP(this.value.replace(/\D/g, ''))"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label for="endereco_loja" class="block text-sm font-medium text-gray-700">
                                        Endereço da Loja
                                    </label>
                                    <textarea name="endereco_loja" id="endereco_loja" rows="3"
                                              class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['endereco_loja'] ?? ''); ?></textarea>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="numero_endereco" class="block text-sm font-medium text-gray-700">
                                        Número
                                    </label>
                                    <input type="text" name="numero_endereco" id="numero_endereco"
                                           value="<?php echo htmlspecialchars($_POST['numero_endereco'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="complemento_endereco" class="block text-sm font-medium text-gray-700">
                                        Complemento
                                    </label>
                                    <input type="text" name="complemento_endereco" id="complemento_endereco"
                                           value="<?php echo htmlspecialchars($_POST['complemento_endereco'] ?? ''); ?>"
                                           placeholder="Apto, Sala, Bloco, etc."
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="estoque_medio" class="block text-sm font-medium text-gray-700">
                                        Estoque Médio de Veículos
                                    </label>
                                    <input type="number" name="estoque_medio" id="estoque_medio"
                                           value="<?php echo htmlspecialchars($_POST['estoque_medio'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="vendas_mensais" class="block text-sm font-medium text-gray-700">
                                        Média de Vendas Mensais
                                    </label>
                                    <input type="number" name="vendas_mensais" id="vendas_mensais"
                                           value="<?php echo htmlspecialchars($_POST['vendas_mensais'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Tipo de Estoque
                                    </label>
                                    <div class="space-y-2">
                                        <?php
                                        $tipos = ['novos', 'seminovos', 'importados'];
                                        $tipo_estoque_selected = $_POST['tipo_estoque'] ?? [];
                                        foreach ($tipos as $tipo):
                                        ?>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="tipo_estoque[]" value="<?php echo $tipo; ?>"
                                                       <?php echo in_array($tipo, $tipo_estoque_selected) ? 'checked' : ''; ?>
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700 capitalize"><?php echo $tipo; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-span-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Segmento de Atuação
                                    </label>
                                    <div class="space-y-2">
                                        <?php
                                        $segmentos = ['populares', 'intermediarios', 'luxo', 'outros'];
                                        $segmento_atuacao_selected = $_POST['segmento_atuacao'] ?? [];
                                        foreach ($segmentos as $segmento):
                                        ?>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="segmento_atuacao[]" value="<?php echo $segmento; ?>"
                                                       <?php echo in_array($segmento, $segmento_atuacao_selected) ? 'checked' : ''; ?>
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700 capitalize"><?php echo $segmento; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações Adicionais -->
                <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <div class="md:grid md:grid-cols-3 md:gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Informações Adicionais</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Dados para configuração do sistema.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <label for="url_estoque" class="block text-sm font-medium text-gray-700">
                                        URL do Estoque
                                        <span class="text-xs text-gray-500">(endereço da sua página no carros.com.br)</span>
                                    </label>
                                    <input type="url" name="url_estoque" id="url_estoque"
                                           value="<?php echo htmlspecialchars($_POST['url_estoque'] ?? 'https://'); ?>"
                                           placeholder="https://exemplo.carros.com.br"
                                           onblur="normalizarURL(this)"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Alertas de Hot Leads (WhatsApp)
                                    </label>
                                    <div id="alertas_whatsapp_container">
                                        <?php 
                                        $alertas_whatsapp_posted = $_POST['alertas_whatsapp'] ?? [''];
                                        if (empty($alertas_whatsapp_posted[0])): 
                                        ?>
                                            <div class="flex gap-2 mb-2">
                                                <input type="text" name="alertas_whatsapp[]" 
                                                       class="flex-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"
                                                       placeholder="(11) 99999-9999" onkeyup="formatarTelefone(this)">
                                                <button type="button" onclick="this.parentElement.remove()" 
                                                        class="py-3 px-4 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                                    Remover
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($alertas_whatsapp_posted as $whatsapp): ?>
                                                <div class="flex gap-2 mb-2">
                                                    <input type="text" name="alertas_whatsapp[]" 
                                                           value="<?php echo htmlspecialchars($whatsapp); ?>"
                                                           class="flex-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"
                                                           placeholder="(11) 99999-9999" onkeyup="formatarTelefone(this)">
                                                    <button type="button" onclick="this.parentElement.remove()" 
                                                            class="py-3 px-4 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                                        Remover
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" onclick="adicionarWhatsApp()" 
                                            class="mt-2 py-2 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        Adicionar WhatsApp
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botão de envio -->
                <div class="flex justify-center">
                    <button type="submit" 
                            class="inline-flex justify-center py-3 px-8 border border-transparent shadow-sm text-lg font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Realizar Cadastro
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Função para normalizar URLs
    function normalizarURL(input) {
        let url = input.value.trim();
        if (url && !url.startsWith('http://') && !url.startsWith('https://')) {
            // Remove www. se existir para evitar duplicação
            url = url.replace(/^www\./, '');
            // Adiciona https://
            url = 'https://' + url;
            input.value = url;
        }
    }
    
    // Função para buscar CEP
    function buscarCEP(cep) {
        if (cep.length === 8) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('endereco_loja').value = 
                            `${data.logradouro}, ${data.bairro}, ${data.localidade} - ${data.uf}`;
                    }
                })
                .catch(error => console.error('Erro ao buscar CEP:', error));
        }
    }
    
    // Função para formatar telefone
    function formatarTelefone(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length <= 10) {
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        } else {
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        }
        input.value = value;
    }
    
    // Função para adicionar campo de WhatsApp para alertas
    function adicionarWhatsApp() {
        const container = document.getElementById('alertas_whatsapp_container');
        const div = document.createElement('div');
        div.className = 'flex gap-2 mb-2';
        div.innerHTML = `
            <input type="text" name="alertas_whatsapp[]" 
                   class="flex-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"
                   placeholder="(11) 99999-9999" onkeyup="formatarTelefone(this)" style="background-color: #deedfc !important;">
            <button type="button" onclick="this.parentElement.remove()" 
                    class="py-3 px-4 bg-red-600 text-white rounded-lg hover:bg-red-700">
                Remover
            </button>
        `;
        container.appendChild(div);
    }
</script>

</body>
</html>