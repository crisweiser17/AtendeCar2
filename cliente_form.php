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

$pdo = getConnection();
$message = '';
$messageType = '';
$cliente = null;
$isEdit = false;

// Verificar se é edição
if (isset($_GET['id'])) {
    $isEdit = true;
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        header('Location: clientes.php');
        exit();
    }
}

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
    $nome_instancia_whatsapp = $_POST['nome_instancia_whatsapp'] ?? '';
    $token_evo_api = $_POST['token_evo_api'] ?? '';
    $url_estoque = $_POST['url_estoque'] ?? '';
    $alertas_whatsapp = array_filter($_POST['alertas_whatsapp'] ?? []);
    $cobranca_ativa = isset($_POST['cobranca_ativa']) ? 1 : 0;
    $status = $_POST['status'] ?? 'pendente';
    $motivo_desabilitacao = $_POST['motivo_desabilitacao'] ?? '';
    
    // Validação básica
    if (empty($nome_responsavel) || empty($email) || empty($celular) || empty($nome_loja)) {
        $message = 'Por favor, preencha todos os campos obrigatórios.';
        $messageType = 'error';
    } else {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE clientes SET
                    nome_responsavel = ?, email = ?, celular = ?, nome_loja = ?,
                    whatsapp_loja = ?, telefone_loja = ?, website_loja = ?, cep = ?, endereco_loja = ?,
                    numero_endereco = ?, complemento_endereco = ?, estoque_medio = ?, vendas_mensais = ?,
                    tipo_estoque = ?, segmento_atuacao = ?, nome_instancia_whatsapp = ?, token_evo_api = ?,
                    url_estoque = ?, alertas_whatsapp = ?, cobranca_ativa = ?, status = ?, motivo_desabilitacao = ?
                    WHERE id = ?");
                
                $stmt->execute([
                    $nome_responsavel, $email, $celular, $nome_loja,
                    $whatsapp_loja, $telefone_loja, $website_loja, $cep, $endereco_loja,
                    $numero_endereco, $complemento_endereco, $estoque_medio, $vendas_mensais,
                    json_encode($tipo_estoque), json_encode($segmento_atuacao),
                    $nome_instancia_whatsapp, $token_evo_api, $url_estoque, json_encode($alertas_whatsapp),
                    $cobranca_ativa, $status, $motivo_desabilitacao,
                    $cliente['id']
                ]);
                
                $message = 'Cliente atualizado com sucesso!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO clientes (
                    nome_responsavel, email, celular, nome_loja, whatsapp_loja, telefone_loja,
                    website_loja, cep, endereco_loja, numero_endereco, complemento_endereco, estoque_medio,
                    vendas_mensais, tipo_estoque, segmento_atuacao, nome_instancia_whatsapp, token_evo_api,
                    url_estoque, alertas_whatsapp, cobranca_ativa, status, motivo_desabilitacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $nome_responsavel, $email, $celular, $nome_loja, $whatsapp_loja, $telefone_loja,
                    $website_loja, $cep, $endereco_loja, $numero_endereco, $complemento_endereco, $estoque_medio,
                    $vendas_mensais, json_encode($tipo_estoque), json_encode($segmento_atuacao),
                    $nome_instancia_whatsapp, $token_evo_api, $url_estoque, json_encode($alertas_whatsapp),
                    $cobranca_ativa, $status, $motivo_desabilitacao
                ]);
                
                $message = 'Cliente cadastrado com sucesso!';
            }
            
            $messageType = 'success';
            
            // Recarregar dados se for edição
            if ($isEdit) {
                $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
                $stmt->execute([$cliente['id']]);
                $cliente = $stmt->fetch();
            }
            
        } catch (Exception $e) {
            $message = 'Erro ao salvar cliente: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Preparar dados para o formulário
if ($cliente) {
    $tipo_estoque = json_decode($cliente['tipo_estoque'] ?? '[]', true) ?: [];
    $segmento_atuacao = json_decode($cliente['segmento_atuacao'] ?? '[]', true) ?: [];
    $alertas_whatsapp = json_decode($cliente['alertas_whatsapp'] ?? '[]', true) ?: [];
} else {
    $tipo_estoque = [];
    $segmento_atuacao = [];
    $alertas_whatsapp = [];
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="clientes.php" class="text-blue-600 hover:text-blue-500 mr-4">← Voltar</a>
                    <h1 class="text-xl font-semibold text-gray-900">
                        <?php echo $isEdit ? 'Editar Cliente' : 'Novo Cliente'; ?>
                    </h1>
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
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            
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
                                Informações básicas do cliente e responsável.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <label for="nome_responsavel" class="block text-sm font-medium text-gray-700">
                                        Nome da Pessoa Responsável *
                                    </label>
                                    <input type="text" name="nome_responsavel" id="nome_responsavel" required
                                           value="<?php echo htmlspecialchars($cliente['nome_responsavel'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                                    <input type="email" name="email" id="email" required
                                           value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="celular" class="block text-sm font-medium text-gray-700">Celular *</label>
                                    <input type="text" name="celular" id="celular" required
                                           value="<?php echo htmlspecialchars($cliente['celular'] ?? ''); ?>"
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
                                Informações sobre a loja de veículos.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <label for="nome_loja" class="block text-sm font-medium text-gray-700">
                                        Nome da Loja de Veículos *
                                    </label>
                                    <input type="text" name="nome_loja" id="nome_loja" required
                                           value="<?php echo htmlspecialchars($cliente['nome_loja'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="whatsapp_loja" class="block text-sm font-medium text-gray-700">WhatsApp da Loja</label>
                                    <input type="text" name="whatsapp_loja" id="whatsapp_loja"
                                           value="<?php echo htmlspecialchars($cliente['whatsapp_loja'] ?? ''); ?>"
                                           onkeyup="formatarTelefone(this)"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="telefone_loja" class="block text-sm font-medium text-gray-700">Telefone Fixo da Loja</label>
                                    <input type="text" name="telefone_loja" id="telefone_loja"
                                           value="<?php echo htmlspecialchars($cliente['telefone_loja'] ?? ''); ?>"
                                           onkeyup="formatarTelefone(this)"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label for="website_loja" class="block text-sm font-medium text-gray-700">
                                        Website da Loja
                                    </label>
                                    <input type="url" name="website_loja" id="website_loja"
                                           value="<?php echo htmlspecialchars($cliente['website_loja'] ?? 'https://'); ?>"
                                           placeholder="https://exemplo.com"
                                           onblur="normalizarURL(this)"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label for="cep" class="block text-sm font-medium text-gray-700">CEP</label>
                                    <input type="text" name="cep" id="cep"
                                           value="<?php echo htmlspecialchars($cliente['cep'] ?? ''); ?>"
                                           placeholder="00000-000"
                                           onkeyup="if(this.value.replace(/\D/g, '').length === 8) buscarCEP(this.value.replace(/\D/g, ''))"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label for="endereco_loja" class="block text-sm font-medium text-gray-700">
                                        Endereço da Loja
                                    </label>
                                    <textarea name="endereco_loja" id="endereco_loja" rows="3"
                                              class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($cliente['endereco_loja'] ?? ''); ?></textarea>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="numero_endereco" class="block text-sm font-medium text-gray-700">
                                        Número
                                    </label>
                                    <input type="text" name="numero_endereco" id="numero_endereco"
                                           value="<?php echo htmlspecialchars($cliente['numero_endereco'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="complemento_endereco" class="block text-sm font-medium text-gray-700">
                                        Complemento
                                    </label>
                                    <input type="text" name="complemento_endereco" id="complemento_endereco"
                                           value="<?php echo htmlspecialchars($cliente['complemento_endereco'] ?? ''); ?>"
                                           placeholder="Apto, Sala, Bloco, etc."
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="estoque_medio" class="block text-sm font-medium text-gray-700">
                                        Estoque Médio de Veículos
                                    </label>
                                    <input type="number" name="estoque_medio" id="estoque_medio"
                                           value="<?php echo htmlspecialchars($cliente['estoque_medio'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="vendas_mensais" class="block text-sm font-medium text-gray-700">
                                        Média de Vendas Mensais
                                    </label>
                                    <input type="number" name="vendas_mensais" id="vendas_mensais"
                                           value="<?php echo htmlspecialchars($cliente['vendas_mensais'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Tipo de Estoque
                                    </label>
                                    <div class="space-y-2">
                                        <?php
                                        $tipos = ['novos', 'seminovos', 'importados'];
                                        foreach ($tipos as $tipo):
                                        ?>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="tipo_estoque[]" value="<?php echo $tipo; ?>"
                                                       <?php echo in_array($tipo, $tipo_estoque) ? 'checked' : ''; ?>
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
                                        foreach ($segmentos as $segmento):
                                        ?>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="segmento_atuacao[]" value="<?php echo $segmento; ?>"
                                                       <?php echo in_array($segmento, $segmento_atuacao) ? 'checked' : ''; ?>
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

                <!-- Ativação do Cliente -->
                <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <div class="md:grid md:grid-cols-3 md:gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Ativação do Cliente</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Configurações de ativação e integração.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <label for="nome_instancia_whatsapp" class="block text-sm font-medium text-gray-700">
                                        Nome da Instância do WhatsApp
                                    </label>
                                    <input type="text" name="nome_instancia_whatsapp" id="nome_instancia_whatsapp"
                                           value="<?php echo htmlspecialchars($cliente['nome_instancia_whatsapp'] ?? ''); ?>"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label for="token_evo_api" class="block text-sm font-medium text-gray-700">
                                        Token Evo API
                                    </label>
                                    <input type="text" name="token_evo_api" id="token_evo_api"
                                           value="<?php echo htmlspecialchars($cliente['token_evo_api'] ?? ''); ?>"
                                           placeholder="Token de acesso da API Evo"
                                           class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="col-span-6">
                                    <label for="url_estoque" class="block text-sm font-medium text-gray-700">
                                        URL do Estoque
                                    </label>
                                    <div class="flex gap-2">
                                        <input type="url" name="url_estoque" id="url_estoque"
                                               value="<?php echo htmlspecialchars($cliente['url_estoque'] ?? 'https://'); ?>"
                                               placeholder="https://exemplo.com/estoque"
                                               onblur="normalizarURL(this)"
                                               class="flex-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <button type="button" onclick="importarEstoque()"
                                                class="py-3 px-4 bg-green-600 text-white rounded-lg hover:bg-green-700 whitespace-nowrap">
                                            Importar Estoque
                                        </button>
                                    </div>
                                </div>

                                <div class="col-span-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Alertas de Hot Leads (WhatsApp)
                                    </label>
                                    <div id="alertas_whatsapp_container">
                                        <?php if (empty($alertas_whatsapp)): ?>
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
                                            <?php foreach ($alertas_whatsapp as $whatsapp): ?>
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

                <!-- Status do Cliente -->
                <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <div class="md:grid md:grid-cols-3 md:gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Status do Cliente</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Controle do status e motivo de desabilitação.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <label class="flex items-center mb-4">
                                        <input type="checkbox" name="cobranca_ativa" value="1"
                                               <?php echo ($cliente['cobranca_ativa'] ?? false) ? 'checked' : ''; ?>
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        <span class="ml-2 text-sm text-gray-700">Cliente com Cobrança Ativa</span>
                                    </label>
                                </div>
                                
                                <div class="col-span-6">
                                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                    <select name="status" id="status" onchange="toggleMotivoDesabilitacao()"
                                            class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="pendente" <?php echo ($cliente['status'] ?? 'pendente') === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="ativo" <?php echo ($cliente['status'] ?? '') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                        <option value="desabilitado" <?php echo ($cliente['status'] ?? '') === 'desabilitado' ? 'selected' : ''; ?>>Desabilitado</option>
                                    </select>
                                </div>

                                <div class="col-span-6" id="motivo_desabilitacao_div" style="display: <?php echo ($cliente['status'] ?? '') === 'desabilitado' ? 'block' : 'none'; ?>">
                                    <label for="motivo_desabilitacao" class="block text-sm font-medium text-gray-700">
                                        Motivo da Desabilitação
                                    </label>
                                    <textarea name="motivo_desabilitacao" id="motivo_desabilitacao" rows="3"
                                              class="mt-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($cliente['motivo_desabilitacao'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de ação -->
                <div class="flex justify-end space-x-3">
                    <a href="clientes.php" 
                       class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <?php echo $isEdit ? 'Atualizar Cliente' : 'Cadastrar Cliente'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>