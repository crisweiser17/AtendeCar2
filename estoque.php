<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

session_start();
require_once 'config/database.php';
require_once 'importador_estoque.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$clienteId = $_GET['id'] ?? null;
if (!$clienteId) {
    header('Location: clientes.php');
    exit();
}

$pdo = getConnection();

// Buscar dados do cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch();

if (!$cliente) {
    header('Location: clientes.php');
    exit();
}

// Parâmetros de paginação
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = (int)($_GET['per_page'] ?? 25);
$allowed_per_page = [10, 25, 50];
if (!in_array($per_page, $allowed_per_page)) {
    $per_page = 25;
}

$importador = new ImportadorEstoque();

// Contar total de veículos ativos
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM veiculos WHERE cliente_id = ? AND ativo = TRUE");
$countStmt->execute([$clienteId]);
$total_records = $countStmt->fetchColumn();

// Calcular paginação
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Buscar veículos com paginação
$stmt = $pdo->prepare("
    SELECT * FROM veiculos
    WHERE cliente_id = ? AND ativo = TRUE
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute([$clienteId]);
$veiculos = $stmt->fetchAll();

// Buscar estatísticas
$stats = $importador->estatisticasEstoque($clienteId);

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="clientes.php" class="text-blue-600 hover:text-blue-500 mr-4">← Clientes</a>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Estoque - <?php echo htmlspecialchars($cliente['nome_loja']); ?>
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
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0h2M7 7h10M7 11h4m6 0h2M7 15h10"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total de Veículos</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total'] ?? 0; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Ativos</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $stats['ativos'] ?? 0; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Preço Médio</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php
                                        if ($stats['preco_medio']) {
                                            // Se o preço médio for numérico, formatar
                                            if (is_numeric($stats['preco_medio'])) {
                                                echo 'R$ ' . number_format($stats['preco_medio'], 0, ',', '.');
                                            } else {
                                                echo htmlspecialchars($stats['preco_medio']);
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Última Atualização</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php 
                                        if ($stats['ultima_atualizacao']) {
                                            echo date('d/m/Y H:i', strtotime($stats['ultima_atualizacao']));
                                        } else {
                                            echo 'Nunca';
                                        }
                                        ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controles e Ações -->
            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">Veículos em Estoque</h2>
                        <p class="text-sm text-gray-500">Lista de veículos importados do site</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="sincronizarEstoqueCliente(<?php echo $clienteId; ?>, '<?php echo htmlspecialchars($cliente['url_estoque']); ?>')"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Sincronizar Estoque
                        </button>
                        <a href="cliente_form.php?id=<?php echo $clienteId; ?>"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Editar Cliente
                        </a>
                    </div>
                </div>
                
                <!-- Controles de paginação -->
                <div class="flex justify-between items-center">
                    <div>
                        <form method="GET" class="flex items-center space-x-2">
                            <input type="hidden" name="id" value="<?php echo $clienteId; ?>">
                            <label for="per_page" class="text-sm text-gray-700">Mostrar:</label>
                            <select name="per_page" id="per_page" onchange="this.form.submit()"
                                    class="py-1 px-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                            </select>
                            <span class="text-sm text-gray-700">por página</span>
                        </form>
                    </div>
                    
                    <?php if ($total_records > 0): ?>
                        <div class="text-sm text-gray-600">
                            Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $per_page, $total_records); ?> de <?php echo $total_records; ?> veículos
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lista de Veículos -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <?php if (empty($veiculos)): ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0h2M7 7h10M7 11h4m6 0h2M7 15h10"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum veículo encontrado</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            <?php if (empty($cliente['url_estoque'])): ?>
                                Configure a URL do estoque do cliente para importar veículos.
                            <?php else: ?>
                                Clique em "Importar Estoque" para buscar veículos da URL configurada.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Tabela de Veículos -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Veículo
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Preço
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        KM
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Detalhes
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($veiculos as $veiculo): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-16 w-24">
                                                    <?php if (!empty($veiculo['foto'])): ?>
                                                        <img class="h-16 w-24 object-cover rounded-md"
                                                             src="<?php echo htmlspecialchars($veiculo['foto']); ?>"
                                                             alt="<?php echo htmlspecialchars($veiculo['nome']); ?>"
                                                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iOTYiIGhlaWdodD0iNjQiIGZpbGw9IiNlNWU3ZWIiIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTkgM0g1Yy0xLjEgMC0yIC45LTIgMnYxNGMwIDEuMS45IDIgMiAyaDE0YzEuMSAwIDItLjkgMi0yVjVjMC0xLjEtLjktMi0yLTJ6bTAgMTZINVY1aDE0djE0ek0xMy41IDEwLjVjMC0uODMtLjY3LTEuNS0xLjUtMS41cy0xLjUuNjctMS41IDEuNS42NyAxLjUgMS41IDEuNSAxLjUtLjY3IDEuNS0xLjV6TTE1IDE3SDlsMS41LTJMMTIgMTdsMS41LTJMMTUgMTd6Ii8+PC9zdmc+'">
                                                    <?php else: ?>
                                                        <div class="h-16 w-24 bg-gray-200 rounded-md flex items-center justify-center">
                                                            <svg class="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM13.5 10.5c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5.67 1.5 1.5 1.5 1.5-.67 1.5-1.5zM15 17H9l1.5-2L12 17l1.5-2L15 17z"/>
                                                            </svg>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($veiculo['versao'] ?? $veiculo['nome'] ?? 'Veículo sem nome'); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php if (!empty($veiculo['marca_modelo'])): ?>
                                                            <?php echo htmlspecialchars($veiculo['marca_modelo']); ?> •
                                                        <?php endif; ?>
                                                        <?php echo $veiculo['ano'] ? $veiculo['ano'] : 'Ano não informado'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (!empty($veiculo['preco']) && $veiculo['preco'] !== 'R$ 0,00'): ?>
                                                <div class="text-lg font-semibold text-green-600">
                                                    <?php echo htmlspecialchars($veiculo['preco']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-500">Não informado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($veiculo['km']): ?>
                                                <div class="text-sm text-gray-900">
                                                    <?php echo number_format($veiculo['km'], 0, ',', '.'); ?> km
                                                </div>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-500">Não informado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php
                                                $detalhes = [];
                                                if ($veiculo['cambio']) $detalhes[] = $veiculo['cambio'];
                                                if ($veiculo['combustivel']) $detalhes[] = $veiculo['combustivel'];
                                                if ($veiculo['cor']) $detalhes[] = $veiculo['cor'];
                                                echo implode(' • ', $detalhes) ?: 'Não informado';
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $veiculo['ativo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo $veiculo['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if (!empty($veiculo['link'])): ?>
                                                <a href="<?php echo htmlspecialchars($veiculo['link']); ?>"
                                                   target="_blank"
                                                   class="text-blue-600 hover:text-blue-900">
                                                    Ver Anúncio
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400">Sem link</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Paginação -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-6 rounded-lg shadow">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Anterior
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Próxima
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Mostrando <span class="font-medium"><?php echo $offset + 1; ?></span> a
                                <span class="font-medium"><?php echo min($offset + $per_page, $total_records); ?></span> de
                                <span class="font-medium"><?php echo $total_records; ?></span> veículos
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <!-- Botão Anterior -->
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Números das páginas -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                                    <?php if ($start_page > 2): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                    <?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $total_pages; ?></a>
                                <?php endif; ?>
                                
                                <!-- Botão Próxima -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function sincronizarEstoqueCliente(clienteId, urlEstoque) {
    if (!urlEstoque || urlEstoque === 'https://') {
        alert('URL do estoque não configurada. Configure na edição do cliente.');
        return;
    }
    
    if (!confirm('Deseja sincronizar o estoque deste cliente? Isso irá adicionar novos veículos e remover os que não existem mais no site. Pode demorar alguns minutos.')) {
        return;
    }
    
    // Mostrar loading
    const botao = event.target;
    const textoOriginal = botao.innerHTML;
    botao.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Sincronizando...';
    botao.disabled = true;
    
    // Fazer requisição AJAX direta para importação
    fetch('importador_estoque.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=importar&cliente_id=${clienteId}&url=${encodeURIComponent(urlEstoque)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            alert(`✅ Sincronização concluída!\n\n${data.mensagem}\nTotal encontrados: ${data.total_encontrados || 0}\nTotal inseridos: ${data.total_inseridos || 0}`);
            location.reload();
        } else {
            alert(`❌ Erro: ${data.erro}`);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro na sincronização. Tente novamente.');
    })
    .finally(() => {
        // Restaurar botão
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
    });
}

</script>

<?php include 'includes/footer.php'; ?>